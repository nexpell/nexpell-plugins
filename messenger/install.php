<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_messages (
  id INT(11) NOT NULL AUTO_INCREMENT,
  sender_id VARCHAR(255) NOT NULL,
  receiver_id VARCHAR(255) NOT NULL,
  text TEXT NOT NULL,
  image_url VARCHAR(255) DEFAULT NULL,
  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Messenger', 'messenger', '[[lang:de]]Mit diesem Plugin könnt ihr eure messenger anzeigen lassen.[[lang:en]]With this plugin you can display your messenger.[[lang:it]]Con questo plugin è possibile mostrare gli Articoli sul sito web.', '', 1, 'T-Seven', 'https://webspell-rm.de', 'messenger', '', '0.3', 'includes/plugins/messenger/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################



#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'messenger')
");
 ?>