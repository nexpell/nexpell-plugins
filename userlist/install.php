<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_userlist_settings (
    id int(11) NOT NULL AUTO_INCREMENT,
    users_per_page INT DEFAULT 10,
    users_widget_count INT DEFAULT 5,
    widget_show_online TINYINT(1) DEFAULT 1,
    widget_sort ENUM('lastlogin','registerdate','username') DEFAULT 'lastlogin',
    show_avatars TINYINT(1) DEFAULT 1,
    show_roles TINYINT(1) DEFAULT 1,
    show_website TINYINT(1) DEFAULT 1,
    show_lastlogin TINYINT(1) DEFAULT 1,
    show_online_status TINYINT(1) DEFAULT 1,
    show_registerdate TINYINT(1) DEFAULT 1,
    default_sort ENUM('username','registerdate','lastlogin','is_online','website') DEFAULT 'username',
    default_order ENUM('ASC','DESC') DEFAULT 'ASC',
    enable_search TINYINT(1) DEFAULT 1,
    enable_role_filter TINYINT(1) DEFAULT 1,
    default_role VARCHAR(100) DEFAULT '',
    pagination_style ENUM('simple','full') DEFAULT 'full',
    table_style ENUM('striped','bordered','compact') DEFAULT 'striped',
    avatar_size ENUM('small','medium','large') DEFAULT 'small',
    highlight_online_users TINYINT(1) DEFAULT 1,
PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8");
        
safe_query("INSERT IGNORE INTO plugins_userlist_settings (id, users_per_page, users_widget_count, widget_show_online, widget_sort, show_avatars, show_roles, show_website, show_lastlogin, show_online_status, show_registerdate, default_sort, default_order, enable_search, enable_role_filter, default_role, pagination_style, table_style, avatar_size, highlight_online_users) VALUES 
(1, 10, 5, 1, 'lastlogin', 1, 1, 1, 1, 1, 1, 'username', 'ASC', 1, 1, '', 'full', 'striped', 'small', 1)");


safe_query("CREATE TABLE IF NOT EXISTS plugins_whoisonline (
  id int(11) NOT NULL AUTO_INCREMENT,
  session_id VARCHAR(64) NOT NULL,
  user_id INT DEFAULT NULL,
  last_activity DATETIME NOT NULL,
  page VARCHAR(255) DEFAULT '',
  ip_hash VARCHAR(64) NOT NULL,
  user_agent TEXT,
  is_guest TINYINT(1) NOT NULL DEFAULT 1,
PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8");

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

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Userlist', 'userlist', '[[lang:de]]Mit diesem Plugin könnt ihr euer Registered Users anzeigen lassen.[[lang:en]]With this plugin you can display your registered user.[[lang:it]]Con questo plugin puoi visualizzare la lista dei tuoi utenti registrati.', 'admin_userlist', 1, 'T-Seven', 'https://webspell-rm.de', 'userlist', '', '0.1', 'includes/plugins/userlist/', 1, 1, 1, 1, 'deactivated'),
('', 'Who is online', 'whoisonline', '[[lang:de]]Mit diesem Plugin könnt ihr eure whoisonline anzeigen lassen.[[lang:en]]With this plugin you can display your whoisonline.[[lang:it]]Con questo plugin è possibile mostrare gli whoisonline sul sito web.', 'admin_whoisonline', 1, 'T-Seven', 'https://webspell-rm.de', 'whoisonline', '', '0.3', 'includes/plugins/whoisonline/', 1, 1, 1, 1, 'deactivated'),
('', 'Counter', 'counter', '[[lang:de]]Mit diesem Plugin könnt ihr eure Counter anzeigen lassen.[[lang:en]]With this plugin you can display your Counter.[[lang:it]]Con questo plugin è possibile mostrare gli Counter sul sito web.', 'admin_counter', 1, 'T-Seven', 'https://webspell-rm.de', 'counter', '', '0.3', 'includes/plugins/counter/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_lastregistered_sidebar', 'Last Registered Sidebar', 'userlist', 'userlist'),
('widget_useronline_sidebar', 'User Online Sidebar', 'userlist', 'userlist')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 3, '[[lang:de]]Userlist Einstellungen[[lang:en]]Userlist Settings[[lang:it]]Impostazioni Userlist', 'userlist', 'admincenter.php?site=admin_userlist', 1),
('', 2, '[[lang:de]]Who is online[[lang:en]]Who is online[[lang:it]]Who is online', 'whoisonline', 'admincenter.php?site=admin_whoisonline', 1),
('', 2, '[[lang:de]]Counter[[lang:en]]Counter[[lang:it]]Counter', 'counter', 'admincenter.php?site=admin_counter', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Mitglieder[[lang:en]]Members[[lang:it]]Membri', 'userlist', 'index.php?site=userlist', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname) VALUES 
  ('', 1, 'link', 'userlist'),
  ('', 1, 'link', 'whoisonline'),
  ('', 1, 'link', 'counter')");


 ?>