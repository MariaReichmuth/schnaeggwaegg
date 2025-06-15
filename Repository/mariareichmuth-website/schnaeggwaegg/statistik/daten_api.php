<?php
// Datenbankverbindungsdaten einbinden (Pfad anpassen bei Unterordnern)
require_once("../db_config.php");

// Verbindung zur Datenbank herstellen
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Falls die Verbindung fehlschlägt, Fehlercode 500 und JSON-Fehlermeldung ausgeben
    http_response_code(500);
    echo json_encode(["error" => "DB-Verbindung fehlgeschlagen"]);
    exit;
}

// Optionaler GET-Parameter zum Filtern nach Gerät, z. B. ?geraet=schnaeggwaegg_1
$geraet = $_GET['geraet'] ?? null;

if ($geraet) {
    // Wenn Gerät angegeben ist, nur Daten dieses Geräts laden (letzte 20 Einträge)
    $sql = "SELECT id, touch, pir, status, zeit, geraet 
            FROM sensordata 
            WHERE geraet = :geraet 
            ORDER BY id DESC 
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['geraet' => $geraet]);
} else {
    // Wenn kein Gerät angegeben ist, die letzten 20 Einträge aller Geräte laden
    $sql = "SELECT id, touch, pir, status, zeit, geraet 
            FROM sensordata 
            ORDER BY id DESC 
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}

// Ergebnis als assoziatives Array holen
$daten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rückgabe im JSON-Format
header('Content-Type: application/json');
echo json_encode($daten);
?>
