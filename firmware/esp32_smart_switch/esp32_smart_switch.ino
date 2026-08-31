/*
 * ==============================================================================
 * Project: ESP32 Smart Switch (IoT Client with Auto-Discovery)
 * Protocol: MQTT (Eclipse Mosquitto / Laravel Server)
 * Platform: ESP32 Dev Module (Arduino IDE)
 * 
 * Required Libraries (Install via Arduino IDE Library Manager):
 *  1. PubSubClient by Nick O'Leary (v2.8+)
 *  2. ArduinoJson by Benoit Blanchon (v6.x or v7.x)
 * 
 * Pinout Configuration:
 *  - RELAY_PIN  : GPIO 23 -> Relay IN (Active LOW / HIGH selectable)
 *  - BUTTON_PIN : GPIO 18 -> Push Button (Connected to GND with internal PULLUP)
 *  - LED_PIN    : GPIO 2  -> Onboard Status LED (Blinks on connecting, SOLID on connected)
 * ==============================================================================
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ======================= 1. USER CONFIGURATION =======================
// Ganti dengan kredensial Wi-Fi Anda
const char* WIFI_SSID     = "HOME_DJB 5G";
const char* WIFI_PASSWORD = "112233qw";

// IP Address Laptop / Raspberry Pi yang menjalankan Mosquitto MQTT Broker
const char* MQTT_BROKER   = "192.168.1.3";
const int   MQTT_PORT     = 1883;
const char* MQTT_USER     = "";  // Kosongkan jika allow_anonymous true
const char* MQTT_PASS     = "";  // Kosongkan jika allow_anonymous true

// Device Custom Name (Nama awal perangkat sebelum diubah di Web)
const char* DEFAULT_DEVICE_NAME = "Smart Switch Lampu 1";

// Pinout
#define RELAY_PIN         23
#define BUTTON_PIN        18
#define LED_PIN           2

// Tipe Relay: set true jika relay jenis Active LOW (umum pada modul relay biru 5V), false jika Active HIGH
#define RELAY_ACTIVE_LOW  true

// ====================================================================

WiFiClient espClient;
PubSubClient mqttClient(espClient);
Preferences preferences;

// Dynamic MQTT Topics & Identifiers (Berdasarkan MAC Address)
String deviceId;
String topicDiscovery;
String topicCommand;
String topicState;
String topicAvailability;

// State Variables
bool currentRelayState = false;
int lastButtonState = HIGH;
unsigned long lastDebounceTime = 0;
const unsigned long debounceDelay = 50; // 50ms debouncing
unsigned long lastMqttRetry = 0;

// ======================= HELPER FUNCTIONS =======================

void setRelayHardware(bool state) {
  currentRelayState = state;
  if (RELAY_ACTIVE_LOW) {
    digitalWrite(RELAY_PIN, state ? LOW : HIGH);
  } else {
    digitalWrite(RELAY_PIN, state ? HIGH : LOW);
  }
  
  // Simpan status terakhir ke memory Flash (NVS)
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
  doc["name"]              = DEFAULT_DEVICE_NAME;
  doc["type"]              = "switch";
  doc["ip_address"]        = WiFi.localIP().toString();
  doc["mac_address"]       = WiFi.macAddress();
  doc["command_topic"]     = topicCommand;
  doc["state_topic"]       = topicState;
  doc["availability_topic"]= topicAvailability;
  doc["channels"]          = 1;

  char buffer[512];
  serializeJson(doc, buffer);

  // Publish payload JSON ke topic auto-discovery sebagai Retained Message
  mqttClient.publish(topicDiscovery.c_str(), buffer, true);
  Serial.printf("[AUTO-DISCOVERY] Announced to: %s\nPayload: %s\n", topicDiscovery.c_str(), buffer);
}

// Callback ketika ada pesan masuk dari Server / Dashboard
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

void setupWiFi() {
  Serial.printf("[WIFI] Connecting to %s", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_PIN, !digitalRead(LED_PIN)); // Blink LED saat mencari Wi-Fi
    delay(300);
    Serial.print(".");
  }

  digitalWrite(LED_PIN, HIGH); // LED solid ON saat Wi-Fi terhubung
  Serial.println("\n[WIFI] Connected!");
  Serial.printf("[WIFI] IP Address: %s\n", WiFi.localIP().toString().c_str());
  Serial.printf("[WIFI] MAC Address: %s\n", WiFi.macAddress().c_str());
}

void connectMQTT() {
  if (WiFi.status() != WL_CONNECTED) return;

  Serial.printf("[MQTT] Connecting to Broker %s:%d ...\n", MQTT_BROKER, MQTT_PORT);

  // Set Last Will and Testament (LWT) topic
  // Jika ESP32 mati/terputus tiba-tiba, broker otomatis mengirim "offline" ke topic availability
  if (mqttClient.connect(deviceId.c_str(), MQTT_USER, MQTT_PASS, topicAvailability.c_str(), 1, true, "offline")) {
    Serial.println("[MQTT] Connected successfully!");

    // Publish status online
    mqttClient.publish(topicAvailability.c_str(), "online", true);

    // Kirim konfigurasi Auto-Discovery ke Laravel
    publishDiscoveryConfig();

    // Subscribe ke topic command saklar
    mqttClient.subscribe(topicCommand.c_str());
    Serial.printf("[MQTT] Subscribed to: %s\n", topicCommand.c_str());

    // Publish status awal relay saat ini
    publishState();
  } else {
    Serial.printf("[MQTT] Failed, rc=%d. Will retry in 5s\n", mqttClient.state());
  }
}

// ======================= ARDUINO MAIN SETUP =======================

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=============================================");
  Serial.println("  ESP32 Smart Switch Initializing...         ");
  Serial.println("=============================================");

  // Inisialisasi Hardware Pin
  pinMode(RELAY_PIN, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);

  // Load Status Relay Terakhir dari Flash (NVS)
  preferences.begin("smart_switch", false);
  bool savedState = preferences.getBool("relay_state", false);
  setRelayHardware(savedState);

  // Generate Unique Device ID & Topic Names berdasarkan MAC Address
  String macClean = WiFi.macAddress();
  macClean.replace(":", "");
  deviceId = "ESP32_" + macClean.substring(macClean.length() - 6); // Contoh: ESP32_84CCA8

  topicDiscovery    = "home/discovery/" + deviceId + "/config";
  topicCommand      = "home/" + deviceId + "/relay1/set";
  topicState        = "home/" + deviceId + "/relay1/state";
  topicAvailability = "home/" + deviceId + "/status";

  Serial.printf("[DEVICE] Unique ID: %s\n", deviceId.c_str());

  // Setup Wi-Fi & MQTT
  setupWiFi();
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512); // Buffer cukup untuk JSON discovery

  connectMQTT();
}

// ======================= ARDUINO MAIN LOOP =======================

void loop() {
  // 1. Maintain Wi-Fi Connection
  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_PIN, LOW);
    setupWiFi();
  }

  // 2. Maintain MQTT Connection
  if (!mqttClient.connected()) {
    unsigned long now = millis();
    if (now - lastMqttRetry > 5000) {
      lastMqttRetry = now;
      connectMQTT();
    }
  } else {
    mqttClient.loop();
  }

  // 3. Handle Physical Push Button (Manual Override with Debounce)
  int reading = digitalRead(BUTTON_PIN);
  if (reading != lastButtonState) {
    lastDebounceTime = millis();
  }

  if ((millis() - lastDebounceTime) > debounceDelay) {
    static int buttonState = HIGH;
    if (reading != buttonState) {
      buttonState = reading;

      // Jika tombol ditekan (LOW karena INPUT_PULLUP)
      if (buttonState == LOW) {
        Serial.println("[BUTTON] Physical button pressed! Toggling relay...");
        setRelayHardware(!currentRelayState);
        publishState();
      }
    }
  }
  lastButtonState = reading;
}
