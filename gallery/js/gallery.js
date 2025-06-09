const saveBtn = document.getElementById('save-order');
let isChanged = false;

// Alle Grids einzeln sortierbar machen
document.querySelectorAll('.grid-wrapper').forEach(wrapper => {
    Sortable.create(wrapper, {
        group: 'gallery',
        animation: 150,
        onEnd: () => {
            isChanged = true;
            saveBtn.disabled = false;
        }
    });
});

saveBtn.addEventListener('click', () => {
    if (!isChanged) return;

    const order = [];
    document.querySelectorAll('.sortable-item').forEach((item, index) => {
        order.push({ id: item.dataset.id, position: index + 1 });
    });

    fetch('/includes/plugins/gallery/admin/save_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(order)
    })
    .then(res => res.ok ? res.text() : Promise.reject())
    .then(() => {
        alert('Reihenfolge gespeichert!');
        isChanged = false;
        saveBtn.disabled = true;
    })
    .catch(() => {
        alert('Fehler beim Speichern!');
        saveBtn.disabled = false;
    });
});

function confirmDelete(id, filename) {
    if (confirm('Bild "' + filename + '" wirklich löschen?')) {
        fetch('admincenter.php?site=admin_gallery&action=delete&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bild gelöscht');
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.error || 'Löschung fehlgeschlagen'));
                }
            }).catch(() => alert('Fehler bei der Anfrage'));
    }
}