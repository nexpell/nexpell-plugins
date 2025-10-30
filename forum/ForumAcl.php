<?php
/**
 * Nexpell Forum ACL Helper
 * Pfad: /includes/plugins/forum/ForumAcl.php
 * Verwaltet Lese-/Schreibrechte je Forum anhand der Tabelle plugins_forum_acl.
 */

declare(strict_types=1);

final class ForumAcl
{
    private static ?mysqli $db = null;

    /** Bootstrap DB (mit robustem Config-Finder) */
    private static function db(): mysqli
    {
        if (self::$db) return self::$db;

        // BASE_PATH bis zum Projekt-Root
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 3)); // /includes/plugins/forum -> 3x hoch -> /
        }

        // Mögliche Config-Dateien suchen
        $candidates = [
            BASE_PATH . '/system/config.inc.php',
            BASE_PATH . '/system/config.php',
        ];
        $found = null;
        foreach ($candidates as $cfg) {
            if (is_file($cfg)) {
                $found = $cfg;
                break;
            }
        }

        if (!$found) {
            throw new RuntimeException('Config nicht gefunden. Versucht: ' . implode(', ', $candidates));
        }
        require_once $found;

        self::$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (self::$db->connect_errno) {
            throw new RuntimeException('DB-Verbindungsfehler: ' . self::$db->connect_error);
        }
        self::$db->set_charset('utf8mb4');

        return self::$db;
    }

    /**
     * Liefert Gruppenschlüssel (guest, logged_in, role:xyz)
     */
    public static function userGroupKeys(?int $userId): array
    {
        $keys = ['guest'];

        if ($userId && $userId > 0) {
            $keys[] = 'logged_in';
            // Rollen (optional)
            if (function_exists('nx_user_roles')) {
                foreach (nx_user_roles($userId) as $rk) {
                    $keys[] = 'role:' . $rk;
                }
            } else {
                // Fallback: "role:member"
                $keys[] = 'role:member';
            }
        }

        return array_unique($keys);
    }

    private static function fallback(?int $userId, string $perm): bool
    {
        return match ($perm) {
            'view', 'read' => true,
            'post', 'reply' => ($userId && $userId > 0),
            'mod' => false,
            default => false,
        };
    }

    /**
     * Prüft, ob User Berechtigung hat.
     */
    public static function can(?int $userId, int $forumId, string $perm): bool
    {
        $col = match ($perm) {
            'view'  => 'can_view',
            'read'  => 'can_read',
            'post'  => 'can_post',
            'reply' => 'can_reply',
            'mod'   => 'is_mod',
            default => null,
        };
        if (!$col) return false;

        $keys = self::userGroupKeys($userId);
        if (empty($keys)) $keys = ['guest'];

        $in = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT MAX($col) AS allowed
                FROM plugins_forum_acl
                WHERE forum_id = ? AND group_key IN ($in)";
        $st = self::db()->prepare($sql);
        if (!$st) return self::fallback($userId, $perm);

        $types = 'i' . str_repeat('s', count($keys));
        $params = array_merge([$types, $forumId], $keys);
        $ref = [];
        foreach ($params as $i => $v) $ref[$i] = &$params[$i];
        call_user_func_array([$st, 'bind_param'], $ref);

        $st->execute();
        $st->bind_result($allowed);
        $has = $st->fetch();
        $st->close();

        return $has && $allowed !== null ? ((int)$allowed === 1) : self::fallback($userId, $perm);
    }

    /** Hart blockieren, wenn nicht erlaubt */
    public static function ensure(?int $userId, int $forumId, string $perm): void
    {
        if (!self::can($userId, $forumId, $perm)) {
            http_response_code(403);
            die('Zugriff verweigert.');
        }
    }

    /**
     * Liste erlaubter Foren (für Index)
     */
    public static function allowedForumIds(?int $userId, string $perm): array
    {
        $col = match ($perm) {
            'view'  => 'can_view',
            'read'  => 'can_read',
            'post'  => 'can_post',
            'reply' => 'can_reply',
            'mod'   => 'is_mod',
            default => null,
        };
        if (!$col) return [];

        $keys = self::userGroupKeys($userId);
        if (empty($keys)) $keys = ['guest'];

        $in = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT DISTINCT forum_id FROM plugins_forum_acl WHERE $col = 1 AND group_key IN ($in)";
        $st = self::db()->prepare($sql);
        if (!$st) return [];

        $types = str_repeat('s', count($keys));
        $params = array_merge([$types], $keys);
        $ref = [];
        foreach ($params as $i => $v) $ref[$i] = &$params[$i];
        call_user_func_array([$st, 'bind_param'], $ref);
        $st->execute();
        $res = $st->get_result();
        $allowed = [];
        while ($r = $res->fetch_assoc()) {
            $allowed[] = (int)$r['forum_id'];
        }
        $st->close();

        // Fallback-Foren (ohne ACL)
        $sql2 = "SELECT f.forumID FROM plugins_forum_forums f
                 LEFT JOIN (SELECT DISTINCT forum_id FROM plugins_forum_acl) a
                   ON a.forum_id = f.forumID
                 WHERE a.forum_id IS NULL";
        $r2 = self::db()->query($sql2);
        if ($r2) {
            while ($row = $r2->fetch_assoc()) {
                $fid = (int)$row['forumID'];
                if (!in_array($fid, $allowed, true) && self::fallback($userId, $perm)) {
                    $allowed[] = $fid;
                }
            }
        }

        return $allowed;
    }
}
