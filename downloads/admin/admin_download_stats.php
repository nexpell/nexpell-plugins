<?php

echo '<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> Download Statistik</div>
            <div>
            </div>
        </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_downloads">Downloads  verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">Download Statistik</li>
        </ol>
    </nav>  

    <div class="card-body">

        <div class="container py-5">';


$queryTop10 = "
    SELECT d.id, d.title, d.file, COUNT(l.logID) AS download_count
    FROM plugins_downloads_logs l
    JOIN plugins_downloads d ON d.id = l.fileID
    GROUP BY l.fileID
    ORDER BY download_count DESC
    LIMIT 10
";
$resultTop10 = $_database->query($queryTop10);

// wir bauen ein JSON-kompatibles Array
$top10Data = [];
while ($row = $resultTop10->fetch_assoc()) {
    $top10Data[] = [
        'title' => $row['title'],
        'file'  => $row['file'],
        'count' => (int)$row['download_count'],
    ];
}
?>

<h4>Top 10 Downloads</h4>
<div class="card">
<div id="chart-container" style="position:relative; width:100%; min-height:400px;">
    <canvas id="top10Chart"></canvas>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const top10Data = <?php echo json_encode($top10Data); ?>;
    const labels = top10Data.map(item => item.title);
    const counts = top10Data.map(item => item.count);

    const backgroundColors = [
        '#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF',
        '#FF9F40','#66BB6A','#FFA726','#AB47BC','#29B6F6'
    ];

    // dynamische Höhe
    const canvas = document.getElementById('top10Chart');
    canvas.height = top10Data.length * 40; // 40px pro Eintrag

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Downloads',
                data: counts,
                backgroundColor: backgroundColors,
                borderColor: '#333',
                borderWidth: 1,
                borderRadius: 4,
                barThickness: 15,   // schmaler
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            animation: {
                duration: 1200,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: { display: false },
                title: {
                    display: true,
                    text: 'Top 10 Downloads',
                    color: '#333',
                    font: { size: 20, weight: 'bold', family: 'Arial' }
                },
                tooltip: {
                    backgroundColor: '#222',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    callbacks: {
                        label: function(context) {
                            const file = top10Data[context.dataIndex].file;
                            return `${context.parsed.x} Downloads (Datei: ${file})`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: '#ddd' },
                    title: {
                        display: true,
                        text: 'Anzahl Downloads',
                        color: '#333',
                        font: { weight: 'bold' }
                    },
                    ticks: {
                        stepSize: 1,      // Schrittweite 1 (ganze Zahlen)
                        precision: 0      // keine Dezimalstellen
                    }
                },
                y: {
                    ticks: {
                        color: '#333',
                        font: { size: 13, weight: 'bold' }
                    },
                    grid: { display: false },
                    categoryPercentage: 0.8,
                    barPercentage: 0.8
                }
            }
        }
    });
</script>
<?php

// jetzt dein bestehendes Log mit den letzten 50 Einträgen
$query = "
    SELECT l.logID, u.username, d.title, d.file, l.downloaded_at
    FROM plugins_downloads_logs l
    JOIN users u ON u.userID = l.userID
    JOIN plugins_downloads d ON d.id = l.fileID
    ORDER BY l.downloaded_at DESC
    LIMIT 50
";

$result = $_database->query($query);


echo '<h4>Letzte 50 Downloads</h4>';
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped bg-white shadow-sm">
        <thead class="table-light">
        <tr>
    <th>#</th>
    <th>Benutzer</th>
    <th>Download-Titel</th>
    <th>Dateiname</th>
    <th>Datum</th>
</tr></thead><tbody>';

while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . (int)$row['logID'] . '</td>';
    echo '<td>' . htmlspecialchars($row['username']) . '</td>';
    echo '<td>' . htmlspecialchars($row['title']) . '</td>';
    echo '<td>' . htmlspecialchars($row['file']) . '</td>';
    echo '<td>' . $row['downloaded_at'] . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>

</div></div></div>';
?>
