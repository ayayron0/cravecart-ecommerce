<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\R;

class Notifications
{
    public static function create(int $userId, string $message): int
    {
        $n = R::dispense('notifications');
        $n->user_id = $userId;
        $n->message = $message;
        $n->is_read = 0;
        return (int) R::store($n);
    }

    public static function findUnreadByUserId(int $userId): array
    {
        return R::getAll(
            'SELECT id, message, created_at FROM notifications
             WHERE user_id = ? AND is_read = 0
             ORDER BY created_at DESC',
            [$userId]
        );
    }

    public static function countUnreadByUserId(int $userId): int
    {
        return (int) R::getCell(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }

    public static function markAllReadByUserId(int $userId): void
    {
        R::exec(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }
}
