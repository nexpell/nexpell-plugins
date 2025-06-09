<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_rules (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL DEFAULT '',
  text text NOT NULL,
  date timestamp NOT NULL DEFAULT current_timestamp(),
  userID int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) DEFAULT 0,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_rules_settings (
  rulessetID int(11) NOT NULL AUTO_INCREMENT,
  rules int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (rulessetID)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_rules_settings (rulessetID, rules) VALUES (1, 5)"); 

#######################################################################################################################################

safe_query("CREATE TABLE IF NOT EXISTS plugins_rules_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_rules_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, plugin_display, widget_display, sidebar) VALUES
('', 'Rules', 'rules', '[[lang:de]]Mit diesem Plugin könnt ihr eure Regeln anzeigen lassen.[[lang:en]]With this plugin it is possible to show the Rules on the website.[[lang:it]]Con questo plugin è possibile mostrare le Regole del sul sito web', 'admin_rules', 1, 'T-Seven', 'https://webspell-rm.de', 'rules', '', '0.1', 'includes/plugins/rules/', 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 5, '[[lang:de]]Regeln[[lang:en]]Rules[[lang:it]]Regole del', 'rules', 'admincenter.php?site=admin_rules', 1)");


safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 2, '[[lang:de]]Regeln[[lang:en]]Rules[[lang:it]]Regole del', 'rules', 'index.php?site=rules', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'rules', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'rules' LIMIT 1
  ))
");
  
 ?>