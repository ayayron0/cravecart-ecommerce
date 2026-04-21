<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class Categories
{
    // Get all categories (sorted by name)
    public static function getAll(): array
    {
        return R::findAll('categories', ' ORDER BY name ASC ');
    }

    // Find category by ID
    public static function findById(int $id): ?OODBBean
    {
        $category = R::load('categories', $id);
        return $category->id == 0 ? null : $category;
    }

    // Find category by name
    public static function findByName(string $name): ?OODBBean
    {
        return R::findOne('categories', ' name = ? ', [trim($name)]);
    }

    // Create new category (no empty/duplicate names)
    public static function create(string $name, ?string $description = null): int
    {
        $name = trim($name);

        if ($name === '') {
            return 0;
        }

        if (self::findByName($name) !== null) {
            return 0;
        }

        $category = R::dispense('categories');
        $category->name = $name;
        $category->description = self::nullIfEmpty($description);

        return (int) R::store($category);
    }

    // Update existing category
    public static function update(int $id, string $name, ?string $description = null): bool
    {
        $category = R::load('categories', $id);
        if ($category->id == 0) {
            return false;
        }

        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $existing = self::findByName($name);
        if ($existing !== null && (int) $existing->id !== $id) {
            return false;
        }

        $category->name = $name;
        $category->description = self::nullIfEmpty($description);

        return (int) R::store($category) > 0;
    }

    // Delete category by ID
    public static function delete(int $id): bool
    {
        $category = R::load('categories', $id);
        if ($category->id == 0) {
            return false;
        }

        R::trash($category);
        return true;
    }

    // Helper: empty string → null
    private static function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}