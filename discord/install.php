<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_discord (
  name VARCHAR(100) NOT NULL,
  value TEXT,
  PRIMARY KEY (name)
)");


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Discord', 'discord', '[[lang:de]]Dieses Widget zeigt die Entwicklungsgeschichte und wichtige Meilensteine von nexpell auf Ihrer Webseite an.[[lang:en]]This widget displays the development history and key milestones of nexpell on your website.[[lang:it]]Questo widget mostra la storia dello sviluppo e le tappe fondamentali di nexpell sul tuo sito web.
', 'admin_discord', 1, 'T-Seven', 'https://www.nexpell.de', 'discord', '', '0.1', 'includes/plugins/discord/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_discord_sidebar', 'Discord Widget Sidebar', 'discord', 'discord')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 11, '[[lang:de]]Discord[[lang:en]]Discord[[lang:it]]Discord', 'discord', 'admincenter.php?site=admin_discord', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 6, '[[lang:de]]Discord[[lang:en]]Discord[[lang:it]]Discord', 'discord', 'index.php?site=discord', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'discord')
");
 ?>