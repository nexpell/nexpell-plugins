<?php

safe_query("CREATE TABLE IF NOT EXISTS `plugins_forum_categories` (
  `catID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `position` INT DEFAULT 0
)");

safe_query("INSERT IGNORE INTO `plugins_forum_categories` (`catID`, `title`, `description`, `position`) VALUES
(1, 'Allgemeines', 'Diskussionen rund um das Thema allgemein', 1),
(2, 'Support', 'Hilfe und Fragen zum Forum', 2),
(3, 'Off-Topic', 'Alles was sonst nirgends reinpasst', 3)
");

safe_query("CREATE TABLE IF NOT EXISTS `plugins_forum_threads` (
  `threadID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `catID` INT UNSIGNED NOT NULL,
  `userID` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `created_at` INT NOT NULL,
  `updated_at` INT NOT NULL,
  `views` INT DEFAULT 0,
  `is_locked` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`catID`) REFERENCES `plugins_forum_categories`(`catID`) ON DELETE CASCADE
)");

safe_query("INSERT IGNORE INTO `plugins_forum_threads` (`catID`, `userID`, `title`, `created_at`, `updated_at`, `views`, `is_locked`) VALUES
(1, 1, 'Willkommen im Forum!', 1748807683, 1750444361, 0, 0),
(2, 1, 'Forum funktioniert nicht richtig', 1748980483, 1749672936, 0, 0),
(3, 1, 'Was macht ihr am Wochenende?', 1749066883, 1749672963, 0, 0)");

safe_query("CREATE TABLE IF NOT EXISTS `plugins_forum_posts` (
  `postID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `threadID` INT UNSIGNED NOT NULL,
  `userID` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` INT NOT NULL,
  `edited_at` INT DEFAULT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`threadID`) REFERENCES `plugins_forum_threads`(`threadID`) ON DELETE CASCADE
)");

safe_query("CREATE TABLE IF NOT EXISTS `plugins_forum_moderators` (
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `category_id`)
)");

safe_query("CREATE TABLE IF NOT EXISTS `forum_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `userID` INT NOT NULL,
  `threadID` INT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created` DATETIME DEFAULT CURRENT_TIMESTAMP
)");

## SYSTEM #####################################################################################################################################

safe_query("INSERT IGNORE INTO settings_plugins (name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, plugin_display, widget_display, sidebar) VALUES
('Forum', 'forum', '[[lang:de]]Mit diesem Plugin könnt ihr ein Forum auf eurer Website integrieren, in dem Benutzer Themen erstellen und diskutieren können.[[lang:en]]With this plugin you can integrate a forum into your website where users can create topics and participate in discussions.[[lang:it]]Con questo plugin puoi integrare un forum nel tuo sito web, dove gli utenti possono creare argomenti e partecipare alle discussioni.', 'admin_forum', 1, 'T-Seven', 'https://webspell-rm.de', 'forum', '', '0.1', 'includes/plugins/forum/', 1, 1, 'deactivated')");

## NAVIGATION #####################################################################################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (catID, name, modulname, url, sort) VALUES
(8, '[[lang:de]]Forum[[lang:en]]Forum[[lang:it]]Forum', 'forum', 'admincenter.php?site=admin_forum', 1)");


safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown, themes_modulname) VALUES
('', 3, '[[lang:de]]Forum[[lang:en]]Forum[[lang:it]]Forum', 'forum', 'index.php?site=forum', 1, 1, 'default')");

#######################################################################################################################################
safe_query("
  INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname, accessID)
  VALUES ('', 1, 'link', 'forum', (
    SELECT linkID FROM navigation_dashboard_links WHERE modulname = 'forum' LIMIT 1
  ))
");
  
 ?>