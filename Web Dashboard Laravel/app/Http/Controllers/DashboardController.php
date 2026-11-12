<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Menampilkan halaman dashboard
    public function index(Request $request)
    {
        $devices = $request->user()->devices()->get();
        $device = $devices->first();

        $latest = $device
            ? $device->sensorReadings()->latest('recorded_at')->first()
            : null;

        $readings = $device
            ? $device->sensorReadings()
                ->latest('recorded_at')
                ->take(20)
                ->get()
                ->reverse()
                ->values()
            : collect();

        $events = $device
            ? $device->postureEvents()
                ->latest('started_at')
                ->take(10)
                ->get()
            : collect();

        $isOnline = $device?->last_seen_at
            && $device->last_seen_at->greaterThan(now()->subSeconds(10));

        return view('dashboard', compact(
            'devices',
            'device',
            'latest',
            'readings',
            'events',
            'isOnline'
        ));
    }

    // Mengirim data terbaru untuk pembaruan dashboard
    public function data(Request $request)
    {
        $device = $request->user()->devices()->first();

        if (!$device) {
            return response()->json(['device' => null]);
        }

        $latest = $device->sensorReadings()
            ->latest('recorded_at')
            ->first();

        $events = $device->postureEvents()
            ->latest('started_at')
            ->take(10)
            ->get();

        $isOnline = $device->last_seen_at
            && $device->last_seen_at->greaterThan(now()->subSeconds(10));

        return response()->json([
            'device' => $device->name,
            'device_id' => $device->device_id,
            'online' => (bool) $isOnline,
            'latest' => $latest,
            'events' => $events,
        ]);
    }
}
