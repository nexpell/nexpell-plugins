<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('pricing');

// Plan löschen
if (isset($_GET['delete_plan'])) {
    $id = (int)$_GET['delete_plan'];
    $_database->query("DELETE FROM plugins_pricing_features WHERE plan_id = $id");
    $_database->query("DELETE FROM plugins_pricing_plans WHERE id = $id");
    header("Location: admincenter.php?site=admin_pricing");
    exit;
}

// Feature löschen
if (isset($_GET['delete_feature'])) {
    $id = (int)$_GET['delete_feature'];
    $_database->query("DELETE FROM plugins_pricing_features WHERE id = $id");
    header("Location: admincenter.php?site=admin_pricing");
    exit;
}

// Plan speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $id = (int)$_POST['id'];
    $title = $_database->real_escape_string($_POST['title']);
    $price = (float)$_POST['price'];
    $price_unit = $_database->real_escape_string($_POST['price_unit']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_advanced = isset($_POST['is_advanced']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];

    if ($id === 0) {
        $_database->query("INSERT INTO plugins_pricing_plans (title, price, price_unit, is_featured, is_advanced, sort_order)
                           VALUES ('$title', $price, '$price_unit', $is_featured, $is_advanced, $sort_order)");
    } else {
        $_database->query("UPDATE plugins_pricing_plans
                           SET title='$title', price=$price, price_unit='$price_unit',
                               is_featured=$is_featured, is_advanced=$is_advanced, sort_order=$sort_order
                           WHERE id=$id");
    }

    header("Location: admincenter.php?site=admin_pricing");
    exit;
}

// Feature speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feature'])) {
    $id = (int)$_POST['id'];
    $plan_id = (int)$_POST['plan_id'];
    $feature_text = $_database->real_escape_string($_POST['feature_text']);
    $available = isset($_POST['available']) ? 1 : 0;

    if ($id === 0) {
        $_database->query("INSERT INTO plugins_pricing_features (plan_id, feature_text, available)
                           VALUES ($plan_id, '$feature_text', $available)");
    } else {
        $_database->query("UPDATE plugins_pricing_features
                           SET feature_text='$feature_text', available=$available
                           WHERE id=$id");
    }

    header("Location: admincenter.php?site=admin_pricing");
    exit;
}

// Pläne & Features laden
$plans = [];
$res = $_database->query("SELECT * FROM plugins_pricing_plans ORDER BY sort_order");
while ($row = $res->fetch_assoc()) {
    $plans[$row['id']] = $row;
    $plans[$row['id']]['features'] = [];
}

$res2 = $_database->query("SELECT * FROM plugins_pricing_features ORDER BY plan_id, id");
while ($feat = $res2->fetch_assoc()) {
    $plans[$feat['plan_id']]['features'][] = $feat;
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-journal-text"></i> Pricing verwalten</div>
            <div>
            </div>
        </div>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb t-5 p-2 bg-light">
            <li class="breadcrumb-item"><a href="admincenter.php?site=admin_pricing">Pricing  verwalten</a></li>
            <li class="breadcrumb-item active" aria-current="page">New / Edit</li>
        </ol>
    </nav>  

    <div class="card-body">

        <div class="container py-5">

  <h4>Neuen Plan hinzufügen</h4>
  <form method="post" class="row g-2">
    <input type="hidden" name="id" value="0">
    <input type="hidden" name="save_plan" value="1">
    <div class="col-md-3"><input class="form-control" name="title" placeholder="Titel" required></div>
    <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="price" placeholder="Preis" required></div>
    <div class="col-md-2"><input class="form-control" name="price_unit" value="/ month"></div>
    <div class="col-md-1"><input class="form-control" type="number" name="sort_order" placeholder="Sort" value="0"></div>
    <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured"> <label class="form-check-label">Featured</label></div></div>
    <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_advanced"> <label class="form-check-label">Advanced</label></div></div>
    <div class="col-md-2"><button class="btn btn-success w-100">Hinzufügen</button></div>
  </form>

  <hr>

  <?php foreach ($plans as $plan): ?>
    <div class="card mt-4">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong><?= htmlspecialchars($plan['title']) ?></strong>
        <a href="admincenter.php?site=admin_pricing&delete_plan=<?= $plan['id'] ?>" onclick="return confirm('Plan löschen?')" class="btn btn-sm btn-danger">Löschen</a>
      </div>
      <div class="card-body">
        <p><strong>Preis:</strong> <?= $plan['price'] ?><?= $plan['price_unit'] ?> | <strong>Sortierung:</strong> <?= $plan['sort_order'] ?></p>
        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="save_feature" value="1">
          <input type="hidden" name="id" value="0">
          <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
          <div class="col-md-8"><input class="form-control" name="feature_text" placeholder="Feature Text" required></div>
          <div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="available"> <label class="form-check-label">Verfügbar</label></div></div>
          <div class="col-md-2"><button class="btn btn-success w-100">Feature hinzufügen</button></div>
        </form>

        <?php if (count($plan['features'])): ?>
          <ul class="list-group">
            <?php foreach ($plan['features'] as $f): ?>
              <li class="list-group-item d-flex justify-content-between">
                <span><?= $f['available'] ? '✅' : '❌' ?> <?= htmlspecialchars($f['feature_text']) ?></span>
                <a href="admincenter.php?site=admin_pricing&delete_feature=<?= $f['id'] ?>" onclick="return confirm('Feature löschen?')" class="bbtn btn-sm btn-danger">Löschen</a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</div>
</div>
