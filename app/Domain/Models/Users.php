<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;

/*
 * Users - model for the users table.
 *
 * WHAT: Contains the database operations used for application users.
 * HOW:  Each method talks to the users table through RedBeanPHP.
 *       Passwords are always stored as hashes, never as plain text.
 *       Methods are static, so other parts of the app can call them directly
 *       like Users::findByEmail($email).
 */
class Users
{
    // Creates a new user row and returns the new ID.
    public static function create(string $name, string $email, string $password_hash, string $role): int
    {
        $user = R::dispense('users');
        $user->name = $name;
        $user->email = $email;
        $user->password_hash = password_hash($password_hash, PASSWORD_BCRYPT);
        $user->role = $role;

        return (int) R::store($user);
    }

    // Loads a user by ID. Returns null when the row does not exist.
    public static function findById(int $user_id): ?OODBBean
    {
        $user = R::load('users', $user_id);

        if ($user->id == 0) {
            return null;
        }

        return $user;
    }
 
    // Finds the first user row that matches the given email address.
    public static function findByEmail(string $email): ?OODBBean
    {
        return R::findOne('users', ' email = ? ', [$email]);
    }

    // Updates the user's basic profile fields.
    public static function update(int $user_id, string $name, string $email): bool
    {
        $user = R::load('users', $user_id);

        if ($user->id == 0) {
            return false;
        }

        $user->name = $name;
        $user->email = $email;

        return (int) R::store($user) > 0;
    }

    // Changes the user's password only after confirming the current password.
    public static function updatePassword(int $user_id, string $current_password, string $new_password): bool
    {
        $user = R::load('users', $user_id);

        if ($user->id == 0) {
            return false;
        }

        if (!password_verify($current_password, $user->password_hash)) {
            return false;
        }

        $user->password_hash = password_hash($new_password, PASSWORD_BCRYPT);

        return (int) R::store($user) > 0;
    }

    // Checks whether the provided password matches the user's current password.
    public static function verifyPassword(int $user_id, string $password): bool
    {
        $user = R::load('users', $user_id);

        if ($user->id == 0) {
            return false;
        }

        return password_verify($password, $user->password_hash);
    }

    // Saves the user's authenticator secret after 2FA setup.
    public static function saveTotpSecret(int $user_id, string $secret): bool
    {
        $user = R::load('users', $user_id);

        if ($user->id == 0) {
            return false;
        }

        $user->totp_secret = $secret;

        return (int) R::store($user) > 0;
    }

    // Returns all users with the administrator role.
    public static function findAdmins(): array
    {
        return R::findAll('users', ' role = ? ', ['administrator']);
    }

    // Deletes the user row. Related rows are handled by database foreign keys.
    public static function delete(int $user_id): bool
    {
        $user = R::load('users', $user_id);

        if ($user->id == 0) {
            return false;
        }

        R::trash($user);

        return true;
    }
}
