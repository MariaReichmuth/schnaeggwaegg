<?php
// Konfigurationsdatei mit DB-Zugangsdaten einbinden
require_once("../db_config.php");

// Verbindung zur Datenbank aufbauen
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Bei Fehler JSON-Antwort und Abbruch
    http_response_code(500);
    echo json_encode(["error" => "DB-Verbindung fehlgeschlagen"]);
    exit;
}

// Wenn "?letzte=1" in der URL steht → letzte Schneckenaktivität aus Zone 3 liefern
if (isset($_GET['letzte']) && $_GET['letzte'] == 1) {
    $stmt = $pdo->prepare("SELECT * FROM sensordata WHERE geraet = :geraet AND touch = 1 ORDER BY zeit DESC LIMIT 1");
    $stmt->execute(['geraet' => 'schnaeggwaegg_2']); // Gerät = Zone 3
    $daten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // JSON-Ausgabe und Skript beenden
    header('Content-Type: application/json');
    echo json_encode($daten);
    exit;
}

// Vorbereitung: leeres Array mit den letzten 30 Tagen
$tage = [];
$heute = new DateTime();

for ($i = 29; $i >= 0; $i--) {
    $tag = clone $heute;
    $tag->modify("-$i day");
    $datum = $tag->format('Y-m-d');
    $wochentag_num = $tag->format('w'); // 0 = Sonntag
    $tage[$datum] = [
        'datum' => $datum,
        'touch_count' => 0,
        'wochentag_num' => $wochentag_num
    ];
}

// Echte Touch-Daten aus DB abrufen (nur für Zone 3 / Gerät 2)
$sql = "
  SELECT 
    DATE(zeit) AS datum,
    COUNT(CASE WHEN touch = 1 THEN 1 END) AS touch_count
  FROM sensordata
  WHERE geraet = 'schnaeggwaegg_2'
    AND zeit >= CURDATE() - INTERVAL 30 DAY
  GROUP BY DATE(zeit)
";
$stmt = $pdo->query($sql);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gefundene Daten in das vorbereitete Array einfügen
foreach ($result as $eintrag) {
    $datum = $eintrag['datum'];
    if (isset($tage[$datum])) {
        $tage[$datum]['touch_count'] = (int)$eintrag['touch_count'];
    }
}

// Wochentag (z. B. "Mo") ergänzen
$wochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
foreach ($tage as &$eintrag) {
    $eintrag['wochentag'] = $wochentage[(int)$eintrag['wochentag_num']];
}
unset($eintrag); // Referenz aufheben

// JSON-Ausgabe der Tagesstatistik
header('Content-Type: application/json');
echo json_encode(array_values($tage));
