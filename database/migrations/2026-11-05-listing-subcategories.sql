UPDATE categories
SET category_name = 'Home'
WHERE category_id = 2
  AND parent_category_id IS NULL
  AND category_name = 'Home and Furniture';

INSERT INTO categories (
    parent_category_id,
    category_name,
    description,
    image_url
)
SELECT new_categories.parent_id,
       new_categories.name,
       new_categories.description,
       NULL
FROM (
    SELECT 1 AS parent_id, 'Phones' AS name,
           'Smartphones and mobile phones.' AS description
    UNION ALL SELECT 1, 'TVs',
           'Televisions and home entertainment displays.'
    UNION ALL SELECT 1, 'Video Games',
           'Video game consoles, games, and accessories.'
    UNION ALL SELECT 1, 'Cameras',
           'Digital cameras and photography equipment.'
    UNION ALL SELECT 2, 'Bathroom',
           'Bathroom accessories and supplies.'
    UNION ALL SELECT 2, 'Home Decor',
           'Decorations and home accessories.'
    UNION ALL SELECT 2, 'Bedding',
           'Sheets, pillows, and bedding.'
    UNION ALL SELECT 3, 'Shoes',
           'Footwear and shoes.'
    UNION ALL SELECT 3, 'Accessories',
           'Fashion accessories.'
) AS new_categories
WHERE NOT EXISTS (
    SELECT 1
    FROM categories AS existing
    WHERE existing.parent_category_id = new_categories.parent_id
      AND existing.category_name = new_categories.name
);
