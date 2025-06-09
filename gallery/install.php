<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(255) NOT NULL,
  class ENUM('wide', 'tall', 'big', '') DEFAULT '',
  upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

safe_query("INSERT IGNORE INTO plugins_gallery (id, filename, class, upload_date, position) VALUES
(1, '1.jpg', '', CURRENT_TIMESTAMP, 1),
(2, '2.jpg', 'wide', CURRENT_TIMESTAMP, 2),
(3, '3.jpg', 'tall', CURRENT_TIMESTAMP, 3),
(4, '4.jpg', 'wide', CURRENT_TIMESTAMP, 4),
(5, '5.jpg', '', CURRENT_TIMESTAMP, 5),
(6, '6.jpg', 'tall', CURRENT_TIMESTAMP, 6),
(7, '7.jpg', '', CURRENT_TIMESTAMP, 7),
(8, '8.jpg', 'big', CURRENT_TIMESTAMP, 8),
(9, '9.jpg', '', CURRENT_TIMESTAMP, 9),
(10, '10.jpg', '', CURRENT_TIMESTAMP, 10),
(11, '11.jpg', 'wide', CURRENT_TIMESTAMP, 11),
(12, '12.jpg', '', CURRENT_TIMESTAMP, 12)");



safe_query("CREATE TABLE IF NOT EXISTS plugins_gallery_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_gallery_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");
## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Gallery', 'gallery', '[[lang:de]]Mit diesem Plugin könnt ihr ein Gallery auf die Webseite anzeigen lassen.[[lang:en]]With this plugin you can display a gallery on the website.[[lang:it]]Con questo plugin puoi visualizzare una galleria sul sito web.', 'admin_gallery', 1, 'T-Seven', 'https://webspell-rm.de', 'gallery', '', '0.1', 'includes/plugins/gallery/', 1, 1, 0, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 9, '[[lang:de]]Gallery[[lang:en]]Gallery[[lang:it]]Gallery', 'gallery', 'admincenter.php?site=admin_gallery', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 4, '[[lang:de]]Gallery[[lang:en]]Gallery[[lang:it]]Gallery', 'gallery', 'index.php?site=gallery', 1, 1, 'default')");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'gallery', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'gallery' LIMIT 1
  ))
");
  
 ?>

