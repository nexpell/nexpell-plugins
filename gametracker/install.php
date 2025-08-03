<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_gametracker_servers (
  id int(11) NOT NULL AUTO_INCREMENT,
  ip varchar(100) NOT NULL,
  port int(11) NOT NULL,
  query_port int(11) DEFAULT NULL,
  game varchar(50) NOT NULL,
  game_pic varchar(255) NOT NULL,
  active tinyint(1) DEFAULT 1,
  sort_order int(11) DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

safe_query("INSERT IGNORE INTO plugins_gametracker_servers (id, ip, port, query_port, game, game_pic, active, sort_order) VALUES
(1, '85.14.192.114', 28960, NULL, 'coduo', 'uo', 1, 0)");


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Gametracker', 'gametracker', '', 'admin_gametracker', 1, 'T-Seven', 'https://www.nexpell.de', 'gametracker', '', '0.1', 'includes/plugins/gametracker/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_gametracker_sidebar', 'Gametracker Sidebar', 'gametracker', 'gametracker')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 11, '[[lang:de]]Spielserver[[lang:en]]Game Servers[[lang:it]]Server di gioco', 'gametracker', 'admincenter.php?site=admin_gametracker', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 6, '[[lang:de]]Spielserver[[lang:en]]Game Servers[[lang:it]]Server di gioco', 'gametracker', 'index.php?site=gametracker', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'gametracker')
");
 ?>