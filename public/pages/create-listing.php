<?php

require_once __DIR__ . '/../components/require-login.php';

$pageTitle = 'Create Listing | OmniBuy';
$activeNav = 'sell';

$errors = [];

$title = (string) ($_SESSION['listing']['title'] ?? '');
$price = (string) ($_SESSION['listing']['price'] ?? '');
$description = (string) ($_SESSION['listing']['description'] ?? '');

$allowedImageTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$maxImageSize = 10 * 1024 * 1024;
$maxPhotos = 10;

$editData = $_SESSION['listing_edit'] ?? null;

$isEditMode = is_array($editData)
    && filter_var(
        $editData['listing_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) !== false;

if (
    $isEditMode &&
    (int) ($editData['seller_user_id'] ?? 0) !== (int) $currentUserId
) {
    http_response_code(403);
    exit('Editing session is not authorized.');
}

$existingPhotos = $isEditMode
    ? ($editData['existing_photos'] ?? [])
    : [];

if (!is_array($existingPhotos)) {
    $existingPhotos = [];
}

$removedPhotoIds = $isEditMode
    ? ($_SESSION['listing']['remove_photo_ids'] ?? [])
    : [];

if (!is_array($removedPhotoIds)) {
    $removedPhotoIds = [];
}

$removedPhotoIds = array_values(array_unique(
    array_filter(array_map('intval', $removedPhotoIds))
));

$escape = static fn($value) => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim((string) ($_POST['title'] ?? ''));
    $price = trim((string) ($_POST['price'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($title === '' || mb_strlen($title) > 100) {
        $errors['title'] =
            'Title is required and must be 100 characters or less.';
    }

    if (
        !preg_match('/^\d+(?:\.\d{1,2})?$/', $price) ||
        (float) $price <= 0 ||
        (float) $price > 99999999.99
    ) {
        $errors['price'] = 'Enter a valid price greater than 0.';
    }

    if ($description === '') {
        $errors['description'] = 'Description is required.';
    }

    $removedPhotoIds = [];

    if ($isEditMode) {
        $submittedIds = $_POST['remove_photo_ids'] ?? [];

        if (!is_array($submittedIds)) {
            $errors['photos'] = 'Invalid photo selection.';
        } else {
            $validIds = array_map(
                'intval',
                array_column($existingPhotos, 'listing_image_id')
            );

            foreach ($submittedIds as $id) {
                if (!is_scalar($id)) {
                    $errors['photos'] = 'Invalid photo selection.';
                    break;
                }

                $photoId = filter_var(
                    $id,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if (
                    $photoId === false ||
                    !in_array($photoId, $validIds, true)
                ) {
                    $errors['photos'] = 'Invalid photo selection.';
                    break;
                }

                $removedPhotoIds[] = $photoId;
            }

            $removedPhotoIds = array_values(
                array_unique($removedPhotoIds)
            );
        }
    }

    $photos = $_FILES['photos'] ?? null;
    $validPhotos = [];

    if (
        $photos &&
        isset($photos['error']) &&
        is_array($photos['error'])
    ) {
        if (count($photos['error']) > $maxPhotos) {
            $errors['photos'] = 'Maximum 10 photos per listing.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($photos['error'] as $index => $error) {

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $errors['photos'] = 'Photo upload failed.';
                break;
            }

            $tmpName = $photos['tmp_name'][$index] ?? '';
            $size = (int) ($photos['size'][$index] ?? 0);

            if (
                $size <= 0 ||
                $size > $maxImageSize ||
                !is_uploaded_file($tmpName)
            ) {
                $errors['photos'] =
                    'Each photo must be a valid file under 10MB.';
                break;
            }

            $mime = $finfo->file($tmpName);

            if (
                !isset($allowedImageTypes[$mime]) ||
                @getimagesize($tmpName) === false
            ) {
                $errors['photos'] =
                    'Photos must be JPG, PNG, or WEBP images.';
                break;
            }

            $validPhotos[] = [
                'tmp_name' => $tmpName,
                'extension' => $allowedImageTypes[$mime],
            ];
        }
    }

    $retainedCount = $isEditMode
        ? count($existingPhotos) - count($removedPhotoIds)
        : 0;

    $totalPhotos = $retainedCount + count($validPhotos);

    if ($totalPhotos < 1 || $totalPhotos > $maxPhotos) {
        $errors['photos'] =
            'Please keep between 1 and 10 photos.';
    }

    if ($errors === []) {

        $draftDirectory =
            sys_get_temp_dir() . '/omnibuy-listing-drafts';

        if (
            !is_dir($draftDirectory) &&
            !mkdir($draftDirectory, 0700, true)
        ) {
            $errors['photos'] =
                'Unable to prepare photo storage.';
        } else {

            $savedPhotos = [];

            try {
                foreach ($validPhotos as $photo) {

                    $filename = bin2hex(random_bytes(16))
                        . '.' . $photo['extension'];

                    $destination =
                        $draftDirectory . '/' . $filename;

                    if (!move_uploaded_file(
                        $photo['tmp_name'],
                        $destination
                    )) {
                        throw new RuntimeException(
                            'Photo upload failed.'
                        );
                    }

                    $savedPhotos[] = $destination;
                }

                foreach (
                    $_SESSION['listing']['photos'] ?? []
                    as $oldPhoto
                ) {
                    if (
                        is_string($oldPhoto) &&
                        dirname($oldPhoto) === $draftDirectory &&
                        is_file($oldPhoto)
                    ) {
                        @unlink($oldPhoto);
                    }
                }

                $_SESSION['listing']['title'] = $title;
                $_SESSION['listing']['price'] = $price;
                $_SESSION['listing']['description'] = $description;
                $_SESSION['listing']['photos'] = $savedPhotos;
                $_SESSION['listing']['remove_photo_ids'] =
                    $removedPhotoIds;

                header(
                    'Location: create-listing-details.php',
                    true,
                    303
                );
                exit;

            } catch (Throwable $e) {

                foreach ($savedPhotos as $path) {
                    @unlink($path);
                }

                error_log(
                    'Listing photo staging failed: '
                    . $e->getMessage()
                );

                $errors['photos'] =
                    'Unable to save photos. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../components/header.php';

?>

<link rel="stylesheet" href="/assets/css/create-listing.css">

<section class="shell create-listing">

    <h1>
        <?= $isEditMode ? 'Edit Listing' : 'Create a Listing' ?>
    </h1>

    <form
        class="create-listing__form"
        method="post"
        enctype="multipart/form-data"
    >

        <div class="create-listing__field">
            <label for="title">Listing Title</label>

            <input
                type="text"
                id="title"
                name="title"
                maxlength="100"
                value="<?= $escape($title) ?>"
                required
            >

            <?php if (isset($errors['title'])): ?>
                <p role="alert"><?= $escape($errors['title']) ?></p>
            <?php endif; ?>
        </div>

        <div class="create-listing__field">
            <label for="price">Price ($)</label>

            <input
                type="number"
                id="price"
                name="price"
                step="0.01"
                min="0.01"
                max="99999999.99"
                value="<?= $escape($price) ?>"
                required
            >

            <?php if (isset($errors['price'])): ?>
                <p role="alert"><?= $escape($errors['price']) ?></p>
            <?php endif; ?>
        </div>

        <div class="create-listing__field">
            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                rows="6"
                required
            ><?= $escape($description) ?></textarea>

            <p class="create-listing__hint">
                Having a description helps the item sell faster.
            </p>

            <?php if (isset($errors['description'])): ?>
                <p role="alert">
                    <?= $escape($errors['description']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="create-listing__field">

            <div class="photo-heading">
                <label for="photos">Listing Photos</label>
                <span id="photo-count">0/10 Photos</span>
            </div>

            <p class="create-listing__hint">
                Add photos using the + button. Remove any photo
                using X. JPG, PNG, or WEBP. Maximum 10MB each.
            </p>

            <div
                id="listing-photo-grid"
                class="create-listing__photo-grid"
            >

                <?php if ($isEditMode): ?>
                    <?php foreach ($existingPhotos as $photo): ?>

                        <?php
                        $photoId = (int) $photo['listing_image_id'];
                        $isRemoved = in_array(
                            $photoId,
                            $removedPhotoIds,
                            true
                        );
                        ?>

                        <div
                            class="create-listing__photo-item existing-photo <?= $isRemoved ? 'is-removed' : '' ?>"
                        >
                            <img
                                src="<?= $escape($photo['image_url']) ?>"
                                alt="Existing listing photo"
                                loading="lazy"
                            >

                            <button
                                type="button"
                                class="create-listing__remove-photo"
                                aria-label="<?= $isRemoved ? 'Restore photo' : 'Remove photo' ?>"
                            ><?= $isRemoved ? '+' : '&times;' ?></button>

                            <input
                                type="checkbox"
                                name="remove_photo_ids[]"
                                value="<?= $photoId ?>"
                                <?= $isRemoved ? 'checked' : '' ?>
                                hidden
                            >
                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>

                <button
                    type="button"
                    id="add-photo-button"
                    class="create-listing__add-photo"
                >
                    <span class="add-photo-plus">+</span>
                    <span>Add Photo</span>
                </button>

            </div>

            <input
                type="file"
                id="photos"
                name="photos[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                class="photo-file-input"
                aria-label="Choose listing photos"
            >

            <p
                id="photo-error"
                role="alert"
                hidden
            ></p>

            <?php if (isset($errors['photos'])): ?>
                <p role="alert">
                    <?= $escape($errors['photos']) ?>
                </p>
            <?php endif; ?>

        </div>

        <button
            class="create-listing__submit"
            type="submit"
        >
            Continue
        </button>

    </form>

</section>

<style>
.create-listing__photo-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 15px 0;
}

.photo-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.create-listing__photo-item {
    position: relative;
    width: 130px;
    height: 130px;
    flex-shrink: 0;
}

.create-listing__photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.create-listing__remove-photo {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: #dc2626;
    color: white;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    z-index: 2;
}

.create-listing__photo-item.is-removed img {
    opacity: 0.3;
}

.create-listing__add-photo {
    width: 130px;
    height: 130px;
    border: 2px dashed #9ca3af;
    border-radius: 8px;
    background: #f9fafb;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-weight: 600;
    color: #374151;
}

.create-listing__add-photo:hover {
    border-color: #2563eb;
    background: #eff6ff;
}

.create-listing__add-photo[hidden] {
    display: none;
}

.add-photo-plus {
    font-size: 32px;
    line-height: 1;
}

.photo-file-input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
}

#photo-error {
    color: #b91c1c;
}
</style>

<script>
(() => {
    const form = document.querySelector('.create-listing__form');
    const fileInput = document.getElementById('photos');
    const addButton = document.getElementById('add-photo-button');
    const grid = document.getElementById('listing-photo-grid');
    const counter = document.getElementById('photo-count');
    const error = document.getElementById('photo-error');

    const selectedFiles = [];
    const maxPhotos = 10;
    const maxSize = 10 * 1024 * 1024;
    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    function showError(message) {
        error.textContent = message;
        error.hidden = !message;
    }

    function existingCount() {
        return Array.from(
            grid.querySelectorAll(
                '.existing-photo input[name="remove_photo_ids[]"]'
            )
        ).filter(checkbox => !checkbox.checked).length;
    }

    function totalCount() {
        return existingCount() + selectedFiles.length;
    }

    function syncFiles() {
        const transfer = new DataTransfer();

        selectedFiles.forEach(file => {
            transfer.items.add(file);
        });

        fileInput.files = transfer.files;
    }

    function updateCounter() {
        const total = totalCount();

        counter.textContent = `${total}/10 Photos`;
        addButton.hidden = total >= maxPhotos;
    }

    function renderNewPhotos() {
        grid.querySelectorAll('.new-photo').forEach(item => {
            item.remove();
        });

        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className =
                'create-listing__photo-item new-photo';

            const image = document.createElement('img');
            const previewUrl = URL.createObjectURL(file);

            image.src = previewUrl;
            image.alt = 'New listing photo';

            image.addEventListener('load', () => {
                URL.revokeObjectURL(previewUrl);
            }, { once: true });

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className =
                'create-listing__remove-photo';
            removeButton.textContent = '×';
            removeButton.setAttribute(
                'aria-label',
                'Remove new photo'
            );

            removeButton.addEventListener('click', () => {
                selectedFiles.splice(index, 1);
                syncFiles();
                renderNewPhotos();
                showError('');
            });

            item.append(image, removeButton);
            grid.insertBefore(item, addButton);
        });

        updateCounter();
    }

    grid.querySelectorAll('.existing-photo').forEach(item => {
        const button = item.querySelector(
            '.create-listing__remove-photo'
        );
        const checkbox = item.querySelector(
            'input[name="remove_photo_ids[]"]'
        );

        button.addEventListener('click', () => {
            checkbox.checked = !checkbox.checked;

            item.classList.toggle(
                'is-removed',
                checkbox.checked
            );

            button.textContent = checkbox.checked ? '+' : '×';
            button.setAttribute(
                'aria-label',
                checkbox.checked ? 'Restore photo' : 'Remove photo'
            );

            updateCounter();
            showError('');
        });
    });

    addButton.addEventListener('click', () => {
        // Clear the picker without clearing selectedFiles.
        fileInput.value = '';
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        const incoming = Array.from(fileInput.files);

        for (const file of incoming) {
            if (!allowedTypes.includes(file.type)) {
                showError('Only JPG, PNG, and WEBP images are allowed.');
                continue;
            }

            if (file.size <= 0 || file.size > maxSize) {
                showError('Each photo must be under 10MB.');
                continue;
            }

            if (totalCount() >= maxPhotos) {
                showError('Maximum 10 photos per listing.');
                break;
            }

            selectedFiles.push(file);
        }

        syncFiles();
        renderNewPhotos();
    });

    form.addEventListener('submit', event => {
        const total = totalCount();

        if (total < 1 || total > maxPhotos) {
            event.preventDefault();
            showError('Please keep between 1 and 10 photos.');
            return;
        }

        syncFiles();
    });

    updateCounter();
})();
</script>

<?php
include __DIR__ . '/../components/footer.php';
?>
