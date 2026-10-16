<?php

/**
 * OmniBuy View User Profile: the public page for any active user.
 */

declare(strict_types=1);

require_once __DIR__ . '/account.php';

const PROFILE_LISTING_DEFAULT_IMAGE = '/assets/images/listing/listing-default-img.jpg';

// How each privacy setting is named on the owner's own profile.
const PROFILE_PRIVACY_LABELS = [
    'last_name' => 'last name',
    'city' => 'town or city',
    'email' => 'email',
    'phone' => 'phone number',
    'bio' => 'bio',
];

/**
 * An active user's profile, or null if there's no such user or the account
 * is deleted, suspended or restricted.
 *
 * Anything the user hid in their privacy settings (UC-05) comes back as
 * null, and the name drops the last name if they hid it. Phone and email
 * are only included when $includeContact is true; pass isLoggedIn() so
 * logged-out visitors never receive them. 'hidden' lists the settings the
 * user turned off, so their own profile can say what others can't see.
 */
function loadPublicProfile(PDO $pdo, int $userId, bool $includeContact): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            u.user_id, u.first_name, u.last_name, u.username,
            u.email, u.phone_number, u.profile_image_url, u.bio, u.city, u.created_at,
            u.is_last_name_hidden, u.is_city_hidden, u.is_email_hidden, u.is_phone_hidden, u.is_bio_hidden,
            sp.store_name, sp.is_verified
        FROM users u
        LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
        WHERE u.user_id = :id AND u.account_status = 'active'
    ");
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if ($row === false) {
        return null;
    }

    $hidden = array_keys(array_filter(
        array_map(static fn (string $column): bool => (bool) $row[$column], PRIVACY_SETTINGS)
    ));
    $isHidden = static fn (string $setting): bool => in_array($setting, $hidden, true);

    $profile = [
        'user_id' => (int) $row['user_id'],
        'display_name' => publicDisplayName($row['first_name'], $row['last_name'], $isHidden('last_name')),
        'username' => $row['username'],
        'profile_image_url' => $row['profile_image_url'] ?: AUTH_DEFAULT_AVATAR,
        'bio' => $isHidden('bio') ? null : $row['bio'],
        'city' => $isHidden('city') ? null : $row['city'],
        'created_at' => $row['created_at'],
        'store_name' => $row['store_name'],
        'is_verified' => (bool) $row['is_verified'],
        'email' => null,
        'phone_number' => null,
        // Both hidden: say the details are private instead of asking visitors to log in.
        'contact_hidden' => $isHidden('email') && $isHidden('phone'),
        'hidden' => $hidden,
    ];

    if ($includeContact) {
        $profile['email'] = $isHidden('email') ? null : $row['email'];
        $profile['phone_number'] = $isHidden('phone') || $row['phone_number'] === '' ? null : $row['phone_number'];
    }

    return $profile;
}

/**
 * The user's public listings, newest first, split into 'active' and 'sold'.
 * Drafts, hidden and archived listings are never shown. Each row has the
 * fields listing-card-component.php needs.
 *
 * @return array{active: array, sold: array}
 */
function loadProfileListings(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            l.listing_id, l.item_name, l.price, l.`condition`, l.fulfillment_type,
            l.listing_status, c.category_name,
            COALESCE((
                SELECT li.image_url
                FROM listing_images li
                WHERE li.listing_id = l.listing_id
                ORDER BY li.display_order
                LIMIT 1
            ), :default_image) AS image_url
        FROM listings l
        JOIN categories c ON c.category_id = l.category_id
        WHERE l.seller_user_id = :id
          AND l.listing_status IN ('active', 'sold')
        ORDER BY l.created_at DESC, l.listing_id DESC
    ");
    $stmt->execute(['id' => $userId, 'default_image' => PROFILE_LISTING_DEFAULT_IMAGE]);

    $listings = ['active' => [], 'sold' => []];
    foreach ($stmt->fetchAll() as $listing) {
        $listings[$listing['listing_status']][] = $listing;
    }

    return $listings;
}
