<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class SavedCart
{
    //sorted by date
    public static function getAll(): array
    {
        return R::findAll('saved_cart', ' ORDER BY saved_at DESC, id DESC ');
    }

    public static function findById(int $id): ?OODBBean
    {
        $item = R::load('saved_cart', $id);
        return $item->id == 0 ? null : $item;
    }

    public static function findByUserId(int $userId): array
    {
        return R::findAll('saved_cart', ' user_id = ? ORDER BY saved_at DESC, id DESC ', [$userId]);
    }

    // Get detailed cart items with dish, cuisine, and category info (JOIN)
    public static function findDetailedByUserId(int $userId): array
    {
        return R::getAll(
            'SELECT
                sc.id,
                sc.user_id,
                sc.dish_id,
                sc.quantity,
                sc.dish_price,
                sc.saved_at,
                d.name AS dish_name,
                d.description AS dish_description,
                d.image_url,
                d.availability,
                c.name AS cuisine_name,
                cat.name AS category_name
             FROM saved_cart sc
             INNER JOIN dishes d ON sc.dish_id = d.id
             INNER JOIN cuisines c ON d.cuisine_id = c.id
             INNER JOIN categories cat ON d.category_id = cat.id
             WHERE sc.user_id = ?
             ORDER BY sc.saved_at DESC, sc.id DESC',
            [$userId]
        );
    }

    public static function findByUserAndDish(int $userId, int $dishId): ?OODBBean
    {
        return R::findOne('saved_cart', ' user_id = ? AND dish_id = ? ', [$userId, $dishId]);
    }

    // Add item to cart (if already exists → increase quantity instead of duplicate row)
    public static function addItem(int $userId, int $dishId, int $quantity, float $dishPrice): int
    {
        // Validate inputs (no invalid IDs, quantity, or price)
        if (
            $userId <= 0 ||
            $dishId <= 0 ||
            $quantity <= 0 ||
            $dishPrice < 0
        ) {
            return 0;
        }

        // Ensure user and dish actually exist in DB
        if (Users::findById($userId) === null || Dishes::findById($dishId) === null) {
            return 0;
        }

        // Check if this dish is already in the user's cart
        $existing = self::findByUserAndDish($userId, $dishId);

        // If it exists → update quantity instead of creating a new row
        if ($existing !== null) {
            $existing->quantity += $quantity; // add to existing quantity
            $existing->dish_price = $dishPrice; // update price (in case it changed)
            return (int) R::store($existing); // save updated item
        }

        // If not exists → create new cart item
        $item = R::dispense('saved_cart');
        $item->user_id = $userId;
        $item->dish_id = $dishId;
        $item->quantity = $quantity;
        $item->dish_price = $dishPrice;

        // Save new item and return its ID
        return (int) R::store($item);
    }

    public static function updateQuantity(int $id, int $quantity): bool
    {
        $item = R::load('saved_cart', $id);
        if ($item->id == 0) {
            return false;
        }

        if ($quantity <= 0) {
            R::trash($item);
            return true;
        }

        $item->quantity = $quantity;
        return (int) R::store($item) > 0;
    }

    public static function removeItem(int $id): bool
    {
        $item = R::load('saved_cart', $id);
        if ($item->id == 0) {
            return false;
        }

        R::trash($item);
        return true;
    }

    public static function clearByUserId(int $userId): int
    {
        return R::exec('DELETE FROM saved_cart WHERE user_id = ?', [$userId]);
    }
}
