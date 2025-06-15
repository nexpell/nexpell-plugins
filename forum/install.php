<?php

safe_query("CREATE TABLE IF NOT EXISTS plugins_forum_categories (
  catID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  position INT DEFAULT 0
)");

safe_query("CREATE TABLE IF NOT EXISTS plugins_forum_threads (
  threadID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  catID INT UNSIGNED NOT NULL,
  userID INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  created_at INT NOT NULL,
  updated_at INT NOT NULL,
  views INT DEFAULT 0,
  FOREIGN KEY (catID) REFERENCES plugins_forum_categories(catID) ON DELETE CASCADE
)");

safe_query("CREATE TABLE IF NOT EXISTS plugins_forum_posts (
  postID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  threadID INT UNSIGNED NOT NULL,
  userID INT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  created_at INT NOT NULL,
  edited_at INT DEFAULT NULL,
  FOREIGN KEY (threadID) REFERENCES plugins_forum_threads(threadID) ON DELETE CASCADE
)");

#######################################################################################################################################

safe_query("CREATE TABLE IF NOT EXISTS plugins_forum_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_forum_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, plugin_display, widget_display, sidebar) VALUES
('Forum', 'forum', '[[lang:de]]Mit diesem Plugin könnt ihr ein Forum auf eurer Website integrieren, in dem Benutzer Themen erstellen und diskutieren können.[[lang:en]]With this plugin you can integrate a forum into your website where users can create topics and participate in discussions.[[lang:it]]Con questo plugin puoi integrare un forum nel tuo sito web, dove gli utenti possono creare argomenti e partecipare alle discussioni.', 'admin_forum', 1, 'T-Seven', 'https://webspell-rm.de', 'forum', '', '0.1', 'includes/plugins/forum/', 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (catID, name, modulname, url, sort) VALUES
(5, '[[lang:de]]Forum[[lang:en]]Forum[[lang:it]]Forum', 'forum', 'admincenter.php?site=admin_forum', 1)");


safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 2, '[[lang:de]]Forum[[lang:en]]Forum[[lang:it]]Forum', 'forum', 'index.php?site=forum', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'forum', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'forum' LIMIT 1
  ))
");
  
 ?>