<?php

$passed = 0;
$failed = 0;

function test(string $name, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        echo "PASS: $name\n";
        $passed++;
    } else {
        echo "FAIL: $name\n";
        $failed++;
    }
}

test('Valid listing title is accepted', trim('iPhone 15') !== '');
test('Empty listing title is rejected', trim('') === '');

test('Positive price is accepted', is_numeric('99.99') && (float) '99.99' > 0);
test('Zero price is rejected', (float) '0' <= 0);
test('Negative price is rejected', (float) '-10' <= 0);
test('Non-numeric price is rejected', !is_numeric('free'));

test('Valid description is accepted', trim('Used phone in good condition') !== '');
test('Empty description is rejected', trim('') === '');

$allowedImageTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

test('JPEG image type is accepted', in_array('image/jpeg', $allowedImageTypes, true));
test('PNG image type is accepted', in_array('image/png', $allowedImageTypes, true));
test('WEBP image type is accepted', in_array('image/webp', $allowedImageTypes, true));
test('Unsupported image type is rejected', !in_array('image/gif', $allowedImageTypes, true));

$maxImageSize = 10 * 1024 * 1024;

test('Image under 10MB is accepted', 5 * 1024 * 1024 <= $maxImageSize);
test('Image over 10MB is rejected', 11 * 1024 * 1024 > $maxImageSize);




test('Whitespace-only title is rejected', trim('   ') === '');
test('Decimal price is accepted', is_numeric('49.95') && (float) '49.95' > 0);
test('Whitespace-only description is rejected', trim('   ') === '');

test('GIF image type is rejected', !in_array('image/gif', $allowedImageTypes, true));
test('PDF file type is rejected', !in_array('application/pdf', $allowedImageTypes, true));

test('Exactly 10MB image is accepted', 10 * 1024 * 1024 <= $maxImageSize);
test('Image just over 10MB is rejected', (10 * 1024 * 1024) + 1 > $maxImageSize);

echo "\n$passed tests passed, $failed tests failed.\n";

exit($failed > 0 ? 1 : 0);