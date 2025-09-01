<?php


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Live Visitor', 'live_visitor', '[[lang:de]]Mit diesem Plugin könnt ihr eure Live-Besucher, Who is Online und Besucherstatistiken anzeigen lassen.[[lang:en]]With this plugin you can display your live visitors, Who is Online and visitor statistics.[[lang:it]]Con questo plugin puoi visualizzare i visitatori in tempo reale, Who is Online e le statistiche dei visitatori.
', '', 1, 'T-Seven', 'https://webspell-rm.de', 'live_visitor', '', '0.1', 'includes/plugins/live_visitor/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Live-Besucher[[lang:en]]Live Visitors[[lang:it]]Visitatori in tempo reale', 'live_visitor', 'index.php?site=live_visitor', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname) VALUES 
  ('', 1, 'link', 'live_visitor')");


 ?>