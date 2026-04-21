<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\R;

/*
 * Categories — model for the categories table
 *
 * WHAT: Handles all database operations related to categories (Food, Desserts, Drinks, etc.).
 * HOW:  Uses RedBeanPHP static methods (R::) to read from the categories table.
 *       All methods are static so you call them directly: Categories::getAll()
 */
class Categories
{
    // Fetches every row from the categories table and returns them as an array of beans.
    public static function getAll(): array
    {
        return R::findAll('categories');
    }
}
