<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;

global $_database, $languageService;

$get = mysqli_fetch_assoc(safe_query("SELECT * FROM settings"));
    $webkey = $get['webkey'];
    $seckey = $get['seckey'];

// Sprachmodul laden
$lang = $languageService->detectLanguage();
$languageService->readPluginModule('shoutbox');

// Headstyle holen
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title'    => $languageService->get('title'),
    'subtitle' => 'Pricing'
];

echo $tpl->loadTemplate("shoutbox", "head", $data_array, 'plugin');

// Username aus Session
$username = $_SESSION['username'] ?? '';

// Loginstatus prüfen
$loggedin = isset($_SESSION['userID']) && intval($_SESSION['userID']) > 0;

// recaptcha-Variablen vorbereiten
$run = 0;
$runregister = "false";
$fehler = [];

if ($loggedin) {
    $run = 1;
} else {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        if (!empty($recaptcha_response)) {
            include("./system/curl_recaptcha.php");
            $google_url = "https://www.google.com/recaptcha/api/siteverify";
            $secret     = $seckey;
            $ip         = $_SERVER['REMOTE_ADDR'];
            $url        = $google_url . "?secret=" . $secret . "&response=" . $recaptcha_response . "&remoteip=" . $ip;
            $res        = getCurlData($url);
            $res        = json_decode($res, true);
            if ($res['success']) {
                $runregister = "true";
                $run = 1;
            } else {
                $fehler[] = "reCAPTCHA Error";
            }
        } else {
            $fehler[] = "reCAPTCHA Error";
        }
    }
}

// Nachrichten initial laden (für NoScript oder erstes Laden)
$result = $_database->query("SELECT id, timestamp, username, message FROM plugins_shoutbox_messages ORDER BY id DESC LIMIT 100");
if (!$result) {
    die('DB-Abfrage fehlgeschlagen: ' . $_database->error);
}
?>
<style>
    #messages ul {
        list-style: none;
        padding: 0;
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ccc;
    }
    #messages li {
        padding: 5px;
        border-bottom: 1px solid #ddd;
    }
    #messages li strong {
        color: #007BFF;
    }
</style>
<div class="card">
  <div class="card-body">
    <div id="messages" class="mb-4">
      <h5 class="mb-3">Shoutbox Nachrichten:</h5>
      <ul class="list-group">
        <?php while ($row = $result->fetch_assoc()): ?>
          <li class="list-group-item">
            <strong><?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <small class="text-muted">
              [<?php echo date('H:i:s', strtotime($row['timestamp'])); ?>]
            </small>:
            <?php echo htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'); ?>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>

    <?php if ($run === 1): ?>
      <form id="shoutbox-form" class="d-flex gap-2">
        <input
          class="form-control"
          type="text"
          id="shoutbox-username"
          name="username"
          placeholder="Name"
          required
          style="flex: 0 0 150px;"
          <?php if ($username !== ''): ?>
            value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
            readonly
          <?php endif; ?>
        />
        <input
          class="form-control flex-grow-1"
          type="text"
          id="shoutbox-message"
          name="message"
          placeholder="Nachricht (max. 500 Zeichen)"
          maxlength="500"
          required
        />
        <button type="submit" class="btn btn-success">
          Senden
        </button>
      </form>
    <?php else: ?>
      <div class="alert alert-info mt-3">
        Bitte registriere dich oder löse das reCAPTCHA, um Nachrichten zu senden.
      </div>
      <?php
      // optionales CAPTCHA-Formular
      if (count($fehler) > 0) {
          foreach ($fehler as $err) {
              echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>';
          }
      }
      ?>
      <form method="post" class="mt-3">
        <div class="g-recaptcha" data-sitekey="<?php echo $webkey; ?>"></div>
        <button type="submit" class="btn btn-primary mt-2">Bestätigen</button>
      </form>
    <?php endif; ?>

  </div>
</div>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function fetchMessages() {
    try {
        const res = await fetch('/includes/plugins/shoutbox/shoutbox_ajax.php', {
            headers: { 'Accept': 'application/json' }
        });
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error('Fehler beim JSON-Parsing:', e.message);
            console.error('Server-Antwort:', text);
            return;
        }

        if (data.status !== 'success') {
            console.error('Fehler beim Laden:', data.message);
            return;
        }

        const container = document.getElementById('messages').querySelector('ul');
        container.innerHTML = '';

        data.messages.forEach(msg => {
            const li = document.createElement('li');
            const time = new Date(msg.timestamp).toLocaleTimeString();
            li.innerHTML = `<strong>${escapeHtml(msg.username)}</strong> <small>[${time}]</small>: ${escapeHtml(msg.message)}`;
            container.appendChild(li);
        });

        const messagesDiv = document.getElementById('messages');
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    } catch (err) {
        console.error('Fehler beim Laden der Nachrichten:', err);
    }
}

document.getElementById('shoutbox-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const usernameInput = document.getElementById('shoutbox-username');
    const messageInput  = document.getElementById('shoutbox-message');

    const username = usernameInput.value.trim();
    const message  = messageInput.value.trim();

    if (!username) {
        alert('Bitte gib einen Namen ein.');
        return;
    }
    if (!message) {
        alert('Bitte gib eine Nachricht ein.');
        return;
    }
    if (message.length > 500) {
        alert('Die Nachricht darf maximal 500 Zeichen lang sein.');
        return;
    }

    try {
        const res = await fetch('/includes/plugins/shoutbox/shoutbox_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: new URLSearchParams({ username, message }).toString()
        });

        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch(e) {
            alert('Fehler beim JSON-Parsing: ' + e.message + '\nServer-Antwort:\n' + text);
            return;
        }

        if (result.status === 'success') {
            messageInput.value = '';
            fetchMessages();
        } else {
            alert('Fehler: ' + (result.message || 'Unbekannter Fehler'));
        }
    } catch (err) {
        alert('Netzwerkfehler: ' + err.message);
    }
});

fetchMessages();
setInterval(fetchMessages, 10000);
</script>
