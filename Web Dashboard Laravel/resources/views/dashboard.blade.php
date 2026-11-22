@extends('layouts.app')

@section('title', 'Dashboard - Smart Posture Monitor')

@section('content')

    {{-- Status perangkat --}}
    <div class="mb-6">
        <div class="card p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-950/60">
                        <i data-lucide="Radar" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <small class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Perangkat Aktif</small>
                        <h2 class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $device?->name ?? 'Belum ada perangkat' }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">ID: {{ $device?->device_id ?? '-' }}</p>
                    </div>
                </div>

                <span id="onlineStatus"
                      class="badge {{ $isOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-current opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-current"></span>
                    </span>
                    {{ $isOnline ? 'Online' : 'Offline' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Statistik sensor --}}
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">Pitch Angle</span>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/60">
                    <i data-lucide="MoveVertical" class="h-5 w-5"></i>
                </div>
            </div>
            <div class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-white" id="pitch">{{ $latest?->pitch ?? '—' }}°</div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sudut depan / belakang</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">Roll Angle</span>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60">
                    <i data-lucide="MoveHorizontal" class="h-5 w-5"></i>
                </div>
            </div>
            <div class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-white" id="roll">{{ $latest?->roll ?? '—' }}°</div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sudut kiri / kanan</p>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">Status Postur</span>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/60">
                    <i data-lucide="AlertTriangle" class="h-5 w-5"></i>
                </div>
            </div>
            <div class="mt-4 text-2xl font-bold tracking-tight text-slate-900 dark:text-white" id="postureStatus">{{ $latest?->status ?? 'Belum ada data' }}</div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kondisi postur terkini</p>
        </div>
    </div>

    {{-- Grafik & riwayat --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6" id="grafik">
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Grafik Monitoring Sensor</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Perubahan sudut (Pitch &amp; Roll)</p>
                </div>
                <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                    {{ $readings->count() }} data
                </span>
            </div>

            <div class="h-[300px]">
                <canvas id="postureChart"></canvas>
            </div>
        </div>

        <div class="card p-6" id="riwayat">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Riwayat Kejadian Postur</h3>
                <a href="{{ route('history') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors hover:text-primary-700 dark:text-primary-400">
                    Lihat semua
                    <i data-lucide="ArrowRight" class="h-4 w-4"></i>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <th class="py-3 pr-4 font-semibold">Waktu</th>
                            <th class="py-3 pr-4 font-semibold">Sudut</th>
                            <th class="py-3 pr-4 font-semibold">Arah</th>
                            <th class="py-3 font-semibold">Durasi</th>
                        </tr>
                    </thead>
                    <tbody id="eventTable">
                        @forelse($events as $event)
                            <tr class="border-b border-slate-100 last:border-0 dark:border-slate-800/60">
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                    {{ $event->started_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-3 pr-4 font-medium text-slate-900 dark:text-white">{{ $event->angle }}°</td>
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $event->direction }}</td>
                                <td class="py-3 text-slate-600 dark:text-slate-300">{{ $event->duration }} detik</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat kejadian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const initialReadings = @json($readings);

        let lastReadingId = initialReadings.length
            ? initialReadings[initialReadings.length - 1].id
            : null;

        const chart = new Chart(document.getElementById('postureChart'), {
            type: 'line',
            data: {
                labels: initialReadings.map(item =>
                    new Date(item.recorded_at).toLocaleTimeString('id-ID')
                ),
                datasets: [
                    {
                        label: 'Pitch',
                        data: initialReadings.map(item => item.pitch),
                        borderColor: '#2563eb',
                        backgroundColor: '#2563eb22',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Roll',
                        data: initialReadings.map(item => item.roll),
                        borderColor: '#7c3aed',
                        backgroundColor: '#7c3aed22',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: '#edf1f5' } },
                    y: { grid: { color: '#edf1f5' }, suggestedMax: 30 }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { usePointStyle: true, boxWidth: 8 }
                    }
                }
            }
        });

        async function updateDashboard() {
            try {
                const response = await fetch("{{ route('dashboard.data') }}", {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) return;

                const result = await response.json();
                if (!result.device) return;

                const status = document.getElementById('onlineStatus');

                status.textContent = result.online ? 'Online' : 'Offline';
                status.className = result.online
                    ? 'badge bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                    : 'badge bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';

                if (result.latest) {
                    const latest = result.latest;

                    document.getElementById('pitch').textContent = latest.pitch + '°';
                    document.getElementById('roll').textContent = latest.roll + '°';
                    document.getElementById('postureStatus').textContent = latest.status;

                    if (latest.id !== lastReadingId) {
                        lastReadingId = latest.id;

                        chart.data.labels.push(
                            new Date(latest.recorded_at).toLocaleTimeString('id-ID')
                        );

                        chart.data.datasets[0].data.push(latest.pitch);
                        chart.data.datasets[1].data.push(latest.roll);

                        if (chart.data.labels.length > 20) {
                            chart.data.labels.shift();
                            chart.data.datasets.forEach(dataset => {
                                dataset.data.shift();
                            });
                        }

                        chart.update();
                    }
                }

                const eventTable = document.getElementById('eventTable');
                eventTable.innerHTML = result.events.length
                    ? result.events.map(event => `
                        <tr class="border-b border-slate-100 last:border-0 dark:border-slate-800/60">
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">${new Date(event.started_at).toLocaleString('id-ID')}</td>
                            <td class="py-3 pr-4 font-medium text-slate-900 dark:text-white">${event.angle}°</td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">${event.direction}</td>
                            <td class="py-3 text-slate-600 dark:text-slate-300">${event.duration} detik</td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="4" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat kejadian.</td></tr>';

            } catch (error) {
                console.error('Gagal memperbarui dashboard:', error);
            }
        }

        setInterval(updateDashboard, 2000);
    });
</script>
@endpush
