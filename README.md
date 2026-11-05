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
| 1 | Gerard Roland K.S | H1H024047 |Perakitan hardware (sabuk, kabel, catu daya) + tuning (ambang, α, kalibrasi di tubuh nyata) |
| 2 | Rasta Listiadi | H1H024053 Dashboard web (Node-RED atau web, tampil sudut, status, riwayat)  |
| 3 | Fathah Ikhwansyah | H1H024063 |Firmware (logika sudut, kalibrasi, timer) + MQTT + laporan  |

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

![Diagram blok](<img width="1700" height="640" alt="image" src="https://github.com/user-attachments/assets/c89026e8-63e7-494f-a3cb-a840a5a6b0d0" />
)
![Flowchart](<img width="1600" height="888" alt="image" src="https://github.com/user-attachments/assets/a71e98b1-339c-407c-bbc9-476312a5184e" />
)
![Konsep](<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/ad4ed119-182a-4cda-8f8f-1d7e57e69c6e" />
)

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

Buka proyek Wokwi: [[Tautan proyek Wokwi](https://wokwi.com/projects/477124223009218561)], lalu tekan tombol Play. Serial monitor menampilkan sudut dan status postur.

<div align="center">

**TEKNIK KOMPUTER**  
**UNIVERSITAS JENDERAL SOEDIRMAN**

</div>
