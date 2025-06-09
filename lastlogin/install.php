<?php

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Lastlogin', 'lastlogin', '{[de]}Mit diesem Plugin ist es möglich die Aktivität der User und Mitglieder zu überprüfen.{[en]}With this plugin it is possible to check the activity of the users and members.{[it]}Con questo plugin è possibile controllare l\'attività degli utenti e dei membri.', 'admin_lastlogin', 1, 'T-Seven', 'https://webspell-rm.de', '', '', '0.1', 'includes/plugins/lastlogin/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 3, '[[lang:de]]Letzte Anmeldung[[lang:en]]Last Login[[lang:it]]Ultimi Login', 'lastlogin', 'admincenter.php?site=admin_lastlogin', 2)");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'lastlogin', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'lastlogin' LIMIT 1
  ))
");
 ?>