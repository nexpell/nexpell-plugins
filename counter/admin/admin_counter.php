<?php
use webspell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprache setzen, falls nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// LanguageService initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul-Sprache laden
$languageService->readPluginModule('counter');

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('counter');

$range = $_GET['range'] ?? 'week';
$days = ($range === 'month') ? 30 : 7;
$since_date = date('Y-m-d', strtotime("-$days days"));

// Besucher heute
$today = date('Y-m-d');
$res_today = mysqli_fetch_assoc(safe_query(
    "SELECT COUNT(*) AS total FROM plugins_counter WHERE DATE(timestamp) = '$today'"
));

// Gesamtbesuche
$res_total = mysqli_fetch_assoc(safe_query(
    "SELECT COUNT(*) AS total FROM plugins_counter"
));

// Seiten-Klicks insgesamt
$res_clicks = safe_query("SELECT page, COUNT(*) AS total FROM plugins_counter GROUP BY page ORDER BY total DESC LIMIT 10");

// Daten für Chart
$chart_data = [];
$labels = [];
$visits = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $res = mysqli_fetch_assoc(safe_query(
        "SELECT COUNT(*) AS cnt FROM plugins_counter WHERE DATE(timestamp) = '$date'"
    ));
    $labels[] = date('d.m.', strtotime($date));
    $visits[] = $res['cnt'] ?? 0;
}

// Top-Seiten (Zeitraum)
$top_pages = safe_query(
    "SELECT page, COUNT(*) AS hits FROM plugins_counter WHERE timestamp >= '$since_date' GROUP BY page ORDER BY hits DESC LIMIT 5"
);

// Device-Auswertung
$res_devices = safe_query(
    "SELECT device_type, COUNT(*) AS total FROM plugins_counter WHERE timestamp >= '$since_date' GROUP BY device_type"
);
$device_data = [];
while ($row = mysqli_fetch_assoc($res_devices)) {
    $device_data[$row['device_type']] = $row['total'];
}

// Referer-Auswertung
$res_referer = safe_query(
    "SELECT referer, COUNT(*) AS hits FROM plugins_counter WHERE timestamp >= '$since_date' GROUP BY referer ORDER BY hits DESC LIMIT 5"
);

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="visits_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Datum', 'IP (hash)', 'Seite', 'Gerät', 'Referer']);
    $res = safe_query("SELECT * FROM plugins_counter WHERE timestamp >= '$since_date'");
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($output, [$row['timestamp'], $row['ip'], $row['page'], $row['device_type'], $row['referer']]);
    }
    fclose($output);
    exit;
}
?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <div class="card">
    <div class="card-header">
        <i class="bi bi-paragraph"></i> Besucherstatistik
    </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item active" aria-current="page">Besucherstatistik</li>
        </ol>
    </nav>  

    <div class="card-body">

<div class="container py-5">
    <h2 class="mb-4">📊 Besucherstatistik</h2>

    <form method="get" class="mb-4">
      <input type="hidden" name="site" value="admin_counter">
      <div class="d-flex justify-content-between">
        <div>
          <select name="range" onchange="this.form.submit()" class="form-select">
            <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>Letzte 7 Tage</option>
            <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Letzte 30 Tage</option>
          </select>
        </div>
        <div>
          <a href="?site=admin_counter&range=<?= $range ?>&export=csv" class="btn btn-outline-secondary">CSV-Export</a>
        </div>
      </div>
    </form>

    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <h5 class="card-title">Übersicht</h5>
        <p>👁️‍🗨️ Besuche heute: <strong><?= (int)$res_today['total'] ?></strong></p>
        <p>📈 Gesamtbesuche: <strong><?= (int)$res_total['total'] ?></strong></p>
      </div>
    </div>

    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <h5 class="card-title">Besuche pro Tag</h5>
        <canvas id="visitChart" height="100"></canvas>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="card mb-4 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Gerätetypen</h5>
            <canvas id="deviceChart" height="150"></canvas>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card mb-4 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Top-Referer</h5>
            <table class="table table-sm">
              <thead><tr><th>Referer</th><th>Hits</th></tr></thead>
              <tbody>
              <?php while ($row = mysqli_fetch_assoc($res_referer)) : ?>
                <tr>
                  <td><?= htmlspecialchars($row['referer']) ?></td>
                  <td><?= (int)$row['hits'] ?></td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <h5 class="card-title">Top-Seiten (<?= $days ?> Tage)</h5>
        <table class="table table-sm">
          <thead><tr><th>Seite</th><th>Hits</th></tr></thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($top_pages)) : ?>
              <tr>
                <td><?= htmlspecialchars($row['page']) ?></td>
                <td><?= (int)$row['hits'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title">Meistaufgerufene Seiten (gesamt)</h5>
        <table class="table table-sm">
          <thead><tr><th>Seite</th><th>Klicks</th></tr></thead>
          <tbody>
            <?php while ($row = mysqli_fetch_assoc($res_clicks)) : ?>
              <tr>
                <td><?= htmlspecialchars($row['page']) ?></td>
                <td><?= (int)$row['total'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div></div>
  <script>
    new Chart(document.getElementById('visitChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
          label: 'Besuche',
          data: <?= json_encode($visits) ?>,
          fill: true,
          borderColor: 'rgb(75, 192, 192)',
          backgroundColor: 'rgba(75, 192, 192, 0.2)',
          tension: 0.3
        }]
      },
      options: {scales: {y: {beginAtZero: true, precision: 0}}}
    });

    new Chart(document.getElementById('deviceChart'), {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_keys($device_data)) ?>,
        datasets: [{
          label: 'Geräte',
          data: <?= json_encode(array_values($device_data)) ?>,
          backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56']
        }]
      }
    });
  </script>
