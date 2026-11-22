// Smart Posture Monitor - ESP32 + MPU6050 + buzzer + tombol kalibrasi
// Tanpa library eksternal (MPU6050 dibaca lewat register I2C).
// Wire: MPU6050 SDA=21, SCL=22 | Buzzer=GPIO25 | Tombol=GPIO27 ke GND

#include <Wire.h>
#include <Preferences.h>
#include <math.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>

void initNow();   // didefinisikan di bagian komunikasi (di bawah)

// ===== Pin =====
const int PIN_SDA = 21;
const int PIN_SCL = 22;
const int PIN_BUZZER = 25;
const int PIN_MOTOR = 26;    // motor getar lewat transistor (jangan langsung dari pin)
const int PIN_BUTTON = 27;   // INPUT_PULLUP, tekan = LOW

// ===== Cara memasang sensor =====
// 0 = sensor datar di breadboard/meja (sumbu Z menghadap atas = posisi "tegak")
// 1 = sensor di punggung: sumbu Y ke atas, sumbu Z keluar dari punggung
// Bila tanda sudut terbalik, ubah mode ini atau balik arah sensor.
#define MOUNT 0

// ===== Parameter (nilai awal rancangan, bukan angka baku klinis) =====
float TILT_ON  = 20.0f;    // miring > 20 derajat ke ARAH MANA PUN (depan, belakang, kiri, kanan, serong)
float TILT_OFF = 15.0f;    // alarm mati di bawah ini (histeresis)
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
  digitalWrite(PIN_MOTOR, f ? HIGH : LOW);   // motor getar ikut menyala bersama bunyi
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
                float &pitchAcc, float &rollAcc, float &pitchRate, float &rollRate, float &yawRate) {
#if MOUNT == 0
  float fwd = a[0], side = a[1], up = a[2], gF = g[0], gS = g[1];
#else
  float fwd = -a[2], side = -a[0], up = a[1], gF = -g[2], gS = -g[0];
#endif
  pitchAcc  = atan2f(-fwd, sqrtf(side * side + up * up)) * RAD2DEG;
  rollAcc   = atan2f(side, up) * RAD2DEG;
  pitchRate = gS;
  rollRate  = gF;
#if MOUNT == 0
  yawRate = -g[2];                  // + = berputar ke kanan
#else
  yawRate = -g[1];
#endif
}

// Sudut miring total dari tegak, dan arahnya (0=depan, 90=kanan, 180=belakang, 270=kiri)
void tiltDir(float pitchDeg, float rollDeg, float &tilt, float &bearing) {
  float a = pitchDeg / RAD2DEG, b = rollDeg / RAD2DEG;
  float c = cosf(a) * cosf(b);
  if (c > 1) c = 1; if (c < -1) c = -1;
  tilt = acosf(c) * RAD2DEG;
  bearing = atan2f(cosf(a) * sinf(b), sinf(a)) * RAD2DEG;
  if (bearing < 0) bearing += 360.0f;
}
const char* arahTeks(float brg) {
  static const char* n[8] = {"depan", "serong kanan-depan", "kanan", "serong kanan-belakang",
                             "belakang", "serong kiri-belakang", "kiri", "serong kiri-depan"};
  return n[((int)roundf(brg / 45.0f)) % 8];
}

// ===== Status =====
Preferences prefs;
float pitch = 0, roll = 0;          // sudut terfilter (absolut)
float pitch0 = 0, roll0 = 0;
int pSign = 1, rSign = 1, ySign = 1;   // -1 = arah dibalik (disimpan di memori)
bool dbg = false;                      // true = cetak data mentah sensor
float yaw = 0, yaw0 = 0, yawBias = 0;   // arah hadap dari giroskop (perkiraan, bisa melayang)        // offset hasil kalibrasi (= 0 derajat)
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
  float tilt, brg;
  tiltDir(relP, relR, tilt, brg);
  bool bad  = tilt > TILT_ON;
  bool good = tilt < TILT_OFF;

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
  pinMode(PIN_MOTOR, OUTPUT); digitalWrite(PIN_MOTOR, LOW);
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
  {                                 // ukur bias giroskop saat diam (jangan digerakkan saat menyala)
    float sum = 0; int n = 0;
    for (int i = 0; i < 100; i++) {
      float a[3], g[3], pa, ra, pr, rr, yr;
      if (readMPU(a, g)) { bodyAngles(a, g, pa, ra, pr, rr, yr); sum += yr; n++; }
      delay(5);
    }
    if (n) yawBias = sum / n;
    Serial.printf("# Bias giroskop yaw=%.2f deg/s (jangan gerakkan sensor saat menyala)\n", yawBias);
  }
  prefs.begin("posture", false);
  pSign = prefs.getInt("ps", 1); rSign = prefs.getInt("rs", 1); ySign = prefs.getInt("ys", 1);
  pitch0 = prefs.getFloat("p0", 0.0f);
  roll0  = prefs.getFloat("r0", 0.0f);
  Serial.printf("# Titik 0 tersimpan: pitch=%.1f roll=%.1f\n", pitch0, roll0);
  TILT_ON = prefs.getFloat("th", 20.0f); TILT_OFF = TILT_ON - 5.0f;
  Serial.printf("# Batas miring: %.0f derajat\n", TILT_ON);
  initNow();
  Serial.println("# Siap. Tombol: tekan = kalibrasi 0 derajat, tahan 2 detik = mute");
  beepBlocking(1500, 80);
  lastUs = micros();
}

// ===== Komunikasi ESP-NOW dengan unit depan =====
uint8_t FRONT_MAC[6] = {0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF};   // FF.. = siaran ke semua; ganti dengan MAC unit depan
// Jika unit depan terhubung ke WiFi router (gateway MQTT), set channel ini
// sama dengan channel WiFi router. 0 = auto (cocok untuk ESP-NOW murni tanpa WiFi router).
#define ESPNOW_WIFI_CHANNEL 0
const uint8_t MAGIC = 0xA5;
struct __attribute__((packed)) DataPkt { uint8_t magic, type; float pitch, roll, tilt, brg, yaw, hold, p0, r0; uint8_t flags, th; uint16_t batt_mV; };  // type 1
struct __attribute__((packed)) CmdPkt  { uint8_t magic, type; char cmd; float val; };                                                          // type 2
volatile char pendingCmd = 0; volatile float pendingVal = 0;

void onRecv(const esp_now_recv_info_t *info, const uint8_t *data, int len) {
  if (len != (int)sizeof(CmdPkt)) return;
  const CmdPkt *c = (const CmdPkt *)data;
  if (c->magic == MAGIC && c->type == 2) { pendingVal = c->val; pendingCmd = c->cmd; }
}

void initNow() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  if (ESPNOW_WIFI_CHANNEL > 0) {
    esp_wifi_set_channel(ESPNOW_WIFI_CHANNEL, WIFI_SECOND_CHAN_NONE);
    Serial.printf("# ESP-NOW channel dipaksa ke %d\n", ESPNOW_WIFI_CHANNEL);
  }
  Serial.printf("# MAC unit belakang: %s\n", WiFi.macAddress().c_str());
  if (esp_now_init() != ESP_OK) { Serial.println("# ERROR: ESP-NOW gagal dimulai"); return; }
  esp_now_register_recv_cb(onRecv);
  esp_now_peer_info_t peer = {};
  memcpy(peer.peer_addr, FRONT_MAC, 6);
  peer.channel = ESPNOW_WIFI_CHANNEL;
  peer.encrypt = false;
  if (esp_now_add_peer(&peer) != ESP_OK) Serial.println("# ERROR: gagal menambah peer unit depan");
}

void sendNow(float p, float r, float tilt, float brg, float yw, float hold) {
  DataPkt d; d.magic = MAGIC; d.type = 1;
  d.pitch = p; d.roll = r; d.tilt = tilt; d.brg = brg; d.yaw = yw; d.hold = hold; d.p0 = pitch0; d.r0 = roll0;
  d.flags = (alarmOn ? 1 : 0) | (muted ? 2 : 0) | (calibrating ? 4 : 0);   // bit0 alarm, bit1 mute, bit2 sedang kalibrasi
  d.th = (uint8_t)roundf(TILT_ON);                                        // batas miring aktif, agar unit depan sinkron
  d.batt_mV = 0;                                                          // isi nanti dari pembagi tegangan baterai
  esp_now_send(FRONT_MAC, (uint8_t *)&d, sizeof(d));
}

// Perintah dari USB (halaman) atau dari unit depan (ESP-NOW). C=kalibrasi, M=mute, Y=nol-kan arah, P/R/T=balik arah, D=data mentah, S=ubah batas
void handleCmd(char ch, float val) {
    if (ch == 'c' || ch == 'C') startCalibration();
    else if (ch == 'p' || ch == 'P') { pSign = -pSign; pitch = -pitch; pitch0 = -pitch0; prefs.putInt("ps", pSign); prefs.putFloat("p0", pitch0); Serial.println("# Arah depan-belakang DIBALIK"); }
    else if (ch == 'r' || ch == 'R') { rSign = -rSign; roll = -roll; roll0 = -roll0; prefs.putInt("rs", rSign); prefs.putFloat("r0", roll0); Serial.println("# Arah kiri-kanan DIBALIK"); }
    else if (ch == 't' || ch == 'T') { ySign = -ySign; yaw = -yaw; yaw0 = -yaw0; prefs.putInt("ys", ySign); Serial.println("# Arah putar DIBALIK"); }
    else if (ch == 'd' || ch == 'D') { dbg = !dbg; Serial.println(dbg ? "# Data mentah ON" : "# Data mentah OFF"); }
    else if (ch == 'y' || ch == 'Y') { yaw0 = yaw; Serial.println("# Arah hadap di-nol-kan"); }
    else if (ch == 'm' || ch == 'M') { muted = !muted; buzzSet(0); Serial.println(muted ? "# Buzzer dimatikan (mute)" : "# Buzzer aktif"); }
    else if (ch == 's' || ch == 'S') {
      if (val >= 10 && val <= 40) { TILT_ON = val; TILT_OFF = val - 5.0f; prefs.putFloat("th", TILT_ON); Serial.printf("# Batas miring diubah: %.0f derajat\n", TILT_ON); }
    }
}

void loop() {
  while (Serial.available()) handleCmd(Serial.read(), 0);
  if (pendingCmd) { char c = pendingCmd; float v = pendingVal; pendingCmd = 0; handleCmd(c, v); }
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

  float pAcc, rAcc, pRate, rRate, yRate;
  bodyAngles(a, g, pAcc, rAcc, pRate, rRate, yRate);
  pAcc *= pSign; pRate *= pSign; rAcc *= rSign; rRate *= rSign;

  uint32_t us = micros();
  float dt = (us - lastUs) / 1e6f;
  lastUs = us;
  if (dt <= 0 || dt > 0.2f) dt = LOOP_MS / 1000.0f;

  if (!inited) { pitch = pAcc; roll = rAcc; inited = true; }
  else {
    pitch = ALPHA * (pitch + pRate * dt) + (1 - ALPHA) * pAcc;
    roll  = ALPHA * (roll  + rRate * dt) + (1 - ALPHA) * rAcc;
    yaw  += (yRate - yawBias) * ySign * dt;
  }

  if (calibrating) {
    calP += pitch; calR += roll; calN++;
    if (calN >= CAL_SAMPLES) {
      pitch0 = calP / calN; roll0 = calR / calN; yaw0 = yaw;
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
    float yw = fmodf(yaw - yaw0, 360.0f);
    if (yw > 180) yw -= 360; if (yw < -180) yw += 360;
    float p = pitch - pitch0, r = roll - roll0;
    float tilt, brg;
    tiltDir(p, r, tilt, brg);
    char txt[64];
    if (tilt < 2) snprintf(txt, sizeof(txt), "LURUS (0 derajat)");
    else snprintf(txt, sizeof(txt), "miring %d derajat ke %s", (int)roundf(tilt), arahTeks(brg));
    // satu baris: kalimat untuk dibaca manusia | data untuk monitor_postur.html
    Serial.printf("Posisi: %s%s | pitch:%.1f,roll:%.1f,tilt:%.1f,brg:%.0f,yaw:%.1f,alarm:%d,hold:%.1f,p0:%.1f,r0:%.1f\n",
                  txt, alarmOn ? " [ALARM]" : "", p, r, tilt, brg, yw, alarmOn ? 1 : 0, hold, pitch0, roll0);
    sendNow(p, r, tilt, brg, yw, hold);
    if (dbg) Serial.printf("# mentah: accel=(%.2f, %.2f, %.2f) g | gyro=(%.1f, %.1f, %.1f) deg/s\n", a[0], a[1], a[2], g[0], g[1], g[2]);
  }
}
