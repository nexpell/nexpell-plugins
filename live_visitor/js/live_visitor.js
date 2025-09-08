function refreshVisitors() {
    fetch('live_visitors.php')
        .then(res => res.json())
        .then(data => {
            const liveContainer = document.getElementById('liveVisitors');
            liveContainer.innerHTML = '';
            data.live.forEach(visitor => {
                const div = document.createElement('div');
                div.className = 'col-md-4 col-lg-3';
                div.innerHTML = `
                    <div class="card visitor-card shadow-sm p-2">
                        <div class="d-flex align-items-center mb-2">
                            ${visitor.avatar ? `<img src="${visitor.avatar}" class="avatar" alt="Avatar">` : ''}
                            <div class="visitor-name">${visitor.username}</div>
                        </div>
                        <div class="visitor-page">${visitor.page}</div>
                        <div class="visitor-country">Land: ${visitor.country_code}</div>
                        <div class="visitor-time">Letzte Aktivität: ${visitor.created_at}</div>
                    </div>`;
                liveContainer.appendChild(div);
            });
        });
}

// Alle 10 Sekunden aktualisieren
setInterval(refreshVisitors, 30000); // 30 Sekunden