
@extends('layouts.app')

@section('title', 'Riwayat Data - Smart Posture Monitor')
@section('page-title', 'Riwayat Data Sensor')

@section('content')

    {{-- Filter data --}}
    <div class="content-card mb-4">
        <div class="content-title">Filter Data Sensor</div>

        <form action="{{ route('history') }}" method="GET">
            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Perangkat</label>
                    <select name="device_id" class="form-select">
                        @foreach($devices as $item)
                            <option value="{{ $item->id }}"
                                {{ $device?->id == $item->id ? 'selected' : '' }}>
                                {{ $item->name }} ({{ $item->device_id }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date"
                        name="start_date"
                        class="form-control"
                        value="{{ $filters['start_date'] ?? '' }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date"
                        name="end_date"
                        class="form-control"
                        value="{{ $filters['end_date'] ?? '' }}">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        Filter
                    </button>
                </div>

            </div>

            <div class="mt-3">
                <a href="{{ route('history') }}"
                   class="btn btn-outline-secondary btn-sm">
                    Reset Filter
                </a>
            </div>
        </form>
    </div>

    {{-- Tabel data sensor --}}
    <div class="content-card">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="content-title mb-0">
                Data Pembacaan Sensor
            </div>

            @if($device)
                <a href="{{ route('history.export', request()->query()) }}"
                   class="btn btn-success btn-sm">
                    Export CSV
                </a>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Waktu</th>
                        <th>Pitch</th>
                        <th>Roll</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @if($readings && $readings->count())
                        @foreach($readings as $index => $reading)
                            <tr>
                                <td>
                                    {{ $readings->firstItem() + $index }}
                                </td>

                                <td>
                                    {{ $reading->recorded_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                                </td>

                                <td>{{ $reading->pitch }}°</td>

                                <td>{{ $reading->roll }}°</td>

                                <td>
                                    @if($reading->status === 'normal')
                                        <span class="badge bg-success">Normal</span>
                                    @elseif($reading->status === 'warning')
                                        <span class="badge bg-warning text-dark">Warning</span>
                                    @else
                                        <span class="badge bg-danger">Bad</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">
                                Belum ada data pembacaan sensor.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($readings && $readings->hasPages())
            <div class="mt-3">
                {{ $readings->links() }}
            </div>
        @endif

    </div>

@endsection
