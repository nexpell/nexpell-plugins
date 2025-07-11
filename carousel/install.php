<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_carousel (
  id int(11) NOT NULL AUTO_INCREMENT,
  type enum('sticky','parallax','agency','carousel') NOT NULL,
  title varchar(255) DEFAULT NULL,
  subtitle text DEFAULT NULL,
  description text DEFAULT NULL,
  link varchar(255) DEFAULT NULL,
  media_type enum('image','video') NOT NULL,
  media_file varchar(255) DEFAULT NULL,
  visible tinyint(1) DEFAULT 1,
  sort int(11) DEFAULT 0,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) AUTO_INCREMENT=9
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");
        
safe_query("INSERT IGNORE INTO plugins_carousel (id, type, title, subtitle, description, link, media_type, media_file, visible, sort, created_at) VALUES
(1, 'sticky', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_687148bb0318b.jpg', 1, 0, '2025-07-11 19:24:11'),
(2, 'parallax', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_6871494833ec1.jpg', 1, 0, '2025-07-11 19:26:32'),
(3, 'agency', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_687149651d571.jpg', 1, 0, '2025-07-11 19:27:01'),
(4, 'carousel', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_687149d478869.jpg', 1, 0, '2025-07-11 19:28:52'),
(5, 'carousel', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_687149e906f43.jpg', 1, 0, '2025-07-11 19:29:13'),
(6, 'carousel', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_687149fd5a1af.jpg', 1, 0, '2025-07-11 19:29:33'),
(7, 'carousel', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'image', 'block_68714d40abe62.jpg', 1, 0, '2025-07-11 19:29:57'),
(8, 'carousel', 'nexpell', 'Das moderne CMS', '', 'https://www.nexpell.de', 'video', 'block_68714a4106e25.mp4', 1, 0, '2025-07-11 19:30:41')");

safe_query("CREATE TABLE IF NOT EXISTS plugins_carousel_settings (
  carouselID int(11) NOT NULL AUTO_INCREMENT,
  carousel_height varchar(255) NOT NULL DEFAULT '0',
  parallax_height varchar(255) NOT NULL DEFAULT '0',
  sticky_height varchar(255) NOT NULL DEFAULT '0',
  agency_height varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (carouselID)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_carousel_settings (carouselID, carousel_height, parallax_height, sticky_height, agency_height) VALUES
(1, '75vh', '75vh', '75vh', '75vh')");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Carousel', 'carousel', '[[lang:de]]Mit diesem Plugin könnt ihr ein Carousel in die Webseite einbinden.[[lang:en]]With this plugin you can integrate a carousel into your website.[[lang:it]]Con questo plugin puoi integrare un carosello nel sito web.', 'admin_carousel', 1, 'T-Seven', 'https://nexpell.de', '', '', '0.1', 'includes/plugins/carousel/', 1, 1, 0, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_sticky_header', 'Sticky Header', 'carousel', 'carousel'),
('widget_carousel_crossfade', 'Carousel Crossfade', 'carousel', 'carousel'),
('widget_parallax_header', 'Parallax Header', 'carousel', 'carousel'),
('widget_agency_header', 'Agency Header', 'carousel', 'carousel')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 10, '[[lang:de]]Carousel[[lang:en]]Carousel[[lang:it]]Carosello Immagini', 'carousel', 'admincenter.php?site=admin_carousel', 1)");

#######################################################################################################################################

safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'carousel', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'carousel' LIMIT 1
  ))
");
  
 ?>

