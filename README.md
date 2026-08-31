# 🔌 IoT Smart Switch (ESP32 + Mosquitto + Laravel 13 + Docker)

Sistem IoT Saklar Pintar lengkap berbasis **ESP32** (Client) dan **Raspberry Pi / Laptop** (Server) dengan arsitektur **Docker Compose**, protokol komunikasi **MQTT**, dan fitur **Auto-Discovery (Plug & Play)**.

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                 DOCKER (Laptop / Raspberry Pi)              │
│                                                             │
│  ┌───────────────────────┐       ┌───────────────────────┐  │
│  │     Web (Nginx)       │       │    Mosquitto Broker   │  │
│  │     Port: 8000        │       │    Port: 1883         │  │
│  └───────────┬───────────┘       └───────────▲───────────┘  │
│              │ (FastCGI)                     │              │
│  ┌───────────▼───────────┐                   │              │
│  │ Laravel 13 App        │                   │              │
│  │ - Laravel Auth        ├───────────────────┘              │
│  │ - SQLite Database     │  (PHP-MQTT Client & Listener)    │
│  │ - Auto-Discovery Engine                                  │
│  └───────────────────────┘                                  │
└──────────────────────────────────────────────┼──────────────┘
                                               │ Wi-Fi (MQTT)
                                               ▼
                                  ┌───────────────────────────┐
                                  │       ESP32 (Client)      │
                                  │   (Arduino IDE / C++)     │
                                  │                           │
                                  │  [GPIO 23] ──► Relay 1-CH │
                                  │  [GPIO 18] ◄── Push Button│
                                  │  [GPIO 2]  ──► Status LED │
                                  └───────────────────────────┘
```

---

## 📁 Struktur Folder

```text
iot-smart-switch/
├── docker-compose.yml           # Konfigurasi Docker multi-container
├── .env.example
├── README.md
├── mosquitto/
│   └── config/
│       └── mosquitto.conf      # Konfigurasi MQTT Broker
├── docker/
│   ├── nginx/
│   │   └── default.conf        # Konfigurasi Reverse Proxy Nginx
│   └── php/
│       └── Dockerfile          # Image PHP 8.3 FPM + SQLite + Sockets
├── server/                     # Laravel 13 Backend & Dashboard
│   ├── app/
│   │   ├── Console/Commands/ListenMqttCommand.php  # Background MQTT Listener
│   │   ├── Http/Controllers/                       # Auth, Dashboard & Device Controller
│   │   ├── Models/                                 # Device & DeviceLog Model
│   │   └── Services/                               # MqttService & DeviceDiscoveryService
│   ├── database/
│   │   └── migrations/                             # Skema tabel devices & logs
│   └── resources/views/                            # Blade Views (Tailwind CSS Dark UI)
└── firmware/
    └── esp32_smart_switch/
        └── esp32_smart_switch.ino                  # Firmware Arduino IDE untuk ESP32
```

---

## 🚀 Cara Menjalankan Server

### Opsi 1: Menggunakan Docker (Direkomendasikan)
Jalankan satu perintah ini di terminal root `iot-smart-switch`:

```bash
docker compose up -d --build
```

Setelah container berjalan:
* **Web Dashboard**: Buka [http://localhost:8000](http://localhost:8000)
* **MQTT Broker**: Berjalan di `localhost:1883`

**Akun Login Default:**
* **Email:** `admin@example.com`
* **Password:** `password`
*(Atau Anda bisa klik **Daftar Akun Baru** di halaman login)*

---

### Opsi 2: Menjalankan Lokal Tanpa Docker (PHP Built-in Server)

1. Jalankan Mosquitto broker (bisa via Docker atau standalone):
   ```bash
   docker run -d --name mosquitto -p 1883:1883 eclipse-mosquitto:2.0
   ```
2. Buka terminal di folder `server/`:
   ```bash
   # Jalankan Web Server
   php artisan serve --port=8000
   ```
3. Di terminal terpisah (masih di folder `server/`):
   ```bash
   # Jalankan MQTT Listener & Auto-Discovery Worker
   php artisan mqtt:listen
   ```

---

## ⚡ Panduan Hardware & Firmware ESP32

### 1. Kebutuhan Library di Arduino IDE
Buka Arduino IDE $\rightarrow$ **Tools** $\rightarrow$ **Manage Libraries...**, lalu install:
1. **`PubSubClient`** by Nick O'Leary
2. **`ArduinoJson`** by Benoit Blanchon (versi 6 atau 7)

### 2. Wiring Diagram Pinout
| Komponen | Pin ESP32 | Keterangan |
| :--- | :--- | :--- |
| **Relay IN** | **GPIO 23** | Kontrol switch ON/OFF |
| **Relay VCC** | **VIN / 5V** | Daya relay 5V |
| **Relay GND** | **GND** | Ground bersama |
| **Push Button** | **GPIO 18** | Terhubung ke pin GPIO 18 dan GND (Internal PULLUP) |
| **Status LED** | **GPIO 2** | Built-in LED pada board ESP32 |

### 3. Konfigurasi Firmware
Buka file `firmware/esp32_smart_switch/esp32_smart_switch.ino`, lalu sesuaikan:
```cpp
const char* WIFI_SSID     = "NAMA_WIFI_ANDA";
const char* WIFI_PASSWORD = "PASSWORD_WIFI_ANDA";

// Masukkan IP Lokal Laptop / Raspberry Pi Anda
const char* MQTT_BROKER   = "192.168.1.50"; 
```

### 4. Upload ke ESP32
1. Pilih Board: **ESP32 Dev Module**.
2. Pilih Port COM yang sesuai.
3. Klik tombol **Upload**.
4. Buka **Serial Monitor** pada baudrate `115200` untuk melihat proses koneksi.

---

## 🪄 Cara Kerja Auto-Discovery (Plug & Play)

1. Saat ESP32 pertama kali booting dan terhubung ke Wi-Fi & Mosquitto:
   * ESP32 mengambil MAC Address uniknya (contoh: `ESP32_84CCA8`).
   * ESP32 mem-publish JSON konfigurasi ke `home/discovery/ESP32_84CCA8/config` (*Retained Message*).
2. Laravel MQTT Listener secara otomatis mendeteksi device baru dan mendaftarkannya ke database SQLite.
3. Di Dashboard Web, saklar baru tersebut **LANGSUNG MUNCUL** secara otomatis tanpa perlu konfigurasi manual!
4. Anda dapat mengubah nama saklar (misal: "Lampu Teras", "Kipas Angin") langsung dari dashboard.

---

## 📡 Daftar Topic MQTT

| Topic | Arah | Payload | Fungsi |
| :--- | :--- | :--- | :--- |
| `home/discovery/{ID}/config` | ESP32 $\rightarrow$ Server | JSON | Pengumuman Auto-Discovery perangkat baru |
| `home/{ID}/relay1/set` | Server $\rightarrow$ ESP32 | `ON` / `OFF` / `TOGGLE` | Perintah mengendalikan relay |
| `home/{ID}/relay1/state` | ESP32 $\rightarrow$ Server | `ON` / `OFF` | Status feedback relay terkini |
| `home/{ID}/status` | ESP32 $\rightarrow$ Server | `online` / `offline` | Status ketersediaan (MQTT Last Will) |
