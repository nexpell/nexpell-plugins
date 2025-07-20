<?php

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zeitraum festlegen (week oder month)
$range = ($_GET['range'] ?? 'week') === 'month' ? 'month' : 'week';
$days = ($range === 'month') ? 30 : 7;
$since_date = date('Y-m-d', strtotime("-{$days} days"));

// Besucher heute
$today = date('Y-m-d');
$res_today = mysqli_fetch_assoc(safe_query(
    "SELECT COUNT(*) AS total FROM plugins_counter_visitors WHERE DATE(timestamp) = '$today'"
));
$visitors_today = (int)$res_today['total'];

// Gesamtbesuche
$res_total = mysqli_fetch_assoc(safe_query(
    "SELECT COUNT(*) AS total FROM plugins_counter_visitors"
));
$visitors_total = (int)$res_total['total'];

// Top 10 Seiten nach Klicks
$res_clicks = safe_query(
    "SELECT page, COUNT(*) AS total FROM plugins_counter_clicks GROUP BY page ORDER BY total DESC LIMIT 10"
);
$top_pages = [];
while ($row = mysqli_fetch_assoc($res_clicks)) {
    $top_pages[] = $row;
}

// Besucher pro Tag für Diagramm
$labels = [];
$visits = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $row = mysqli_fetch_assoc(safe_query(
        "SELECT COUNT(*) AS cnt FROM plugins_counter_visitors WHERE DATE(timestamp) = '$date'"
    ));
    $labels[] = date('d.m.', strtotime($date));
    $visits[] = (int)($row['cnt'] ?? 0);
}

// Geräte-Auswertung
$res_devices = safe_query(
    "SELECT device_type, COUNT(*) AS total FROM plugins_counter_visitors WHERE timestamp >= '$since_date' GROUP BY device_type"
);
$device_data = [];
while ($row = mysqli_fetch_assoc($res_devices)) {
    $device_data[$row['device_type']] = (int)$row['total'];
}

// OS-Auswertung
$res_os = safe_query(
    "SELECT os, COUNT(*) AS total FROM plugins_counter_visitors WHERE timestamp >= '$since_date' GROUP BY os"
);
$os_data = [];
while ($row = mysqli_fetch_assoc($res_os)) {
    $os_data[$row['os']] = (int)$row['total'];
}

// Browser-Auswertung
$res_browser = safe_query(
    "SELECT browser, COUNT(*) AS total FROM plugins_counter_visitors WHERE timestamp >= '$since_date' GROUP BY browser"
);
$browser_data = [];
while ($row = mysqli_fetch_assoc($res_browser)) {
    $browser_data[$row['browser']] = (int)$row['total'];
}

// Top-Referer
$res_referer = safe_query(
    "SELECT referer, COUNT(*) AS hits FROM plugins_counter_visitors WHERE timestamp >= '$since_date' GROUP BY referer ORDER BY hits DESC LIMIT 5"
);
$top_referers = [];
while ($row = mysqli_fetch_assoc($res_referer)) {
    $top_referers[] = $row;
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visits_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Datum', 'IP (hash)', 'Gerät', 'OS', 'Browser', 'Referer']);

    $res_export = safe_query("SELECT * FROM plugins_counter_visitors WHERE timestamp >= '$since_date' ORDER BY timestamp ASC");
    while ($row = mysqli_fetch_assoc($res_export)) {
        fputcsv($output, [
            $row['timestamp'],
            $row['ip_hash'],
            $row['device_type'],
            $row['os'],
            $row['browser'],
            $row['referer']
        ]);
    }
    fclose($output);
    exit;
}



$res_unique = mysqli_fetch_assoc(safe_query(
  "SELECT COUNT(DISTINCT ip_hash) AS unique_visitors FROM plugins_counter_visitors WHERE timestamp >= '$since_date'"
));
$unique_visitors = (int)$res_unique['unique_visitors'];

$avg_per_day = round($visitors_total / max($days, 1), 1);

$active_since = date('Y-m-d H:i:s', strtotime('-10 minutes'));
$res_online = mysqli_fetch_assoc(safe_query(
  "SELECT COUNT(DISTINCT ip_hash) AS online_visitors FROM plugins_counter_visitors WHERE timestamp >= '$active_since'"
));
$online_visitors = (int)$res_online['online_visitors'];



?>



<div class="container">
    <h1>Besucherstatistik</h1>

    <form method="get" action="admincenter.php" class="mb-4 d-flex justify-content-between align-items-center" style="max-width: 400px;">
        <input type="hidden" name="site" value="admin_counter">
        <select name="range" class="form-select" onchange="this.form.submit()">
            <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>Letzte 7 Tage</option>
            <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Letzte 30 Tage</option>
        </select>
        <a href="?site=admin_counter&range=<?= htmlspecialchars($range) ?>&export=csv"
   class="btn btn-outline-secondary ms-3"
   title="CSV Export"
   style="min-width: 180px;">
   <i class="bi bi-file-earmark-arrow-down"></i> CSV-Export
</a>
    </form>














<div class="row g-3">

  <div class="col-md-6 col-xl-3">
    <div class="card bg-primary text-white">
      <div class="card-body">
        <h6>Besucher heute</h6>
        <h4 class="text-right">
          <i class="bi bi-calendar float-start"></i>
          <span class="ms-3"><?= $visitors_today ?></span>
        </h4>
        <p class="mb-0">Neu heute<span class="float-end"><?= $new_visitors_today ?? '–' ?></span></p>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-3">
    <div class="card bg-success text-white">
      <div class="card-body">
        <h6>Gesamtbesuche</h6>
        <h4 class="text-right">
          <i class="bi bi-people float-start"></i>
          <span class="ms-3"><?= $visitors_total ?></span>
        </h4>
        <p class="mb-0">Eindeutige Besucher<span class="float-end"><?= $unique_visitors ?></span></p>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-3">
    <div class="card bg-warning text-white">
      <div class="card-body">
        <h6>Ø Besuche pro Tag</h6>
        <h4 class="text-right">
          <i class="bi bi-bar-chart-line float-start"></i>
          <span class="ms-3"><?= $avg_per_day ?></span>
        </h4>
        <p class="mb-0">Im Durchschnitt<span class="float-end"><?= round($avg_per_day, 2) ?></span></p>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-3">
    <div class="card bg-danger text-white">
      <div class="card-body">
        <h6>Besucher online</h6>
        <h4 class="text-right">
          <i class="bi bi-clock float-start"></i>
          <span class="ms-3"><?= $online_visitors ?></span>
        </h4>
        <p class="mb-0">Letzte 10 Min.<span class="float-end"><?= $online_visitors ?></span></p>
      </div>
    </div>
  </div>

</div>























    <h3>Besucher pro Tag</h3>
    <canvas id="visitorsChart" height="100"></canvas>



    <div class="row">

                <div class="col-md-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Gerätetypen</h5>
                            <canvas id="deviceChart" height="150"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Betriebssysteme</h5>
                            <canvas id="osChart" height="150"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Browser</h5>
                            <canvas id="browserChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
    </div>            

    <div class="row mt-4">
        <div class="col-md-4">
            <h4>Geräte</h4>
            <ul class="list-group">
                <?php foreach ($device_data as $device => $count): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($device) ?>
                        <span class="badge bg-info rounded-pill"><?= $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="col-md-4">
            <h4>Betriebssysteme</h4>
            <ul class="list-group">
                <?php foreach ($os_data as $os => $count): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($os) ?>
                        <span class="badge bg-info rounded-pill"><?= $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="col-md-4">
            <h4>Browser</h4>
            <ul class="list-group">
                <?php foreach ($browser_data as $browser => $count): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($browser) ?>
                        <span class="badge bg-info rounded-pill"><?= $count ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <h3 class="mt-4">Top 10 Seiten nach Klicks</h3>
    <ul class="list-group mb-4">
        <?php foreach ($top_pages as $page): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($page['page']) ?>
                <span class="badge bg-secondary rounded-pill"><?= $page['total'] ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <h4 class="mt-4">Top 5 Referer</h4>
    <ul class="list-group mb-4">
        <?php foreach ($top_referers as $referer): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($referer['referer']) ?>
                <span class="badge bg-warning rounded-pill"><?= $referer['hits'] ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const visitorsCtx = document.getElementById('visitorsChart').getContext('2d');
const visitorsChart = new Chart(visitorsCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Besuche',
            data: <?= json_encode($visits) ?>,
            borderColor: 'rgba(54, 162, 235, 1)',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            tension: 0.3,
            fill: true,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// Gerätetypen Diagramm
const deviceChart = new Chart(document.getElementById('deviceChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($device_data)) ?>,
        datasets: [{
            label: 'Gerätetypen',
            data: <?= json_encode(array_values($device_data)) ?>,
            backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0']
        }]
    }
});

// Betriebssysteme Diagramm
const osChart = new Chart(document.getElementById('osChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($os_data)) ?>,
        datasets: [{
            label: 'Betriebssysteme',
            data: <?= json_encode(array_values($os_data)) ?>,
            backgroundColor: ['#FF6384', '#36A2EB', '#9966FF', '#FFCE56']
        }]
    }
});

// Browser Diagramm
const browserChart = new Chart(document.getElementById('browserChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($browser_data)) ?>,
        datasets: [{
            label: 'Browser',
            data: <?= json_encode(array_values($browser_data)) ?>,
            backgroundColor: ['#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56']
        }]
    }
});
</script>