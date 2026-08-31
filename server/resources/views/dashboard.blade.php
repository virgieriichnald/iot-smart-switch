@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-8">
    <!-- Header & Auto-Discovery Status -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-slate-900/50 border border-slate-800 p-6 rounded-2xl backdrop-blur">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-microchip text-blue-500"></i> Smart Switch Control Center
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Monitor dan kontrol perangkat ESP32 secara realtime melalui MQTT Broker.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-3.5 py-1.5 rounded-xl text-xs font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                <span>Auto-Discovery: <strong>ACTIVE</strong></span>
            </div>
            <button onclick="fetchStatus()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-xl border border-slate-700 text-xs transition flex items-center gap-1.5">
                <i class="fa-solid fa-arrows-rotate" id="refresh-icon"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Counter Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Devices</p>
                    <h3 class="text-3xl font-extrabold text-white mt-1" id="stat-total">{{ $stats['total_devices'] }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-xl border border-blue-500/20">
                    <i class="fa-solid fa-network-wired"></i>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Devices Online</p>
                    <h3 class="text-3xl font-extrabold text-emerald-400 mt-1" id="stat-online">{{ $stats['online_devices'] }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xl border border-emerald-500/20">
                    <i class="fa-solid fa-wifi"></i>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Saklar Menyala (ON)</p>
                    <h3 class="text-3xl font-extrabold text-amber-400 mt-1" id="stat-active">{{ $stats['active_switches'] }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-xl border border-amber-500/20">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Connected Devices Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-toggle-on text-indigo-400"></i> Saklar Perangkat
            </h2>
            <span class="text-xs text-slate-400">Update otomatis setiap 2.5 detik</span>
        </div>

        <div id="devices-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($devices as $device)
                <div id="device-card-{{ $device->id }}" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl relative overflow-hidden transition-all duration-300 hover:border-slate-700">
                    <!-- Top Bar: Name & Status -->
                    <div class="flex items-start justify-between gap-3 mb-5">
                        <div>
                            <h3 class="font-bold text-white text-lg leading-tight flex items-center gap-2">
                                <span id="device-name-{{ $device->id }}">{{ $device->name }}</span>
                                <button onclick="openRenameModal('{{ $device->id }}', '{{ addslashes($device->name) }}')" class="text-slate-500 hover:text-slate-300 text-xs">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5 font-mono">{{ $device->device_id }}</p>
                        </div>
                        <span id="device-badge-{{ $device->id }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $device->is_online ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border border-rose-500/30' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $device->is_online ? 'bg-emerald-400 pulse-dot' : 'bg-rose-400' }}"></span>
                            <span id="device-status-text-{{ $device->id }}">{{ $device->is_online ? 'Online' : 'Offline' }}</span>
                        </span>
                    </div>

                    <!-- Center: Toggle Switch Display -->
                    <div class="my-6 flex flex-col items-center justify-center p-6 rounded-xl bg-slate-950/60 border border-slate-800/80">
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Status Saklar</div>
                        
                        <button onclick="toggleDevice('{{ $device->id }}', 'TOGGLE')" 
                                id="device-toggle-btn-{{ $device->id }}"
                                class="w-24 h-24 rounded-full flex flex-col items-center justify-center transition-all duration-300 transform active:scale-95 shadow-xl {{ $device->state === 'ON' ? 'bg-emerald-500 text-white switch-glow-on' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' }}">
                            <i class="fa-solid fa-power-off text-3xl mb-1"></i>
                            <span id="device-state-text-{{ $device->id }}" class="text-xs font-extrabold tracking-wider">{{ $device->state }}</span>
                        </button>
                    </div>

                    <!-- Details & Metadata -->
                    <div class="space-y-1.5 text-xs text-slate-400 pt-3 border-t border-slate-800/80 font-mono">
                        <div class="flex justify-between">
                            <span class="text-slate-500">IP:</span>
                            <span id="device-ip-{{ $device->id }}" class="text-slate-300">{{ $device->ip_address ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Topic Set:</span>
                            <span class="text-slate-300 truncate max-w-[180px]" title="{{ $device->command_topic }}">{{ $device->command_topic }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Last Seen:</span>
                            <span id="device-seen-{{ $device->id }}" class="text-slate-300">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum pernah' }}</span>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-between gap-2 mt-4 pt-3 border-t border-slate-800/80">
                        <div class="flex gap-1.5">
                            <button onclick="toggleDevice('{{ $device->id }}', 'ON')" class="px-2.5 py-1 text-xs font-medium rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 transition">
                                Force ON
                            </button>
                            <button onclick="toggleDevice('{{ $device->id }}', 'OFF')" class="px-2.5 py-1 text-xs font-medium rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 transition">
                                Force OFF
                            </button>
                        </div>
                        <form method="POST" action="{{ route('devices.destroy', $device) }}" onsubmit="return confirm('Hapus device ini dari dashboard?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-slate-500 hover:text-rose-400 text-xs p-1 transition" title="Hapus Device">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div id="no-devices-card" class="col-span-full bg-slate-900/60 border border-dashed border-slate-800 rounded-3xl p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center mx-auto mb-4 text-2xl border border-blue-500/20">
                        <i class="fa-solid fa-tower-broadcast animate-pulse"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white">Menunggu Koneksi ESP32...</h3>
                    <p class="text-sm text-slate-400 max-w-md mx-auto mt-2">
                        Belum ada ESP32 yang terdeteksi. Nyalakan ESP32 Anda yang sudah di-flash firmware. Fitur <strong>Auto-Discovery</strong> akan mendaftarkan perangkat secara otomatis ke layar ini!
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Activity Log Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i> Riwayat Aktivitas & Event
            </h2>
            <span class="text-xs text-slate-500">15 Log Terakhir</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Perangkat</th>
                        <th class="py-3 px-4">Aksi / State</th>
                        <th class="py-3 px-4">Sumber Pemicu</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody" class="divide-y divide-slate-800/60">
                    @forelse($recentLogs as $log)
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="py-3 px-4 text-slate-400 whitespace-nowrap">{{ $log->created_at->format('H:i:s d M') }}</td>
                            <td class="py-3 px-4 font-medium text-white">{{ $log->device->name ?? 'Unknown Device' }}</td>
                            <td class="py-3 px-4">
                                @if(in_array($log->action, ['ON', 'ONLINE', 'DISCOVERED']))
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ $log->action }}</span>
                                @elseif(in_array($log->action, ['OFF', 'OFFLINE']))
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">{{ $log->action }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">{{ $log->action }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-400">
                                @if($log->source === 'web')
                                    <span class="text-blue-400"><i class="fa-solid fa-globe mr-1"></i> Web Dashboard</span>
                                @elseif($log->source === 'physical_button')
                                    <span class="text-amber-400"><i class="fa-solid fa-hand-pointer mr-1"></i> Tombol Fisik (ESP32)</span>
                                @elseif($log->source === 'auto_discovery')
                                    <span class="text-purple-400"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Auto-Discovery</span>
                                @elseif($log->source === 'mqtt_lwt')
                                    <span class="text-slate-400"><i class="fa-solid fa-heart-crack mr-1"></i> MQTT LWT</span>
                                @else
                                    <span>{{ $log->source }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-slate-500">Belum ada aktivitas yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div id="rename-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-2">Ubah Nama Perangkat</h3>
        <p class="text-xs text-slate-400 mb-4">Beri nama yang mudah dikenali (misal: "Lampu Kamar Tidur")</p>
        
        <form id="rename-form" method="POST" action="">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <input type="text" name="name" id="rename-input" required
                       class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeRenameModal()" class="px-4 py-2 text-xs text-slate-400 hover:text-white transition">Batal</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-4 py-2 rounded-xl transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function openRenameModal(deviceId, currentName) {
        const modal = document.getElementById('rename-modal');
        const form = document.getElementById('rename-form');
        const input = document.getElementById('rename-input');
        
        form.action = `/devices/${deviceId}`;
        input.value = currentName;
        modal.classList.remove('hidden');
        input.focus();
    }

    function closeRenameModal() {
        document.getElementById('rename-modal').classList.add('hidden');
    }

    // Toggle device command via AJAX
    async function toggleDevice(deviceId, command) {
        const btn = document.getElementById(`device-toggle-btn-${deviceId}`);
        if (btn) btn.classList.add('opacity-50', 'pointer-events-none');

        try {
            const response = await fetch(`/devices/${deviceId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ command: command })
            });

            const data = await response.json();
            if (data.success && data.device) {
                updateDeviceUI(data.device);
            }
        } catch (error) {
            console.error('Error toggling device:', error);
        } finally {
            if (btn) btn.classList.remove('opacity-50', 'pointer-events-none');
        }
    }

    function updateDeviceUI(device) {
        const toggleBtn = document.getElementById(`device-toggle-btn-${device.id}`);
        const stateText = document.getElementById(`device-state-text-${device.id}`);
        const badge = document.getElementById(`device-badge-${device.id}`);
        const statusText = document.getElementById(`device-status-text-${device.id}`);
        const ipEl = document.getElementById(`device-ip-${device.id}`);

        if (toggleBtn && stateText) {
            stateText.textContent = device.state;
            if (device.state === 'ON') {
                toggleBtn.className = 'w-24 h-24 rounded-full flex flex-col items-center justify-center transition-all duration-300 transform active:scale-95 shadow-xl bg-emerald-500 text-white switch-glow-on';
            } else {
                toggleBtn.className = 'w-24 h-24 rounded-full flex flex-col items-center justify-center transition-all duration-300 transform active:scale-95 shadow-xl bg-slate-800 text-slate-400 hover:bg-slate-700';
            }
        }

        if (badge && statusText) {
            if (device.is_online) {
                badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-dot"></span><span>Online</span>`;
            } else {
                badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30';
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span><span>Offline</span>`;
            }
        }

        if (ipEl && device.ip_address) {
            ipEl.textContent = device.ip_address;
        }
    }

    // Realtime Polling loop every 2.5 seconds
    async function fetchStatus() {
        const icon = document.getElementById('refresh-icon');
        if (icon) icon.classList.add('fa-spin');

        try {
            const response = await fetch('/api/devices/status');
            const data = await response.json();

            // Update stats
            if (data.stats) {
                document.getElementById('stat-total').textContent = data.stats.total_devices;
                document.getElementById('stat-online').textContent = data.stats.online_devices;
                document.getElementById('stat-active').textContent = data.stats.active_switches;
            }

            // Update each device
            if (data.devices) {
                data.devices.forEach(device => {
                    const existingCard = document.getElementById(`device-card-${device.id}`);
                    if (existingCard) {
                        updateDeviceUI(device);
                    } else {
                        // If new device was auto-discovered, reload page once to construct its card
                        location.reload();
                    }
                });
            }
        } catch (error) {
            console.error('Error fetching device status:', error);
        } finally {
            if (icon) setTimeout(() => icon.classList.remove('fa-spin'), 400);
        }
    }

    // Start polling
    setInterval(fetchStatus, 2500);
</script>
@endsection
