<?php
## CREATE TABLES #################################################################

safe_query("CREATE TABLE IF NOT EXISTS plugins_achievements (
  id int(11) NOT NULL AUTO_INCREMENT,
  category_id int(11) NOT NULL DEFAULT 0,
  name varchar(255) NOT NULL,
  description text NOT NULL,
  type enum('level','points','role','activity_count','category_points','registration_time','bonus_points','manual') NOT NULL DEFAULT 'level',
  trigger_value varchar(255) NOT NULL,
  trigger_condition varchar(255) DEFAULT NULL,
  image varchar(255) NOT NULL,
  is_standalone tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  show_in_overview tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = wird angezeigt, 0 = wird verborgen',
  allow_html tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4;");

safe_query("CREATE TABLE IF NOT EXISTS plugins_achievements_categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4;");

safe_query("CREATE TABLE IF NOT EXISTS plugins_achievements_admin_log (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  admin_id int(11) NOT NULL,
  log_type enum('manual_award','bonus_points') NOT NULL,
  related_id int(11) DEFAULT NULL COMMENT 'Für achievement_id bei manual_award',
  value int(11) DEFAULT NULL COMMENT 'Für die Punktzahl bei bonus_points',
  timestamp datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4;");

safe_query("CREATE TABLE IF NOT EXISTS plugins_achievements_settings (
  setting_key varchar(255) NOT NULL DEFAULT '',
  setting_value text NOT NULL,
  PRIMARY KEY (setting_key)
) DEFAULT CHARSET=utf8mb4;");

## INSERT DEFAULT ACHIEVEMENTS ##################################################

safe_query("INSERT INTO `plugins_achievements` (`id`, `category_id`, `name`, `description`, `type`, `trigger_value`, `trigger_condition`, `image`, `is_standalone`, `sort_order`, `show_in_overview`, `allow_html`) VALUES
(5, 0, 'Schreiber', 'Du hast deinen ersten Artikel geschrieben', 'activity_count', '1', 'Artikel', 'schreiber.png', 0, 0, 1, 0),
(7, 0, 'Punkteregen', 'Ein Admin hat es gut mit dir gemeint und dir {points} Punkte geschenkt.', 'bonus_points', '1', '', 'punkteregen.png', 0, 0, 0, 0),
(12, 3, 'Silber', 'Silber', 'points', '500', '', 'silber.png', 0, 0, 1, 0),
(13, 3, 'Bronze', 'Bronze', 'points', '300', '', 'bronze.png', 0, 0, 1, 0),
(14, 0, 'Gold', 'Gold', 'points', '1200', '', 'gold.png', 0, 0, 1, 0),
(15, 3, 'Diamand', 'Diamand', 'points', '3000', '', 'diamand.png', 0, 0, 1, 0),
(16, 3, 'Daumen', 'Daumen hoch', 'activity_count', '5', 'Likes', 'daumen.png', 0, 0, 1, 0),
(17, 3, 'Flash', 'flash', 'level', '99', '', 'flash.png', 0, 0, 1, 0),
(18, 3, 'Diamond2', 'Diamond2', 'points', '9000', '', 'diamond2.png', 0, 0, 1, 0),
(19, 3, 'Comments', 'Comments', 'activity_count', '15', 'Kommentare', 'comments.png', 0, 0, 1, 0),
(20, 0, 'Forum', 'Forum', 'activity_count', '80', 'Forum', 'forum.png', 0, 0, 1, 0),
(22, 0, 'Admin', '{clan_name} Admin', 'role', 'Admin', '', 'admin.png', 1, 0, 1, 0),
(23, 4, 'Moderator', 'Moderator', 'role', 'Moderator', '', 'moderator.png', 0, 0, 1, 0),
(24, 3, 'Collector', 'Collector', 'points', '20000', '', 'collector.png', 1, 0, 1, 0),
(25, 2, 'Treasure', 'Treasure', 'level', '80', '', 'treasure.png', 1, 0, 1, 0),
(28, 0, 'VIP', 'VIP User', 'manual', '', '', 'vip.png', 0, 0, 0, 0),
(29, 3, 'Downloads', 'Downloads', 'activity_count', '15', 'Downloads', 'downloads.png', 0, 0, 1, 0),
(30, 3, 'Login', 'Login', 'activity_count', '50', 'Logins', 'login.png', 0, 0, 1, 0),
(31, 3, 'Links', 'Links', 'activity_count', '9', 'Links', 'links.png', 0, 0, 1, 0),
(32, 2, 'Registrierungszeit', 'Registrierungszeit', 'registration_time', '10', 'days', 'registrierungszeit.png', 0, 0, 1, 0),
(33, 3, 'Clan Regeln', 'Clan Regeln', 'activity_count', '18', 'Clan-Regeln', 'clan_regeln.png', 0, 0, 1, 0),
(34, 4, 'Designer', 'Designer', 'role', 'Designer', '', 'designer.png', 0, 0, 1, 0);");

safe_query("INSERT IGNORE INTO plugins_achievements_admin_log (id, user_id, admin_id, log_type, related_id, value, timestamp) VALUES
(4, 2, 1, 'manual_award', 28, NULL, '2025-08-18 13:03:59'),
(12, 3, 2, 'manual_award', 28, NULL, '2025-08-18 13:33:52'),
(15, 2, 1, 'bonus_points', NULL, 999, '2025-08-19 17:30:00'),
(16, 2, 1, 'manual_award', 24, NULL, '2025-08-19 17:33:52');");

safe_query("INSERT IGNORE INTO plugins_achievements_categories (id, name, description) VALUES
(2, 'Level', 'Hier kommen die Level Achievements rein'),
(3, 'Punkte', 'Hier kommen die Punkte Achievements rein'),
(4, 'Rollen', 'Achievements zu Rollen');");

safe_query("INSERT IGNORE INTO plugins_achievements_settings (setting_key, setting_value) VALUES
('admin_bonus_award_limit', '1'),
('custom_locked_icon', 'locked.png'),
('hide_locked_icon', 'no'),
('max_bonus_points', '2000'),
('points_per_level', '100'),
('weight_Artikel', '10'),
('weight_Clan-Regeln', '5'),
('weight_Downloads', '2'),
('weight_Forum', '2'),
('weight_Kommentare', '2'),
('weight_Likes', '2'),
('weight_Links', '5'),
('weight_Logins', '2'),
('weight_Partners', '5'),
('weight_Sponsoren', '5');");

## PLUGIN SYSTEM SETTINGS #######################################################

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Achievements', 'achievements', '[[lang:de]]Dies ist eine Testbeschreibung vom Achievements Plugin[[lang:en]]This is the english description', 'admin_achievements', 1, 'Fjolnd', 'https://www.nexpell.de', 'achievements', '', '1.0', 'includes/plugins/achievements/', 1, 1, 1, 1, 'activated');");

## NAVIGATION ###################################################################

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 3, '[[lang:de]]Errungenschaften[[lang:en]]Achievements', 'achievements', 'admincenter.php?site=admin_achievements', 1);");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Errungenschaften[[lang:en]]Achievements', 'achievements', 'index.php?site=achievements', 1, 1);");

## ADMIN NAVIGATION RIGHTS ######################################################

safe_query("INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname) VALUES
('', 1, 'link', 'achievements');");

?>
