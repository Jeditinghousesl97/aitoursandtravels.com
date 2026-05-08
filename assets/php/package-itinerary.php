<?php

function nt_ensure_package_itinerary_table(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS package_itinerary_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            package_id INT UNSIGNED NOT NULL,
            day_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            image_1 VARCHAR(300) DEFAULT NULL,
            image_2 VARCHAR(300) DEFAULT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ensured = true;
}

function nt_fetch_package_itinerary(PDO $pdo, int $packageId): array
{
    nt_ensure_package_itinerary_table($pdo);

    $stmt = $pdo->prepare('
        SELECT id, package_id, day_number, title, description, image_1, image_2, sort_order
        FROM package_itinerary_items
        WHERE package_id = ?
        ORDER BY sort_order ASC, id ASC
    ');
    $stmt->execute([$packageId]);

    return $stmt->fetchAll() ?: [];
}

function nt_package_itinerary_form_items(array $post, array $existingItems = []): array
{
    if (!isset($post['itinerary_title']) || !is_array($post['itinerary_title'])) {
        return $existingItems !== [] ? array_values($existingItems) : [[
            'id' => 0,
            'title' => '',
            'description' => '',
            'image_1' => '',
            'image_2' => '',
        ]];
    }

    $ids = $post['itinerary_item_id'] ?? [];
    $titles = $post['itinerary_title'] ?? [];
    $descriptions = $post['itinerary_description'] ?? [];
    $image1 = $post['itinerary_existing_image_1'] ?? [];
    $image2 = $post['itinerary_existing_image_2'] ?? [];
    $count = max(count($ids), count($titles), count($descriptions), count($image1), count($image2));

    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $id = (int)($ids[$i] ?? 0);
        $existing = $id > 0 ? ($existingItems[$id] ?? null) : null;

        $items[] = [
            'id' => $id,
            'title' => (string)($titles[$i] ?? ''),
            'description' => (string)($descriptions[$i] ?? ''),
            'image_1' => (string)($image1[$i] ?? ($existing['image_1'] ?? '')),
            'image_2' => (string)($image2[$i] ?? ($existing['image_2'] ?? '')),
        ];
    }

    return $items !== [] ? $items : [[
        'id' => 0,
        'title' => '',
        'description' => '',
        'image_1' => '',
        'image_2' => '',
    ]];
}

function nt_save_package_itinerary(PDO $pdo, int $packageId, array $post, array $files, array $existingItems, array &$errors): void
{
    nt_ensure_package_itinerary_table($pdo);

    $existingById = [];
    foreach ($existingItems as $item) {
        $existingById[(int)$item['id']] = $item;
    }

    $ids = $post['itinerary_item_id'] ?? [];
    $titles = $post['itinerary_title'] ?? [];
    $descriptions = $post['itinerary_description'] ?? [];
    $existingImage1 = $post['itinerary_existing_image_1'] ?? [];
    $existingImage2 = $post['itinerary_existing_image_2'] ?? [];
    $removeImage1 = $post['itinerary_remove_image_1'] ?? [];
    $removeImage2 = $post['itinerary_remove_image_2'] ?? [];

    $count = max(
        count($ids),
        count($titles),
        count($descriptions),
        nt_uploaded_file_count($files['itinerary_image_1'] ?? null),
        nt_uploaded_file_count($files['itinerary_image_2'] ?? null)
    );

    $itemsToSave = [];
    $newUploads = [];

    for ($i = 0; $i < $count; $i++) {
        $id = (int)($ids[$i] ?? 0);
        $existing = $id > 0 ? ($existingById[$id] ?? null) : null;
        $title = trim((string)($titles[$i] ?? ''));
        $description = trim((string)($descriptions[$i] ?? ''));
        $image1 = trim((string)($existingImage1[$i] ?? ($existing['image_1'] ?? '')));
        $image2 = trim((string)($existingImage2[$i] ?? ($existing['image_2'] ?? '')));

        if (($removeImage1[$i] ?? '0') === '1') {
            $image1 = '';
        }

        if (($removeImage2[$i] ?? '0') === '1') {
            $image2 = '';
        }

        $uploaded1 = nt_store_package_itinerary_upload($files['itinerary_image_1'] ?? null, $i, $errors);
        if ($uploaded1 !== null) {
            $image1 = $uploaded1;
            $newUploads[] = $uploaded1;
        }

        $uploaded2 = nt_store_package_itinerary_upload($files['itinerary_image_2'] ?? null, $i, $errors);
        if ($uploaded2 !== null) {
            $image2 = $uploaded2;
            $newUploads[] = $uploaded2;
        }

        $hasAny = $title !== '' || $description !== '' || $image1 !== '' || $image2 !== '' || $id > 0;
        if (!$hasAny) {
            continue;
        }

        if ($title === '') {
            $errors[] = 'Each itinerary stop needs a title.';
        }

        $itemsToSave[] = [
            'title' => $title,
            'description' => $description,
            'image_1' => $image1,
            'image_2' => $image2,
        ];
    }

    if ($errors !== []) {
        foreach (array_unique($newUploads) as $path) {
            nt_delete_uploaded_file($path);
        }
        return;
    }

    $oldPaths = [];
    foreach ($existingItems as $item) {
        foreach (['image_1', 'image_2'] as $field) {
            $path = trim((string)($item[$field] ?? ''));
            if ($path !== '') {
                $oldPaths[] = $path;
            }
        }
    }

    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM package_itinerary_items WHERE package_id = ?')->execute([$packageId]);

        if ($itemsToSave !== []) {
            $stmt = $pdo->prepare('
                INSERT INTO package_itinerary_items
                    (package_id, day_number, title, description, image_1, image_2, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');

            foreach ($itemsToSave as $index => $item) {
                $dayNumber = $index + 1;
                $stmt->execute([
                    $packageId,
                    $dayNumber,
                    $item['title'],
                    $item['description'],
                    $item['image_1'],
                    $item['image_2'],
                    $index,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach (array_unique($newUploads) as $path) {
            nt_delete_uploaded_file($path);
        }
        $errors[] = 'Failed to save itinerary stops.';
        return;
    }

    $keptPaths = [];
    foreach ($itemsToSave as $item) {
        $keptPaths[] = $item['image_1'];
        $keptPaths[] = $item['image_2'];
    }

    foreach (array_unique($oldPaths) as $path) {
        if (!in_array($path, $keptPaths, true)) {
            nt_delete_uploaded_file($path);
        }
    }
}

function nt_store_package_itinerary_upload(?array $fileGroup, int $index, array &$errors): ?string
{
    if ($fileGroup === null || !isset($fileGroup['error'][$index])) {
        return null;
    }

    $error = (int)$fileGroup['error'][$index];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = 'Failed to upload one of the itinerary images.';
        return null;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $type = (string)($fileGroup['type'][$index] ?? '');
    if (!in_array($type, $allowed, true)) {
        $errors[] = 'Itinerary images must be JPG, PNG or WEBP.';
        return null;
    }

    $size = (int)($fileGroup['size'][$index] ?? 0);
    if ($size > 25 * 1024 * 1024) {
        $errors[] = 'Each itinerary image must be under 25MB.';
        return null;
    }

    $dir = SITE_ROOT . 'uploads/packages/itinerary/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $errors[] = 'Itinerary upload folder could not be created: ' . $dir;
        return null;
    }

    if (!is_writable($dir)) {
        $errors[] = 'Itinerary upload folder is not writable: ' . $dir;
        return null;
    }

    $originalName = (string)($fileGroup['name'][$index] ?? 'image.jpg');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'jpg';
    }

    $name = 'iti_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $dir . $name;
    $tmpName = (string)($fileGroup['tmp_name'][$index] ?? '');

    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'Failed to upload itinerary image.';
        return null;
    }

    return 'uploads/packages/itinerary/' . $name;
}

function nt_uploaded_file_count(?array $fileGroup): int
{
    if ($fileGroup === null || !isset($fileGroup['name']) || !is_array($fileGroup['name'])) {
        return 0;
    }

    return count($fileGroup['name']);
}

function nt_delete_uploaded_file(string $relativePath): void
{
    $path = SITE_ROOT . ltrim($relativePath, '/');
    if (is_file($path)) {
        unlink($path);
    }
}
