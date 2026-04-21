<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;//

/*
 * Cuisines — model for the cuisines table
 *
 * WHAT: Handles all database operations related to cuisines.
 * HOW:  Uses RedBeanPHP static methods (R::) to read from the cuisines table.
 *       All methods are static so you call them directly: Cuisines::getAll()
 *       without needing to create an object first.
 */
class Cuisines
{
    // Fetches every row from the cuisines table and returns them as an array of beans.
    // Used by HomeController to populate the cuisine cards on the home page.
    public static function getAll(): array
    {
        return R::findAll('cuisines');
    }

    
}