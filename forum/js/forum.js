document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const postID = btn.dataset.postid;
        const liked = btn.dataset.liked === '1';
        const action = liked ? 'unlike' : 'like';

        fetch('/includes/plugins/forum/like_post_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `postID=${postID}&action=${action}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                btn.dataset.liked = liked ? '0' : '1';
                btn.textContent = liked ? 'Like' : 'Unlike';
                btn.nextElementSibling.textContent = data.likes;
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(() => alert('Netzwerkfehler'));
    });
});

document.addEventListener('DOMContentLoaded', function () {
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('uploadImage');
  let editor = null;

  // Wenn keine DropArea im DOM -> Script sofort beenden
  if (!dropArea || !fileInput) {
    return;
  }

  // CKEditor-Instanz beobachten
  CKEDITOR.on('instanceReady', function(evt) {
    if (evt.editor.name === 'ckeditor') {
      editor = evt.editor;
    }
  });

  dropArea.addEventListener('click', () => fileInput.click());

  dropArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropArea.classList.add('bg-warning');
  });

  dropArea.addEventListener('dragleave', () => {
    dropArea.classList.remove('bg-warning');
  });

  dropArea.addEventListener('drop', (e) => {
    e.preventDefault();
    dropArea.classList.remove('bg-warning');
    if (e.dataTransfer.files.length > 0) {
      uploadImage(e.dataTransfer.files[0]);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
      uploadImage(fileInput.files[0]);
    }
  });

  function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);

    fetch('/includes/plugins/forum/upload_image.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.url) {
        if (!editor) {
          console.error('CKEditor ist noch nicht bereit.');
          return;
        }
        editor.focus();
        // Bild als Link einfügen, 75% Breite, klickbar für Original
        const html = `<a href="${data.url}" target="_blank"><img src="${data.url}" style="width:75%; height:auto;" alt=""></a>`;
        editor.insertHtml(html);
      } else {
        alert(data.message || 'Upload fehlgeschlagen');
      }
    })
    .catch(err => {
      alert('Fehler beim Upload: ' + err.message);
    });
  }
});

document.addEventListener("DOMContentLoaded", function () {
    const hash = window.location.hash;
    if (hash.startsWith("#post")) {
        const target = document.querySelector(hash);
        if (target) {
            target.classList.add("highlight-post");

            // optional wieder entfernen nach 4 Sekunden
            setTimeout(() => {
                target.classList.remove("highlight-post");
            }, 4000);
        }
    }
});