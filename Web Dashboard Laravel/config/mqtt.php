<?php

return [
    // Alamat MQTT Broker
    'host' => env('MQTT_HOST', '127.0.0.1'),

    // Port MQTT lokal
    'port' => (int) env('MQTT_PORT', 1883),

    // Identitas client Laravel
    'client_id' => env('MQTT_CLIENT_ID', 'smart-posture-laravel'),

    // Quality of Service
    'qos' => (int) env('MQTT_QOS', 1),

    // Waktu maksimal koneksi
    'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 10),
];
