<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\DeviceDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_discovery_registers_device_automatically(): void
    {
        $service = app(DeviceDiscoveryService::class);

        $payload = json_encode([
            'device_id' => 'ESP32_84CCA8',
            'name' => 'Smart Switch Lampu 1',
            'type' => 'switch',
            'ip_address' => '192.168.1.45',
            'mac_address' => '24:6F:28:84:CC:A8',
            'command_topic' => 'home/ESP32_84CCA8/relay1/set',
            'state_topic' => 'home/ESP32_84CCA8/relay1/state',
            'availability_topic' => 'home/ESP32_84CCA8/status',
            'channels' => 1,
        ]);

        $device = $service->handleDiscovery('home/discovery/ESP32_84CCA8/config', $payload);

        $this->assertNotNull($device);
        $this->assertDatabaseHas('devices', [
            'device_id' => 'ESP32_84CCA8',
            'name' => 'Smart Switch Lampu 1',
            'is_online' => true,
        ]);
        $this->assertDatabaseHas('device_logs', [
            'device_id' => $device->id,
            'action' => 'DISCOVERED',
        ]);
    }

    public function test_state_change_updates_device_state_and_logs(): void
    {
        $service = app(DeviceDiscoveryService::class);

        $device = Device::create([
            'device_id' => 'ESP32_84CCA8',
            'name' => 'Smart Switch Lampu 1',
            'type' => 'switch',
            'command_topic' => 'home/ESP32_84CCA8/relay1/set',
            'state_topic' => 'home/ESP32_84CCA8/relay1/state',
            'availability_topic' => 'home/ESP32_84CCA8/status',
            'state' => 'OFF',
            'is_online' => true,
        ]);

        $service->handleState('home/ESP32_84CCA8/relay1/state', 'ON');

        $this->assertEquals('ON', $device->fresh()->state);
        $this->assertDatabaseHas('device_logs', [
            'device_id' => $device->id,
            'action' => 'ON',
            'source' => 'physical_button',
        ]);
    }

    public function test_lwt_availability_updates_device_online_status(): void
    {
        $service = app(DeviceDiscoveryService::class);

        $device = Device::create([
            'device_id' => 'ESP32_84CCA8',
            'name' => 'Smart Switch Lampu 1',
            'type' => 'switch',
            'command_topic' => 'home/ESP32_84CCA8/relay1/set',
            'state_topic' => 'home/ESP32_84CCA8/relay1/state',
            'availability_topic' => 'home/ESP32_84CCA8/status',
            'state' => 'OFF',
            'is_online' => true,
        ]);

        $service->handleAvailability('home/ESP32_84CCA8/status', 'offline');

        $this->assertFalse($device->fresh()->is_online);
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Smart Switch Control Center');
    }
}
