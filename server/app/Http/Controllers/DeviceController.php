<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    protected MqttService $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    /**
     * Send toggle / ON / OFF command to device.
     */
    public function toggle(Request $request, Device $device)
    {
        $request->validate([
            'command' => 'required|string|in:ON,OFF,TOGGLE',
        ]);

        $success = $this->mqttService->sendCommand(
            $device,
            $request->input('command'),
            Auth::id(),
            'web'
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'device' => $device->fresh(),
                'message' => $success ? "Command sent to {$device->name}" : "Failed to send command",
            ], $success ? 200 : 500);
        }

        return back()->with($success ? 'success' : 'error', $success ? "Command sent to {$device->name}" : "Failed to send command");
    }

    /**
     * Rename device.
     */
    public function update(Request $request, Device $device)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $device->update([
            'name' => $request->input('name'),
        ]);

        return back()->with('success', "Device renamed successfully to {$device->name}");
    }

    /**
     * Delete device from database.
     */
    public function destroy(Device $device)
    {
        $name = $device->name;
        $device->delete();

        return back()->with('success', "Device {$name} deleted successfully");
    }

    /**
     * Realtime JSON status for live dashboard refresh.
     */
    public function apiStatus(): JsonResponse
    {
        $devices = Device::all();
        $recentLogs = \App\Models\DeviceLog::with(['device'])->latest()->limit(10)->get();

        return response()->json([
            'devices' => $devices,
            'recentLogs' => $recentLogs,
            'stats' => [
                'total_devices' => $devices->count(),
                'online_devices' => $devices->where('is_online', true)->count(),
                'active_switches' => $devices->where('state', 'ON')->count(),
            ]
        ]);
    }
}
