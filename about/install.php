<?php
safe_query("CREATE TABLE IF NOT EXISTS plugins_about (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title TEXT NOT NULL,
  intro TEXT NOT NULL,
  history TEXT NOT NULL,
  core_values TEXT NOT NULL,
  team TEXT NOT NULL,
  cta TEXT NOT NULL,
  image1 VARCHAR(255) NOT NULL DEFAULT '',
  image2 VARCHAR(255) NOT NULL DEFAULT '',
  image3 VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");

safe_query("INSERT IGNORE INTO plugins_about (title, intro, history, core_values, team, cta, image1, image2, image3) VALUES
    (
        '[[lang:de]]Über uns[[lang:en]]About us[[lang:it]]Chi siamo',
        
        '[[lang:de]]Willkommen auf unserer Website! Wir freuen uns, dir hier einen Einblick in das geben zu können, was hinter Webspell-RM-RM 3.0 steckt.
        [[lang:en]]Welcome to our website! We\'re happy to give you an insight into what Webspell-RM-RM 3.0 is all about.
        [[lang:it]]Benvenuto sul nostro sito web! Siamo felici di darti una panoramica di ciò che c\'è dietro Webspell-RM-RM 3.0.',
        
        '[[lang:de]]Webspell-RM war schon immer ein beliebtes und zuverlässiges System für Clan- und Community-Webseiten. Die ersten Arbeiten an der Plattform begannen bereits 2018 mit dem Ziel, die Benutzerfreundlichkeit deutlich zu verbessern, die Leistung zu steigern und die Flexibilität für verschiedenste Einsatzzwecke zu erhöhen. Über die Jahre wurde das CMS stetig weiterentwickelt und optimiert.<br>Mit der Version 2.1.6 entschieden wir uns bewusst, einen neuen Weg einzuschlagen und die Weichen für eine grundlegende Neugestaltung zu stellen. Ziel war es, die bestehende Basis zu modernisieren, aktuelle Webtechnologien zu integrieren und ein zeitgemäßes, responsives Design zu bieten, das auf allen Geräten optimal funktioniert.<br>Daraus entstand Webspell-RM-RM 3.0 – eine komplett neu strukturierte und erweiterte Version des CMS, die mit vielen neuen Features aufwartet. Neben verbesserter Performance und Skalierbarkeit stehen vor allem die Nutzerfreundlichkeit, einfache Erweiterbarkeit und vor allem die Sicherheit an erster Stelle. Webspell-RM-RM 3.0 legt großen Wert darauf, den Schutz der Daten und den sicheren Betrieb der Plattform jederzeit zu gewährleisten. Damit wollen wir sowohl Entwickler als auch Community-Manager bestmöglich unterstützen, um ihre Projekte flexibel, zukunftssicher und sicher umzusetzen.
        [[lang:en]]Webspell-RM has always been a popular and reliable system for clan and community websites. The initial development began in 2018 with the aim of significantly improving usability, boosting performance, and increasing flexibility for various use cases. Over the years, the CMS has been continuously developed and optimized.<br>With version 2.1.6, we consciously decided to take a new path and set the course for a fundamental redesign. The goal was to modernize the existing base, integrate current web technologies, and offer a contemporary, responsive design that works optimally on all devices.<br>This resulted in Webspell-RM-RM 3.0 – a completely restructured and extended version of the CMS that comes with many new features. In addition to improved performance and scalability, usability, easy extensibility, and especially security are top priorities. Webspell-RM-RM 3.0 places great emphasis on protecting data and ensuring the platform\'s secure operation at all times. We aim to support both developers and community managers in implementing their projects flexibly, future-proof, and securely.
        [[lang:it]]Webspell-RM è sempre stato un sistema popolare e affidabile per siti web di clan e community. Lo sviluppo iniziale è iniziato nel 2018 con l\'obiettivo di migliorare significativamente l\'usabilità, aumentare le prestazioni e aumentare la flessibilità per vari casi d\'uso. Nel corso degli anni, il CMS è stato continuamente sviluppato e ottimizzato.<br>Con la versione 2.1.6, abbiamo deciso consapevolmente di intraprendere una nuova strada e di impostare il corso per una riprogettazione fondamentale. L\'obiettivo era modernizzare la base esistente, integrare le tecnologie web attuali e offrire un design contemporaneo e reattivo che funzioni in modo ottimale su tutti i dispositivi.<br>Ne è nato Webspell-RM-RM 3.0 – una versione completamente ristrutturata ed estesa del CMS che offre molte nuove funzionalità. Oltre a prestazioni e scalabilità migliorate, facilità d\'uso, facile estensibilità e soprattutto sicurezza sono le massime priorità. Webspell-RM-RM 3.0 pone grande enfasi sulla protezione dei dati e sull\'assicurare il funzionamento sicuro della piattaforma in ogni momento. Vogliamo supportare sia gli sviluppatori che i gestori delle community nell\'implementare i loro progetti in modo flessibile, a prova di futuro e sicuro.',
        
        '[[lang:de]]Wir glauben an Open Source, Transparenz und eine starke Community. Unser Ziel ist es, Entwicklern und Community-Managern ein System zur Verfügung zu stellen, das leicht zu bedienen, flexibel und zukunftssicher ist.
        [[lang:en]]We believe in open source, transparency, and a strong community. Our goal is to provide developers and community managers with a system that is easy to use, flexible, and future-proof.
        [[lang:it]]Crediamo nell\'open source, nella trasparenza e in una community forte. Il nostro obiettivo è fornire agli sviluppatori e ai gestori delle community un sistema facile da usare, flessibile e a prova di futuro.',
        
        '[[lang:de]]Hinter Webspell-RM-RM steht ein kleines, engagiertes Team aus freiwilligen Entwicklern, Designern und Testern. Uns vereint die Leidenschaft für Webentwicklung, Gaming-Communities und gutes Code-Design.
        [[lang:en]]Behind Webspell-RM-RM is a small, dedicated team of volunteer developers, designers, and testers. We are united by our passion for web development, gaming communities, and clean code design.
        [[lang:it]]Dietro Webspell-RM-RM c\'è un piccolo team impegnato di sviluppatori, designer e tester volontari. Ci unisce la passione per lo sviluppo web, le community di gaming e un codice ben strutturato.',
        
        '[[lang:de]]Du möchtest mithelfen, Feedback geben oder ein Plugin beisteuern? Dann kontaktiere uns oder schau auf GitHub vorbei – wir freuen uns über jede Unterstützung!
        [[lang:en]]Want to contribute, give feedback, or create a plugin? Get in touch or visit us on GitHub – we appreciate every contribution!
        [[lang:it]]Vuoi contribuire, inviare un feedback o sviluppare un plugin? Contattaci o visita il nostro GitHub – ogni aiuto è benvenuto!', 'intro.jpg', 'history.jpg', 'team.jpg')");



#######################################################################################################################################
safe_query("CREATE TABLE IF NOT EXISTS plugins_about_settings_widgets (
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

safe_query("INSERT IGNORE INTO plugins_about_settings_widgets (id, position, modulname, themes_modulname, widgetname, widgetdatei, activated, sort) VALUES
('1', 'navigation_widget', 'navigation', 'default', 'Navigation', 'widget_navigation', 1, 1),
('2', 'footer_widget', 'footer_easy', 'default', 'Footer Easy', 'widget_footer_easy', 1, 1)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'About', 'about', '[[lang:de]]Dieses Widget zeigt allgemeine Informationen (kleiner Lebenslauf) über Sie auf Ihrer Webspell-RM-RM-Seite an.[[lang:en]]This widget will show general information (small resume) About You on your Webspell-RM-RM site.[[lang:it]]Questo widget mostrerà informazioni generali (piccolo curriculum) su di te sul tuo sito Webspell-RM-RM.', 'admin_about', 1, 'T-Seven', 'https://Webspell-RM-rm.de', 'about', '', '0.1', 'includes/plugins/about/', 1, 1, 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 5, '[[lang:de]]About[[lang:en]]About[[lang:it]]About', 'about', 'admincenter.php?site=admin_about', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 2, '[[lang:de]]About[[lang:en]]About[[lang:it]]About', 'about', 'index.php?site=about', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'about', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'about' LIMIT 1
  ))
");
 ?>