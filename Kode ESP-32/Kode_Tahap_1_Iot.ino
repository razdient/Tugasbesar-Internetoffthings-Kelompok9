#include <Wire.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>

// ==============================
// WiFi
// ==============================
const char* ssid = "Wokwi-GUEST";
const char* password = "";

// ==============================
// MQTT
// ==============================
const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;
const char* mqtt_topic = "smartposture/rasta/data";

// ==============================
// Hardware
// ==============================
#define SDA_PIN 21
#define SCL_PIN 22
#define BUZZER_PIN 25

Adafruit_MPU6050 mpu;

WiFiClient espClient;
PubSubClient client(espClient);

// ==============================
// Koneksi WiFi
// ==============================
void setupWiFi() {
  Serial.print("Menghubungkan ke WiFi");

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("WiFi terhubung!");
  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());
}

// ==============================
// Koneksi MQTT
// ==============================
void reconnectMQTT() {
  while (!client.connected()) {

    Serial.print("Menghubungkan ke MQTT...");

    String clientId =
      "SmartPosture-" +
      String(random(0xffff), HEX);

    if (client.connect(clientId.c_str())) {

      Serial.println(" terhubung!");

    } else {

      Serial.print(" gagal, kode: ");
      Serial.println(client.state());

      delay(2000);
    }
  }
}

// ==============================
// SETUP
// ==============================
void setup() {

  Serial.begin(115200);

  // Buzzer
  ledcAttach(BUZZER_PIN, 1000, 8);
  ledcWriteTone(BUZZER_PIN, 0);

  // I2C
  Wire.begin(SDA_PIN, SCL_PIN);

  // MPU6050
  if (!mpu.begin()) {

    Serial.println("MPU6050 tidak ditemukan!");

    while (1) {
      delay(10);
    }
  }

  Serial.println("MPU6050 berhasil terhubung!");

  // Pengaturan MPU6050
  mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);

  // WiFi
  setupWiFi();

  // MQTT
  client.setServer(mqtt_server, mqtt_port);
}

// ==============================
// LOOP
// ==============================
void loop() {

  // Pastikan MQTT tetap terhubung
  if (!client.connected()) {
    reconnectMQTT();
  }

  client.loop();

  // Membaca MPU6050
  sensors_event_t a, g, temp;

  mpu.getEvent(&a, &g, &temp);

  // Menghitung sudut kemiringan
  float sudut =
    atan2(
      a.acceleration.x,
      a.acceleration.z
    ) * 180.0 / PI;

  String status;

  // ==============================
  // Menentukan status postur
  // ==============================

  if (abs(sudut) > 20) {

    status = "Membungkuk";

    Serial.println("WARNING: Postur membungkuk!");

    // Buzzer ON
    ledcWriteTone(BUZZER_PIN, 1000);

    delay(300);

    // Buzzer OFF
    ledcWriteTone(BUZZER_PIN, 0);

  } else {

    status = "Normal";

    Serial.println("Postur normal");

    ledcWriteTone(BUZZER_PIN, 0);
  }

  // ==============================
  // Serial Monitor
  // ==============================

  Serial.print("Sudut: ");
  Serial.print(sudut);
  Serial.println(" derajat");

  Serial.print("Status: ");
  Serial.println(status);

  // ==============================
  // Membuat JSON
  // ==============================

  char payload[150];

  snprintf(
    payload,
    sizeof(payload),
    "{\"sudut\":%.2f,\"status\":\"%s\"}",
    sudut,
    status.c_str()
  );

  // ==============================
  // Kirim data ke MQTT
  // ==============================

  client.publish(
    mqtt_topic,
    payload
  );

  Serial.print("MQTT Publish: ");
  Serial.println(payload);

  Serial.println("--------------------");

  delay(1000);
}
