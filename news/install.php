<?php
safe_query("
CREATE TABLE IF NOT EXISTS plugins_news (
  id int(11) NOT NULL AUTO_INCREMENT,
  category_id int(11) DEFAULT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  slug varchar(255) NOT NULL,
  content text NOT NULL,
  link varchar(255) NOT NULL DEFAULT '',
  banner_image varchar(255) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  updated_at int(14) NOT NULL DEFAULT 0,
  userID int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 0,
  topnews_is_active tinyint(1) NOT NULL DEFAULT 0,
  views int(11) NOT NULL DEFAULT 0,
  allow_comments tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe_query("CREATE TABLE IF NOT EXISTS plugins_news_categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  slug varchar(255) NOT NULL,
  description text NOT NULL,
  image varchar(255) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/*safe_query("INSERT IGNORE INTO plugins_news_categories (id, name, slug, description, image, sort_order) VALUES
(1, 'nexpell Core', 'nexpell-core', '', '1758049936_3.jpg', 0),
(2, 'nexpell Plugins', 'nexpell-plugins', '', '1758050383_1.jpg', 0),
(3, 'nexpell Themes', 'nexpell-themes', '', '1758050511_5.jpg', 0),
(4, 'nexpell Installation', 'nexpell-installation', '', '1759013707_core2.png', 0)");


safe_query("INSERT IGNORE INTO plugins_news 
(id, category_id, title, slug, content, link, banner_image, sort_order, updated_at, userID, is_active, topnews_is_active, views, allow_comments) VALUES
(1, 1, 'nexpell Core – Von Alpha zur Beta: Der nächste Entwicklungsschritt', 'von-alpha-zur-beta', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', '', '', 0, UNIX_TIMESTAMP(), 1, 1, 0, 0, 1),
(2, 2, 'nexpell Plugins – Erweiterungen für noch mehr Möglichkeiten', 'erweiterungen-fuer-noch-mehr-moeglichkeiten', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', '', '', 0, UNIX_TIMESTAMP(), 1, 1, 0, 0, 1),
(3, 3, 'Neues Default-Theme für eigene Anpassungen', 'default-theme', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', '', '', 0, UNIX_TIMESTAMP(), 1, 1, 0, 0, 1),
(4, 4, 'Neue Core-Features in Nexpell: SEO und mehrsprachige Eingabe', 'seo-und-mehrsprachige-eingabe', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', '', '', 0, UNIX_TIMESTAMP(), 1, 1, 0, 0, 1),
(5, 4, 'Neues Such-Plugin für Nexpell veröffentlicht', 'such-plugin', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', '', '', 0, UNIX_TIMESTAMP(), 1, 1, 0, 0, 1)");*/



## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'News', 'news', '[[lang:de]]Dieses Plugin ermöglicht das Erstellen und Verwalten von News-Artikeln auf Ihrer Webspell-RM-Seite.[[lang:en]]This plugin allows you to create and manage news articles on your Webspell-RM site.[[lang:it]]Questo plugin consente di creare e gestire articoli di notizie sul tuo sito Webspell-RM.', 'admin_news', 1, 'T-Seven', 'https://Webspell-RM-rm.de', 'news', '', '1.0', 'includes/plugins/news/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_news_masonry', 'News Masonry', 'news', 'news'),
('widget_news_carousel', 'News Carousel', 'news', 'news'),
('widget_news_featured_list', 'News Featured List', 'news', 'news'),
('widget_news_flip', 'News Flip', 'news', 'news'),
('widget_news_magazine', 'News Magazine', 'news', 'news'),
('widget_news_topnews', 'News Topnews', 'news', 'news')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]News[[lang:en]]News[[lang:it]]Notizie', 'news', 'admincenter.php?site=admin_news', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 1, '[[lang:de]]News[[lang:en]]News[[lang:it]]Notizie', 'news', 'index.php?site=news', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname) VALUES 
  ('', 1, 'link', 'news')");

 ?>