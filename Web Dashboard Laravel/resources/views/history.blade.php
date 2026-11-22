@extends('layouts.app')

@section('title', 'Riwayat Data - Smart Posture Monitor')

@section('content')

    {{-- Filter data --}}
    <div class="mb-6">
        <div class="card p-6">
            <form action="{{ route('history') }}" method="GET">
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label for="device_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Perangkat</label>
                        <select id="device_id" name="device_id" class="input">
                            @foreach($devices as $item)
                                <option value="{{ $item->id }}" {{ $device?->id == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} ({{ $item->device_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="start_date" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Dari Tanggal</label>
                        <input type="date" name="start_date" id="start_date" class="input" value="{{ $filters['start_date'] ?? '' }}">
                    </div>

                    <div>
                        <label for="end_date" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Sampai Tanggal</label>
                        <input type="date" name="end_date" id="end_date" class="input" value="{{ $filters['end_date'] ?? '' }}">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="btn-primary w-full">Filter</button>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-3">
                    <a href="{{ route('history') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition-all duration-200 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i data-lucide="RotateCcw" class="h-4 w-4"></i>
                        Reset Filter
                    </a>

                    @if($device)
                        <a href="{{ route('history.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:bg-emerald-700">
                            <i data-lucide="Download" class="h-4 w-4"></i>
                            Export CSV
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel data sensor --}}
    <div class="card">
        <div class="flex flex-col gap-3 p-6 pb-0 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Data Pembacaan Sensor</h3>
            @php
                $readingsTotal = $readings instanceof \Illuminate\Contracts\Pagination\Paginator ? $readings->total() : $readings->count();
            @endphp
            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $readingsTotal }} data</span>
        </div>

        <div class="overflow-x-auto px-6 py-4">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <th class="py-3 pr-4 font-semibold">No.</th>
                        <th class="py-3 pr-4 font-semibold">Waktu</th>
                        <th class="py-3 pr-4 font-semibold">Pitch</th>
                        <th class="py-3 pr-4 font-semibold">Roll</th>
                        <th class="py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @if($readings && $readings->count())
                        @foreach($readings as $index => $reading)
                            <tr class="border-b border-slate-100 last:border-0 dark:border-slate-800/60">
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">{{ $readings->firstItem() + $index }}</td>
                                <td class="py-3 pr-4 text-slate-600 dark:text-slate-300">
                                    {{ $reading->recorded_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-3 pr-4 font-medium text-slate-900 dark:text-white">{{ $reading->pitch }}°</td>
                                <td class="py-3 pr-4 font-medium text-slate-900 dark:text-white">{{ $reading->roll }}°</td>
                                <td class="py-3">
                                    @if($reading->status === 'normal')
                                        <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Normal</span>
                                    @elseif($reading->status === 'warning')
                                        <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">Warning</span>
                                    @else
                                        <span class="badge bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Bad</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada data pembacaan sensor.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($readings && $readings->hasPages())
            <div class="px-6 py-4">
                {{ $readings->links() }}
            </div>
        @endif
    </div>

@endsection
