<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;

require_once __DIR__ . '/../Support/TestHelpers.php';

/** UC-18: Search Seller Profiles and public-data privacy regression. */
final class SellerSearchTest extends DatabaseTestCase
{
    public function testExactSellerSearchReturnsSeededSeller(): void
    {
        $seller = $this->activeSeller();
        $response = $this->searchSellers((string) $seller['store_name']);

        self::assertStringContainsString((string) $seller['store_name'], $response);
        self::assertStringNotContainsString('No sellers found', $response);
    }

    public function testPartialSellerSearchReturnsExpectedSeller(): void
    {
        $seller = $this->activeSeller();
        $sampleSeller = (string) $seller['store_name'];
        $partialSeller = substr($sampleSeller, 0, 4);
        $response = $this->searchSellers($partialSeller);

        self::assertStringContainsString(
            $sampleSeller,
            $response,
            'Partial Seller Search should return the expected seeded seller.'
        );
    }

    public function testSellerSearchDisplaysNoResultsState(): void
    {
        $response = $this->searchSellers('ci-no-such-seller-948372');
        self::assertStringContainsString('No sellers found', $response);
    }

    public function testSellerSearchExcludesInactiveSellers(): void
    {
        $stmt = $this->pdo->query("
            SELECT sp.store_name
            FROM seller_profiles AS sp
            INNER JOIN users AS u ON sp.user_id = u.user_id
            WHERE u.account_status <> 'active'
            ORDER BY sp.user_id ASC
            LIMIT 1
        ");
        $inactiveSeller = $stmt->fetchColumn();

        if ($inactiveSeller === false) {
            self::markTestSkipped('No inactive seeded seller available.');
        }

        $response = $this->searchSellers((string) $inactiveSeller);
        self::assertStringContainsString(
            'No sellers found',
            $response,
            'Seller Search should not return inactive sellers.'
        );
    }

    public function testSellerSearchDoesNotExposeRestrictedData(): void
    {
        $seller = $this->activeSeller();
        $response = $this->searchSellers((string) $seller['store_name']);

        // Retain all sensitive values and field-name checks from the original test.
        foreach (['password_hash', 'birth_date', 'payout_account_reference'] as $field) {
            $value = $seller[$field];

            if ($value !== null && (string) $value !== '') {
                self::assertStringNotContainsString(
                    (string) $value,
                    $response,
                    "Seller Search should not expose the value of {$field}."
                );
            }

            self::assertStringNotContainsString(
                strtolower($field),
                strtolower($response),
                "Seller Search should not expose the field name {$field}."
            );
        }
    }

    /** @return array<string, mixed> */
    private function activeSeller(): array
    {
        $stmt = $this->pdo->query("
            SELECT sp.user_id, sp.store_name, u.password_hash,
                   u.birth_date, sp.payout_account_reference
            FROM seller_profiles AS sp
            INNER JOIN users AS u ON sp.user_id = u.user_id
            WHERE u.account_status = 'active'
            ORDER BY sp.user_id ASC
            LIMIT 1
        ");
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($seller, 'No active seeded seller available.');

        return $seller;
    }

    private function searchSellers(string $query): string
    {
        return \httpGet('/pages/search.php', [
            'q' => $query,
            'search_type' => 'seller_profiles',
        ]);
    }
}
