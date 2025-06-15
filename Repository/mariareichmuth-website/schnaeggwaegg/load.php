<?php
/*************************************************************
 * load.php – Empfängt JSON-Daten vom ESP32 und speichert sie in die Tabelle `sensordata`
 * Erwartetes JSON-Format:
 * {
 *   "touch": 1,
 *   "pir": 0,                 // optional (nicht jedes Gerät sendet das)
 *   "status": 1,
 *   "zeit": "2025-05-19 16:21:03",
 *   "geraet": "schnaeggwaegg_1"
 * }
 *************************************************************/

// Fehleranzeige aktivieren (nützlich beim Entwickeln oder Debuggen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Konfigurationsdatei einbinden – enthält DB-Zugangsdaten
require_once("db_config.php");
echo "Empfang läuft...<br>";

###################################### Verbindung zur Datenbank herstellen
try {
    // Mit der Datenbank verbinden
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "✅ DB-Verbindung erfolgreich<br>";
} catch (PDOException $e) {
    // Bei Fehler Verbindung abbrechen und ausgeben
    error_log("DB-Fehler: " . $e->getMessage());
    echo "❌ DB-Verbindung fehlgeschlagen";
    exit;
}

###################################### JSON-Daten empfangen und dekodieren
// JSON-Daten vom ESP32 auslesen
$inputJSON = file_get_contents('php://input');

// JSON-Daten in ein PHP-Array umwandeln
$input = json_decode($inputJSON, true);

// Prüfen, ob die JSON-Daten gültig sind
if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
    echo "❌ Ungültige JSON-Daten erhalten.";
    exit;
}

###################################### Einzelne Werte extrahieren
// Felder aus dem JSON holen, falls vorhanden – sonst null
$touch  = isset($input["touch"])  ? $input["touch"]  : null;
$pir    = isset($input["pir"])    ? $input["pir"]    : null;
$status = isset($input["status"]) ? $input["status"] : null;
$zeit   = isset($input["zeit"])   ? $input["zeit"]   : null;
$geraet = isset($input["geraet"]) ? $input["geraet"] : null;

###################################### Validierung (Pflichtfelder vorhanden?)
if ($touch === null || $status === null || $zeit === null || $geraet === null) {
    echo "❌ Fehlende Felder im JSON.";
    exit;
}

###################################### Daten in Datenbank speichern
// SQL-Befehl vorbereiten (5 Felder, davon pir optional)
$sql = "INSERT INTO sensordata (touch, pir, status, zeit, geraet) VALUES (?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

// SQL ausführen mit den gesammelten Werten
$success = $stmt->execute([$touch, $pir, $status, $zeit, $geraet]);

// Rückmeldung an den Client (ESP32)
if ($success) {
    echo "✅ Datensatz erfolgreich gespeichert.";
} else {
    // Bei Fehler: Details anzeigen
    $errorInfo = $stmt->errorInfo();
    echo "❌ Fehler beim Speichern:<br>";
    echo "SQLSTATE: " . $errorInfo[0] . "<br>";
    echo "Fehlercode: " . $errorInfo[1] . "<br>";
    echo "Nachricht: " . $errorInfo[2] . "<br>";
}
?>
