<?php
/********************************************************************
 * mariareichmuth.ch/schnaeggwaegg/kontrolle.php
 * Diese Unterseite dient zur schnellen Überprüfung der Sensordaten,
 * ohne dass phpMyAdmin oder direkte Datenbankzugriffe nötig sind.
 ********************************************************************/

// Datenbankverbindungsdaten laden
require_once("db_config.php");

// Verbindung zur Datenbank aufbauen
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Bei Fehler: Skript abbrechen und Fehlermeldung ausgeben
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// SQL-Abfrage: die letzten 20 Einträge aus der Tabelle `sensordata`, absteigend sortiert
$sql = "SELECT * FROM sensordata ORDER BY id DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// Ergebnis als assoziatives Array holen
$daten = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sensordaten Übersicht</title>
    <style>
        /* Grunddesign für die Seite */
        body {
            font-family: sans-serif;
            padding: 2rem;
            background: #f5f5f5;
        }

        h1 {
            text-align: center;
        }

        /* Tabelle optisch gestalten */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid #ccc;
        }

        th {
            background-color: #007acc;
            color: white;
        }

        tr:nth-child(even) {
            background: #eef6fc;
        }
    </style>
</head>
<body>

<!-- Titel der Seite -->
<h1>Letzte 20 Sensordaten</h1>

<!-- Tabelle mit Daten aus der Datenbank -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Touch</th>
            <th>PIR</th>
            <th>Status</th>
            <th>Zeit</th>
            <th>Gerät</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($daten as $eintrag): ?>
            <tr>
                <!-- Ausgabe einzelner Felder, ID und Gerät zusätzlich mit htmlspecialchars abgesichert -->
                <td><?= htmlspecialchars($eintrag['id']) ?></td>
                <td><?= $eintrag['touch'] ?></td>
                <td><?= $eintrag['pir'] ?></td>
                <td><?= $eintrag['status'] ?></td>
                <td><?= $eintrag['zeit'] ?></td>
                <td><?= isset($eintrag['geraet']) ? htmlspecialchars($eintrag['geraet']) : "-" ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
