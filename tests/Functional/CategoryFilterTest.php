<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;

require_once __DIR__ . '/../Support/TestHelpers.php';

/**
 * UC-19: Category filtering, including parent and direct-child categories.
 *
 * These requests simulate the values submitted by the category dropdown.
 * Category IDs and expected results are read from the test database, rather
 * than hardcoded from one version of seed_data.sql.
 */
final class CategoryFilterTest extends DatabaseTestCase
{
    public function testParentCategoryIncludesListingsFromBothSubcategories(): void
    {
        $parentId = $this->categoryId('Electronics');
        $computerId = $this->categoryId('Computers');
        $audioId = $this->categoryId('Audio');

        $response = $this->searchCategory($parentId);

        self::assertStringContainsString(
            $this->activeListingName($computerId),
            $this->decodedHtml($response)
        );
        self::assertStringContainsString(
            $this->activeListingName($audioId),
            $this->decodedHtml($response)
        );
        $this->assertResultCount($response, $this->activeCountForCategory($parentId));
    }

    public function testCollectiblesIncludesTradingCardsAndActionFigures(): void
    {
        $parentId = $this->categoryId('Collectibles');
        $cardsId = $this->categoryId('Trading Cards');
        $figuresId = $this->categoryId('Action Figures');

        $response = $this->decodedHtml($this->searchCategory($parentId));

        self::assertStringContainsString($this->activeListingName($cardsId), $response);
        self::assertStringContainsString($this->activeListingName($figuresId), $response);
    }

    public function testSubcategoryDisplaysItsListingsButNotSiblingListings(): void
    {
        $computerId = $this->categoryId('Computers');
        $audioId = $this->categoryId('Audio');

        $response = $this->searchCategory($computerId);
        $html = $this->decodedHtml($response);

        self::assertStringContainsString($this->activeListingName($computerId), $html);
        self::assertStringNotContainsString($this->activeListingName($audioId), $html);
        $this->assertResultCount($response, $this->activeCountForCategory($computerId));
    }

    public function testLeafCategoryWithoutSubcategoriesDisplaysItsListings(): void
    {
        $booksId = $this->categoryId('Books');

        $response = $this->searchCategory($booksId);
        $html = $this->decodedHtml($response);

        self::assertStringContainsString($this->activeListingName($booksId), $html);
        self::assertStringNotContainsString(
            $this->activeListingName($this->categoryId('Computers')),
            $html
        );
        $this->assertResultCount($response, $this->activeCountForCategory($booksId));
    }

    public function testEveryMainCategoryHasTheExpectedNumberOfActiveListings(): void
    {
        foreach (['Electronics', 'Home and Furniture', 'Clothing', 'Books', 'Collectibles'] as $name) {
            $id = $this->categoryId($name);
            $response = $this->searchCategory($id);

            $this->assertResultCount(
                $response,
                $this->activeCountForCategory($id),
                "Incorrect active listing count for {$name}."
            );
        }
    }

    public function testInactiveListingIsExcludedFromItsCategory(): void
    {
        $stmt = $this->pdo->query("
            SELECT item_name, category_id
            FROM listings
            WHERE listing_status <> 'active'
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $inactive = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inactive === false) {
            self::markTestSkipped('No inactive seeded listing is available.');
        }

        $response = $this->searchCategory((int) $inactive['category_id']);

        self::assertStringNotContainsString(
            (string) $inactive['item_name'],
            $this->decodedHtml($response),
            'An inactive listing must not appear in public category results.'
        );
        $this->assertResultCount(
            $response,
            $this->activeCountForCategory((int) $inactive['category_id'])
        );
    }

    public function testMatchingKeywordWithinCategoryFindsTheListing(): void
    {
        $categoryId = $this->categoryId('Books');
        $itemName = $this->activeListingName($categoryId);

        $response = $this->searchCategory($categoryId, ['q' => $itemName]);

        self::assertStringContainsString($itemName, $this->decodedHtml($response));
        self::assertStringNotContainsString('No listings found', $response);
    }

    public function testNonMatchingKeywordWithinCategoryDisplaysNoResults(): void
    {
        $response = $this->searchCategory(
            $this->categoryId('Books'),
            ['q' => 'ci-no-such-category-item-948372']
        );

        self::assertStringContainsString('No listings found', $response);
        $this->assertResultCount($response, 0);
    }

    public function testSelectedCategoryRemainsSelectedInDropdown(): void
    {
        $categoryId = $this->categoryId('Action Figures');
        $response = $this->searchCategory($categoryId);

        self::assertMatchesRegularExpression(
            '/<option\b[^>]*value="' . $categoryId . '"[^>]*\bselected\b[^>]*>/s',
            $response,
            'The active category should remain selected after submitting the filter.'
        );
    }

    public function testChangingCategoryUpdatesTheDisplayedListings(): void
    {
        $booksId = $this->categoryId('Books');
        $computersId = $this->categoryId('Computers');
        $bookName = $this->activeListingName($booksId);
        $computerName = $this->activeListingName($computersId);

        $booksResponse = $this->decodedHtml($this->searchCategory($booksId));
        $computersResponse = $this->decodedHtml($this->searchCategory($computersId));

        self::assertStringContainsString($bookName, $booksResponse);
        self::assertStringNotContainsString($computerName, $booksResponse);
        self::assertStringContainsString($computerName, $computersResponse);
        self::assertStringNotContainsString($bookName, $computersResponse);
    }

    public function testRemovingCategoryFilterRestoresBrowseAllResults(): void
    {
        // Simulates selecting "All Categories" while browsing all listings.
        $response = \httpGet('/pages/search.php', [
            'browse' => 'all',
            'search_type' => 'items',
            'category' => '',
        ]);

        $html = $this->decodedHtml($response);
        self::assertStringContainsString(
            $this->activeListingName($this->categoryId('Books')),
            $html
        );
        self::assertStringContainsString(
            $this->activeListingName($this->categoryId('Computers')),
            $html
        );

        $count = (int) $this->pdo->query("
            SELECT COUNT(*) FROM listings WHERE listing_status = 'active'
        ")->fetchColumn();
        $this->assertResultCount($response, $count);
    }

    /** @param array<string, string> $additionalParams */
    private function searchCategory(int $categoryId, array $additionalParams = []): string
    {
        return \httpGet('/pages/search.php', array_merge([
            'category' => (string) $categoryId,
            'search_type' => 'items',
        ], $additionalParams));
    }

    private function categoryId(string $categoryName): int
    {
        $stmt = $this->pdo->prepare("
            SELECT category_id
            FROM categories
            WHERE category_name = :name
            ORDER BY category_id ASC
            LIMIT 1
        ");
        $stmt->execute(['name' => $categoryName]);
        $id = $stmt->fetchColumn();

        self::assertNotFalse($id, "Expected seeded category: {$categoryName}.");
        return (int) $id;
    }

    private function activeListingName(int $categoryId): string
    {
        $stmt = $this->pdo->prepare("
            SELECT item_name
            FROM listings
            WHERE listing_status = 'active' AND category_id = :category_id
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $stmt->execute(['category_id' => $categoryId]);
        $name = $stmt->fetchColumn();

        self::assertNotFalse($name, "No active listing in category {$categoryId}.");
        return (string) $name;
    }

    private function activeCountForCategory(int $categoryId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM listings AS l
            LEFT JOIN categories AS c ON c.category_id = l.category_id
            WHERE l.listing_status = 'active'
              AND (l.category_id = :category_id OR c.parent_category_id = :parent_id)
        ");
        $stmt->execute([
            'category_id' => $categoryId,
            'parent_id' => $categoryId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function decodedHtml(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertResultCount(string $html, int $expected, string $message = ''): void
    {
        $matched = preg_match(
            '/class="search-results__count"\s*>\s*(\d+)\s+results?\s*</s',
            $html,
            $matches
        );
        self::assertSame(1, $matched, 'Search result count was not found in the response.');
        self::assertSame($expected, (int) $matches[1], $message ?: 'Wrong number of results.');
    }
}
