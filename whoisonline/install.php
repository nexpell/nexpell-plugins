<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_whoisonline (
  id int(11) NOT NULL AUTO_INCREMENT,
  session_id VARCHAR(64) NOT NULL,
  user_id INT DEFAULT NULL,
  last_activity DATETIME NOT NULL,
  page VARCHAR(255) DEFAULT '',
  ip_hash VARCHAR(64) NOT NULL,
  user_agent TEXT,
  is_guest TINYINT(1) NOT NULL DEFAULT 1,
PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8");


safe_query("CREATE TABLE IF NOT EXISTS plugins_whoisonline_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_whoisonline_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Who is online', 'whoisonline', '[[lang:de]]Mit diesem Plugin könnt ihr eure whoisonline anzeigen lassen.[[lang:en]]With this plugin you can display your whoisonline.[[lang:it]]Con questo plugin è possibile mostrare gli whoisonline sul sito web.', 'admin_whoisonline', 1, 'T-Seven', 'https://webspell-rm.de', 'whoisonline', '', '0.3', 'includes/plugins/whoisonline/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_plugins_widget (id, modulname, widgetname, widgetdatei, area) VALUES
('', 'whoisonline', 'Who is online Sidebar', 'widget_whoisonline_sidebar', '4')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 2, '[[lang:de]]Who is online[[lang:en]]Who is online[[lang:it]]Who is online', 'whoisonline', 'admincenter.php?site=admin_whoisonline', 1)");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'whoisonline', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'whoisonline' LIMIT 1
  ))
");
 ?>