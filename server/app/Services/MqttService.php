<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Support\Facades\Log;
use PhpMqtt\LaravelClient\Facades\MQTT;

class MqttService
{
    /**
     * Send ON, OFF, or TOGGLE command to a specific device.
     */
    public function sendCommand(Device $device, string $command, ?int $userId = null, string $source = 'web'): bool
    {
        $command = strtoupper($command);
        if (!in_array($command, ['ON', 'OFF', 'TOGGLE'])) {
            return false;
        }

        try {
            // Publish message to device command topic
            MQTT::publish($device->command_topic, $command, 0, false);

            $newState = $command === 'TOGGLE' ? ($device->state === 'ON' ? 'OFF' : 'ON') : $command;
            
            $device->update([
                'state' => $newState,
                'last_seen_at' => now(),
            ]);

            // Log the action
            DeviceLog::create([
                'device_id' => $device->id,
                'user_id' => $userId,
                'action' => $command,
                'source' => $source,
                'payload' => json_encode(['command' => $command, 'topic' => $device->command_topic]),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to publish MQTT command to {$device->device_id}: " . $e->getMessage());
            return false;
        }
    }
}
