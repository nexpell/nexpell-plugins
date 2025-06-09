<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use webspell\LanguageService;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readPluginModule('pricing');

$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

    // Header-Daten
    $data_array = [
        'class'    => $class,
        'title' => $languageService->get('title'),
        'subtitle' => 'Pricing'
    ];
    
    echo $tpl->loadTemplate("pricing", "head", $data_array, 'plugin');

// Alle Pläne laden
$plans = [];
$sql = "SELECT * FROM plugins_pricing_plans ORDER BY sort_order ASC";
$res = $_database->query($sql);
while ($plan = $res->fetch_assoc()) {
    $plans[$plan['id']] = $plan;
    $plans[$plan['id']]['features'] = [];
}

// Features laden
$sql2 = "SELECT * FROM plugins_pricing_features ORDER BY plan_id, id";
$res2 = $_database->query($sql2);
while ($feat = $res2->fetch_assoc()) {
    $plans[$feat['plan_id']]['features'][] = $feat;
}
?>
<div class="card">
              <div class="card-body">
<!-- ======= Pricing Section ======= -->
<section id="pricing" class="pricing">
  

    <div class="mt-3 row">
      <?php
      $delay = 0;
      foreach ($plans as $plan):
        $featuredClass = $plan['is_featured'] ? ' featured' : '';
        $advancedLabel = $plan['is_advanced'] ? '<span class="advanced">Advanced</span>' : '';
        ?>
        <div class="col-lg-3 col-md-6 mt-4 mt-md-0" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
          <div class="box<?= $featuredClass ?>">
            <?= $advancedLabel ?>
            <h3><?= htmlspecialchars($plan['title']) ?></h3>
            <h4><sup>€</sup><?= htmlspecialchars($plan['price']) ?><span> <?= htmlspecialchars($plan['price_unit']) ?></span></h4>
            <ul>
              <?php foreach ($plan['features'] as $feature): ?>
                <?php $class = $feature['available'] ? '' : ' class="na"'; ?>
                <li<?= $class ?>><?= htmlspecialchars($feature['feature_text']) ?></li>
              <?php endforeach; ?>
            </ul>
            <div class="btn-wrap">
              <a href="#" class="btn-buy">Buy Now</a>
            </div>
          </div>
        </div>
        <?php $delay += 100; ?>
      <?php endforeach; ?>
    </div>
  
</section>
<!-- End Pricing Section -->
</div></div>
