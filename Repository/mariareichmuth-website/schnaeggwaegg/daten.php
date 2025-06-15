<?php
// mariareichmuth.ch/schnaeggwaegg/daten.php
// dient nur als schnelle Kontrolle der Sensordaten,
// ohne die Datenbank manuell über phpMyAdmin aufrufen zu müssen.
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sensordaten – Live-Ansicht</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: sans-serif;
            padding: 2rem;
            background: #f4f4f4;
        }
        h1 {
            text-align: center;
        }
        #chart-container {
            width: 100%;
            max-width: 800px;
            margin: 2rem auto;
        }
        table {
            width: 100%;
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.6rem;
            text-align: center;
            border: 1px solid #ccc;
        }
        th {
            background: #007acc;
            color: white;
        }
        tr:nth-child(even) {
            background: #eef6fc;
        }
    </style>
</head>
<body>

<h1>Sensordaten – Live</h1>

<div id="chart-container">
    <canvas id="statusChart"></canvas>
</div>

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
    <tbody id="data-table">
        <!-- Live-Daten werden hier eingefügt -->
    </tbody>
</table>

<script>
// Holt die aktuellen Sensordaten von der API und aktualisiert Tabelle und Diagramm
async function ladeDaten() {
    const res = await fetch("daten_api.php");
    const daten = await res.json();

    // Tabelle neu befüllen
    const table = document.getElementById("data-table");
    table.innerHTML = "";
    daten.forEach(row => {
        table.innerHTML += `
            <tr>
                <td>${row.id}</td>
                <td>${row.touch}</td>
                <td>${row.pir ?? "-"}</td>
                <td>${row.status}</td>
                <td>${row.zeit}</td>
                <td>${row.geraet}</td>
            </tr>
        `;
    });

    // Diagramm aktualisieren
    const labels = daten.map(e => e.zeit);
    const statusWerte = daten.map(e => e.status);

    statusChart.data.labels = labels.reverse();
    statusChart.data.datasets[0].data = statusWerte.reverse();
    statusChart.update();
}

// Diagramm initialisieren
const ctx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'Status (0 = inaktiv, 1 = aktiv)',
            data: [],
            borderWidth: 2,
            borderColor: 'green',
            backgroundColor: 'rgba(0, 128, 0, 0.2)',
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                min: 0,
                max: 1,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Initialer Abruf + Intervall alle 5 Sekunden
ladeDaten();
setInterval(ladeDaten, 5000);
</script>

</body>
</html>
