<?php
/**
 * store.php — fake user storage for the demo (JSON file).
 * In a real project, replace this with a query against your own database.
 */

declare(strict_types=1);

final class UserStore
{
    private static string $path = __DIR__ . '/users.json';

    public static function all(): array
    {
        if (!file_exists(self::$path)) {
            return [];
        }
        return json_decode(file_get_contents(self::$path), true) ?: [];
    }

    public static function find(string $email): ?array
    {
        foreach (self::all() as $u) {
            if ($u['email'] === $email) return $u;
        }
        return null;
    }

    public static function save(array $user): void
    {
        $users = self::all();
        $found = false;
        foreach ($users as $i => $u) {
            if ($u['email'] === $user['email']) {
                $users[$i] = $user;
                $found = true;
                break;
            }
        }
        if (!$found) $users[] = $user;
        file_put_contents(self::$path, json_encode($users, JSON_PRETTY_PRINT));
    }
}
