<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_todo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NOT NULL,
  task VARCHAR(255) NOT NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Todo', 'todo', '[[lang:de]]Dieses Widget zeigt allgemeine Informationen (kleiner Lebenslauf) über Sie auf Ihrer Webspell-RM-RM-Seite an.[[lang:en]]This widget will show general information (small resume) todo You on your Webspell-RM-RM site.[[lang:it]]Questo widget mostrerà informazioni generali (piccolo curriculum) su di te sul tuo sito Webspell-RM-RM.', 'admin_todo', 1, 'T-Seven', 'https://Webspell-RM-rm.de', 'todo', '', '0.1', 'includes/plugins/todo/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]Todo[[lang:en]]Todo[[lang:it]]Todo', 'todo', 'admincenter.php?site=admin_todo', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Todo[[lang:en]]Todo[[lang:it]]Todo', 'todo', 'index.php?site=todo', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'todo')
");
 ?>