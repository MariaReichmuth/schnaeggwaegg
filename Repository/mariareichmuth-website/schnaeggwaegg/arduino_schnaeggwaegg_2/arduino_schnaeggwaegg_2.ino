#include <WiFi.h>
#include <HTTPClient.h>
#include <Arduino_JSON.h>
#include <time.h>

// Sensor-Pin
const int touchPin = 7;
const int ledPin = LED_BUILTIN;

int touchState = 0;
int lastOutputState = -1;

unsigned long lastRestart = 0;
const unsigned long restartInterval = 5 * 60 * 1000; // 5 Minuten

// WLAN-Zugangsdaten
const char* ssid = "T-";
const char* pass = "-";

// URL der Datenbank-Schnittstelle
const char* serverURL = "https://mariareichmuth.ch/schnaeggwaegg/load.php";

// NTP-Konfiguration mit Fallback
const char* ntpServer1 = "pool.ntp.org";
const char* ntpServer2 = "132.163.96.1";
const long gmtOffset_sec = 3600;
const int daylightOffset_sec = 3600;
bool zeitOK = false;

void setup() {
  Serial.begin(115200);
  pinMode(touchPin, INPUT);
  pinMode(ledPin, OUTPUT);

  WiFi.begin(ssid, pass);
  Serial.print("🔌 Verbinde mit WLAN");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.printf("\n✅ WLAN verbunden: %s\n", WiFi.localIP().toString().c_str());

  // Zeitsynchronisation – Erst pool.ntp.org, dann IP
  Serial.println("🕒 Warte auf NTP-Zeit...");
  configTime(gmtOffset_sec, daylightOffset_sec, ntpServer1);
  for (int i = 1; i <= 5; i++) {
    struct tm timeinfo;
    if (getLocalTime(&timeinfo)) {
      zeitOK = true;
      Serial.println("✅ Zeit erfolgreich synchronisiert (1).");
      break;
    }
    Serial.printf("⏳ Versuch %d...\n", i);
    delay(5000);
  }

  if (!zeitOK) {
    Serial.println("🔄 Wechsel zu IP-basiertem NTP...");
    configTime(gmtOffset_sec, daylightOffset_sec, ntpServer2);
    for (int i = 1; i <= 5; i++) {
      struct tm timeinfo;
      if (getLocalTime(&timeinfo)) {
        zeitOK = true;
        Serial.println("✅ Zeit erfolgreich synchronisiert (2).");
        break;
      }
      Serial.printf("⏳ IP-Versuch %d...\n", i);
      delay(5000);
    }
  }

  if (!zeitOK) {
    Serial.println("❌ Zeit konnte nicht synchronisiert werden!");
  }

  lastRestart = millis();
}

void loop() {
  touchState = digitalRead(touchPin);
  int outputState = (touchState == 1) ? 1 : 0;
  digitalWrite(ledPin, outputState);

  // Nur bei Zustandswechsel und nur bei Aktivität (1)
  if (outputState != lastOutputState && outputState == 1 && zeitOK) {
    lastOutputState = outputState;

    struct tm timeinfo;
    if (!getLocalTime(&timeinfo)) {
      Serial.println("❌ Fehler beim Abrufen der Zeit");
      return;
    }

    char timestamp[20];
    strftime(timestamp, sizeof(timestamp), "%Y-%m-%d %H:%M:%S", &timeinfo);

    Serial.print("📡 Aktivität erkannt um ");
    Serial.println(timestamp);

    // JSON-Daten vorbereiten
    JSONVar dataObject;
    dataObject["touch"] = touchState;
    dataObject["status"] = outputState;
    dataObject["zeit"] = timestamp;
    dataObject["geraet"] = "schnaeggwaegg_2";

    String jsonString = JSON.stringify(dataObject);
    Serial.println("📤 Sende: " + jsonString);

    // HTTP POST
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
      Serial.println("❌ WiFi getrennt.");
    }
  }

  // Automatischer Neustart alle 5 Minuten
  if (millis() - lastRestart >= restartInterval) {
    Serial.println("🔁 Neustart zur Kalibrierung...");
    delay(100);
    ESP.restart();
  }

  delay(50);
}
