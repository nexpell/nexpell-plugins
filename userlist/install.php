<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_userlist (
  ruID int(11) NOT NULL AUTO_INCREMENT,
  users_list int(11) NOT NULL DEFAULT '0',
  users_online int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (ruID)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");
        
safe_query("INSERT IGNORE INTO plugins_userlist (ruID, users_list, users_online) VALUES ('1', '15', '5')");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Userlist', 'userlist', '[[lang:de]]Mit diesem Plugin könnt ihr euer Registered Users anzeigen lassen.[[lang:en]]With this plugin you can display your registered user.[[lang:it]]Con questo plugin puoi visualizzare la lista dei tuoi utenti registrati.', 'admin_userlist', 1, 'T-Seven', 'https://webspell-rm.de', 'userlist', '', '0.1', 'includes/plugins/userlist/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_lastregistered_sidebar', 'Last Registered Sidebar', 'userlist', 'userlist'),
('widget_useronline_sidebar', 'User Online Sidebar', 'userlist', 'userlist')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 3, '[[lang:de]]Mitglieder[[lang:en]]Members[[lang:it]]Membri', 'userlist', 'admincenter.php?site=admin_userlist', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Mitglieder[[lang:en]]Members[[lang:it]]Membri', 'userlist', 'index.php?site=userlist', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'userlist')
");
 ?>