<?php
// Verbindung zur Datenbank einbinden
require_once("../db_config.php");

try {
    // Verbindung mit PDO herstellen
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Bei Fehler: HTTP-Fehlercode 500 zurückgeben und Fehlermeldung als JSON
    http_response_code(500);
    echo json_encode(["error" => "DB-Verbindung fehlgeschlagen"]);
    exit;
}

// Berechnung ab 6 Tage vor heute (inkl. heute = 7 Tage)
$heute = new DateTime();
$start = $heute->modify('-6 days')->format('Y-m-d');

// ----------------------------------------------
// ZONE 1 (Gerät: schnaeggwaegg_1) — Touch + PIR
// ----------------------------------------------
$sql_z1 = "SELECT SUM(touch + IFNULL(pir, 0)) AS summe FROM sensordata 
           WHERE geraet = 'schnaeggwaegg_1' AND DATE(zeit) >= :start";
$stmt_z1 = $pdo->prepare($sql_z1);
$stmt_z1->execute(['start' => $start]);
$summe_z1 = (int)$stmt_z1->fetchColumn();

// ----------------------------------------------
// ZONE 3 (Gerät: schnaeggwaegg_2) — nur Touch
// ----------------------------------------------
$sql_z3 = "SELECT SUM(touch) AS summe FROM sensordata 
           WHERE geraet = 'schnaeggwaegg_2' AND DATE(zeit) >= :start";
$stmt_z3 = $pdo->prepare($sql_z3);
$stmt_z3->execute(['start' => $start]);
$summe_z3 = (int)$stmt_z3->fetchColumn();

// Erfolgsquote berechnen (Differenz zu Zone 1)
$quote = $summe_z1 > 0 ? round((1 - ($summe_z3 / $summe_z1)) * 100) : 0;

// Antwort als JSON zurückgeben
echo json_encode([
    "zone1" => $summe_z1,
    "zone2" => $summe_z1, // Zone 2 zeigt visuell das gleiche wie Zone 1
    "zone3" => $summe_z3,
    "erfolgsquote" => $quote
]);
