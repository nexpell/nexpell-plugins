<?php

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Resume', 'resume', '[[lang:de]]Dieses Widget zeigt die Entwicklungsgeschichte und wichtige Meilensteine von webSPELL-RM auf Ihrer Webseite an.[[lang:en]]This widget displays the development history and key milestones of webSPELL-RM on your website.[[lang:it]]Questo widget mostra la storia dello sviluppo e le tappe fondamentali di webSPELL-RM sul tuo sito web.
', 'admin_resume', 1, 'T-Seven', 'https://Webspell-RM-rm.de', 'resume', '', '0.1', 'includes/plugins/resume/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 5, '[[lang:de]]Resume[[lang:en]]Resume[[lang:it]]Resume', 'resume', 'admincenter.php?site=admin_resume', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 6, '[[lang:de]]Resume[[lang:en]]Resume[[lang:it]]Resume', 'resume', 'index.php?site=resume', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'resume', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'resume' LIMIT 1
  ))
");
 ?>