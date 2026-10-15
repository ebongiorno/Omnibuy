<?php
declare(strict_types=1);

/**
 * Validates listing details before publication.
 *
 * @param array<string, mixed> $data
 * @param array<int, string> $validCategoryIds
 * @return array<string, string>
 */
function validateListingDetails(
    array $data,
    array $validCategoryIds
): array {
    $errors = [];

    $category = trim((string) ($data['category'] ?? ''));
    $condition = trim((string) ($data['condition'] ?? ''));
    $quantity = trim((string) ($data['quantity'] ?? ''));
    $fulfillment = trim((string) ($data['fulfillment_type'] ?? ''));

    $allowedConditions = [
        'new',
        'open_box',
        'like_new',
        'excellent',
        'good',
        'fair',
        'poor',
        'refurbished',
        'for_parts',
    ];

    // Validate category.
    if (!in_array($category, $validCategoryIds, true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    // Validate condition.
    if (!in_array($condition, $allowedConditions, true)) {
        $errors['condition'] = 'Please select a valid condition.';
    }

    // Validate quantity.
    if (
        filter_var(
            $quantity,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) === false
    ) {
        $errors['quantity'] = 'Quantity must be at least 1.';
    }

    // Validate shipping or meetup.
    if (!in_array($fulfillment, ['shipping', 'meetup'], true)) {
        $errors['fulfillment'] =
            'Please select shipping or meetup.';
    }

    return $errors;
}
