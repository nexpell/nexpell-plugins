<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_twitch_settings (
  id int(11) NOT NULL AUTO_INCREMENT,
  main_channel varchar(100) NOT NULL,
  extra_channels text NOT NULL,
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=2
");

safe_query("INSERT IGNORE INTO plugins_twitch_settings (id, main_channel, extra_channels, updated_at) VALUES
(1, 'fl0m', 'zonixxcs,trilluxe', '2025-07-13 19:03:30')");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Twitch', 'twitch', '[[lang:de]]Dieses Widget zeigt die Entwicklungsgeschichte und wichtige Meilensteine von nexpell auf Ihrer Webseite an.[[lang:en]]This widget displays the development history and key milestones of nexpell on your website.[[lang:it]]Questo widget mostra la storia dello sviluppo e le tappe fondamentali di nexpell sul tuo sito web.
', 'admin_twitch', 1, 'T-Seven', 'https://www.nexpell.de', 'twitch', '', '0.1', 'includes/plugins/twitch/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 11, '[[lang:de]]Twitch[[lang:en]]Twitch[[lang:it]]Twitch', 'twitch', 'admincenter.php?site=admin_twitch', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 4, '[[lang:de]]Twitch[[lang:en]]Twitch[[lang:it]]Twitch', 'twitch', 'index.php?site=twitch', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'twitch')
");
 ?>