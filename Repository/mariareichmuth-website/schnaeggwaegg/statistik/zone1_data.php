<?php
// Datenbank-Zugang einbinden (Pfad angepasst wegen Unterordner)
require_once("../db_config.php");

// Verbindung zur Datenbank aufbauen
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Bei Verbindungsfehler: HTTP 500 und Fehlermeldung als JSON ausgeben
    http_response_code(500);
    echo json_encode(["error" => "DB-Verbindung fehlgeschlagen"]);
    exit;
}

// Wenn die Abfrage den Parameter ?letzte=1 enthält:
// -> Letzter Sensor-Event von Gerät 'schnaeggwaegg_1' (touch oder pir)
if (isset($_GET['letzte']) && $_GET['letzte'] == 1) {
    $stmt = $pdo->prepare("
        SELECT * FROM sensordata 
        WHERE geraet = :geraet AND (touch = 1 OR pir = 1) 
        ORDER BY zeit DESC 
        LIMIT 1
    ");
    $stmt->execute(['geraet' => 'schnaeggwaegg_1']);
    $daten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ausgabe des Resultats als JSON
    header('Content-Type: application/json');
    echo json_encode($daten);
    exit;
}

// ------------------------------
// Vorbereitung: 30-Tage-Zeitraum initialisieren
// ------------------------------
$tage = [];
$heute = new DateTime();

for ($i = 29; $i >= 0; $i--) {
    $tag = clone $heute;
    $tag->modify("-$i day");
    $datum = $tag->format('Y-m-d');
    $wochentag_num = $tag->format('w'); // 0 = Sonntag, 6 = Samstag

    // Defaultwerte für jeden Tag setzen
    $tage[$datum] = [
        'datum' => $datum,
        'touch_count' => 0,
        'pir_count' => 0,
        'wochentag_num' => $wochentag_num
    ];
}

// ------------------------------
// Echte Sensordaten aus der DB lesen (letzte 30 Tage, Gerät 1)
// ------------------------------
$sql = "
  SELECT 
    DATE(zeit) AS datum,
    COUNT(CASE WHEN touch = 1 THEN 1 END) AS touch_count,
    COUNT(CASE WHEN pir = 1 THEN 1 END) AS pir_count
  FROM sensordata
  WHERE geraet = 'schnaeggwaegg_1'
    AND zeit >= CURDATE() - INTERVAL 30 DAY
  GROUP BY DATE(zeit)
";
$stmt = $pdo->query($sql);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gefundene Werte den vorbereiteten Tagen zuordnen
foreach ($result as $eintrag) {
    $datum = $eintrag['datum'];
    if (isset($tage[$datum])) {
        $tage[$datum]['touch_count'] = (int)$eintrag['touch_count'];
        $tage[$datum]['pir_count'] = (int)$eintrag['pir_count'];
    }
}

// ------------------------------
// Wochentagsnamen ergänzen (z.B. Mo, Di, Mi, ...)
// ------------------------------
$wochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
foreach ($tage as &$eintrag) {
    $eintrag['wochentag'] = $wochentage[(int)$eintrag['wochentag_num']];
}
unset($eintrag); // Referenz aufheben

// ------------------------------
// Ausgabe als JSON (alle 30 Tage mit Zählern)
// ------------------------------
header('Content-Type: application/json');
echo json_encode(array_values($tage));
