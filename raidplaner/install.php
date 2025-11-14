<?php
// Raidplaner – Install
// - Defaults (Rollen, Klassen, Settings)

/* =============================
 *  TABLES
 * ============================= */

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_roles (
  id INT(11) NOT NULL AUTO_INCREMENT,
  role_name VARCHAR(100) NOT NULL,
  icon VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_classes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  class_name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_templates (
  id INT(11) NOT NULL AUTO_INCREMENT,
  template_name VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  duration_minutes INT(11) NOT NULL DEFAULT 180,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_template_setup (
  template_id INT(11) NOT NULL,
  role_id INT(11) NOT NULL,
  needed_count INT(11) NOT NULL,
  PRIMARY KEY (template_id, role_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_bosses (
  id INT(11) NOT NULL AUTO_INCREMENT,
  boss_name VARCHAR(255) NOT NULL,
  sort_index INT(11) NOT NULL DEFAULT 0,
  tactics TEXT NULL,
  raid_id INT(11) NOT NULL DEFAULT 0,
  template_id INT(11) DEFAULT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_template_bosses (
  template_id INT(11) NOT NULL,
  boss_id INT(11) NOT NULL,
  PRIMARY KEY (template_id, boss_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_events (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  event_time DATETIME NOT NULL,
  duration_minutes INT(11) DEFAULT 180,
  template_id INT(11) DEFAULT NULL,
  created_by_user_id INT(11) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  discord_message_id VARCHAR(30) DEFAULT NULL,
  duration INT(11) DEFAULT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_event_bosses (
  event_id INT(11) NOT NULL,
  boss_id INT(11) NOT NULL,
  PRIMARY KEY (event_id, boss_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_event_setup (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  role_id INT(11) NOT NULL,
  count INT(11) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_role_unique (event_id, role_id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_setup (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  role_id INT(11) NOT NULL,
  needed_count INT(11) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_role_unique (event_id, role_id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_items (
  id INT(11) NOT NULL AUTO_INCREMENT,
  item_name VARCHAR(255) NOT NULL,
  source VARCHAR(255) DEFAULT NULL,
  boss_id INT(11) DEFAULT NULL,
  boss_name VARCHAR(255) DEFAULT NULL,
  raid_name VARCHAR(255) DEFAULT NULL,
  slot VARCHAR(100) DEFAULT NULL,
  class_spec VARCHAR(100) DEFAULT NULL,
  is_bis TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_bis_list (
  id INT(11) NOT NULL AUTO_INCREMENT,
  class_id INT(11) NOT NULL,
  item_id INT(11) NOT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_characters (
  id INT(11) NOT NULL AUTO_INCREMENT,
  userID INT(11) NOT NULL,
  character_name VARCHAR(255) NOT NULL,
  class_id INT(11) NOT NULL,
  level INT(11) DEFAULT NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_character_gear (
  id INT(11) NOT NULL AUTO_INCREMENT,
  character_id INT(11) NOT NULL,
  item_id INT(11) NOT NULL,
  is_obtained TINYINT(1) NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_changed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_char_item (character_id, item_id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_signups (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  character_id INT(11) NOT NULL,
  role_id INT(11) NOT NULL,
  status ENUM('Angemeldet','Ersatzbank','Abgemeldet') NOT NULL,
  comment VARCHAR(255) DEFAULT NULL,
  signup_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY event_character_unique (event_id, character_id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_participants (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  userID INT(11) NOT NULL,
  character_id INT(11) NOT NULL,
  role_id INT(11) NOT NULL,
  signup_status ENUM('Angemeldet','Ersatzbank','Abgemeldet') NOT NULL,
  attendance_status ENUM('Anwesend','Abwesend') DEFAULT NULL,
  comment VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_user_unique (event_id, userID)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_loot_history (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  boss_id INT(11) DEFAULT NULL,
  item_id INT(11) NOT NULL,
  character_id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  original_wish_status INT(1) DEFAULT NULL,
  assigned_by INT(11) DEFAULT NULL,
  looted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_item_char (event_id, item_id, character_id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_loot_distributed (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  item_id INT(11) NOT NULL,
  userID INT(11) NOT NULL,
  character_id INT(11) NOT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_wishlists (
  id INT(11) NOT NULL AUTO_INCREMENT,
  participant_id INT(11) NOT NULL,
  item_id INT(11) NOT NULL,
  assigned_by INT(11) DEFAULT NULL,
  assigned_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_attendance (
  id INT(11) NOT NULL AUTO_INCREMENT,
  event_id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  character_id INT(11) NOT NULL,
  status ENUM('Anwesend','Ersatzbank','Abwesend','Verspätet') NOT NULL,
  PRIMARY KEY (id)
) AUTO_INCREMENT=1
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

safe_query("CREATE TABLE IF NOT EXISTS plugins_raidplaner_settings (
  setting_key VARCHAR(50) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  PRIMARY KEY (setting_key)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* =============================
 *  TRIGGERS (Titel-Logik für Events)
 * ============================= */

safe_query("DROP TRIGGER IF EXISTS raidevents_bi");
safe_query("DROP TRIGGER IF EXISTS raidevents_bu");

safe_query("CREATE TRIGGER raidevents_bi BEFORE INSERT ON plugins_raidplaner_events FOR EACH ROW
BEGIN
  IF NEW.template_id IS NOT NULL THEN
    SET NEW.title = NULL;
  ELSE
    IF NEW.title IS NULL OR NEW.title = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Titel ist erforderlich, wenn keine Vorlage gewählt ist';
    END IF;
  END IF;
END");

safe_query("CREATE TRIGGER raidevents_bu BEFORE UPDATE ON plugins_raidplaner_events FOR EACH ROW
BEGIN
  IF NEW.template_id IS NOT NULL THEN
    SET NEW.title = NULL;
  ELSE
    IF NEW.title IS NULL OR NEW.title = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Titel ist erforderlich, wenn keine Vorlage gewählt ist';
    END IF;
  END IF;
END");

/* =============================
 *  DEFAULTS
 * ============================= */

safe_query("INSERT IGNORE INTO plugins_raidplaner_roles (id, role_name, icon) VALUES
(1, 'Healer', 'bi-heart-fill'),
(2, 'DD', 'bi-crosshair'),
(3, 'Tank', 'bi-shield-fill')");

safe_query("INSERT IGNORE INTO plugins_raidplaner_classes (id, class_name) VALUES
(1,'Krieger'),(2,'Magier'),(3,'Jäger'),(4,'Schurke'),(5,'Priester'),(6,'Hexenmeister'),
(7,'Paladin'),(8,'Druide'),(9,'Schamane'),(10,'Mönch'),(11,'Dämonenjäger'),(12,'Rufer'),(13,'Todesritter')");

safe_query("INSERT IGNORE INTO plugins_raidplaner_settings (setting_key, setting_value) VALUES
('discord_color_hex', '#C691AF'),
('discord_footer_text', 'Raidplaner'),
('discord_mention_role_id', ''),
('discord_ping_on_post', '0'),
('discord_show_date', '1'),
('discord_show_description', '1'),
('discord_show_roles', '1'),
('discord_show_signup', '1'),
('discord_show_time', '1'),
('discord_thumbnail_uploaded_url', ''),
('discord_thumbnail_url', ''),
('discord_title_prefix', '🗡️ '),
('discord_webhook_url', ''),
('manage_default_roles', '1')");

/* =============================
 *  DEMO (ein Template, zwei Bosse, ein Event => ID 1)
 * ============================= */

// Template (ID = 1)
safe_query("INSERT IGNORE INTO plugins_raidplaner_templates (id, template_name, title, description, duration_minutes) VALUES
(1, 'Demo Raid', 'Demo Raid', 'Demo Beschreibung', 180)");

// Bosse (IDs = 1,2)
safe_query("INSERT IGNORE INTO plugins_raidplaner_bosses (id, boss_name, sort_index, tactics) VALUES
(1, 'Demo Boss #1', 1, 'Demo Beschreibung'),
(2, 'Demo Boss #2', 2, 'Demo Beschreibung')");

// Boss-Zuordnung zur Vorlage
safe_query("INSERT IGNORE INTO plugins_raidplaner_template_bosses (template_id, boss_id) VALUES
(1,1),(1,2)");

// Rollenbedarf laut Standardrollen
safe_query("INSERT IGNORE INTO plugins_raidplaner_template_setup (template_id, role_id, needed_count) VALUES
(1, 1, 4),  -- Healer
(1, 2, 10), -- DD
(1, 3, 2)   -- Tank
");

// Demo-Item
safe_query("INSERT IGNORE INTO plugins_raidplaner_items (id, item_name, source, boss_id, boss_name, raid_name, slot, is_bis) VALUES
(1, 'Demo Item', 'Drop: Demo Boss #1', 1, 'Demo Boss #1', 'Demo Raid', 'Demo Slot', 0)");

// Ein Demo-Event auf Basis des Templates 1 (Event-ID = 1)
safe_query("INSERT IGNORE INTO plugins_raidplaner_events (id, title, description, event_time, duration_minutes, template_id, created_by_user_id, is_active)
VALUES (1, NULL, 'Demo Beschreibung', DATE_ADD(NOW(), INTERVAL 7 DAY), 180, 1, 1, 1)");

// Bosse aus der Vorlage ins Event übernehmen
safe_query("INSERT IGNORE INTO plugins_raidplaner_event_bosses (event_id, boss_id) VALUES
(1,1),(1,2)");

// Setup aus der Vorlage ins Event übernehmen
safe_query("INSERT IGNORE INTO plugins_raidplaner_event_setup (event_id, role_id, count) VALUES
(1,1,4),(1,2,10),(1,3,2)");

safe_query("INSERT IGNORE INTO plugins_raidplaner_setup (event_id, role_id, needed_count) VALUES
(1,1,4),(1,2,10),(1,3,2)");

/* =============================
 *  SYSTEM-EINBINDUNG
 * ============================= */

safe_query("INSERT IGNORE INTO settings_plugins (pluginID, name, modulname, info, admin_file, activate, author, website, index_link, hiddenfiles, version, path, status_display, plugin_display, widget_display, delete_display, sidebar) VALUES
('', 'Raidplaner', 'raidplaner', '[[lang:de]]Der Raidplaner ist ein vielseitiges Plugin zur Organisation und Verwaltung von Gilden-Raids. Es ermöglicht das Erstellen von Raids mit Bossen, Rollenverteilung und Teilnehmeranmeldung inklusive Charakter- und Klassenverwaltung. Loot-, Anwesenheits- und BiS-Tracking unterstützen die Auswertung und Itemplanung. Über den Adminbereich können Bosse, Templates und Einstellungen komfortabel gepflegt werden. Optional ist eine Discord-Anbindung für automatische Raidankündigungen integriert.[[lang:en]]The raid planner is a versatile plugin for organizing and managing guild raids. It allows you to create raids with bosses, assign roles, and handle participant sign-ups, including character and class management. Loot, attendance, and BiS tracking support analysis and item planning. Through the admin area, bosses, templates, and settings can be conveniently maintained. An optional Discord integration enables automatic raid announcements.', 'admin_raidplaner', 1, 'Fjolnd', 'https://www.nexpell.de', 'raidplaner', '', '1.0.0', 'includes/plugins/raidplaner/', 1, 1, 1, 1, 'deactivated')");

safe_query("INSERT IGNORE INTO settings_widgets (widget_key, title, plugin, modulname) VALUES
('widget_raidplaner_content', 'Raidplaner Widget Content', 'raidplaner', 'raidplaner'),
('widget_raidplaner_sidebar', 'Raidplaner Widget Sidebar', 'raidplaner', 'raidplaner')");

safe_query("INSERT IGNORE INTO navigation_dashboard_links (linkID, catID, name, modulname, url, sort) VALUES
('', 8, '[[lang:de]]Raidplaner[[lang:en]]Raid planner', 'raidplaner', 'admincenter.php?site=admin_raidplaner', 1)");

safe_query("INSERT IGNORE INTO navigation_website_sub (snavID, mnavID, name, modulname, url, sort, indropdown) VALUES
('', 3, '[[lang:de]]Raids[[lang:en]]Raids', 'raidplaner', 'index.php?site=raidplaner', 1, 1)");

safe_query("INSERT IGNORE INTO user_role_admin_navi_rights (id, roleID, type, modulname)
VALUES ('', 1, 'link', 'raidplaner')");
?>
