<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\Device;
use App\Models\SensorReading;
use App\Models\PostureEvent;
use Carbon\Carbon;

#[Signature('mqtt:subscribe')]
#[Description('Menerima data sensor dari MQTT Broker')]
class MqttSubscribe extends Command
{
    public function handle()
    {
        // Ambil konfigurasi MQTT
        $host = config('mqtt.host');
        $port = config('mqtt.port');
        $clientId = config('mqtt.client_id');

        // Coba menghubungkan ulang jika koneksi terputus
    while (true) {

    // Buat MQTT client baru
    $client = new MqttClient($host, $port, $clientId);

    // Atur koneksi MQTT
    $settings = (new ConnectionSettings)
        ->setConnectTimeout(config('mqtt.connect_timeout'));

    try {
            // Hubungkan Laravel ke MQTT Broker
            $client->connect($settings, true);

            $this->info("Terhubung ke MQTT Broker: {$host}:{$port}");

            // Subscribe topik data sensor
            $client->subscribe('posture/+/data', function ($topic, $message) {
                try {
                    // Ubah JSON menjadi object PHP
                    $data = json_decode($message);

                    // Periksa format dan kelengkapan data
                    if (
                    !$data ||
                    !isset(
                        $data->device_id,
                        $data->timestamp,
                        $data->pitch,
                        $data->roll,
                        $data->status
                    ) ||
                    !is_string($data->device_id) ||
                    !is_string($data->timestamp) ||
                    trim($data->device_id) === '' ||
                    trim($data->timestamp) === '' ||
                    !is_numeric($data->pitch) ||
                    !is_numeric($data->roll) ||
                    !is_string($data->status) ||
                    !in_array($data->status, ['normal', 'warning', 'bad'], true)
                ) {
                    $this->warn("Data sensor tidak valid dari {$topic}");
                    return;
                }

                    // Ambil ID perangkat dari topik MQTT
                    $topicDeviceId = explode('/', $topic)[1] ?? null;

                    // Cocokkan ID perangkat pada topik dan JSON
                    if ($topicDeviceId !== $data->device_id) {
                        $this->warn("ID perangkat pada topik dan JSON tidak cocok.");
                        return;
                    }

                    // Cari perangkat berdasarkan ID MQTT
                    $device = Device::where('device_id', $data->device_id)->first();

                    if (!$device) {
                        $this->warn("Perangkat {$data->device_id} belum terdaftar.");
                        return;
                    }
                    
                    // Periksa dan ubah timestamp
                    $recordedAt = Carbon::parse($data->timestamp);
                    
                    
                    // Simpan pembacaan sensor
                    SensorReading::create([
                        'device_id' => $device->id,
                        'pitch' => $data->pitch,
                        'roll' => $data->roll,
                        'status' => $data->status,
                        'recorded_at' => $recordedAt,
                    ]);

                    // Perbarui waktu terakhir perangkat aktif
                    $device->update([
                        'last_seen_at' => now(),
                    ]);

                    $this->info("Data sensor {$device->device_id} berhasil disimpan.");

                } catch (\Throwable $e) {
                    $this->error("Gagal menyimpan data sensor: " . $e->getMessage());
                }
            }, config('mqtt.qos'));

            // Subscribe topik kejadian membungkuk
            $client->subscribe('posture/+/event', function ($topic, $message) {
                try {
                    // Ubah JSON menjadi object PHP
                    $data = json_decode($message);

                    // Periksa format dan kelengkapan data
                    if (
                    !$data ||
                    !isset(
                        $data->device_id,
                        $data->timestamp,
                        $data->angle,
                        $data->direction,
                        $data->duration
                    ) ||
                    !is_string($data->device_id) ||
                    !is_string($data->timestamp) ||
                    trim($data->device_id) === '' ||
                    trim($data->timestamp) === '' ||
                    !is_numeric($data->angle) ||
                    !is_numeric($data->duration) ||
                    !is_string($data->direction) ||
                    trim($data->direction) === '' ||
                    $data->duration < 0
                ) {
                    $this->warn("Event tidak valid dari {$topic}");
                    return;
                }

                    // Ambil ID perangkat dari topik MQTT
                    $topicDeviceId = explode('/', $topic)[1] ?? null;

                    // Cocokkan ID perangkat pada topik dan JSON
                    if ($topicDeviceId !== $data->device_id) {
                        $this->warn("ID perangkat pada topik dan JSON tidak cocok.");
                        return;
                    }

                    // Cari perangkat berdasarkan ID MQTT
                    $device = Device::where('device_id', $data->device_id)->first();

                    if (!$device) {
                        $this->warn("Perangkat {$data->device_id} belum terdaftar.");
                        return;
                    }
                    
                    // Periksa dan ubah timestamp
                    $startedAt = Carbon::parse($data->timestamp);

                    // Simpan kejadian membungkuk
                    PostureEvent::create([
                        'device_id' => $device->id,
                        'angle' => $data->angle,
                        'direction' => $data->direction,
                        'duration' => $data->duration,
                        'started_at' => $startedAt,
                    ]);

                    // Perbarui waktu terakhir perangkat aktif
                    $device->update([
                        'last_seen_at' => now(),
                    ]);

                    $this->info("Event posture {$device->device_id} berhasil disimpan.");

                } catch (\Throwable $e) {
                    $this->error("Gagal menyimpan event: " . $e->getMessage());
                }
            }, config('mqtt.qos'));

            // Tampilkan status subscriber
            $this->info('Menunggu pesan MQTT... Tekan Ctrl+C untuk berhenti.');

            // Jalankan proses penerimaan pesan
$client->loop(true);

// Putuskan koneksi jika loop berhenti normal
$client->disconnect();

} catch (\Throwable $e) {

    // Tampilkan penyebab koneksi terputus
    $this->error('Koneksi MQTT gagal: ' . $e->getMessage());
}

// Tunggu sebelum mencoba koneksi kembali
$this->warn('Mencoba menghubungkan kembali dalam 5 detik...');

sleep(5);

} // Akhir while
    }
}
