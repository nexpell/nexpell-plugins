<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_sponsors (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  slug varchar(255) DEFAULT NULL,
  logo varchar(255) DEFAULT NULL,
  level enum('Platin Sponsor','Gold Sponsor','Silber Sponsor','Bronze Sponsor','Partner','Unterstützer') DEFAULT 'Unterstützer',
  description text DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT current_timestamp(),
  userID int(11) NOT NULL,
  sort_order int(11) DEFAULT 0,
  is_active tinyint(1) DEFAULT 1,
  PRIMARY KEY (id)
) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_sponsors (id, name, slug, logo, level, description, updated_at, userID, sort_order, is_active) VALUES
(1, 'Firma A', 'https://www.nexpell.de', '1.png', 'Platin Sponsor', NULL, '2025-06-01 13:46:22', 1, 1, 1),
(2, 'Firma B', 'https://www.nexpell.de', '2.png', 'Gold Sponsor', NULL, '2025-06-01 13:46:22', 1, 2, 1),
(3, 'Firma C', 'https://www.nexpell.de', '3.png', 'Silber Sponsor', NULL, '2025-06-01 13:46:22', 1, 3, 1),
(4, 'Firma D', 'https://www.nexpell.de', '4.png', 'Bronze Sponsor', NULL, '2025-06-01 13:46:22', 1, 4, 1),
(5, 'Firma E', 'https://www.nexpell.de', '5.png', 'Partner', NULL, '2025-06-01 13:46:22', 1, 5, 1),
(6, 'Firma F', 'https://www.nexpell.de', '6.png', 'Unterstützer', NULL, '2025-06-01 13:46:22', 1, 6, 1)");

safe_query("CREATE TABLE IF NOT EXISTS plugins_sponsors_settings (
  sponsorssetID INT(11) NOT NULL AUTO_INCREMENT,
  sponsors INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (sponsorssetID)
) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_sponsors_settings (sponsorssetID, sponsors) VALUES (1, 5)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Sponsors', 'sponsors', '[[lang:de]]Mit diesem Plugin könnt ihr eure Sponsoren anzeigen lassen.[[lang:en]]With this plugin you can display your sponsors.[[lang:it]]Con questo plugin puoi visualizzare i tuoi sponsor.', 'admin_sponsors', 1, 'T-Seven', 'https://www.nexpell.de', 'sponsors', '', '0.2', 'includes/plugins/sponsors/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 13, '[[lang:de]]Sponsoren[[lang:en]]Sponsors[[lang:it]]Sponsor', 'sponsors', 'admincenter.php?site=admin_sponsors', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 5, '[[lang:de]]Sponsoren[[lang:en]]Sponsors[[lang:it]]Sponsor', 'sponsors', 'index.php?site=sponsors', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'sponsors')
");
 ?>