/*
 * ==============================================================================
 * Project: ESP32 Smart Switch (IoT Client with Web Captive Portal & Auto-Discovery)
 * Protocol: MQTT (Eclipse Mosquitto / Laravel Server)
 * Platform: ESP32 Dev Module (Arduino IDE)
 * 
 * Required Libraries (Install via Arduino IDE Library Manager):
 *  1. PubSubClient by Nick O'Leary (v2.8+)
 *  2. ArduinoJson by Benoit Blanchon (v6.x or v7.x)
 * 
 * Built-in ESP32 Libraries (No install needed):
 *  - WiFi.h, WebServer.h, DNSServer.h, ESPmDNS.h, Preferences.h
 * 
 * Pinout Configuration:
 *  - RELAY_PIN  : GPIO 23 -> Relay IN (Active LOW / HIGH selectable)
 *  - BUTTON_PIN : GPIO 18 -> Push Button (Connected to GND with internal PULLUP)
 *                            - Tekan Cepat (< 1s) : Toggle ON / OFF Saklar
 *                            - Tahan 3 Detik      : Masuk ke Mode Web Portal Config (AP)
 *  - LED_PIN    : GPIO 2  -> Onboard Status LED
 *                            - Kedip Lambat (1s) : Mencari Wi-Fi
 *                            - Kedip Cepat (200ms) : Mode Setup AP / Portal Web
 *                            - Menyala Terus      : Terhubung & Siap
 * ==============================================================================
 */

#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <ESPmDNS.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ======================= PINOUT & HARDWARE CONFIG =======================
#define RELAY_PIN         23
#define BUTTON_PIN        18
#define LED_PIN           2

// Tipe Relay: set true jika modul relay jenis Active LOW (standar relay biru 5V), false jika Active HIGH
#define RELAY_ACTIVE_LOW  true

// Durasi tahan tombol untuk reset ke mode Web Portal (millidetik)
#define CONFIG_PORTAL_HOLD_TIME 3000

// ======================= GLOBAL OBJECTS & VARIABLES =======================
WiFiClient espClient;
PubSubClient mqttClient(espClient);
Preferences preferences;
WebServer webServer(80);
DNSServer dnsServer;

// Configuration Variables (Disimpan di Flash Memory / NVS)
String wifiSsid        = "HOME_DJB";
String wifiPassword    = "112233qw";
String mqttBroker      = "192.168.1.50";
int    mqttPort        = 1883;
String mqttUser        = "";
String mqttPass        = "";
String customDeviceName= "Smart Switch ESP32";

// Dynamic Identifiers & Topics (Berdasarkan MAC Address)
String deviceId;
String apSSID;
String topicDiscovery;
String topicCommand;
String topicState;
String topicAvailability;

// State Flags & Timers
bool isConfigMode       = false;
bool currentRelayState  = false;
int  lastButtonState    = HIGH;
unsigned long buttonPressStartTime = 0;
bool buttonIsPressed    = false;
unsigned long lastMqttRetry = 0;
unsigned long lastLedBlink  = 0;
bool ledState = false;

// ======================= HARDWARE CONTROLS =======================

void setRelayHardware(bool state) {
  currentRelayState = state;
  if (RELAY_ACTIVE_LOW) {
    digitalWrite(RELAY_PIN, state ? LOW : HIGH);
  } else {
    digitalWrite(RELAY_PIN, state ? HIGH : LOW);
  }
  
  // Simpan status terakhir ke Flash Memory (NVS)
  preferences.putBool("relay_state", state);
  Serial.printf("[RELAY] State changed to: %s\n", state ? "ON" : "OFF");
}

void publishState() {
  if (mqttClient.connected()) {
    const char* payload = currentRelayState ? "ON" : "OFF";
    mqttClient.publish(topicState.c_str(), payload, true); // Retained = true
    Serial.printf("[MQTT] Published State: %s to %s\n", payload, topicState.c_str());
  }
}

void publishDiscoveryConfig() {
  if (!mqttClient.connected()) return;

  StaticJsonDocument<512> doc;
  doc["device_id"]         = deviceId;
  doc["name"]              = customDeviceName;
  doc["type"]              = "switch";
  doc["ip_address"]        = WiFi.localIP().toString();
  doc["mac_address"]       = WiFi.macAddress();
  doc["command_topic"]     = topicCommand;
  doc["state_topic"]       = topicState;
  doc["availability_topic"]= topicAvailability;
  doc["channels"]          = 1;

  char buffer[512];
  serializeJson(doc, buffer);

  mqttClient.publish(topicDiscovery.c_str(), buffer, true);
  Serial.printf("[AUTO-DISCOVERY] Announced to: %s\n", topicDiscovery.c_str());
}

// ======================= MQTT CALLBACK =======================

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String message = "";
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  message.trim();
  message.toUpperCase();

  Serial.printf("[MQTT RECV] Topic [%s] Payload: %s\n", topic, message.c_str());

  if (String(topic) == topicCommand) {
    if (message == "ON") {
      setRelayHardware(true);
      publishState();
    } else if (message == "OFF") {
      setRelayHardware(false);
      publishState();
    } else if (message == "TOGGLE") {
      setRelayHardware(!currentRelayState);
      publishState();
    }
  }
}

void connectMQTT() {
  if (WiFi.status() != WL_CONNECTED || mqttBroker.length() == 0) return;

  Serial.printf("[MQTT] Connecting to Broker %s:%d ...\n", mqttBroker.c_str(), mqttPort);

  // Set Last Will and Testament (LWT)
  if (mqttClient.connect(deviceId.c_str(), mqttUser.c_str(), mqttPass.c_str(), topicAvailability.c_str(), 1, true, "offline")) {
    Serial.println("[MQTT] Connected successfully!");

    mqttClient.publish(topicAvailability.c_str(), "online", true);
    publishDiscoveryConfig();
    mqttClient.subscribe(topicCommand.c_str());
    Serial.printf("[MQTT] Subscribed to: %s\n", topicCommand.c_str());
    publishState();
  } else {
    Serial.printf("[MQTT] Failed, rc=%d. Will retry in 5s\n", mqttClient.state());
  }
}

// ======================= WEB CAPTIVE PORTAL =======================

String getScanNetworksOptions() {
  int n = WiFi.scanNetworks();
  String options = "";
  if (n == 0) {
    options = "<option value=''>Tidak ada Wi-Fi ditemukan</option>";
  } else {
    for (int i = 0; i < n; ++i) {
      String ssid = WiFi.SSID(i);
      int rssi = WiFi.RSSI(i);
      String selected = (ssid == wifiSsid) ? " selected" : "";
      options += "<option value='" + ssid + "'" + selected + ">" + ssid + " (" + String(rssi) + " dBm)</option>";
    }
  }
  return options;
}

void handlePortalRoot() {
  String html = "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'>";
  html += "<title>" + customDeviceName + " Setup</title>";
  html += "<style>";
  html += "body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:16px;}";
  html += ".card{background:#1e293b;padding:28px;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,0.5);width:100%;max-width:400px;border:1px solid #334155;}";
  html += "h2{margin-top:0;color:#38bdf8;font-size:22px;text-align:center;}";
  html += "p.subtitle{color:#94a3b8;font-size:13px;text-align:center;margin-bottom:24px;}";
  html += ".field{margin-bottom:16px;}";
  html += "label{display:block;margin-bottom:6px;font-size:13px;color:#cbd5e1;font-weight:600;}";
  html += "input,select{width:100%;padding:10px 12px;background:#0f172a;border:1px solid #475569;border-radius:8px;color:#f8fafc;box-sizing:border-box;font-size:14px;}";
  html += "input:focus,select:focus{border-color:#38bdf8;outline:none;}";
  html += "button{width:100%;padding:12px;background:#0284c7;color:#fff;border:none;border-radius:8px;font-weight:bold;font-size:15px;cursor:pointer;margin-top:10px;transition:0.2s;}";
  html += "button:hover{background:#0369a1;}";
  html += ".info{background:#334155;padding:10px;border-radius:8px;font-size:12px;color:#94a3b8;margin-top:20px;text-align:center;}";
  html += "</style></head><body>";
  html += "<div class='card'>";
  html += "<h2>⚙️ Konfigurasi Smart Switch</h2>";
  html += "<p class='subtitle'>Atur Wi-Fi & Alamat Host MQTT Broker</p>";
  html += "<form method='POST' action='/save'>";
  
  html += "<div class='field'><label>Pilih Jaringan Wi-Fi (2.4 GHz):</label>";
  html += "<select id='ssid_select' onchange=\"document.getElementById('ssid_manual').value=this.value\">";
  html += "<option value=''>-- Pilih SSID --</option>";
  html += getScanNetworksOptions();
  html += "</select></div>";

  html += "<div class='field'><label>SSID Wi-Fi:</label>";
  html += "<input type='text' id='ssid_manual' name='ssid' value='" + wifiSsid + "' placeholder='Nama Wi-Fi' required></div>";

  html += "<div class='field'><label>Password Wi-Fi:</label>";
  html += "<input type='password' name='password' value='" + wifiPassword + "' placeholder='Password Wi-Fi'></div>";

  html += "<div class='field'><label>IP / Hostname MQTT Broker:</label>";
  html += "<input type='text' name='broker' value='" + mqttBroker + "' placeholder='Contoh: 192.168.1.3 atau broker.local' required></div>";

  html += "<div class='field'><label>Port MQTT Broker:</label>";
  html += "<input type='number' name='port' value='" + String(mqttPort) + "' required></div>";

  html += "<div class='field'><label>Nama Saklar (Web Dashboard):</label>";
  html += "<input type='text' name='name' value='" + customDeviceName + "'></div>";

  html += "<button type='submit'>💾 Simpan & Hubungkan</button>";
  html += "</form>";
  html += "<div class='info'>Device ID: <b>" + deviceId + "</b><br>Tahan tombol fisik 3 detik kapan saja untuk kembali ke mode setup ini.</div>";
  html += "</div></body></html>";

  webServer.send(200, "text/html", html);
}

void handlePortalSave() {
  if (webServer.hasArg("ssid"))     wifiSsid         = webServer.arg("ssid");
  if (webServer.hasArg("password")) wifiPassword     = webServer.arg("password");
  if (webServer.hasArg("broker"))   mqttBroker       = webServer.arg("broker");
  if (webServer.hasArg("port"))     mqttPort         = webServer.arg("port").toInt();
  if (webServer.hasArg("name"))     customDeviceName = webServer.arg("name");

  // Simpan ke Flash Memory (Preferences / NVS)
  preferences.putString("wifi_ssid", wifiSsid);
  preferences.putString("wifi_pass", wifiPassword);
  preferences.putString("mqtt_broker", mqttBroker);
  preferences.putInt("mqtt_port", mqttPort);
  preferences.putString("dev_name", customDeviceName);

  String html = "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'>";
  html += "<meta http-equiv='refresh' content='5;url=/'>";
  html += "<style>body{font-family:sans-serif;background:#0f172a;color:#f8fafc;text-align:center;padding:40px;}";
  html += ".box{background:#1e293b;padding:30px;border-radius:12px;display:inline-block;max-width:360px;}</style></head><body>";
  html += "<div class='box'><h2 style='color:#4ade80;'>✅ Tersimpan!</h2>";
  html += "<p>ESP32 sedang me-restart dan menghubungkan ke Wi-Fi <b>" + wifiSsid + "</b> & Broker <b>" + mqttBroker + "</b>...</p>";
  html += "<p style='font-size:12px;color:#94a3b8;'>Jika berhasil, lampu status LED akan menyala solid.</p></div></body></html>";

  webServer.send(200, "text/html", html);
  delay(1500);
  ESP.restart();
}

void startConfigPortal() {
  isConfigMode = true;
  WiFi.disconnect();
  WiFi.mode(WIFI_AP_STA); // AP_STA to allow scanning while serving AP
  WiFi.softAP(apSSID.c_str(), "12345678");

  IPAddress apIP(192, 168, 4, 1);
  WiFi.softAPConfig(apIP, apIP, IPAddress(255, 255, 255, 0));

  // DNS Server untuk Captive Portal (Redirect semua DNS request ke 192.168.4.1)
  dnsServer.start(53, "*", apIP);

  webServer.on("/", handlePortalRoot);
  webServer.on("/save", HTTP_POST, handlePortalSave);
  webServer.onNotFound(handlePortalRoot); // Captive Portal redirect
  webServer.begin();

  Serial.println("\n[PORTAL] Mode Konfigurasi Web Portal AKTIF!");
  Serial.printf("[PORTAL] Hubungkan HP/Laptop ke Wi-Fi AP: %s (Password: 12345678)\n", apSSID.c_str());
  Serial.println("[PORTAL] Buka Browser ke: http://192.168.4.1");
}

void loadStoredConfig() {
  preferences.begin("smart_switch", false);
  wifiSsid         = preferences.getString("wifi_ssid", "HOME_DJB");
  wifiPassword     = preferences.getString("wifi_pass", "112233qw");
  mqttBroker       = preferences.getString("mqtt_broker", "192.168.1.50");
  mqttPort         = preferences.getInt("mqtt_port", 1883);
  customDeviceName = preferences.getString("dev_name", "Smart Switch ESP32");
  currentRelayState= preferences.getBool("relay_state", false);
}

bool connectToWiFiStation() {
  if (wifiSsid.length() == 0) return false;

  Serial.printf("\n[WIFI] Menghubungkan ke: %s ...\n", wifiSsid.c_str());
  WiFi.mode(WIFI_STA);
  WiFi.begin(wifiSsid.c_str(), wifiPassword.c_str());

  unsigned long startTime = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - startTime) < 12000) { // 12 detik timeout
    digitalWrite(LED_PIN, !digitalRead(LED_PIN));
    delay(300);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    digitalWrite(LED_PIN, HIGH); // Solid ON saat sukses
    Serial.println("\n[WIFI] Berhasil Terhubung!");
    Serial.printf("[WIFI] IP ESP32 : %s\n", WiFi.localIP().toString().c_str());
    Serial.printf("[WIFI] Gateway  : %s\n", WiFi.gatewayIP().toString().c_str());
    return true;
  }

  Serial.println("\n[WIFI] Gagal terhubung ke Wi-Fi!");
  return false;
}

// ======================= MAIN ARDUINO SETUP =======================

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n=============================================");
  Serial.println("  ESP32 Smart Switch (Flexible Config Portal) ");
  Serial.println("=============================================");

  pinMode(RELAY_PIN, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);

  // Load config dari flash memory
  loadStoredConfig();
  setRelayHardware(currentRelayState);

  // Generate Unique ID & Topic Names
  String macClean = WiFi.macAddress();
  macClean.replace(":", "");
  deviceId = "ESP32_" + macClean.substring(macClean.length() - 6);
  apSSID   = "SmartSwitch_" + macClean.substring(macClean.length() - 4);

  topicDiscovery    = "home/discovery/" + deviceId + "/config";
  topicCommand      = "home/" + deviceId + "/relay1/set";
  topicState        = "home/" + deviceId + "/relay1/state";
  topicAvailability = "home/" + deviceId + "/status";

  Serial.printf("[DEVICE] Device ID : %s\n", deviceId.c_str());
  Serial.printf("[DEVICE] AP Setup  : %s\n", apSSID.c_str());

  // Coba hubungkan ke Wi-Fi tersimpan
  if (!connectToWiFiStation()) {
    Serial.println("[SETUP] Wi-Fi tidak tersedia, membuka Web Captive Portal...");
    startConfigPortal();
  } else {
    mqttClient.setServer(mqttBroker.c_str(), mqttPort);
    mqttClient.setCallback(mqttCallback);
    mqttClient.setBufferSize(512);
    connectMQTT();
  }
}

// ======================= MAIN ARDUINO LOOP =======================

void loop() {
  // -------------------------------------------------------------
  // Mode 1: Web Captive Portal (Untuk Ubah Wi-Fi / IP MQTT Broker)
  // -------------------------------------------------------------
  if (isConfigMode) {
    dnsServer.processNextRequest();
    webServer.handleClient();

    // Kedip cepat LED tanda mode Setup AP
    if (millis() - lastLedBlink > 150) {
      lastLedBlink = millis();
      ledState = !ledState;
      digitalWrite(LED_PIN, ledState);
    }
    return;
  }

  // -------------------------------------------------------------
  // Mode 2: Normal Operation
  // -------------------------------------------------------------

  // 1. Maintain Wi-Fi
  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_PIN, LOW);
    if (!connectToWiFiStation()) {
      // Jika berkali-kali putus, bisa masuk portal atau tunggu
      delay(2000);
    }
  }

  // 2. Maintain MQTT
  if (WiFi.status() == WL_CONNECTED) {
    if (!mqttClient.connected()) {
      digitalWrite(LED_PIN, LOW);
      unsigned long now = millis();
      if (now - lastMqttRetry > 5000) {
        lastMqttRetry = now;
        connectMQTT();
      }
    } else {
      digitalWrite(LED_PIN, HIGH);
      mqttClient.loop();
    }
  }

  // 3. Tombol Fisik: Tekan Cepat = Toggle, Tahan 3 Detik = Reset ke Web Setup Portal
  int reading = digitalRead(BUTTON_PIN);

  if (reading == LOW && !buttonIsPressed) {
    buttonIsPressed = true;
    buttonPressStartTime = millis();
  } else if (reading == HIGH && buttonIsPressed) {
    unsigned long duration = millis() - buttonPressStartTime;
    buttonIsPressed = false;

    if (duration >= CONFIG_PORTAL_HOLD_TIME) {
      Serial.println("[BUTTON] Tombol ditahan > 3 detik! Mengaktifkan Web Captive Portal...");
      startConfigPortal();
    } else if (duration > 50) {
      // Short press: Toggle saklar
      Serial.println("[BUTTON] Tombol fisik ditekan -> Toggle Relay");
      setRelayHardware(!currentRelayState);
      publishState();
    }
  }
}
