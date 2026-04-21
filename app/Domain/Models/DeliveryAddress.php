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
        $address = R::load('delivery_address', $id);
        return $address->id == 0 ? null : $address;
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

        $address = R::dispense('delivery_address');
        $address->user_id = $userId;
        $address->street = $street;
        $address->city = $city;
        $address->postal_code = $postalCode;

        return (int) R::store($address);
    }

    public static function update(
        int $id,
        int $userId,
        string $street,
        string $city,
        string $postalCode
    ): bool {
        $address = R::load('delivery_address', $id);
        if ($address->id == 0) {
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

        $address->user_id = $userId;
        $address->street = $street;
        $address->city = $city;
        $address->postal_code = $postalCode;

        return (int) R::store($address) > 0;
    }

    public static function delete(int $id): bool
    {
        $address = R::load('delivery_address', $id);
        if ($address->id == 0) {
            return false;
        }

        R::trash($address);
        return true;
    }
}
