<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        // Ambil perangkat milik user yang sedang login
        $devices = $request->user()->devices()->get();

        // Ambil ID perangkat dari filter
        $selectedDevice = $request->input('device_id');

        // Pilih perangkat yang diminta atau perangkat pertama
        $device = $devices->firstWhere('id', (int) $selectedDevice)
            ?? $devices->first();

        // Validasi tanggal filter
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Siapkan query data sensor
        $readings = $device
            ? $device->sensorReadings()->orderByDesc('recorded_at')
            : collect();

        // Filter tanggal awal
        if (!empty($filters['start_date'])) {
            $readings->whereDate('recorded_at', '>=', $filters['start_date']);
        }

        // Filter tanggal akhir
        if (!empty($filters['end_date'])) {
            $readings->whereDate('recorded_at', '<=', $filters['end_date']);
        }

        // Tampilkan 20 data per halaman
        if ($device) {
            $readings = $readings->paginate(20)->withQueryString();
        }

        return view('history', compact(
            'devices',
            'device',
            'readings',
            'filters'
        ));
    }

public function export(Request $request)
{
    // Validasi filter
    $filters = $request->validate([
        'device_id' => ['nullable', 'integer'],
        'start_date' => ['nullable', 'date'],
        'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
    ]);

    // Pastikan perangkat milik pengguna yang login
    $devices = $request->user()->devices();

    $device = !empty($filters['device_id'])
        ? $devices->findOrFail($filters['device_id'])
        : $devices->first();

    abort_if(!$device, 404, 'Perangkat tidak ditemukan.');

    // Ambil data sensor
    $readings = $device->sensorReadings()
        ->orderBy('recorded_at');

    if (!empty($filters['start_date'])) {
        $readings->whereDate('recorded_at', '>=', $filters['start_date']);
    }

    if (!empty($filters['end_date'])) {
        $readings->whereDate('recorded_at', '<=', $filters['end_date']);
    }

    // Unduh hasil dalam format CSV
    return response()->streamDownload(function () use ($readings) {
        $file = fopen('php://output', 'w');

        // BOM agar karakter terbaca dengan baik di Excel
        fwrite($file, "\xEF\xBB\xBF");

        // Header CSV
        fputcsv($file, [
            'Waktu',
            'Pitch',
            'Roll',
            'Status'
        ], ';');

        // Tulis data sensor
        $readings->chunk(500, function ($rows) use ($file) {
            foreach ($rows as $reading) {
                fputcsv($file, [
                    $reading->recorded_at,
                    $reading->pitch,
                    $reading->roll,
                    $reading->status,
                ], ';');
            }
        });

        fclose($file);
    }, 'riwayat-sensor-' . now()->format('Ymd-His') . '.csv', [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

}
