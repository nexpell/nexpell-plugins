<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_todo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NOT NULL,
  task VARCHAR(255) NOT NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


#######################################################################################################################################
safe_query("CREATE TABLE IF NOT EXISTS plugins_todo_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_todo_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");




## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Todo', 'todo', '[[lang:de]]Dieses Widget zeigt allgemeine Informationen (kleiner Lebenslauf) über Sie auf Ihrer Webspell-RM-RM-Seite an.[[lang:en]]This widget will show general information (small resume) todo You on your Webspell-RM-RM site.[[lang:it]]Questo widget mostrerà informazioni generali (piccolo curriculum) su di te sul tuo sito Webspell-RM-RM.', 'admin_todo', 1, 'T-Seven', 'https://Webspell-RM-rm.de', 'todo', '', '0.1', 'includes/plugins/todo/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]Todo[[lang:en]]Todo[[lang:it]]Todo', 'todo', 'admincenter.php?site=admin_todo', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 3, '[[lang:de]]Todo[[lang:en]]Todo[[lang:it]]Todo', 'todo', 'index.php?site=todo', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'todo', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'todo' LIMIT 1
  ))
");
 ?>