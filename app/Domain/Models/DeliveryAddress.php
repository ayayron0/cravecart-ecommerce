<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

class DeliveryAddress
{
    //get all delivery addresses (sorted by ID)
    public static function getAll(): array
    {
        return R::findAll('delivery_address', ' ORDER BY id ASC ');
    }

    public static function findById(int $id): ?OODBBean
    {
        return R::findOne('delivery_address', ' id = ? ', [$id]);
    }

    public static function findByUserId(int $userId): array
    {
        return R::findAll('delivery_address', ' user_id = ? ORDER BY id DESC ', [$userId]);
    }

    //validates user and fields
    public static function create(
        int $userId,
        string $street,
        string $city,
        string $postalCode
    ): int {
        $street = trim($street);
        $city = trim($city);
        $postalCode = trim($postalCode);

        if (
            $userId <= 0 ||
            $street === '' ||
            $city === '' ||
            $postalCode === ''
        ) {
            return 0;
        }

        if (Users::findById($userId) === null) {
            return 0;
        }

        $inserted = R::exec(
            'INSERT INTO delivery_address (user_id, street, city, postal_code)
             VALUES (?, ?, ?, ?)',
            [$userId, $street, $city, $postalCode]
        );

        if ($inserted <= 0) {
            return 0;
        }

        return (int) R::getInsertID();
    }

    public static function update(
        int $id,
        int $userId,
        string $street,
        string $city,
        string $postalCode
    ): bool {
        if (self::findById($id) === null) {
            return false;
        }

        $street = trim($street);
        $city = trim($city);
        $postalCode = trim($postalCode);

        if (
            $userId <= 0 ||
            $street === '' ||
            $city === '' ||
            $postalCode === ''
        ) {
            return false;
        }

        if (Users::findById($userId) === null) {
            return false;
        }

        return R::exec(
            'UPDATE delivery_address
             SET user_id = ?, street = ?, city = ?, postal_code = ?
             WHERE id = ?',
            [$userId, $street, $city, $postalCode, $id]
        ) > 0;
    }

    public static function delete(int $id): bool
    {
        return R::exec('DELETE FROM delivery_address WHERE id = ?', [$id]) > 0;
    }
}
