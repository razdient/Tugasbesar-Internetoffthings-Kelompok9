<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SensorController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Mengambil data sensor terbaru
Route::get('/devices/{id}/latest', [SensorController::class, 'latest'])
    ->middleware('auth:sanctum');

    // Mengambil riwayat pembacaan sensor
Route::get('/devices/{id}/readings', [SensorController::class, 'readings'])
    ->middleware('auth:sanctum');

// Mengambil riwayat kejadian postur
Route::get('/devices/{id}/events', [SensorController::class, 'events'])
    ->middleware('auth:sanctum');
    
// Mengirim perintah MQTT ke ESP32
Route::post('/devices/{id}/command', [SensorController::class, 'command'])
    ->middleware('auth:sanctum');