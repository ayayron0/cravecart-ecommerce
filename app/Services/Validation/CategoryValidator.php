<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Domain\Models\Categories;

class CategoryValidator
{
    public function validate(array $input, ?int $ignoreId = null): array
    {
        $data = [
            'name' => trim($input['name'] ?? ''),
            'description' => trim($input['description'] ?? ''),
        ];

        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Category name is required.';
        }

        $existing = Categories::findByName($data['name']);
        if (
            $data['name'] !== '' &&
            $existing !== null &&
            (int) $existing->id !== (int) ($ignoreId ?? 0)
        ) {
            $errors[] = 'A category with that name already exists.';
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'valid' => empty($errors),
        ];
    }
}
