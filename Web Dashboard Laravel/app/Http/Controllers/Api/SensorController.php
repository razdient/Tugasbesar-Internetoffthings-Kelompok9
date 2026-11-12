<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class SensorController extends Controller
{
    // Mengambil data sensor terbaru dari perangkat milik pengguna
    public function latest(Request $request, int $id)
    {
        // Cari perangkat berdasarkan ID dan pemilik yang sedang login
        $device = $request->user()->devices()->findOrFail($id);

        // Ambil satu pembacaan sensor paling baru
        $latest = $device->sensorReadings()
            ->latest('recorded_at')
            ->first();

        // Kirim hasil dalam format JSON
        return response()->json([
            'sukses' => true,
            'perangkat' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'nama' => $device->name,
            ],
            'data_sensor' => $latest,
        ]);
    }

    // Mengambil riwayat pembacaan sensor
    public function readings(Request $request, int $id)
    {
        // Validasi filter tanggal
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        // Pastikan perangkat milik pengguna yang sedang login
        $device = $request->user()->devices()->findOrFail($id);

        // Mulai mengambil riwayat sensor perangkat
        $query = $device->sensorReadings();

        // Filter tanggal awal jika dikirim
        if (!empty($validated['from'])) {
            $query->whereDate('recorded_at', '>=', $validated['from']);
        }

        // Filter tanggal akhir jika dikirim
        if (!empty($validated['to'])) {
            $query->whereDate('recorded_at', '<=', $validated['to']);
        }

        // Ambil data terbaru, maksimal 50 data per halaman
        $readings = $query
            ->orderBy('recorded_at', 'desc')
            ->paginate(50);

        // Kirim hasil dalam format JSON
        return response()->json([
            'sukses' => true,
            'perangkat' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'nama' => $device->name,
            ],
            'riwayat' => $readings,
        ]);
}

    // Mengambil riwayat kejadian postur
    public function events(Request $request, int $id)
    {
        // Validasi filter tanggal
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        // Pastikan perangkat milik pengguna yang sedang login
        $device = $request->user()->devices()->findOrFail($id);

        // Mulai mengambil riwayat kejadian perangkat
        $query = $device->postureEvents();

        // Filter tanggal awal jika dikirim
        if (!empty($validated['from'])) {
            $query->whereDate('started_at', '>=', $validated['from']);
        }

        // Filter tanggal akhir jika dikirim
        if (!empty($validated['to'])) {
            $query->whereDate('started_at', '<=', $validated['to']);
        }

        // Ambil data terbaru, maksimal 50 data per halaman
        $events = $query
            ->orderBy('started_at', 'desc')
            ->paginate(50);

        // Kirim hasil dalam format JSON
        return response()->json([
            'sukses' => true,
            'perangkat' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'nama' => $device->name,
            ],
            'riwayat_kejadian' => $events,
        ]);
    }


    // Mengirim perintah dari Laravel ke ESP32 melalui MQTT
    public function command(Request $request, int $id)
    {
        // Validasi perintah yang diperbolehkan
        $validated = $request->validate([
            'command' => 'required|in:set_threshold,calibrate,test_buzzer',
            'angle' => 'required_if:command,set_threshold|numeric|min:1|max:90',
            'duration' => 'required_if:command,set_threshold|integer|min:1|max:120',
        ]);

        // Pastikan perangkat milik pengguna yang sedang login
        $device = $request->user()->devices()->findOrFail($id);

        // Siapkan isi pesan MQTT
        $payload = [
            'command' => $validated['command'],
        ];

        // Sertakan pengaturan jika perintahnya mengubah threshold
        if ($validated['command'] === 'set_threshold') {
            $payload['angle'] = (float) $validated['angle'];
            $payload['duration'] = (int) $validated['duration'];
        }

        try {
            // Buat MQTT client dengan ID unik
            $client = new MqttClient(
                config('mqtt.host'),
                config('mqtt.port'),
                config('mqtt.client_id') . '-api-' . uniqid()
            );

            // Atur koneksi MQTT
            $settings = (new ConnectionSettings())
                ->setConnectTimeout(config('mqtt.connect_timeout'));

            // Hubungkan ke MQTT Broker
            $client->connect($settings, true);

            // Tentukan topic tujuan perangkat
            $topic = 'posture/' . $device->device_id . '/command';

            // Kirim perintah ke ESP32
            $client->publish(
                $topic,
                json_encode($payload, JSON_THROW_ON_ERROR),
                config('mqtt.qos')
            );

            // Putuskan koneksi MQTT
            $client->disconnect();

            // Berikan respons berhasil
            return response()->json([
                'sukses' => true,
                'pesan' => 'Perintah berhasil dikirim ke MQTT Broker.',
                'topic' => $topic,
                'perintah' => $payload,
            ]);

        } catch (\Throwable $e) {
            // Berikan respons jika pengiriman gagal
            return response()->json([
                'sukses' => false,
                'pesan' => 'Gagal mengirim perintah MQTT.',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

}
