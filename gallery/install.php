<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_gallery_categories (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_gallery (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  filename varchar(255) NOT NULL,
  class enum('wide','tall','big','') DEFAULT '',
  upload_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  position int(10) UNSIGNED NOT NULL DEFAULT 0,
  category_id int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");



safe_query("INSERT IGNORE INTO plugins_gallery (id, filename, class, upload_date, position, category_id) VALUES
(1, 'img_68839134a01b4.jpg', '', CURRENT_TIMESTAMP, 1, 1),
(2, 'img_688391421701b.jpg', 'wide', CURRENT_TIMESTAMP, 2, 1),
(3, 'img_6883914ca02e4.jpg', 'tall', CURRENT_TIMESTAMP, 3, 1),
(4, 'img_688391578247d.jpg', 'wide', CURRENT_TIMESTAMP, 4, 1),
(5, 'img_68839167eadb7.jpg', '', CURRENT_TIMESTAMP, 5, 1),
(6, 'img_688391793db05.jpg', 'tall', CURRENT_TIMESTAMP, 6, 1),
(7, 'img_6883918321c6a.jpg', '', CURRENT_TIMESTAMP, 7, 1),
(8, 'img_6883918f85626.jpg', 'big', CURRENT_TIMESTAMP, 8, 1),
(9, 'img_6883919ad6c13.jpg', '', CURRENT_TIMESTAMP, 9, 1),
(10, 'img_688391a5ecfa1.jpg', '', CURRENT_TIMESTAMP, 10, 1),
(11, 'img_688391b3c986c.jpg', 'wide', CURRENT_TIMESTAMP, 11, 1),
(12, 'img_688391be98734.jpg', '', CURRENT_TIMESTAMP, 12, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Gallery', 'gallery', '[[lang:de]]Mit diesem Plugin könnt ihr ein Gallery auf die Webseite anzeigen lassen.[[lang:en]]With this plugin you can display a gallery on the website.[[lang:it]]Con questo plugin puoi visualizzare una galleria sul sito web.', 'admin_gallery', 1, 'T-Seven', 'https://webspell-rm.de', 'gallery', '', '0.1', 'includes/plugins/gallery/', 1, 1, 0, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 9, '[[lang:de]]Gallery[[lang:en]]Gallery[[lang:it]]Gallery', 'gallery', 'admincenter.php?site=admin_gallery', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 4, '[[lang:de]]Gallery[[lang:en]]Gallery[[lang:it]]Gallery', 'gallery', 'index.php?site=gallery', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'gallery')
");
 ?>

