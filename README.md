 <div align="center">

<img src="https://unsoed.ac.id/media-proxy/media/large_Logo_Resmi_Unsoed_5b6a514e09.png" alt="Logo Universitas Jenderal Soedirman" width="130">

# TUGAS BESAR
## PRAKTIKUM INTERNET OF THINGS (IoT)

**UNIVERSITAS JENDERAL SOEDIRMAN**

Program Studi Teknik Komputer

---

</div>

## Identitas Kelompok

| Keterangan | Informasi |
|:---|:---|
| Nama Kelompok | Smart Posture Monitor |
| Kelas | B |
| Mata Kuliah | Praktikum Internet of Things |
| Program Studi | Teknik Komputer |
| Universitas | Universitas Jenderal Soedirman |

## Anggota Kelompok

| No. | Nama | NIM | Peran |
|:---:|---|:---:|---|
| 1 | Gerard Roland K.S | H1H024047 |Perangkat keras dan sensor |
| 2 | Rasta Listiadi | H1H024053 |Firmware dan logika peringatan |
| 3 | Fathah Ikhwansyah | H1H024063 |Jaringan (MQTT) dan dashboard |

---
# Smart Posture Monitor

Sabuk pemantau postur duduk berbasis **ESP32** dan sensor **MPU6050**, dengan peringatan buzzer dan dashboard pemantauan lewat Wi-Fi (MQTT).

## Deskripsi Singkat

Sensor MPU6050 dipasang pada sabuk di punggung untuk membaca sudut kemiringan tubuh. ESP32 menghitung sudut terhadap posisi tegak. Jika sudut melewati ambang awal 20° dan bertahan 10 detik (nilai awal), buzzer berbunyi. Data sudut dan status dikirim ke dashboard untuk pemantauan real-time dan riwayat.

## Komponen

| Komponen | Fungsi |
|---|---|
| ESP32 DevKit | Pengendali utama dan Wi-Fi |
| MPU6050 | Akselerometer dan giroskop (I2C: SDA GPIO 21, SCL GPIO 22) |
| Buzzer | Peringatan suara (GPIO 25, PWM) |
| Sabuk elastis | Penahan modul di punggung |
| Catu daya 5 V | Sumber daya portabel |
| Mosquitto + Node-RED | Broker MQTT, server, dan dashboard (rencana) |

## Arsitektur

Lapisan IoT: Perception (MPU6050, buzzer) → Processing edge (ESP32) → Network (Wi-Fi, MQTT) → Processing server (Node-RED) → Application (dashboard).

![Diagram blok](docs/Diagram_Blok_Smart_Posture_Monitor.png)
![Flowchart](docs/Flowchart_Smart_Posture_Monitor.png)
![Konsep](docs/Gambar_Konsep_Smart_Posture_Monitor.png)

## Struktur Repositori

```
.
├── README.md
├── docs/        # diagram, flowchart, PDF progress
└── src/         # kode ESP32 (.ino), simulasi Wokwi
```

## Status Progres

- [x] Simulasi Wokwi: ESP32 + MPU6050 + buzzer, hitung sudut, ambang 20°
- [ ] Timer 10 detik dan kalibrasi posisi tegak
- [ ] Filter komplementer (akselerometer + giroskop)
- [ ] Wi-Fi, MQTT, dashboard Node-RED
- [ ] Perakitan perangkat keras pada sabuk
- [ ] Pengujian akurasi dan uji pengguna

## Menjalankan Simulasi

Buka proyek Wokwi: [Tautan proyek Wokwi], lalu tekan tombol Play. Serial monitor menampilkan sudut dan status postur.

<div align="center">

**TEKNIK KOMPUTER**  
**UNIVERSITAS JENDERAL SOEDIRMAN**

</div>
