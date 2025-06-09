<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_links_categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(100) NOT NULL,
  icon varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

safe_query("CREATE TABLE IF NOT EXISTS plugins_links (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(100) NOT NULL,
  url varchar(255) NOT NULL,
  description text DEFAULT NULL,
  category_id int(11) DEFAULT NULL,
  image varchar(255) DEFAULT NULL,
  target varchar(10) DEFAULT '_blank',
  visible tinyint(1) DEFAULT 1,
  userID int(11) NOT NULL DEFAULT 0,
  updated_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

safe_query("INSERT IGNORE INTO plugins_links_categories (id, title, icon) VALUES
(1, 'Webseiten', 'bi bi-globe'),
(2, 'YouTube-Kanäle', 'bi bi-youtube'),
(3, 'Tools & Dienste', 'bi bi-tools'),
(4, 'Gaming', 'bi bi-controller'),
(5, 'Lernen & Wissen', 'bi bi-book');");

safe_query("INSERT IGNORE INTO plugins_links (id, title, url, description, category_id, image, target, visible, userID, updated_at) VALUES
(1, 'Linus Tech Tips', 'https://www.youtube.com/user/LinusTechTips', 'Technik-Videos rund ums Thema PC', 2, 'includes/plugins/links/images/linkimg_6845a2777b0b7.jpg', '_blank', 1, 1, '2025-06-01 11:46:22'),
(2, 'PHP Offizielle Webseite', 'https://www.php.net', 'Offizielle PHP Webseite mit Doku und Downloads', 1, 'includes/plugins/links/images/linkimg_683ded6f9c855.png', '_blank', 1, 1, '2025-06-01 11:46:22'),
(3, 'GitHub', 'https://github.com', 'Hosting für Softwareprojekte mit Git', 1, 'includes/plugins/links/images/linkimg_683dec5a8925a.jpg', '_blank', 1, 1, '2025-06-01 11:46:22'),
(4, 'callofduty', 'https://www.callofduty.com', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.', 4, 'includes/plugins/links/images/linkimg_6845a3a14bd60.jpg', '_blank', 1, 1, '2025-06-01 11:46:22'),
(5, 'all-inkl', 'https://all-inkl.com', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.', 3, 'includes/plugins/links/images/upload_6845a3872c9f5.png', '_blank', 1, 1, '2025-06-01 11:46:22'),
(6, 'Webspell-RM 3.0', 'https://208.webspell-rm.de', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.', 3, 'includes/plugins/links/images/linkimg_6845a36655b83.jpg', '_blank', 1, 1, '2025-06-01 11:46:22'),
(7, 'werstreamt.es', 'https://www.werstreamt.es/', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.', 3, 'includes/plugins/links/images/linkimg_6845a36eda557.png', '_blank', 1, 1, '2025-06-01 11:46:22'),
(8, 'Miley Cyrus - End of the World', 'https://www.youtube.com/watch?v=CXBFU97X61I&list=RDMMCXBFU97X61I&start_radio=1', 'Miley Cyrus - End of the World', 2, 'includes/plugins/links/images/linkimg_6845a30d44f11.jpg', '_blank', 1, 1, '2025-06-01 11:46:22');");


    

safe_query("CREATE TABLE IF NOT EXISTS plugins_links_settings (
  linkssetID int(11) NOT NULL AUTO_INCREMENT,
  links int(11) NOT NULL,
  linkchars int(11) NOT NULL,
  PRIMARY KEY (linkssetID)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_links_settings (linkssetID, links, linkchars) VALUES (1, 4, '300')");

safe_query("CREATE TABLE IF NOT EXISTS plugins_links_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_links_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Links', 'links', '[[lang:de]]Mit diesem Plugin könnt ihr eure Links anzeigen lassen.[[lang:en]]With this plugin you can display your links.[[lang:it]]Con questo plugin puoi visualizzare i tuoi link.', 'admin_links', 1, 'T-Seven', 'https://webspell-rm.de', 'links,admin_links,links_rating', '', '0.1', 'includes/plugins/links/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 13, '[[lang:de]]Links[[lang:en]]Links[[lang:it]]Link', 'links', 'admincenter.php?site=admin_links', 1)");


safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 4, '[[lang:de]]Links[[lang:en]]Links[[lang:it]]Link', 'links', 'index.php?site=links', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'links', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'links' LIMIT 1
  ))
");
 ?>