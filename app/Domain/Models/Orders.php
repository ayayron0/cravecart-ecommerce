<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class Orders
{
    public static function getAll(): array
    {
        return R::findAll('orders', ' ORDER BY ordered_at DESC, id DESC ');
    }

    public static function findById(int $id): ?OODBBean
    {
        $order = R::load('orders', $id);
        return $order->id == 0 ? null : $order;
    }

    public static function findByUserId(int $userId): array
    {
        return R::findAll('orders', ' user_id = ? ORDER BY ordered_at DESC, id DESC ', [$userId]);
    }

    // Get all orders with user and address details (JOIN)
    public static function findAllDetailed(): array
    {
        return R::getAll(
            'SELECT
                o.id,
                o.user_id,
                o.address_id,
                o.subtotal,
                o.taxes,
                o.total,
                o.status,
                o.notes,
                o.ordered_at,
                u.name AS customer_name,
                u.email AS customer_email,
                da.street,
                da.city,
                da.postal_code
             FROM orders o
             INNER JOIN users u ON o.user_id = u.id
             INNER JOIN delivery_address da ON o.address_id = da.id
             ORDER BY o.ordered_at DESC, o.id DESC'
        );
    }

    //validates user, address, and totals)
    public static function create(
        int $userId,
        int $addressId,
        float $subtotal,
        float $taxes,
        float $total,
        string $status = 'pending',
        ?string $notes = null
    ): int {
        $status = trim($status);

        if (
            $userId <= 0 ||
            $addressId <= 0 ||
            $subtotal < 0 ||
            $taxes < 0 ||
            $total < 0
        ) {
            return 0;
        }

        $user = Users::findById($userId);
        $address = DeliveryAddress::findById($addressId);

        if ($user === null || $address === null) {
            return 0;
        }

        if ((int) $address->user_id !== $userId) {
            return 0;
        }

        $order = R::dispense('orders');
        $order->user_id = $userId;
        $order->address_id = $addressId;
        $order->subtotal = $subtotal;
        $order->taxes = $taxes;
        $order->total = $total;
        $order->status = $status === '' ? 'pending' : $status;
        $order->notes = self::nullIfEmpty($notes);

        return (int) R::store($order);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $order = R::load('orders', $id);
        if ($order->id == 0) {
            return false;
        }

        $status = trim($status);
        if ($status === '') {
            return false;
        }

        $order->status = $status;
        return (int) R::store($order) > 0;
    }

    public static function updateNotes(int $id, ?string $notes): bool
    {
        $order = R::load('orders', $id);
        if ($order->id == 0) {
            return false;
        }

        $order->notes = self::nullIfEmpty($notes);
        return (int) R::store($order) > 0;
    }

    public static function delete(int $id): bool
    {
        $order = R::load('orders', $id);
        if ($order->id == 0) {
            return false;
        }

        R::trash($order);
        return true;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
