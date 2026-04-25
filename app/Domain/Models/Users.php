<?php

declare(strict_types=1);

namespace App\Domain\Models;

use RedBeanPHP\OODBBean;
use RedBeanPHP\R;//

/*
 * Users — model for the users table
 *
 * WHAT: Handles all database operations related to users (admin and customers).
 * HOW:  Uses RedBeanPHP static methods (R::) to create, read, update, and delete users.
 *       Passwords are always hashed with PASSWORD_BCRYPT — plain text is never stored.
 *       All methods are static so you call them directly: Users::findByEmail($email)
 */
class Users
{
    //create a new bean
    public static function create(string $name, string $email, string $password_hash, string $role): int
    {
        $user = R::dispense('users');//create a new bean
        $user->name = $name;
        $user->email = $email;
        $user->password_hash = password_hash($password_hash, PASSWORD_BCRYPT);
        $user->role = $role;
        return (int) R::store($user);//store the user in the database and return the id
    }

    //read a bean
    //find a bean by id
    public static function findById(int $user_id): ?OODBBean
    {
        //load the user from the database
        $user = R::load('users', $user_id);

        //if the user doesn't exist, return null
        if($user->id == 0){
            return null;
        }
        return $user;
    }
 
    //find a bean by email
    public static function findByEmail(string $email): ?OODBBean
    {
        return R::findOne('users', ' email = ? ', [$email]);
    }

    //update a bean
    public static function update(int $user_id, string $name, string $email): bool
    {
        $user = R::load('users', $user_id);
        if($user->id == 0){
            return false;
        }

        //update the user's properties
        $user->name = $name;
        $user->email = $email;
        return (int) R::store($user) > 0;//update the user in the database and return true if successful
    }

    // Updates a user's password after verifying their current one.
    // Returns true on success, false if the user doesn't exist or current password is wrong.
    public static function updatePassword(int $user_id, string $current_password, string $new_password): bool
    {
        $user = R::load('users', $user_id);
        if ($user->id == 0) return false;

        // Make sure the current password is correct before allowing the change
        if (!password_verify($current_password, $user->password_hash)) return false;

        $user->password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        return (int) R::store($user) > 0;
    }

    // Checks whether the provided password matches the user's current password hash.
    public static function verifyPassword(int $user_id, string $password): bool
    {
        $user = R::load('users', $user_id);
        if ($user->id == 0) {
            return false;
        }

        return password_verify($password, $user->password_hash);
    }

    public static function saveTotpSecret(int $user_id, string $secret): bool
    {
        $user = R::load('users', $user_id);
        if ($user->id == 0) return false;
        $user->totp_secret = $secret;
        return (int) R::store($user) > 0;
    }

    //delete a bean
    public static function delete(int $user_id): bool
    {
        $user = R::load('users', $user_id);
        if($user->id == 0){
            return false;
        }
        R::trash($user);//delete the user from the database
        return true;
    }
}
