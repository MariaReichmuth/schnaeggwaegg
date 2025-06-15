// Kontrollvariable für Scroll-Intro
let triggered = false;

// Intro-Animation auslösen
function triggerAnimation() {
  const videoContainer = document.querySelector(".video-container");
  videoContainer?.classList.add("shrink");
  triggered = true;

  // Nach Animation Scroll erlauben + Logo aktivieren
  setTimeout(() => {
    document.body.classList.remove("lock-scroll");

    const logo = document.getElementById("logo");
    if (logo) {
      logo.style.cursor = "pointer";
      logo.classList.add("logo-clickable");

      // Beim Klick aufs Logo zurück zur Startansicht
      logo.addEventListener("click", () => {
        document.getElementById("statistik-wrapper").style.display = "none";
        document.getElementById("start-content").style.display = "block";
        window.scrollTo({ top: 0, behavior: "auto" });
      });
    }
  }, 1600);
}

// Scroll verhindern, bis Animation fertig
function handleScrollTrigger(e) {
  if (!triggered) {
    e.preventDefault();
    window.scrollTo(0, 0);
    triggerAnimation();
  }
}

// Nach dem Laden der Seite: Scroll sperren & Intro starten
document.addEventListener("DOMContentLoaded", () => {
  window.addEventListener("wheel", handleScrollTrigger, { passive: false });
  window.addEventListener("touchmove", handleScrollTrigger, { passive: false });
  window.addEventListener("scroll", () => {
    if (!triggered) window.scrollTo(0, 0);
  });

  // Letzte Aktivität für Zone 1 und 3 laden
  ladeLetzteAktivitaet("zone1", "schnaeggwaegg_1");
  ladeLetzteAktivitaet("zone3", "schnaeggwaegg_2");

  // Nach Klick auf Links nach oben scrollen
  document.querySelectorAll("button, a").forEach((el) => {
    el.addEventListener("click", () => {
      setTimeout(() => {
        window.scrollTo({ top: 0, behavior: "instant" });
      }, 50);
    });
  });
});

// Holt die letzte Aktivität einer Zone vom Server und zeigt sie an
function ladeLetzteAktivitaet(zone, geraetName) {
  fetch(`statistik/${zone}_data.php?letzte=1`)
    .then((res) => res.json())
    .then((daten) => {
      if (!daten.length) return;

      const zeit = new Date(daten[0].zeit);
      const hhmm = zeit.toLocaleTimeString("de-CH", { hour: "2-digit", minute: "2-digit" });
      const ddmm = zeit.toLocaleDateString("de-CH", { day: "2-digit", month: "2-digit" });
      const format = `${hhmm}, ${ddmm}`;

      const el = document.getElementById(`time-${zone}`);
      if (el) el.textContent = format;
    });
}

// Buttons „Zurück“ initialisieren
function initialisiereZurueckButtons() {
  document.querySelectorAll(".zurueck-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.getElementById("statistik-wrapper").style.display = "none";
      document.getElementById("start-content").style.display = "block";
      window.scrollTo({ top: 0, behavior: "auto" });
    });
  });
}

// Buttons im „Über“-Bereich initialisieren
function initialisiereUeberSeiteButtons() {
  document.querySelectorAll(".zone-nav-btn").forEach((btn) => {
    btn.addEventListener("click", () => ladeStatistik(btn.dataset.zone));
  });
}

// Statistik für eine bestimmte Zone oder Seite laden
function ladeStatistik(zone) {
  const wrapper = document.getElementById("statistik-wrapper");
  const startContent = document.getElementById("start-content");

  // Übersicht „Alle Zonen“ laden
  if (zone === "alleZonen") {
    ladeAlleZonenStatistik();
    return;
  }

  // „Über“-Seite laden
  if (zone === "ueber") {
    fetch("statistik/ueber.html")
      .then((res) => res.text())
      .then((html) => {
        wrapper.innerHTML = html;
        wrapper.style.display = "block";
        startContent.style.display = "none";
        window.scrollTo({ top: 0, behavior: "auto" });
        initialisiereZurueckButtons();
        initialisiereUeberSeiteButtons();
      });
    return;
  }

  // Zonen-HTML laden (Zone 1, 2 oder 3)
  fetch(`statistik/${zone}.html`)
    .then((res) => res.text())
    .then((html) => {
      wrapper.innerHTML = html;
      wrapper.style.display = "block";
      startContent.style.display = "none";
      window.scrollTo({ top: 0, behavior: "auto" });

      // Navigation aktiv halten
      const buttons = document.querySelectorAll(".zone-nav-btn");
      buttons.forEach((btn) => {
        btn.classList.remove("active-zone-btn");
        if (btn.dataset.zone === zone) {
          btn.classList.add("active-zone-btn");
        }
        btn.addEventListener("click", () => ladeStatistik(btn.dataset.zone));
      });

      initialisiereZurueckButtons();

      // Für Zone 2 keine Charts laden
      if (zone === "zone2") return;

      // Für Zonen 1 und 3 Chart initialisieren
      const canvasId = zone === "zone1" ? "chartZone1" : "chartZone3";
      const hasPir = zone === "zone1";
      setupChartAndButtons(zone, canvasId, hasPir);
    });
}

// Diagramm für eine Zone erstellen und steuern
function setupChartAndButtons(zone, canvasId, hasPir) {
  let chart;

  function ladeDaten(tage) {
    fetch(`statistik/${zone}_data.php`)
      .then((res) => res.json())
      .then((data) => {
        const dayNames = ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"];
        const heute = new Date();
        const labels = [];

        // Erzeuge Label für letzten N Tage (auch wenn leer)
        for (let i = tage - 1; i >= 0; i--) {
          const d = new Date();
          d.setDate(heute.getDate() - i);
          const wd = dayNames[d.getDay()];
          const key = d.toISOString().split("T")[0];
          labels.push({ label: wd, key });
        }

        // Füge zu jedem Tag die vorhandenen Daten (oder 0) hinzu
        const mapped = labels.map((item) => {
          const found = data.find((e) => e.datum === item.key);
          return {
            label: item.label,
            touch: found ? +found.touch_count : 0,
            pir: hasPir && found ? +found.pir_count : 0,
          };
        });

        // Daten für Diagramm vorbereiten
        const chartLabels = mapped.map((e) => e.label);
        const touch = mapped.map((e) => e.touch);
        const pir = hasPir ? mapped.map((e) => e.pir) : [];

        // Balkendaten definieren
        const datasets = [
          {
            label: "Berührung",
            data: touch,
            backgroundColor: "#4B4B4B",
            borderRadius: 4,
            barPercentage: 0.6,
          },
        ];

        if (hasPir) {
          datasets.push({
            label: "Bewegung",
            data: pir,
            backgroundColor: "#9F2395",
            borderRadius: 4,
            barPercentage: 0.6,
          });
        }

        if (chart) chart.destroy(); // Vorherigen Chart löschen

        const ctx = document.getElementById(canvasId).getContext("2d");

        // Diagramm mit Chart.js erstellen
        chart = new Chart(ctx, {
          type: "bar",
          data: { labels: chartLabels, datasets },
          options: {
            responsive: true,
            plugins: {
              legend: {
                position: "bottom",
                labels: { color: "#000", font: { size: 12 } },
              },
              tooltip: {
                backgroundColor: "#222",
                titleColor: "#fff",
                bodyColor: "#fff",
              },
            },
            scales: {
              x: {
                grid: { display: false },
                ticks: { color: "#000", font: { size: 14 } },
              },
              y: {
                beginAtZero: true,
                grid: { color: "#ccc" },
                ticks: { color: "#000", font: { size: 14 } },
              },
            },
          },
        });
      });
  }

  // Buttons zur Auswahl des Zeitraums
  const btn7 = document.querySelector(".toggle-btn[data-range='7']");
  const btn30 = document.querySelector(".toggle-btn[data-range='30']");

  btn7?.addEventListener("click", () => {
    btn7.classList.add("active");
    btn30?.classList.remove("active");
    ladeDaten(7);
  });

  btn30?.addEventListener("click", () => {
    btn30.classList.add("active");
    btn7?.classList.remove("active");
    ladeDaten(30);
  });

  // Standardmässig: 7 Tage laden
  btn7?.classList.add("active");
  ladeDaten(7);
}

// „Alle Zonen“-Statistik laden
function ladeAlleZonenStatistik() {
  const wrapper = document.getElementById("statistik-wrapper");
  const startContent = document.getElementById("start-content");

  fetch("statistik/alleZonen.html")
    .then((res) => res.text())
    .then((html) => {
      wrapper.innerHTML = html;
      wrapper.style.display = "block";
      startContent.style.display = "none";
      window.scrollTo({ top: 0, behavior: "auto" });

      initialisiereZurueckButtons();

      const buttons = document.querySelectorAll(".zone-nav-btn");
      buttons.forEach((btn) => {
        btn.classList.remove("active-zone-btn");
        if (btn.dataset.zone === "alleZonen") {
          btn.classList.add("active-zone-btn");
        }
        btn.addEventListener("click", () => ladeStatistik(btn.dataset.zone));
      });

      // Diagrammdaten für alle drei Zonen holen
      fetch("statistik/alleZonen_data.php")
        .then((res) => res.json())
        .then((data) => {
          const erfolg = data.erfolgsquote;
          const z1 = data.zone1;
          const z2 = data.zone2;
          const z3 = data.zone3;

          document.getElementById("erfolgs-wert").textContent = erfolg + "%";

          // Balkendiagramm zeichnen
          const ctx = document.getElementById("chartAlleZonen").getContext("2d");
          new Chart(ctx, {
            type: "bar",
            data: {
              labels: ["Z1", "Z2", "Z3"],
              datasets: [
                {
                  data: [z1, z2, z3],
                  backgroundColor: "#4B4B4B",
                  borderRadius: 4,
                  barPercentage: 0.5,
                },
              ],
            },
            options: {
              responsive: true,
              plugins: {
                legend: { display: false },
                tooltip: {
                  backgroundColor: "#222",
                  titleColor: "#fff",
                  bodyColor: "#fff",
                },
              },
              scales: {
                x: {
                  grid: { display: false },
                  ticks: { color: "#000", font: { size: 14, weight: "bold" } },
                },
                y: {
                  beginAtZero: true,
                  grid: { color: "#ccc" },
                  ticks: { color: "#000", font: { size: 14 } },
                },
              },
            },
          });
        });
    });
}
