<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Domain\Models\Cuisines;

class CuisineValidator
{
    public function validate(array $input, ?int $ignoreId = null): array
    {
        $data = [
            'name' => trim($input['name'] ?? ''),
            'code' => trim($input['code'] ?? ''),
            'description' => trim($input['description'] ?? ''),
            'image_url' => trim($input['image_url'] ?? ''),
        ];

        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Cuisine name is required.';
        }

        if ($data['code'] === '') {
            $errors[] = 'Cuisine code is required.';
        }

        $existing = Cuisines::findByName($data['name']);
        if (
            $data['name'] !== '' &&
            $existing !== null &&
            (int) $existing->id !== (int) ($ignoreId ?? 0)
        ) {
            $errors[] = 'A cuisine with that name already exists.';
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'valid' => empty($errors),
        ];
    }
}
