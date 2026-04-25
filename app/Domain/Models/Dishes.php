<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

/*
 * Dishes — database model for the dishes table.
 *
 * WHAT: Provides all read and write operations for dishes.
 * HOW:  Uses RedBeanPHP (R::) to interact with the database.
 *       Each method is static so it can be called directly without
 *       instantiating the class (e.g. Dishes::findById(1)).
 *       searchDish() joins cuisines and categories so the result
 *       contains everything the browse page and search bar need.
 */
class Dishes
{
    //sorted by name
    public static function getAll(): array
    {
        return R::findAll('dishes', ' ORDER BY name ASC ');
    }

    // Searches dishes by name using a partial, case-insensitive match.
    // JOINs cuisines and categories so the result includes cuisine_slug and
    // category_name — both are needed by the frontend to build the browse URL.
    public static function searchDish(string $query): array
    {
        return R::getAll(
            'SELECT
                d.id,
                d.name,
                d.description,
                d.price,
                d.image_url,
                d.availability,
                c.name AS cuisine_name,
                c.slug AS cuisine_slug,
                cat.name AS category_name
             FROM dishes d
             INNER JOIN cuisines c ON d.cuisine_id = c.id
             INNER JOIN categories cat ON d.category_id = cat.id
             WHERE LOWER(d.name) LIKE ?
             ORDER BY d.name ASC',
            // Wrapping with % on both sides turns an exact match into a partial
            // (substring) search. strtolower() matches the LOWER() in the query
            // so capitalisation never blocks a result.
            ['%' . strtolower($query) . '%']
        );
    }

    public static function findById(int $id): ?OODBBean
    {
        $dish = R::load('dishes', $id);
        return $dish->id == 0 ? null : $dish;
    }

    public static function findByCuisineAndCategory(string $cuisineSlug, string $categorySlug): array
    {
        $categoryName = ucfirst(trim(strtolower($categorySlug)));

        return R::findAll(
            'dishes',
            ' cuisine_id IN (
                SELECT id FROM cuisines WHERE slug = ?
            )
            AND category_id IN (
                SELECT id FROM categories WHERE name = ?
            )
            ORDER BY name ASC ',
            [trim(strtolower($cuisineSlug)), $categoryName]
        );
    }


    //get all dishes with joined cuisine and category details
    public static function getAllDetailed(): array
    {
        return R::getAll(
            'SELECT
                d.id,
                d.name,
                d.description,
                d.price,
                d.image_url,
                d.availability,
                c.name AS cuisine_name,
                c.slug AS cuisine_slug,
                cat.name AS category_name
             FROM dishes d
             INNER JOIN cuisines c ON d.cuisine_id = c.id
             INNER JOIN categories cat ON d.category_id = cat.id
             ORDER BY d.name ASC'
        );
    }

    // Returns detailed dish rows for a specific list of dish IDs.
    // Used by the cart when the current source of truth is the session cart.
    public static function findDetailedByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));

        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return R::getAll(
            'SELECT
                d.id,
                d.name,
                d.description,
                d.price,
                d.image_url,
                d.availability,
                c.name AS cuisine_name,
                c.slug AS cuisine_slug,
                cat.name AS category_name
             FROM dishes d
             INNER JOIN cuisines c ON d.cuisine_id = c.id
             INNER JOIN categories cat ON d.category_id = cat.id
             WHERE d.id IN (' . $placeholders . ')
             ORDER BY d.name ASC',
            $ids
        );
    }

    //validates category, cuisine and fields
    public static function create(
        int $categoryId,
        int $cuisineId,
        string $name,
        ?string $description,
        float $price,
        ?string $imageUrl = null,
        string $availability = 'available'
    ): int {
        $name = trim($name);
        $availability = trim($availability);

        if (
            $categoryId <= 0 ||
            $cuisineId <= 0 ||
            $name === '' ||
            $price < 0
        ) {
            return 0;
        }

        if (Categories::findById($categoryId) === null || Cuisines::findById($cuisineId) === null) {
            return 0;
        }

        $dish = R::dispense('dishes');
        $dish->category_id = $categoryId;
        $dish->cuisine_id = $cuisineId;
        $dish->name = $name;
        $dish->description = self::nullIfEmpty($description);
        $dish->price = $price;
        $dish->image_url = self::nullIfEmpty($imageUrl);
        $dish->availability = $availability === '' ? 'available' : $availability;

        return (int) R::store($dish);
    }

    public static function update(
        int $id,
        int $categoryId,
        int $cuisineId,
        string $name,
        ?string $description,
        float $price,
        ?string $imageUrl = null,
        string $availability = 'available'
    ): bool {
        $dish = R::load('dishes', $id);
        if ($dish->id == 0) {
            return false;
        }

        $name = trim($name);
        $availability = trim($availability);

        if (
            $categoryId <= 0 ||
            $cuisineId <= 0 ||
            $name === '' ||
            $price < 0
        ) {
            return false;
        }

        if (Categories::findById($categoryId) === null || Cuisines::findById($cuisineId) === null) {
            return false;
        }

        $dish->category_id = $categoryId;
        $dish->cuisine_id = $cuisineId;
        $dish->name = $name;
        $dish->description = self::nullIfEmpty($description);
        $dish->price = $price;
        $dish->image_url = self::nullIfEmpty($imageUrl);
        $dish->availability = $availability === '' ? 'available' : $availability;

        return (int) R::store($dish) > 0;
    }

    public static function delete(int $id): bool
    {
        $dish = R::load('dishes', $id);
        if ($dish->id == 0) {
            return false;
        }

        R::trash($dish);
        return true;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
    
}
