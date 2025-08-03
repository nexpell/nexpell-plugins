<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_articles_categories (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  sort_order INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_articles (
  id INT(11) NOT NULL AUTO_INCREMENT,
  category_id int(11) NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  content text NOT NULL,
  slug varchar(255) NOT NULL DEFAULT '',
  banner_image varchar(255) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  updated_at int(14) NOT NULL DEFAULT 0,
  userID int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 0,
  rating int(11) NOT NULL DEFAULT 0,
  points int(11) NOT NULL DEFAULT 0,
  votes int(11) NOT NULL DEFAULT 0,
  views int(11) NOT NULL DEFAULT 0,
  allow_comments TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_articles_settings (
  articlessetID int(11) NOT NULL AUTO_INCREMENT,
  articles int(11) NOT NULL,
  articleschars int(11) NOT NULL,
  PRIMARY KEY (articlessetID)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8 DEFAULT COLLATE utf8_unicode_ci");

safe_query("INSERT IGNORE INTO plugins_articles_settings (articlessetID, articles, articleschars) VALUES
(1, 4, '100')");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Articles', 'articles', '[[lang:de]]Mit diesem Plugin könnt ihr eure Articles anzeigen lassen.[[lang:en]]With this plugin you can display your articles.[[lang:it]]Con questo plugin è possibile mostrare gli Articoli sul sito web.', 'admin_articles', 1, 'T-Seven', 'https://webspell-rm.de', 'articles,articles_rating,articles_comments', '', '0.3', 'includes/plugins/articles/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_articles_content', 'Artikel Widget Content', 'articles', 'articles'),
('widget_articles_sidebar', 'Artikel Widget Sidebar', 'articles', 'articles')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]Artikel[[lang:en]]Articles[[lang:it]]Articoli', 'articles', 'admincenter.php?site=admin_articles', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Artikel[[lang:en]]Articles[[lang:it]]Articoli', 'articles', 'index.php?site=articles', 1, 1)");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
  VALUES ('', 1, 'link', 'articles')
");
 ?>