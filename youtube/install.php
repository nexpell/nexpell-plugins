<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_youtube (
  id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  plugin_name varchar(50) NOT NULL,
  setting_key varchar(50) NOT NULL,
  setting_value text NOT NULL,
  is_first tinyint(1) NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY plugin_key_unique (plugin_name, setting_key)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

safe_query("CREATE TABLE IF NOT EXISTS plugins_youtube_settings (
  id INT(11) NOT NULL AUTO_INCREMENT,
  plugin_name VARCHAR(50) NOT NULL,
  setting_key VARCHAR(50) NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

safe_query("INSERT IGNORE INTO plugins_youtube (id, plugin_name, setting_key, setting_value, is_first, updated_at) VALUES
(2, 'youtube', 'video_1', 'FAfxTvlq87s', 0, '2025-08-23 16:17:11'),
(6, 'youtube', 'video_2', 'PPQeNNvOdis', 0, '2025-08-23 18:25:48'),
(8, 'youtube', 'video_4', 'N6DW31S_oyI', 0, '2025-08-23 17:23:35'),
(9, 'youtube', 'video_5', 'hqQY9UkGC_A', 0, '2025-08-23 15:57:28'),
(10, 'youtube', 'video_6', 'ft4jcPSLJfY', 0, '2025-08-23 18:22:53'),
(11, 'youtube', 'video_7', '8wRW57nBLMI', 0, '2025-08-23 16:55:32'),
(12, 'youtube', 'video_8', 'a0nPjZkxCzQ', 0, '2025-08-23 16:16:04'),
(13, 'youtube', 'video_9', 'C3sW15lSAlM', 0, '2025-08-23 17:39:41'),
(14, 'youtube', 'video_10', 'wTUtBMMLseQ', 0, '2025-08-23 17:40:08'),
(15, 'youtube', 'video_11', 'ahzO3kqxP8Q', 1, '2025-08-23 18:48:50')");


safe_query("INSERT IGNORE INTO plugins_youtube_settings (id, plugin_name, setting_key, setting_value, updated_at) VALUES
(1, 'youtube', 'default_video_id', 'N6DW31S_oyI', '2025-08-23 18:49:00'),
(2, 'youtube', 'videos_per_page', '4', '2025-08-23 18:49:00'),
(3, 'youtube', 'videos_per_page_other', '6', '2025-08-23 18:49:00'),
(4, 'youtube', 'display_mode', 'grid', '2025-08-23 18:49:00'),
(5, 'youtube', 'first_full_width', '1', '2025-08-23 18:49:00')");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Youtube', 'youtube', '[[lang:de]]Mit diesem Plugin könnt ihr eure Youtube anzeigen lassen.[[lang:en]]With this plugin you can display your Youtube.[[lang:it]]Con questo plugin è possibile mostrare gli Youtube sul sito web.', 'admin_youtube', 1, 'T-Seven', 'https://www.nexpell.de', 'youtube', '', '0.3', 'includes/plugins/youtube/', 1, 1, 1, 1, 'deactivated')");

/*safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_Youtube_content', 'Youtube Widget Content', 'youtube', 'youtube'),
('widget_Youtube_sidebar', 'Youtube Widget Sidebar', 'youtube', 'youtube')");*/

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 9, '[[lang:de]]Youtube[[lang:en]]Youtube[[lang:it]]Youtube', 'youtube', 'admincenter.php?site=admin_youtube', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, last_modified, sort, indropdown) VALUES
('', 4, '[[lang:de]]Youtube[[lang:en]]Youtube[[lang:it]]Youtube', 'youtube', 'index.php?site=youtube', NOW(), 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'youtube')
");
 ?>