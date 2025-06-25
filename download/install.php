<?php


safe_query("CREATE TABLE IF NOT EXISTS plugins_download_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_download_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Download', 'download', '[[lang:de]]Mit diesem Plugin könnt ihr eure Download anzeigen lassen.[[lang:en]]With this plugin you can display your Download.[[lang:it]]Con questo plugin è possibile mostrare gli Download sul sito web.', 'admin_download', 1, 'T-Seven', 'https://webspell-rm.de', 'download', '', '0.3', 'includes/plugins/download/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 13, '[[lang:de]]Download[[lang:en]]Download[[lang:it]]Download', 'download', 'admincenter.php?site=admin_download', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 4, '[[lang:de]]Download[[lang:en]]Download[[lang:it]]Download', 'download', 'index.php?site=download', 1, 1, 'default')");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'download', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'download' LIMIT 1
  ))
");
  
 ?>