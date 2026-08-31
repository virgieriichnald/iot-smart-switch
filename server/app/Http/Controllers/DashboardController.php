<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $devices = Device::with(['logs' => function ($q) {
            $q->latest()->limit(5);
        }])->get();

        $recentLogs = DeviceLog::with(['device', 'user'])->latest()->limit(15)->get();

        $stats = [
            'total_devices' => $devices->count(),
            'online_devices' => $devices->where('is_online', true)->count(),
            'active_switches' => $devices->where('state', 'ON')->count(),
        ];

        return view('dashboard', compact('devices', 'recentLogs', 'stats'));
    }
}
