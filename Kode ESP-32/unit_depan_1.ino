// UNIT DEPAN - ESP32 + LCD 16x2 (I2C, chip PCF8574) + rotary encoder. Tanpa library eksternal.
// Tugas: menerima data dari unit belakang (ESP-NOW), menampilkannya di LCD, meneruskannya ke USB
// (agar monitor_postur.html bisa dipakai), dan mengirim perintah ke unit belakang lewat menu.
//
// Wiring:  LCD  : GND, VCC=5V, SDA=GPIO21, SCL=GPIO22
//          Encoder (modul KY-040): GND, "+"=3V3 (JANGAN 5V), CLK=GPIO32, DT=GPIO33, SW=GPIO25
//
// Cara pakai encoder:
//   Layar PANTAU : tekan = buka MENU | tahan 1 detik = kalibrasi langsung
//   MENU         : putar = pilih, tekan = jalankan, tahan 1 detik = kembali
//   BATAS        : putar = ubah batas miring (10-40), tekan = kirim ke unit belakang, tahan = batal

#include <WiFi.h>
#include <esp_now.h>
#include <Wire.h>
#include <string.h>
#include <math.h>

const int PIN_SDA = 21, PIN_SCL = 22;
const int PIN_CLK = 32, PIN_DT = 33, PIN_SW = 25;
uint8_t BACK_MAC[6] = {0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF};   // FF.. = siaran; ganti dengan MAC unit belakang
const uint8_t MAGIC = 0xA5;
#define DEG "\xDF"                                              // simbol derajat bawaan LCD HD44780

struct __attribute__((packed)) DataPkt { uint8_t magic, type; float pitch, roll, tilt, brg, yaw, hold, p0, r0; uint8_t flags, th; uint16_t batt_mV; };  // sama dengan unit belakang
struct __attribute__((packed)) CmdPkt  { uint8_t magic, type; char cmd; float val; };

// ===== LCD 16x2 lewat I2C (PCF8574): P0=RS, P1=RW, P2=EN, P3=lampu, P4-P7=D4-D7 =====
uint8_t lcdAddr = 0;
char shadow[2][17] = {"", ""};
void i2cByte(uint8_t b) { Wire.beginTransmission(lcdAddr); Wire.write(b); Wire.endTransmission(); }
void lcdNib(uint8_t n, uint8_t rs) { uint8_t b = (n & 0xF0) | 0x08 | rs; i2cByte(b | 0x04); delayMicroseconds(2); i2cByte(b); delayMicroseconds(60); }
void lcdSend(uint8_t v, uint8_t rs) { lcdNib(v, rs); lcdNib((uint8_t)(v << 4), rs); }
void lcdCmd(uint8_t c) { lcdSend(c, 0); if (c <= 3) delay(2); }

bool lcdInit() {
  Wire.begin(PIN_SDA, PIN_SCL); Wire.setClock(100000);
  const uint8_t cand[] = {0x27, 0x3F, 0x26, 0x25, 0x24, 0x23, 0x22, 0x21, 0x20, 0x38, 0x39, 0x3A, 0x3B, 0x3C, 0x3D, 0x3E};
  for (uint8_t a : cand) { Wire.beginTransmission(a); if (Wire.endTransmission() == 0) { lcdAddr = a; break; } }
  if (!lcdAddr) { Serial.println("# LCD TIDAK ditemukan di I2C. Cek SDA=GPIO21, SCL=GPIO22, VCC=5V, GND"); return false; }
  Serial.printf("# LCD I2C ditemukan di alamat 0x%02X\n", lcdAddr);
  delay(50); lcdNib(0x30, 0); delay(5); lcdNib(0x30, 0); delayMicroseconds(150); lcdNib(0x30, 0); lcdNib(0x20, 0);   // masuk mode 4-bit
  lcdCmd(0x28); lcdCmd(0x0C); lcdCmd(0x06); lcdCmd(0x01); delay(2);
  return true;
}

void lcdLine(int row, const char *s) {          // hanya menulis ulang baris yang berubah
  char b[17]; snprintf(b, sizeof(b), "%-16s", s);
  if (!lcdAddr || !strcmp(b, shadow[row])) return;
  strcpy(shadow[row], b);
  lcdCmd(0x80 | (row ? 0x40 : 0));
  for (int i = 0; i < 16; i++) lcdSend((uint8_t)b[i], 1);
}

// ===== Data, perintah, dan menu =====
DataPkt last; volatile bool gotNew = false; bool haveData = false;
uint8_t prevFlags = 0; uint32_t lastRx = 0, lastWarn = 0;

enum Mode { PANTAU, MENU, BATAS };
Mode mode = PANTAU; int menuIdx = 0; int thEdit = 20;
char msg[17] = ""; uint32_t msgUntil = 0;
int lastClk = HIGH; bool swState = HIGH, swLast = HIGH, longDone = false; uint32_t swT = 0, pressT = 0;

void onRecv(const esp_now_recv_info_t *info, const uint8_t *data, int len) {
  if (len != (int)sizeof(DataPkt)) return;
  DataPkt d; memcpy(&d, data, sizeof(d));
  if (d.magic == MAGIC && d.type == 1) { last = d; gotNew = true; lastRx = millis(); }
}

void sendCmd(char c, float v) {
  CmdPkt p; p.magic = MAGIC; p.type = 2; p.cmd = c; p.val = v;
  esp_now_send(BACK_MAC, (uint8_t *)&p, sizeof(p));
}
void showMsg(const char *m) { snprintf(msg, sizeof(msg), "%s", m); msgUntil = millis() + 2000; }

const char *arahTeks(float brg) {
  static const char *n[8] = {"depan", "serong kanan-depan", "kanan", "serong kanan-belakang",
                             "belakang", "serong kiri-belakang", "kiri", "serong kiri-depan"};
  return n[((int)roundf(brg / 45.0f)) % 8];
}
const char *arahPendek(float brg) {
  static const char *n[8] = {"Depan", "Depan-Kanan", "Kanan", "Belakang-Kanan", "Belakang", "Belakang-Kiri", "Kiri", "Depan-Kiri"};
  return n[((int)roundf(brg / 45.0f)) % 8];
}

void render(uint32_t now) {
  char a[40], b[40];
  if (now < msgUntil) { lcdLine(0, msg); lcdLine(1, ""); return; }
  if (prevFlags & 4) { lcdLine(0, "Kalibrasi..."); lcdLine(1, "Duduk tegak,diam"); return; }
  if (mode == MENU) {
    char it[4][20]; snprintf(it[0], 20, "Kalibrasi"); snprintf(it[1], 20, "Batas: %d" DEG, haveData ? last.th : thEdit);
    snprintf(it[2], 20, "Mute / aktif"); snprintf(it[3], 20, "Kembali");
    snprintf(a, sizeof(a), "> %s", it[menuIdx]); snprintf(b, sizeof(b), "  %s", it[(menuIdx + 1) % 4]);
    lcdLine(0, a); lcdLine(1, b); return;
  }
  if (mode == BATAS) { lcdLine(0, "Batas miring:"); snprintf(b, sizeof(b), "%d" DEG " (tekan=OK)", thEdit); lcdLine(1, b); return; }
  if (!haveData) { lcdLine(0, "Menunggu data..."); lcdLine(1, "unit belakang"); return; }
  if (last.tilt < 2) { lcdLine(0, "Posisi: LURUS"); snprintf(b, sizeof(b), "(0 derajat)%s", (last.flags & 2) ? "   M" : ""); lcdLine(1, b); return; }
  snprintf(a, sizeof(a), "Miring:%d" DEG "%s", (int)roundf(last.tilt), (last.flags & 1) ? " ALARM" : "");
  snprintf(b, sizeof(b), "%-14s%s", arahPendek(last.brg), (last.flags & 2) ? " M" : "");
  lcdLine(0, a); lcdLine(1, b);
}

void onRotate(int dir) {
  if (mode == MENU) menuIdx = (menuIdx + dir + 4) % 4;
  else if (mode == BATAS) { thEdit += dir; if (thEdit < 10) thEdit = 10; if (thEdit > 40) thEdit = 40; }
}
void onShortPress() {
  if (mode == PANTAU) { mode = MENU; menuIdx = 0; }
  else if (mode == MENU) {
    if (menuIdx == 0) { sendCmd('C', 0); showMsg("Kalibrasi kirim"); Serial.println("# Perintah kalibrasi dikirim ke unit belakang"); mode = PANTAU; }
    else if (menuIdx == 1) { thEdit = haveData ? last.th : 20; mode = BATAS; }
    else if (menuIdx == 2) { sendCmd('M', 0); showMsg("Mute diubah"); Serial.println("# Perintah mute dikirim"); mode = PANTAU; }
    else mode = PANTAU;
  } else {
    sendCmd('S', thEdit); char m[17]; snprintf(m, sizeof(m), "Batas: %d" DEG, thEdit); showMsg(m);
    Serial.printf("# Batas %d derajat dikirim ke unit belakang\n", thEdit); mode = PANTAU;
  }
}
void onLongPress() {
  if (mode == PANTAU) { sendCmd('C', 0); showMsg("Kalibrasi kirim"); Serial.println("# Perintah kalibrasi dikirim ke unit belakang"); }
  else mode = PANTAU;
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_CLK, INPUT_PULLUP); pinMode(PIN_DT, INPUT_PULLUP); pinMode(PIN_SW, INPUT_PULLUP);
  lastClk = digitalRead(PIN_CLK);
  lcdInit(); lcdLine(0, "Unit depan"); lcdLine(1, "Menunggu data...");
  WiFi.mode(WIFI_STA); WiFi.disconnect();
  Serial.printf("# MAC unit depan: %s\n", WiFi.macAddress().c_str());
  if (esp_now_init() != ESP_OK) { Serial.println("# ERROR: ESP-NOW gagal dimulai"); return; }
  esp_now_register_recv_cb(onRecv);
  esp_now_peer_info_t peer = {};
  memcpy(peer.peer_addr, BACK_MAC, 6); peer.channel = 0; peer.encrypt = false;
  if (esp_now_add_peer(&peer) != ESP_OK) Serial.println("# ERROR: gagal menambah peer unit belakang");
  Serial.println("# Unit depan siap. Menunggu data dari unit belakang...");
}

void loop() {
  uint32_t now = millis();

  while (Serial.available()) {                  // perintah dari halaman monitor_postur.html diteruskan ke unit belakang
    char ch = Serial.read();
    if (strchr("cCmMyYpPrRtTdD", ch)) sendCmd(ch, 0);
  }

  int clk = digitalRead(PIN_CLK);               // putar encoder
  if (clk != lastClk && clk == LOW) onRotate((digitalRead(PIN_DT) != clk) ? 1 : -1);   // bila arah terbalik, tukar 1 dan -1
  lastClk = clk;

  bool sw = digitalRead(PIN_SW);                // tombol encoder
  if (sw != swLast) { swT = now; swLast = sw; }
  if (now - swT > 30 && sw != swState) {
    swState = sw;
    if (swState == LOW) { pressT = now; longDone = false; }
    else if (!longDone) onShortPress();
  }
  if (swState == LOW && !longDone && now - pressT >= 1000) { longDone = true; onLongPress(); }

  if (gotNew) {                                 // data baru dari unit belakang
    gotNew = false;
    DataPkt d = last; haveData = true;
    if ((prevFlags & 4) && !(d.flags & 4)) Serial.printf("# Kalibrasi selesai: offset pitch=%.1f roll=%.1f\n", d.p0, d.r0);
    if ((d.flags & 2) != (prevFlags & 2)) Serial.println((d.flags & 2) ? "# Buzzer dimatikan (mute)" : "# Buzzer aktif");
    if (!(prevFlags & 1) && (d.flags & 1)) Serial.println("# ALARM: membungkuk lama");
    prevFlags = d.flags;
    char txt[64];
    if (d.tilt < 2) snprintf(txt, sizeof(txt), "LURUS (0 derajat)");
    else snprintf(txt, sizeof(txt), "miring %d derajat ke %s", (int)roundf(d.tilt), arahTeks(d.brg));
    Serial.printf("Posisi: %s%s | pitch:%.1f,roll:%.1f,tilt:%.1f,brg:%.0f,yaw:%.1f,alarm:%d,hold:%.1f,p0:%.1f,r0:%.1f\n",
                  txt, (d.flags & 1) ? " [ALARM]" : "", d.pitch, d.roll, d.tilt, d.brg, d.yaw, (d.flags & 1) ? 1 : 0, d.hold, d.p0, d.r0);
  }
  if (lastRx && now - lastRx > 2000 && now - lastWarn > 3000) { lastWarn = now; Serial.println("# PERINGATAN: tidak ada data dari unit belakang (cek daya, jarak, antena)"); }
  render(now);
}
