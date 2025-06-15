#include <WiFi.h>
#include <HTTPClient.h>
#include <Arduino_JSON.h>
#include <time.h>

// Sensor-Pins
const int touchPin = 7;
const int PIRPin = 1;
const int ledPin = LED_BUILTIN;

int touchState = 0;
int pirState = 0;
int lastOutputState = -1;

unsigned long lastRestart = 0;
const unsigned long restartInterval = 5 * 60 * 1000; // 5 Minuten

// WLAN-Zugangsdaten
const char* ssid = "-";
const char* pass = "-";

// URL der Datenbank-Schnittstelle
const char* serverURL = "https://mariareichmuth.ch/schnaeggwaegg/load.php";

// Zeiteinstellungen
const char* ntpServer = "pool.ntp.org";
const long gmtOffset_sec = 3600;
const int daylightOffset_sec = 3600;

void blinkLED(int count, int duration) {
  for (int i = 0; i < count; i++) {
    digitalWrite(ledPin, HIGH);
    delay(duration);
    digitalWrite(ledPin, LOW);
    delay(duration);
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(touchPin, INPUT);
  pinMode(PIRPin, INPUT);
  pinMode(ledPin, OUTPUT);

  WiFi.begin(ssid, pass);
  Serial.print("🔌 Verbinde mit WLAN");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.printf("\n✅ WLAN verbunden: %s\n", WiFi.localIP().toString().c_str());

  // NTP konfigurieren
  configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
  Serial.println("🕒 Warte auf NTP-Zeit...");

  struct tm timeinfo;
  int versuche = 0;
  while (!getLocalTime(&timeinfo) && versuche < 10) {
    Serial.printf("⏳ Versuch %d...\n", versuche + 1);
    delay(1000);
    versuche++;
  }

  if (versuche == 10) {
    Serial.println("❌ Zeit konnte nicht synchronisiert werden!");
  } else {
    char t[20];
    strftime(t, sizeof(t), "%Y-%m-%d %H:%M:%S", &timeinfo);
    Serial.printf("✅ Zeit synchronisiert: %s\n", t);
  }

  lastRestart = millis();
}

void loop() {
  touchState = digitalRead(touchPin);
  pirState = digitalRead(PIRPin);

  int outputState = (touchState == 1 || pirState == 1) ? 1 : 0;
  digitalWrite(ledPin, outputState);

  if (outputState != lastOutputState) {
    lastOutputState = outputState;

    if (outputState == 1) {
      // Zeit abrufen
      struct tm timeinfo;
      if (!getLocalTime(&timeinfo)) {
        Serial.println("❌ Fehler beim Abrufen der Zeit.");
        return;
      }

      char timestamp[20];
      strftime(timestamp, sizeof(timestamp), "%Y-%m-%d %H:%M:%S", &timeinfo);

      Serial.print("📍 Sensorstatus geändert: ");
      Serial.print(outputState);
      Serial.print(" | Zeit: ");
      Serial.println(timestamp);

      // LED-Muster
      if (touchState == 1 && pirState == 0) blinkLED(1, 200);
      else if (touchState == 0 && pirState == 1) blinkLED(2, 150);
      else if (touchState == 1 && pirState == 1) blinkLED(3, 100);

      // JSON-Daten vorbereiten
      JSONVar dataObject;
      dataObject["touch"] = touchState;
      dataObject["pir"] = pirState;
      dataObject["status"] = outputState;
      dataObject["zeit"] = timestamp;
      dataObject["geraet"] = "schnaeggwaegg_1";

      String jsonString = JSON.stringify(dataObject);
      Serial.println("📤 Sende: " + jsonString);

      if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(serverURL);
        http.addHeader("Content-Type", "application/json");

        int httpResponseCode = http.POST(jsonString);
        if (httpResponseCode > 0) {
          String response = http.getString();
          Serial.printf("✅ HTTP %d: %s\n", httpResponseCode, response.c_str());
        } else {
          Serial.printf("❌ Fehler beim Senden: %d\n", httpResponseCode);
        }

        http.end();
      } else {
        Serial.println("⚠️ WLAN nicht verbunden.");
      }
    }
  }

  if (millis() - lastRestart >= restartInterval) {
    Serial.println("♻️ Neustart zur Kalibrierung...");
    delay(100);
    ESP.restart();
  }

  delay(50);
}
