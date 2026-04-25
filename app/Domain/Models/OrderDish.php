<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class OrderDish
{
    public static function getAll(): array
    {
        return R::findAll('order_dish', ' ORDER BY id ASC ');
    }

    public static function findById(int $id): ?OODBBean
    {
        return R::findOne('order_dish', ' id = ? ', [$id]);
    }

    public static function findByOrderId(int $orderId): array
    {
        return R::findAll('order_dish', ' order_id = ? ORDER BY id ASC ', [$orderId]);
    }

    //get detailed order items with dish info using JOIN
    public static function findDetailedByOrderId(int $orderId): array
    {
        return R::getAll(
            'SELECT
                od.id,
                od.order_id,
                od.dish_id,
                od.quantity,
                od.item_price,
                d.name AS dish_name,
                d.description AS dish_description,
                d.image_url,
                d.availability
             FROM order_dish od
             INNER JOIN dishes d ON od.dish_id = d.id
             WHERE od.order_id = ?
             ORDER BY od.id ASC',
            [$orderId]
        );
    }

    // Create new order-dish item (validates order, dish, and values)
    public static function create(
        int $orderId,
        int $dishId,
        int $quantity,
        float $itemPrice
    ): int {
        if (
            $orderId <= 0 ||
            $dishId <= 0 ||
            $quantity <= 0 ||
            $itemPrice < 0
        ) {
            return 0;
        }

        if (Orders::findById($orderId) === null || Dishes::findById($dishId) === null) {
            return 0;
        }

        $inserted = R::exec(
            'INSERT INTO order_dish (order_id, dish_id, quantity, item_price)
             VALUES (?, ?, ?, ?)',
            [$orderId, $dishId, $quantity, $itemPrice]
        );

        if ($inserted <= 0) {
            return 0;
        }

        return (int) R::getInsertID();
    }

    public static function update(
        int $id,
        int $orderId,
        int $dishId,
        int $quantity,
        float $itemPrice
    ): bool {
        if (self::findById($id) === null) {
            return false;
        }

        if (
            $orderId <= 0 ||
            $dishId <= 0 ||
            $quantity <= 0 ||
            $itemPrice < 0
        ) {
            return false;
        }

        if (Orders::findById($orderId) === null || Dishes::findById($dishId) === null) {
            return false;
        }

        return R::exec(
            'UPDATE order_dish
             SET order_id = ?, dish_id = ?, quantity = ?, item_price = ?
             WHERE id = ?',
            [$orderId, $dishId, $quantity, $itemPrice, $id]
        ) > 0;
    }

    public static function delete(int $id): bool
    {
        return R::exec('DELETE FROM order_dish WHERE id = ?', [$id]) > 0;
    }

    public static function deleteByOrderId(int $orderId): int
    {
        return R::exec('DELETE FROM order_dish WHERE order_id = ?', [$orderId]);
    }
}
