<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class Cuisines
{
    //get all cuisines (sorted by name)
    public static function getAll(): array
    {
        return R::findAll('cuisines', ' ORDER BY name ASC ');
    }

    public static function findById(int $id): ?OODBBean
    {
        $cuisine = R::load('cuisines', $id);
        return $cuisine->id == 0 ? null : $cuisine;
    }

    public static function findBySlug(string $slug): ?OODBBean
    {
        return R::findOne('cuisines', ' slug = ? ', [self::slugify($slug)]);
    }

    public static function findByName(string $name): ?OODBBean
    {
        return R::findOne('cuisines', ' name = ? ', [trim($name)]);
    }

    //validates name,code, slug uniqueness
    public static function create(
        string $name,
        string $code,
        ?string $description = null,
        ?string $imageUrl = null
    ): int {
        $name = trim($name);
        $code = strtoupper(trim($code));
        $slug = self::slugify($name);

        if ($name === '' || $code === '' || $slug === '') {
            return 0;
        }

        if (self::findByName($name) !== null || self::findBySlug($slug) !== null) {
            return 0;
        }

        $cuisine = R::dispense('cuisines');
        $cuisine->name = $name;
        $cuisine->code = $code;
        $cuisine->slug = $slug;
        $cuisine->description = self::nullIfEmpty($description);
        $cuisine->image_url = self::nullIfEmpty($imageUrl);

        return (int) R::store($cuisine);
    }

    //validates uniqueness
    public static function update(
        int $id,
        string $name,
        string $code,
        ?string $description = null,
        ?string $imageUrl = null
    ): bool {
        $cuisine = R::load('cuisines', $id);
        if ($cuisine->id == 0) {
            return false;
        }

        $name = trim($name);
        $code = strtoupper(trim($code));
        $slug = self::slugify($name);

        if ($name === '' || $code === '' || $slug === '') {
            return false;
        }

        $existingByName = self::findByName($name);
        if ($existingByName !== null && (int) $existingByName->id !== $id) {
            return false;
        }

        $existingBySlug = self::findBySlug($slug);
        if ($existingBySlug !== null && (int) $existingBySlug->id !== $id) {
            return false;
        }

        $cuisine->name = $name;
        $cuisine->code = $code;
        $cuisine->slug = $slug;
        $cuisine->description = self::nullIfEmpty($description);
        $cuisine->image_url = self::nullIfEmpty($imageUrl);

        return (int) R::store($cuisine) > 0;
    }

    public static function delete(int $id): bool
    {
        $cuisine = R::load('cuisines', $id);
        if ($cuisine->id == 0) {
            return false;
        }

        R::trash($cuisine);
        return true;
    }

    //helper to generate url-friendly slug
    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    // Helper: empty string → null
    private static function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
