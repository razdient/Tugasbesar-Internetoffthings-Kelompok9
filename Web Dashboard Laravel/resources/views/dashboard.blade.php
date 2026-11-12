
@extends('layouts.app')

@section('title', 'Dashboard - Smart Posture Monitor')
@section('page-title', 'Dashboard')

@section('content')

    {{-- Informasi perangkat --}}
    <div class="content-card mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="stat-label">PERANGKAT AKTIF</small>
                <h6 class="fw-bold mt-2 mb-1">
                    {{ $device?->name ?? 'Belum ada perangkat' }}
                </h6>
                <small class="text-secondary">
                    ID: {{ $device?->device_id ?? '-' }}
                </small>
            </div>

            <span id="onlineStatus"
                class="badge {{ $isOnline ? 'bg-success' : 'bg-secondary' }}">
                {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
            </span>
        </div>
    </div>

    {{-- Statistik sensor --}}
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <span class="stat-label">Pitch Angle</span>
                    <div class="stat-icon">↕</div>
                </div>
                <div class="stat-value" id="pitch">
                    {{ $latest?->pitch ?? 0 }}°
                </div>
                <small class="text-secondary">
                    Sudut depan / belakang
                </small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <span class="stat-label">Roll Angle</span>
                    <div class="stat-icon">↔</div>
                </div>
                <div class="stat-value" id="roll">
                    {{ $latest?->roll ?? 0 }}°
                </div>
                <small class="text-secondary">
                    Sudut kiri / kanan
                </small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <span class="stat-label">Status Postur</span>
                    <div class="stat-icon">●</div>
                </div>
                <div class="stat-value fs-4 mt-3" id="postureStatus">
                    {{ $latest?->status ?? 'Belum ada data' }}
                </div>
                <small class="text-secondary">
                    Kondisi postur terkini
                </small>
            </div>
        </div>

    </div>

    {{-- Grafik monitoring --}}
    <div class="content-card mb-4" id="grafik">
        <div class="content-title">
            Grafik Monitoring Sensor
        </div>

        <div style="height: 300px;">
            <canvas id="postureChart"></canvas>
        </div>
    </div>

    {{-- Riwayat kejadian postur --}}
    <div class="content-card" id="riwayat">
        <div class="content-title">
            Riwayat Kejadian Postur
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Sudut</th>
                        <th>Arah</th>
                        <th>Durasi</th>
                    </tr>
                </thead>

                <tbody id="eventTable">
                    @forelse($events as $event)
                        <tr>
                            <td>
                                {{ $event->started_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                            </td>
                            <td>{{ $event->angle }}°</td>
                            <td>{{ $event->direction }}</td>
                            <td>{{ $event->duration }} detik</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary">
                                Belum ada riwayat kejadian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    // Data awal dari database
    const initialReadings = @json($readings);

    let lastReadingId = initialReadings.length
        ? initialReadings[initialReadings.length - 1].id
        : null;

    // Membuat grafik Pitch dan Roll
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
                    borderColor: '#3478c9',
                    backgroundColor: '#3478c922',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Roll',
                    data: initialReadings.map(item => item.roll),
                    borderColor: '#21a179',
                    backgroundColor: '#21a17922',
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
                y: { grid: { color: '#edf1f5' } }
            }
        }
    });

    // Mengambil data terbaru dari Laravel
    async function updateDashboard() {
        try {
            const response = await fetch("{{ route('dashboard.data') }}", {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) return;

            const result = await response.json();
            if (!result.device) return;

            // Memperbarui status perangkat
            const status = document.getElementById('onlineStatus');

            status.textContent = result.online ? 'ONLINE' : 'OFFLINE';
            status.className = result.online
                ? 'badge bg-success'
                : 'badge bg-secondary';

            if (result.latest) {
                const latest = result.latest;

                // Memperbarui nilai sensor
                document.getElementById('pitch').textContent = latest.pitch + '°';
                document.getElementById('roll').textContent = latest.roll + '°';
                document.getElementById('postureStatus').textContent = latest.status;

                // Menambahkan data baru ke grafik
                if (latest.id !== lastReadingId) {
                    lastReadingId = latest.id;

                    chart.data.labels.push(
                        new Date(latest.recorded_at).toLocaleTimeString('id-ID')
                    );

                    chart.data.datasets[0].data.push(latest.pitch);
                    chart.data.datasets[1].data.push(latest.roll);

                    // Membatasi grafik maksimal 20 data
                    if (chart.data.labels.length > 20) {
                        chart.data.labels.shift();

                        chart.data.datasets.forEach(dataset => {
                            dataset.data.shift();
                        });
                    }

                    chart.update();
                }
            }

            // Memperbarui tabel kejadian postur
            document.getElementById('eventTable').innerHTML =
                result.events.length
                    ? result.events.map(event => `
                        <tr>
                            <td>${new Date(event.started_at).toLocaleString('id-ID')}</td>
                            <td>${event.angle}°</td>
                            <td>${event.direction}</td>
                            <td>${event.duration} detik</td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="4" class="text-center text-secondary">Belum ada riwayat kejadian.</td></tr>';

        } catch (error) {
            console.error('Gagal memperbarui dashboard:', error);
        }
    }

    // Memperbarui dashboard setiap 2 detik
    setInterval(updateDashboard, 2000);
</script>
@endpush
