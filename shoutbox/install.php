<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_shoutbox_messages (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  timestamp datetime NOT NULL DEFAULT current_timestamp(),
  username varchar(50) NOT NULL,
  message text NOT NULL,
  PRIMARY KEY (id),
  KEY timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


safe_query("CREATE TABLE IF NOT EXISTS plugins_shoutbox_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_shoutbox_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Shoutbox', 'shoutbox', '[[lang:de]]Mit diesem Plugin könnt ihr ein shoutbox auf die Webseite anzeigen lassen.[[lang:en]]With this plugin you can display a shoutbox on the website.[[lang:it]]Con questo plugin puoi visualizzare una galleria sul sito web.', 'admin_shoutbox', 1, 'T-Seven', 'https://webspell-rm.de', 'shoutbox', '', '0.1', 'includes/plugins/shoutbox/', 1, 1, 0, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_plugins_widget (id, modulname, widgetname, widgetdatei, area) VALUES
('', 'shoutbox', 'Shoutbox Sidebar', 'widget_shoutbox_sidebar', 4)");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 9, '[[lang:de]]Shoutbox[[lang:en]]Shoutbox[[lang:it]]Shoutbox', 'shoutbox', 'admincenter.php?site=admin_shoutbox', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 3, '[[lang:de]]Shoutbox[[lang:en]]Shoutbox[[lang:it]]Shoutbox', 'shoutbox', 'index.php?site=shoutbox', 1, 1, 'default')");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'shoutbox', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'shoutbox' LIMIT 1
  ))
");
  
 ?>

