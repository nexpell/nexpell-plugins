<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_counter_visitors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash VARCHAR(64) NOT NULL,
    timestamp DATETIME NOT NULL,
    device_type VARCHAR(20),
    os VARCHAR(50),
    browser VARCHAR(100),
    referer VARCHAR(300),
    user_agent TEXT,
    PRIMARY KEY (id),
    INDEX idx_ip_hash (ip_hash),
    INDEX idx_timestamp (timestamp)
);");

safe_query("CREATE TABLE IF NOT EXISTS plugins_counter_clicks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash VARCHAR(64) NOT NULL,
    page VARCHAR(255) NOT NULL,
    timestamp DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_ip_hash (ip_hash),
    INDEX idx_timestamp (timestamp),
    INDEX idx_page (page)
);");





safe_query("CREATE TABLE IF NOT EXISTS plugins_counter_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_counter_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Counter', 'counter', '[[lang:de]]Mit diesem Plugin könnt ihr eure Counter anzeigen lassen.[[lang:en]]With this plugin you can display your Counter.[[lang:it]]Con questo plugin è possibile mostrare gli Counter sul sito web.', 'admin_counter', 1, 'T-Seven', 'https://webspell-rm.de', 'counter', '', '0.3', 'includes/plugins/counter/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_plugins_widget (id, modulname, widgetname, widgetdatei, area) VALUES
('', 'counter', 'Counter Sidebar', 'widget_Counter_sidebar', '4')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 2, '[[lang:de]]Counter[[lang:en]]Counter[[lang:it]]Counter', 'counter', 'admincenter.php?site=admin_counter', 1)");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'counter', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'counter' LIMIT 1
  ))
");
  
 ?>