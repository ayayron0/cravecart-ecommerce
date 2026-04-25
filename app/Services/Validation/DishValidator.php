<?php

declare(strict_types=1);

namespace App\Services\Validation;

class DishValidator
{
    public function validate(array $input): array
    {
        $data = [
            'name' => trim($input['name'] ?? ''),
            'cuisine_id' => (int) ($input['cuisine_id'] ?? 0),
            'category_id' => (int) ($input['category_id'] ?? 0),
            'description' => trim($input['description'] ?? ''),
            'price' => (float) ($input['price'] ?? 0),
            'availability' => trim($input['availability'] ?? 'available'),
            'image_url' => trim($input['image_url'] ?? ''),
        ];

        $errors = [];
        $allowedAvailability = ['available', 'seasonal', 'unavailable'];

        if ($data['name'] === '') {
            $errors[] = 'Dish name is required.';
        }

        if ($data['cuisine_id'] <= 0) {
            $errors[] = 'Cuisine is required.';
        }

        if ($data['category_id'] <= 0) {
            $errors[] = 'Category is required.';
        }

        if ($data['price'] < 0) {
            $errors[] = 'Price cannot be negative.';
        }

        if (!in_array($data['availability'], $allowedAvailability, true)) {
            $errors[] = 'Availability value is invalid.';
        }

        return [
            'data' => $data,
            'errors' => $errors,
            'valid' => empty($errors),
        ];
    }
}
