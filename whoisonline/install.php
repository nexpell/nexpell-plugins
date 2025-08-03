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

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Who is online', 'whoisonline', '[[lang:de]]Mit diesem Plugin könnt ihr eure whoisonline anzeigen lassen.[[lang:en]]With this plugin you can display your whoisonline.[[lang:it]]Con questo plugin è possibile mostrare gli whoisonline sul sito web.', 'admin_whoisonline', 1, 'T-Seven', 'https://webspell-rm.de', 'whoisonline', '', '0.3', 'includes/plugins/whoisonline/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 2, '[[lang:de]]Who is online[[lang:en]]Who is online[[lang:it]]Who is online', 'whoisonline', 'admincenter.php?site=admin_whoisonline', 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'whoisonline')
");
 ?>