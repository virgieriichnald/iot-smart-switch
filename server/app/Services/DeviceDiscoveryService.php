<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Support\Facades\Log;

class DeviceDiscoveryService
{
    /**
     * Process auto-discovery JSON config from ESP32.
     */
    public function handleDiscovery(string $topic, string $message): ?Device
    {
        $data = json_decode($message, true);
        if (!$data || !isset($data['device_id'])) {
            Log::warning("[AUTO-DISCOVERY] Invalid payload on topic {$topic}: {$message}");
            return null;
        }

        $deviceId = $data['device_id'];
        $isNew = false;

        $device = Device::where('device_id', $deviceId)->first();
        if (!$device) {
            $isNew = true;
            $device = new Device();
            $device->device_id = $deviceId;
            $device->name = $data['name'] ?? "Switch {$deviceId}";
        }

        $device->type = $data['type'] ?? 'switch';
        $device->ip_address = $data['ip_address'] ?? null;
        $device->mac_address = $data['mac_address'] ?? null;
        $device->command_topic = $data['command_topic'] ?? "home/{$deviceId}/relay1/set";
        $device->state_topic = $data['state_topic'] ?? "home/{$deviceId}/relay1/state";
        $device->availability_topic = $data['availability_topic'] ?? "home/{$deviceId}/status";
        $device->channels = (int) ($data['channels'] ?? 1);
        $device->is_online = true;
        $device->last_seen_at = now();
        $device->save();

        DeviceLog::create([
            'device_id' => $device->id,
            'user_id' => null,
            'action' => $isNew ? 'DISCOVERED' : 'UPDATED',
            'source' => 'auto_discovery',
            'payload' => $message,
        ]);

        Log::info("[AUTO-DISCOVERY] Successfully " . ($isNew ? "registered new" : "updated") . " device: {$deviceId}");

        return $device;
    }

    /**
     * Handle state updates from device (e.g. home/ESP32_84CCA8/relay1/state).
     */
    public function handleState(string $topic, string $message): void
    {
        $state = strtoupper(trim($message));
        if (!in_array($state, ['ON', 'OFF'])) {
            return;
        }

        // Topic format: home/{deviceId}/relay1/state
        $parts = explode('/', $topic);
        if (count($parts) < 4) return;
        $deviceId = $parts[1];

        $device = Device::where('device_id', $deviceId)->first();
        if ($device) {
            $oldState = $device->state;
            $device->update([
                'state' => $state,
                'is_online' => true,
                'last_seen_at' => now(),
            ]);

            if ($oldState !== $state) {
                DeviceLog::create([
                    'device_id' => $device->id,
                    'user_id' => null,
                    'action' => $state,
                    'source' => 'physical_button',
                    'payload' => json_encode(['synced_state' => $state, 'topic' => $topic]),
                ]);
            }
        }
    }

    /**
     * Handle availability status (e.g. home/ESP32_84CCA8/status -> online / offline).
     */
    public function handleAvailability(string $topic, string $message): void
    {
        $status = strtolower(trim($message));
        $isOnline = ($status === 'online');

        // Topic format: home/{deviceId}/status
        $parts = explode('/', $topic);
        if (count($parts) < 3) return;
        $deviceId = $parts[1];

        $device = Device::where('device_id', $deviceId)->first();
        if ($device) {
            $device->update([
                'is_online' => $isOnline,
                'last_seen_at' => now(),
            ]);

            DeviceLog::create([
                'device_id' => $device->id,
                'user_id' => null,
                'action' => $isOnline ? 'ONLINE' : 'OFFLINE',
                'source' => 'mqtt_lwt',
                'payload' => json_encode(['status' => $status, 'topic' => $topic]),
            ]);
        }
    }
}
