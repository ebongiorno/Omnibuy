<?php
declare(strict_types=1);

/** Longest return window a seller can offer, in days. */
const LISTING_MAX_RETURN_DAYS = 30;

/**
 * The submitted return policy as whole days (0 = no returns), or null if
 * it isn't a whole number from 0 to LISTING_MAX_RETURN_DAYS.
 */
function parseReturnPolicyDays(mixed $value): ?int
{
    $days = filter_var(
        is_string($value) ? trim($value) : $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => LISTING_MAX_RETURN_DAYS]]
    );

    return $days === false ? null : $days;
}

/**
 * "No returns" or "30-day returns", for listing and order pages.
 */
function formatReturnPolicy(int $days): string
{
    if ($days <= 0) {
        return 'No returns';
    }

    return $days === 1 ? '1-day returns' : "$days-day returns";
}

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
