<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_shoutbox_messages (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  timestamp datetime NOT NULL DEFAULT current_timestamp(),
  username varchar(50) NOT NULL,
  message text NOT NULL,
  PRIMARY KEY (id),
  KEY timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Shoutbox', 'shoutbox', '[[lang:de]]Mit diesem Plugin könnt ihr ein shoutbox auf die Webseite anzeigen lassen.[[lang:en]]With this plugin you can display a shoutbox on the website.[[lang:it]]Con questo plugin puoi visualizzare una galleria sul sito web.', 'admin_shoutbox', 1, 'T-Seven', 'https://webspell-rm.de', 'shoutbox', '', '0.1', 'includes/plugins/shoutbox/', 1, 1, 0, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_shoutbox_sidebar', 'Shoutbox Sidebar', 'shoutbox', 'shoutbox')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 9, '[[lang:de]]Shoutbox[[lang:en]]Shoutbox[[lang:it]]Shoutbox', 'shoutbox', 'admincenter.php?site=admin_shoutbox', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Shoutbox[[lang:en]]Shoutbox[[lang:it]]Shoutbox', 'shoutbox', 'index.php?site=shoutbox', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'shoutbox')
"); 
 ?>

