<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_pricing_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100),
  price DECIMAL(10,2),
  price_unit VARCHAR(50) DEFAULT '/ month',
  is_featured TINYINT(1) DEFAULT 0,
  is_advanced TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0
);");

safe_query("CREATE TABLE IF NOT EXISTS plugins_pricing_features (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  feature_text VARCHAR(255) NOT NULL,
  available TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (plan_id) REFERENCES plugins_pricing_plans(id) ON DELETE CASCADE
);");

safe_query("INSERT IGNORE INTO plugins_pricing_plans (title, price, price_unit, is_featured, is_advanced, sort_order)
VALUES
('Free', 0.00, '/ month', 0, 0, 1),
('Business', 19.00, '/ month', 1, 0, 2),
('Developer', 29.00, '/ month', 0, 0, 3),
('Ultimate', 49.00, '/ month', 0, 1, 4)");

safe_query("INSERT IGNORE INTO plugins_pricing_features (plan_id, feature_text, available) VALUES
(1, 'Aida dere', 1),
(1, 'Nec feugiat nisl', 1),
(1, 'Nulla at volutpat dola', 1),
(1, 'Pharetra massa', 0),
(1, 'Massa ultricies mi', 0)");

safe_query("INSERT IGNORE INTO plugins_pricing_features (plan_id, feature_text, available) VALUES
(2, 'Aida dere', 1),
(2, 'Nec feugiat nisl', 1),
(2, 'Nulla at volutpat dola', 1),
(2, 'Pharetra massa', 1),
(2, 'Massa ultricies mi', 0)");

safe_query("INSERT IGNORE INTO plugins_pricing_features (plan_id, feature_text, available) VALUES
(3, 'Aida dere', 1),
(3, 'Nec feugiat nisl', 1),
(3, 'Nulla at volutpat dola', 1),
(3, 'Pharetra massa', 1),
(3, 'Massa ultricies mi', 1)");

safe_query("INSERT IGNORE INTO plugins_pricing_features (plan_id, feature_text, available) VALUES
(4, 'Aida dere', 1),
(4, 'Nec feugiat nisl', 1),
(4, 'Nulla at volutpat dola', 1),
(4, 'Pharetra massa', 1),
(4, 'Massa ultricies mi', 1)");


safe_query("CREATE TABLE IF NOT EXISTS plugins_pricing_settings_widgets (
  id int(11) NOT NULL AUTO_INCREMENT,
  position varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  modulname varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  themes_modulname varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  widgetname varchar(255) NOT NULL DEFAULT '',
  widgetdatei varchar(255) NOT NULL DEFAULT '',
  activated int(1) DEFAULT 1,
  sort int(11) DEFAULT 1,
PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_pricing_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");
## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Pricing', 'pricing', '[[lang:de]]Mit diesem Plugin könnt ihr ein Pricing auf die Webseite anzeigen lassen.[[lang:en]]With this plugin you can display a Pricing on the website.[[lang:it]]Con questo plugin puoi visualizzare una galleria sul sito web.', 'admin_pricing', 1, 'T-Seven', 'https://webspell-rm.de', 'pricing', '', '0.1', 'includes/plugins/pricing/', 1, 1, 0, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]Pricing[[lang:en]]Pricing[[lang:it]]Pricing', 'pricing', 'admincenter.php?site=admin_pricing', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 1, '[[lang:de]]Pricing[[lang:en]]Pricing[[lang:it]]Pricing', 'pricing', 'index.php?site=pricing', 1, 1, 'default')");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'pricing', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'pricing' LIMIT 1
  ))
");
  
 ?>

