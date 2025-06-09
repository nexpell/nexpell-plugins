const saveBtn = document.getElementById('save-order');
let isChanged = false;

// Drag-&-Drop aktivieren
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

// Reihenfolge speichern
saveBtn.addEventListener('click', () => {
    const order = [];
    document.querySelectorAll('.grid-wrapper').forEach(wrapper => {
        wrapper.querySelectorAll('.sortable-item').forEach(item => {
            order.push({ id: item.dataset.id, position: order.length + 1 });
        });
    });

    fetch('/includes/plugins/gallery/admin/save_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(order)
    })
    .then(res => res.ok ? res.text() : Promise.reject(res))
    .then(() => {
        alert('Reihenfolge gespeichert!');
        reloadGalleryGrid();  // ⬅️ danach Grid aktualisieren
    })
    .catch((err) => {
        console.error('Fehler beim Speichern:', err); // <--- wichtig!
        alert('Fehler beim Speichern!');
    });
});

// Soft-Reload der Galerie
function reloadGalleryGrid() {
    fetch('admincenter.php?site=admin_gallery&action=sort&partial=1')
        .then(res => res.text())
        .then(html => {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newGrid = temp.querySelector('#sortable-gallery');
            document.getElementById('sortable-gallery').replaceWith(newGrid);

            // Sortable nachladen
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

            saveBtn.disabled = true;
            isChanged = false;
        });
}
