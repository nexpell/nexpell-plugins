<?php
/*safe_query("CREATE TABLE IF NOT EXISTS plugins_news (
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
  KEY category_id (category_id),
  CONSTRAINT fk_news_category
    FOREIGN KEY (category_id) REFERENCES plugins_news_categories (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");*/

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

// 3. FK nachträglich hinzufügen
/*safe_query("
ALTER TABLE plugins_news
  ADD CONSTRAINT fk_news_category
  FOREIGN KEY (category_id)
  REFERENCES plugins_news_categories (id)
  ON DELETE SET NULL
  ON UPDATE CASCADE
");*/

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

safe_query("INSERT IGNORE INTO plugins_news_categories (id, name, slug, description, image, sort_order) VALUES
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
(5, 4, 'Neues Such-Plugin für Nexpell veröffentlicht', 'such-plugin', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.', '', '', 0, UNIX_TIMESTAMP(), 1, 1, 0, 0, 1)");

/*
(4, 1, 'Neue Core-Features in Nexpell: SEO und mehrsprachige Eingabe', 'seo-und-mehrsprachige-eingabe', '<p>Mit dem jüngsten Update des Nexpell CMS-Core haben wir bedeutende Verbesserungen rund um SEO und die mehrsprachige Content-Verwaltung eingeführt, die Ihre Website flexibler und suchmaschinenfreundlicher machen. Im Folgenden stellen wir die wichtigsten Neuerungen vor:</p>\r\n\r\n<h2>1. Separate SEO-Eingabefelder pro Seite</h2>\r\n\r\n<p>Um die Auffindbarkeit Ihrer Website in Suchmaschinen zu optimieren, können nun für jede einzelne Seite spezifische SEO-Daten individuell eingetragen werden:</p>\r\n\r\n<ul>\r\n  <li><strong>Title:</strong> Der Seitentitel wird nicht mehr global, sondern je Seite separat gepflegt. So lässt sich für jede Seite ein präziser, suchmaschinenoptimierter Titel vergeben.</li>\r\n <li><strong>Meta-Description:</strong> Ebenfalls für jede URL individuell hinterlegbar, ermöglicht diese kurze Beschreibung eine bessere Darstellung in den Suchergebnissen.</li>\r\n</ul>\r\n\r\n<p>Diese Trennung sorgt für mehr SEO-Kontrolle und erhöht die Relevanz der Suchergebnisanzeigen für Ihre Besucher.</p>\r\n\r\n<h2>2. Erweiterte Mehrsprachigkeit mit Content-Abschnitten</h2>\r\n\r\n<p>Nexpell ermöglicht jetzt eine strukturierte Mehrsprachigkeit direkt im CMS-Content. Für jede unterstützte Sprache – zum Beispiel Deutsch, Englisch und Italienisch – steht ein separates Eingabefeld zur Verfügung, in dem Redakteure die Inhalte jeweils pflegen.</p>\r\n\r\n<p>Beim Speichern werden diese Einzel-Eingaben automatisch zu einem mehrsprachigen Block im Format</p>\r\n\r\n<pre>\r\n<code>[[lang:de]]...[[lang:en]]...[[lang:it]]...</code></pre>\r\n\r\n<p>zusammengeführt und in der Datenbank gespeichert.</p>\r\n\r\n<p>Dieses Verfahren erlaubt eine zentrale, klare und übersichtliche Verwaltung von mehrsprachigem Content. Im Frontend werden automatisch die passenden Sprachabschnitte ausgegeben – abhängig von der aktuellen Sprache der Website.</p>\r\n\r\n<h2>3. Dynamische Sprachumschaltung mit SEO-freundlichen URLs</h2>\r\n\r\n<p>Die Sprachumschaltung wurde grundlegend verbessert und verwendet nun SEO-freundliche URLs. Statt der bisherigen URL-Parameter</p>\r\n\r\n<pre>\r\n<code>index.php?site=seite&amp;lang=de</code></pre>\r\n\r\n<p>werden jetzt saubere Pfade genutzt, zum Beispiel:</p>\r\n\r\n<pre>\r\n<code>/de/seite</code></pre>\r\n\r\n<p>Das bringt folgende Vorteile:</p>\r\n\r\n<ul>\r\n  <li><strong>Bessere SEO:</strong> Suchmaschinen erkennen die Sprachversionen als eigenständige Seiten, was Duplicate Content verhindert und das Ranking verbessert.</li>\r\n  <li><strong>Höhere Nutzerfreundlichkeit:</strong> Klar strukturierte und sprechende URLs sind für Besucher einfacher zu verstehen und zu merken.</li>\r\n <li><strong>Flexible Nutzung:</strong> Das System unterstützt weiterhin sowohl die klassische als auch die neue URL-Variante, sodass beide Formate parallel verwendet und nahtlos umgeschaltet werden können.</li>\r\n</ul>\r\n\r\n<p>Zusätzlich wurde die Sitemap-Funktion erweitert: Mit einem Mausklick lässt sich jetzt eine vollständige Sitemap generieren, die alle Seiten in allen Sprachen auflistet. Diese Sitemap erleichtert Suchmaschinen die effiziente Erfassung und Indexierung des gesamten mehrsprachigen Seitenumfangs – und steigert so die Sichtbarkeit Ihrer Website.</p>\r\n\r\n<div class=\"alert alert-info mt-4\" role=\"alert\">\r\n<h5 class=\"alert-heading\">Jetzt Nexpell ausprobieren</h5>\r\n\r\n<p>Ladet euch den aktuellen <strong>Nexpell Installer</strong> im Downloadbereich unserer Website herunter und startet direkt mit der Installation. Der Installer führt euch Schritt für Schritt durch die Einrichtung, sodass ihr schnell und unkompliziert euer eigenes CMS mit den neuesten Features starten könnt.</p>\r\n\r\n<p><strong>Hinweis:</strong> Nexpell befindet sich derzeit noch in der Beta-Phase. Deshalb bieten wir aktuell kein direktes Update von älteren Versionen an. Wir empfehlen eine frische Installation, um alle neuen Funktionen voll nutzen zu können.</p>\r\n\r\n<p>Wir freuen uns auf euer Feedback und eure Ideen, um Nexpell kontinuierlich weiterzuentwickeln und bald auch ein Update-System zu integrieren.</p>\r\n</div>\r\n', 'index.php?site=downloads', '4.png', 0, UNIX_TIMESTAMP(NOW()), 1, 1, 0, 298, 0),
(5, 2, 'Neues Such-Plugin für Nexpell veröffentlicht', 'such-plugin', '<p data-end=\"496\" data-start=\"414\">Ab sofort steht für das <strong data-end=\"453\" data-start=\"438\">Nexpell CMS</strong> ein neues <strong data-end=\"479\" data-start=\"464\">Such-Plugin</strong> zur Verfügung.</p>\r\n\r\n<p data-end=\"634\" data-start=\"503\">Mit diesem Plugin können Besucher Inhalte eurer Webseite schnell und komfortabel durchsuchen – egal ob News, Seiten oder Plugins.</p>\r\n\r\n<p data-end=\"670\" data-start=\"641\"><strong data-end=\"668\" data-start=\"641\">Highlights des Plugins:</strong></p>\r\n\r\n<ul data-end=\"835\" data-start=\"673\">\r\n <li data-end=\"699\" data-start=\"673\">\r\n  <p data-end=\"699\" data-start=\"675\">Schnelle Volltextsuche</p>\r\n </li>\r\n <li data-end=\"751\" data-start=\"702\">\r\n  <p data-end=\"751\" data-start=\"704\">Übersichtliche Ergebnisliste mit Hervorhebung</p>\r\n  </li>\r\n <li data-end=\"787\" data-start=\"754\">\r\n  <p data-end=\"787\" data-start=\"756\">Mehrsprachigkeit (DE, EN, IT)</p>\r\n  </li>\r\n <li data-end=\"831\" data-start=\"790\">\r\n  <p data-end=\"831\" data-start=\"792\">SEO-optimiert für bessere Indexierung</p>\r\n  </li>\r\n</ul>\r\n\r\n<p data-end=\"943\" data-start=\"838\">Damit wird die Navigation auf eurer Clan- oder Community-Seite noch einfacher und benutzerfreundlicher.</p>\r\n\r\n<p data-end=\"1021\" data-start=\"950\">👉 Das Plugin ist ab sofort im <strong data-end=\"1008\" data-start=\"981\">Nexpell Plugin-Installer</strong> verfügbar.</p>\r\n', '', '5.png', 0, 1755635836, 1, 1, 1, 172, 1),
(6, 2, 'Neues Plugin verfügbar: Achievements', 'achievements', '<p data-end=\"249\" data-start=\"138\">Wir freuen uns, ein weiteres Highlight für unser System vorstellen zu dürfen: das <strong data-end=\"243\" data-start=\"220\">Achievements-Plugin</strong>! 🎉</p>\r\n\r\n<p data-end=\"531\" data-start=\"251\">Mit diesem Plugin erhält eure Community ein modernes <strong data-end=\"337\" data-start=\"304\">Erfolge- und Belohnungssystem</strong>. Mitglieder können durch verschiedene Aktivitäten – wie das Erstellen von Beiträgen, regelmäßige Logins oder das Erreichen besonderer Meilensteine – automatisch <strong data-end=\"515\" data-start=\"499\">Achievements</strong> freischalten.</p>\r\n\r\n<h4 data-end=\"578\" data-start=\"533\">Die wichtigsten Features im Überblick:</h4>\r\n\r\n<ul data-end=\"1019\" data-start=\"579\">\r\n <li data-end=\"673\" data-start=\"579\">\r\n  <p data-end=\"673\" data-start=\"581\">🏅 <strong data-end=\"608\" data-start=\"584\">Individuelle Erfolge</strong>: Frei definierbar mit eigenen Icons, Titeln und Beschreibungen</p>\r\n </li>\r\n <li data-end=\"745\" data-start=\"674\">\r\n  <p data-end=\"745\" data-start=\"676\">⚙️ <strong data-end=\"703\" data-start=\"679\">Automatische Vergabe</strong>: Erfolgt basierend auf Benutzeraktionen</p>\r\n </li>\r\n <li data-end=\"840\" data-start=\"746\">\r\n  <p data-end=\"840\" data-start=\"748\">👤 <strong data-end=\"773\" data-start=\"751\">Profil-Integration</strong>: Alle freigeschalteten Achievements sind im User-Profil sichtbar</p>\r\n </li>\r\n <li data-end=\"935\" data-start=\"841\">\r\n  <p data-end=\"935\" data-start=\"843\">🚀 <strong data-end=\"873\" data-start=\"846\">Motivation & Engagement</strong>: Mehr Aktivität und Interaktion durch spielerische Elemente</p>\r\n </li>\r\n <li data-end=\"1019\" data-start=\"936\">\r\n <p data-end=\"1019\" data-start=\"938\">🔧 <strong data-end=\"956\" data-start=\"941\">Erweiterbar</strong>: Neue Erfolge können jederzeit ergänzt oder angepasst werden</p>\r\n </li>\r\n</ul>\r\n\r\n<p data-end=\"1201\" data-start=\"1021\">Das <strong data-end=\"1048\" data-start=\"1025\">Achievements-Plugin</strong> eignet sich ideal für <strong data-end=\"1107\" data-start=\"1071\">Communities, Gaming-Clans, Foren</strong> und alle Plattformen, die ihre Nutzer mit einem <strong data-end=\"1179\" data-start=\"1156\">Gamification-Ansatz</strong> begeistern möchten.</p>\r\n', '', '6.png', 0, UNIX_TIMESTAMP(NOW()), 1, 1, 1, 163, 1)
");*/



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