<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Domain\Models\Users;

class RegistrationValidator
{


    public function validate(array $input): array
    {
        $data = [
            'first_name' => trim($input['first_name'] ?? ''),
            'last_name' => trim($input['last_name'] ?? ''),
            'email' => trim($input['email'] ?? ''),
            'password' => $input['password'] ?? '',
            'password_confirm' => $input['password_confirm'] ?? '',
        ];

        $errors = [];

        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'First name and last name are required.';
        }

        if ($data['email'] === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (Users::findByEmail($data['email']) !== null) {
            $errors[] = 'Email already in use';
        }

        if ($data['password'] !== $data['password_confirm']) {
            $errors[] = 'Passwords do not match';
        }

        $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/';

        if (!preg_match($passwordPattern, $data['password'])) {
            $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
        }

        if (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'valid' => empty($errors),
        ];
    }
}
