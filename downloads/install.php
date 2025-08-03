<?php


safe_query("CREATE TABLE IF NOT EXISTS plugins_downloads_categories (
  categoryID int(11) NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (categoryID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");



safe_query("CREATE TABLE IF NOT EXISTS plugins_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoryID INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file VARCHAR(255) NOT NULL,
    downloads INT DEFAULT 0,
    access_roles TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoryID) REFERENCES `plugins_downloads_categories`(`categoryID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");



safe_query("CREATE TABLE IF NOT EXISTS plugins_downloads_logs (
  logID int(11) NOT NULL AUTO_INCREMENT,
  userID int(11) NOT NULL,
  fileID int(11) NOT NULL,
  downloaded_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (logID),
  KEY userID (userID),
  KEY fileID (fileID),
  CONSTRAINT plugins_downloads_logs_ibfk_1 FOREIGN KEY (userID) REFERENCES users (userID),
  CONSTRAINT plugins_downloads_logs_ibfk_2 FOREIGN KEY (fileID) REFERENCES plugins_downloads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");


## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Download', 'downloads', '[[lang:de]]Mit diesem Plugin könnt ihr eure Download anzeigen lassen.[[lang:en]]With this plugin you can display your Download.[[lang:it]]Con questo plugin è possibile mostrare gli Download sul sito web.', 'admin_downloads,admin_download_stats', 1, 'T-Seven', 'https://webspell-rm.de', 'downloads', '', '0.3', 'includes/plugins/downloads/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 13, '[[lang:de]]Download[[lang:en]]Download[[lang:it]]Download', 'downloads', 'admincenter.php?site=admin_downloads', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 5, '[[lang:de]]Download[[lang:en]]Download[[lang:it]]Download', 'downloads', 'index.php?site=downloads', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'downloads')
");
 ?>