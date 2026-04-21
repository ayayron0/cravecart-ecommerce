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
        $orderDish = R::load('order_dish', $id);
        return $orderDish->id == 0 ? null : $orderDish;
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

        $orderDish = R::dispense('order_dish');
        $orderDish->order_id = $orderId;
        $orderDish->dish_id = $dishId;
        $orderDish->quantity = $quantity;
        $orderDish->item_price = $itemPrice;

        return (int) R::store($orderDish);
    }

    public static function update(
        int $id,
        int $orderId,
        int $dishId,
        int $quantity,
        float $itemPrice
    ): bool {
        $orderDish = R::load('order_dish', $id);
        if ($orderDish->id == 0) {
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

        $orderDish->order_id = $orderId;
        $orderDish->dish_id = $dishId;
        $orderDish->quantity = $quantity;
        $orderDish->item_price = $itemPrice;

        return (int) R::store($orderDish) > 0;
    }

    public static function delete(int $id): bool
    {
        $orderDish = R::load('order_dish', $id);
        if ($orderDish->id == 0) {
            return false;
        }

        R::trash($orderDish);
        return true;
    }

    public static function deleteByOrderId(int $orderId): int
    {
        return R::exec('DELETE FROM order_dish WHERE order_id = ?', [$orderId]);
    }
}
