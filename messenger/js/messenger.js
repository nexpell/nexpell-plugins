async function updateMailBadge() {
    try {
        const res = await fetch('includes/plugins/messenger/get_total_unread_count.php');
        const data = await res.json();

        const badge = document.getElementById('total-unread-badge');
        const icon = document.getElementById('mail-icon');

        const unread = data.total_unread;
        badge.textContent = unread > 99 ? '99+' : unread;

        // Icon wechseln
        if (unread > 0) {
            icon.classList.remove('bi-envelope-dash');
            icon.classList.add('bi-envelope-check');
        } else {
            icon.classList.remove('bi-envelope-check');
            icon.classList.add('bi-envelope-dash');
        }

        // Badge immer sichtbar, auch bei 0
        badge.style.display = 'inline-block';

    } catch (err) {
        console.error("Fehler beim Laden der Mail-Badge:", err);
    }
}

// Direkt beim Laden und alle 30 Sekunden
document.addEventListener('DOMContentLoaded', () => {
    updateMailBadge();
    setInterval(updateMailBadge, 30000);
});