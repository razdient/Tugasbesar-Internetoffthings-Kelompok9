// Smart Posture Monitor - ESP32 + MPU6050 + buzzer + tombol kalibrasi
// Tanpa library eksternal (MPU6050 dibaca lewat register I2C).
// Wire: MPU6050 SDA=21, SCL=22 | Buzzer=GPIO25 | Tombol=GPIO27 ke GND

#include <Wire.h>
#include <Preferences.h>
#include <math.h>

// ===== Pin =====
const int PIN_SDA = 21;
const int PIN_SCL = 22;
const int PIN_BUZZER = 25;
const int PIN_BUTTON = 27;   // INPUT_PULLUP, tekan = LOW

// ===== Cara memasang sensor =====
// 0 = sensor datar di breadboard/meja (sumbu Z menghadap atas = posisi "tegak")
// 1 = sensor di punggung: sumbu Y ke atas, sumbu Z keluar dari punggung
// Bila tanda sudut terbalik, ubah mode ini atau balik arah sensor.
#define MOUNT 0

// ===== Parameter (nilai awal rancangan, bukan angka baku klinis) =====
const float PITCH_ON  = 20.0f;   // condong depan: alarm mulai dihitung
const float PITCH_OFF = 15.0f;   // alarm mati di bawah ini (histeresis)
const float ROLL_ON   = 15.0f;   // condong samping (usulan)
const float ROLL_OFF  = 10.0f;
const uint32_t HOLD_MS = 10000;  // harus bertahan 10 detik
const uint32_t ESC_MS  = 30000;  // setelah 30 detik bunyi makin cepat
const float ALPHA = 0.98f;       // bobot giroskop pada filter komplementer
const uint32_t LOOP_MS = 20;     // 50 Hz

const uint8_t MPU_ADDR = 0x68;   // AD0 ke GND
const float RAD2DEG = 57.2957795f;

// ===== Buzzer (LEDC) - mendukung ESP32 Arduino core 2.x dan 3.x =====
#if defined(ESP_ARDUINO_VERSION_MAJOR) && ESP_ARDUINO_VERSION_MAJOR >= 3
void buzzInit()            { ledcAttach(PIN_BUZZER, 2000, 10); }
void buzzTone(uint32_t f)  { ledcWriteTone(PIN_BUZZER, f); }
#else
const int BUZZ_CH = 0;
void buzzInit()            { ledcSetup(BUZZ_CH, 2000, 10); ledcAttachPin(PIN_BUZZER, BUZZ_CH); }
void buzzTone(uint32_t f)  { ledcWriteTone(BUZZ_CH, f); }
#endif

uint32_t curFreq = 0;
void buzzSet(uint32_t f) {            // hanya menulis bila berubah
  if (f == curFreq) return;
  curFreq = f;
  buzzTone(f);
}
void beepBlocking(uint32_t f, uint32_t ms) { buzzTone(f); delay(ms); buzzTone(0); curFreq = 0; }

// ===== MPU6050 =====
bool mpuWrite(uint8_t reg, uint8_t val) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(reg);
  Wire.write(val);
  return Wire.endTransmission() == 0;
}

bool readMPU(float a[3], float g[3]) {
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x3B);
  if (Wire.endTransmission(false) != 0) return false;
  if (Wire.requestFrom((uint8_t)MPU_ADDR, (uint8_t)14) != 14) return false;
  int16_t v[7];
  for (int i = 0; i < 7; i++) {
    uint8_t hi = Wire.read();
    uint8_t lo = Wire.read();
    v[i] = (int16_t)((hi << 8) | lo);
  }
  a[0] = v[0] / 4096.0f;  a[1] = v[1] / 4096.0f;  a[2] = v[2] / 4096.0f;   // +-8 g
  g[0] = v[4] / 65.5f;    g[1] = v[5] / 65.5f;    g[2] = v[6] / 65.5f;     // +-500 deg/s
  return true;
}

// Mengubah sumbu sensor menjadi sudut badan.
// pitch + = condong ke depan, roll + = condong ke kanan.
void bodyAngles(const float a[3], const float g[3],
                float &pitchAcc, float &rollAcc, float &pitchRate, float &rollRate) {
#if MOUNT == 0
  float fwd = a[0], side = a[1], up = a[2], gF = g[0], gS = g[1];
#else
  float fwd = -a[2], side = -a[0], up = a[1], gF = -g[2], gS = -g[0];
#endif
  pitchAcc  = atan2f(-fwd, sqrtf(side * side + up * up)) * RAD2DEG;
  rollAcc   = atan2f(side, up) * RAD2DEG;
  pitchRate = gS;
  rollRate  = gF;
}

// ===== Status =====
Preferences prefs;
float pitch = 0, roll = 0;          // sudut terfilter (absolut)
float pitch0 = 0, roll0 = 0;        // offset hasil kalibrasi (= 0 derajat)
bool inited = false;
uint32_t lastUs = 0, lastLoop = 0, lastPrint = 0;

bool over = false, alarmOn = false, muted = false;
uint32_t tStart = 0;

bool calibrating = false;
float calP = 0, calR = 0;
int calN = 0;
const int CAL_SAMPLES = 40;         // 40 x 20 ms = 0,8 detik

// tombol: tekan singkat = kalibrasi, tahan 2 detik = mute/unmute
bool btnState = HIGH, btnLast = HIGH, longDone = false;
uint32_t btnT = 0, pressT = 0;

void startCalibration() {
  calibrating = true; calP = 0; calR = 0; calN = 0;
  over = false; alarmOn = false; buzzSet(0);
  Serial.println("# Kalibrasi: duduk tegak, jangan bergerak...");
}

void handleButton() {
  bool r = digitalRead(PIN_BUTTON);
  uint32_t now = millis();
  if (r != btnLast) { btnT = now; btnLast = r; }
  if (now - btnT > 30 && r != btnState) {
    btnState = r;
    if (btnState == LOW) {
      pressT = now; longDone = false;
      Serial.println("# Tombol DITEKAN");
      beepBlocking(2000, 30);            // bunyi pendek = tombol terbaca
    } else {
      Serial.println("# Tombol DILEPAS");
      if (!longDone) startCalibration();
    }
  }
  if (btnState == LOW && !longDone && now - pressT >= 2000) {
    longDone = true;
    muted = !muted;
    buzzSet(0);
    Serial.println(muted ? "# Buzzer dimatikan (mute)" : "# Buzzer aktif");
  }
}

void updatePosture(uint32_t now) {
  float relP = pitch - pitch0;
  float relR = roll - roll0;
  bool bad  = (relP > PITCH_ON)  || (fabsf(relR) > ROLL_ON);
  bool good = (relP < PITCH_OFF) && (fabsf(relR) < ROLL_OFF);

  if (bad) {
    if (!over) { over = true; tStart = now; }
    if (!alarmOn && now - tStart >= HOLD_MS) {
      alarmOn = true;
      Serial.println("# ALARM: membungkuk lama");
    }
  } else if (good) {
    if (alarmOn) Serial.println("# Postur kembali normal");
    over = false; alarmOn = false;
  } else if (!alarmOn) {
    over = false;                   // zona histeresis: timer dibatalkan bila belum alarm
  }
}

void updateBuzzer(uint32_t now) {
  if (!alarmOn || muted) { buzzSet(0); return; }
  uint32_t t = now - tStart;
  bool esc = t >= ESC_MS;
  uint32_t period = esc ? 500 : 2000;
  if (t % period < 150) buzzSet(esc ? 2000 : 1000);
  else buzzSet(0);
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_BUTTON, INPUT_PULLUP);
  buzzInit();
  Wire.begin(PIN_SDA, PIN_SCL);
  Wire.setClock(400000);

  if (!mpuWrite(0x6B, 0x00)) Serial.println("# ERROR: MPU6050 tidak terdeteksi, cek SDA/SCL/AD0");
  delay(100);
  mpuWrite(0x1A, 0x04);   // filter digital 21 Hz
  mpuWrite(0x1B, 0x08);   // giroskop +-500 deg/s
  mpuWrite(0x1C, 0x10);   // akselerometer +-8 g

  Wire.beginTransmission(MPU_ADDR); Wire.write(0x75); Wire.endTransmission(false);
  Wire.requestFrom((uint8_t)MPU_ADDR, (uint8_t)1);
  uint8_t who = Wire.available() ? Wire.read() : 0xFF;
  Serial.printf("# MPU WHO_AM_I=0x%02X (normal 0x68; 0xFF = sensor tidak terbaca)\n", who);
  Serial.printf("# Tombol saat boot: %s\n", digitalRead(PIN_BUTTON) ? "HIGH (benar)" : "LOW (kaki tombol salah / terhubung terus)");
  prefs.begin("posture", false);
  pitch0 = prefs.getFloat("p0", 0.0f);
  roll0  = prefs.getFloat("r0", 0.0f);
  Serial.printf("# Titik 0 tersimpan: pitch=%.1f roll=%.1f\n", pitch0, roll0);
  Serial.println("# Siap. Tombol: tekan = kalibrasi 0 derajat, tahan 2 detik = mute");
  beepBlocking(1500, 80);
  lastUs = micros();
}

void loop() {
  while (Serial.available()) {          // perintah dari halaman monitor_postur.html
    char ch = Serial.read();
    if (ch == 'c' || ch == 'C') startCalibration();
    else if (ch == 'm' || ch == 'M') { muted = !muted; buzzSet(0); Serial.println(muted ? "# Buzzer dimatikan (mute)" : "# Buzzer aktif"); }
  }
  handleButton();
  uint32_t now = millis();
  if (now - lastLoop < LOOP_MS) return;
  lastLoop = now;

  float a[3], g[3];
  if (!readMPU(a, g)) {
    static uint32_t lastErr = 0;
    if (now - lastErr > 1000) { lastErr = now; Serial.println("# ERROR: gagal membaca MPU6050 (cek SDA/SCL/VCC/GND)"); }
    return;
  }
#if MOUNT == 0
  float upG = a[2];
#else
  float upG = a[1];
#endif
  static uint32_t lastWarn = 0;
  if (upG < 0.5f && now - lastWarn > 3000) {
    lastWarn = now;
    Serial.println("# PERINGATAN: gravitasi tidak di sumbu 'atas' untuk MOUNT ini. Ganti MOUNT (0/1) atau perbaiki letak sensor");
  }

  float pAcc, rAcc, pRate, rRate;
  bodyAngles(a, g, pAcc, rAcc, pRate, rRate);

  uint32_t us = micros();
  float dt = (us - lastUs) / 1e6f;
  lastUs = us;
  if (dt <= 0 || dt > 0.2f) dt = LOOP_MS / 1000.0f;

  if (!inited) { pitch = pAcc; roll = rAcc; inited = true; }
  else {
    pitch = ALPHA * (pitch + pRate * dt) + (1 - ALPHA) * pAcc;
    roll  = ALPHA * (roll  + rRate * dt) + (1 - ALPHA) * rAcc;
  }

  if (calibrating) {
    calP += pitch; calR += roll; calN++;
    if (calN >= CAL_SAMPLES) {
      pitch0 = calP / calN; roll0 = calR / calN;
      prefs.putFloat("p0", pitch0); prefs.putFloat("r0", roll0);
      calibrating = false;
      Serial.printf("# Kalibrasi selesai: offset pitch=%.1f roll=%.1f\n", pitch0, roll0);
      beepBlocking(1500, 80); delay(80); beepBlocking(1500, 80);
    }
  } else {
    updatePosture(now);
    updateBuzzer(now);
  }

  if (now - lastPrint >= 200) {     // format cocok untuk Serial Plotter
    lastPrint = now;
    float hold = over ? (now - tStart) / 1000.0f : 0.0f;
    Serial.printf("pitch:%.1f,roll:%.1f,alarm:%d,hold:%.1f,p0:%.1f,r0:%.1f\n", pitch - pitch0, roll - roll0, alarmOn ? 1 : 0, hold, pitch0, roll0);
  }
}
