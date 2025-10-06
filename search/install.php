<?php


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'search', 'search', '[[lang:de]]Mit diesem Plugin könnt ihr eure Suche anzeigen lassen.[[lang:en]]With this plugin you can display your search.[[lang:it]]Con questo plugin potete mostrare la vostra ricerca.', 'admin_search', 1, 'T-Seven', 'https://www.nexpell.de', 'search', '', '0.1', 'includes/plugins/search/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_search_sidebar', 'Search Sidebar', 'search', 'search')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]Suche[[lang:en]]Search[[lang:it]]Ricerca', 'search', 'admincenter.php?site=admin_search', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 5, '[[lang:de]]Suche[[lang:en]]Search[[lang:it]]Ricerca', 'search', 'index.php?site=search', 1, 1)");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'search')
");
 ?>