<?php

declare(strict_types=1);

namespace App\Services\Validation;

class ProfileValidator
{
    public function validate(array $input): array
    {
        $data = [
            'name' => trim($input['name'] ?? ''),
            'email' => trim($input['email'] ?? ''),
            'current_password' => $input['current_password'] ?? '',
            'new_password' => $input['new_password'] ?? '',
            'confirm_password' => $input['confirm_password'] ?? '',
        ];

        $errors = [];

        if ($data['name'] === '' || $data['email'] === '') {
            $errors[] = 'Name and email cannot be empty.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (
            $data['current_password'] !== '' ||
            $data['new_password'] !== '' ||
            $data['confirm_password'] !== ''
        ) {
            if (strlen($data['new_password']) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($data['new_password'] !== $data['confirm_password']) {
                $errors[] = 'New passwords do not match.';
            }
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'valid' => empty($errors),
        ];
    }
}
