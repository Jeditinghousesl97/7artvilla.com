<?php

if (!function_exists('stay_slugify')) {
    function stay_slugify(string $text, string $fallback = 'item'): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        $text = trim($text, '-');
        return $text !== '' ? $text : $fallback;
    }
}

if (!function_exists('stay_detect_upload_mime_type')) {
    function stay_detect_upload_mime_type(string $tmp_file, string $original_name = ''): string
    {
        if ($tmp_file === '' || !is_file($tmp_file)) {
            return '';
        }

        $normalize = static function (string $mime): string {
            $mime = strtolower(trim($mime));
            return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        };

        if (function_exists('finfo_open') && function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmp_file);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $normalize($mime);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmp_file);
            if (is_string($mime) && $mime !== '') {
                return $normalize($mime);
            }
        }

        if (function_exists('exif_imagetype')) {
            $img_type = @exif_imagetype($tmp_file);
            $map = [
                IMAGETYPE_JPEG => 'image/jpeg',
                IMAGETYPE_PNG  => 'image/png',
                IMAGETYPE_WEBP => 'image/webp',
            ];
            if ($img_type && isset($map[$img_type])) {
                return $map[$img_type];
            }
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($tmp_file);
            if (is_array($info) && !empty($info['mime'])) {
                return $normalize((string)$info['mime']);
            }
        }

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $ext_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        return $ext_map[$ext] ?? '';
    }
}

if (!function_exists('stay_save_uploaded_image')) {
    function stay_save_uploaded_image(array $file, string $dest_dir, string $public_dir, string $prefix, array &$errors): ?string
    {
        if (empty($file['name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
            return null;
        }

        $mime = stay_detect_upload_mime_type((string)($file['tmp_name'] ?? ''), (string)($file['name'] ?? ''));
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Invalid image type. Only JPG, PNG, and WebP are allowed.';
            return null;
        }
        if ((int)($file['size'] ?? 0) > 25 * 1024 * 1024) {
            $errors[] = 'Image must be under 25MB.';
            return null;
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        }

        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }

        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
            $errors[] = 'Failed to save uploaded image.';
            return null;
        }

        return rtrim($public_dir, '/') . '/' . $filename;
    }
}

if (!function_exists('stay_delete_public_file')) {
    function stay_public_file_has_references(string $path): bool
    {
        $path = trim($path);
        if ($path === '' || !function_exists('db')) {
            return false;
        }

        static $reference_map = [
            ['table' => 'villas', 'column' => 'hero_image_path'],
            ['table' => 'villas', 'column' => 'featured_image_path'],
            ['table' => 'villa_gallery_images', 'column' => 'image_path'],
            ['table' => 'villa_spaces', 'column' => 'featured_image_path'],
            ['table' => 'villa_space_gallery_images', 'column' => 'image_path'],
            ['table' => 'villa_space_showcase_images', 'column' => 'image_path'],
            ['table' => 'bookable_units', 'column' => 'featured_image_path'],
            ['table' => 'bookable_unit_gallery_images', 'column' => 'image_path'],
        ];

        try {
            $pdo = db();
            foreach ($reference_map as $ref) {
                $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', $ref['table'], $ref['column']);
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$path]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            return true;
        }

        return false;
    }

    function stay_delete_public_file(string $path): void
    {
        if ($path === '') {
            return;
        }
        if (stay_public_file_has_references($path)) {
            return;
        }
        $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if (!function_exists('stay_space_type_labels')) {
    function stay_space_type_labels(): array
    {
        return [
            'kabana'    => 'Kabana',
            'sub_villa' => 'Sub Villa',
            'block'     => 'Block',
            'wing'      => 'Wing',
            'other'     => 'Other',
        ];
    }
}

if (!function_exists('stay_unit_type_labels')) {
    function stay_unit_type_labels(): array
    {
        return [
            'room'         => 'Room',
            'suite'        => 'Suite',
            'family_room'  => 'Family Room',
            'entire_villa' => 'Entire Villa',
            'custom'       => 'Custom',
        ];
    }
}

if (!function_exists('stay_ensure_schema')) {
    function stay_ensure_schema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS villas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(180) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                location_label VARCHAR(160) DEFAULT NULL,
                tagline VARCHAR(255) DEFAULT NULL,
                short_description TEXT DEFAULT NULL,
                description LONGTEXT DEFAULT NULL,
                hero_image_path VARCHAR(255) DEFAULT NULL,
                featured_image_path VARCHAR(255) DEFAULT NULL,
                checkin_time VARCHAR(60) DEFAULT NULL,
                checkout_time VARCHAR(60) DEFAULT NULL,
                min_stay VARCHAR(80) DEFAULT NULL,
                extra_guest_charge VARCHAR(160) DEFAULT NULL,
                pricing_note TEXT DEFAULT NULL,
                max_guests VARCHAR(60) DEFAULT NULL,
                bedrooms VARCHAR(80) DEFAULT NULL,
                pool_label VARCHAR(120) DEFAULT NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                is_homepage TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS villa_spaces (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                villa_id INT UNSIGNED NOT NULL,
                name VARCHAR(180) NOT NULL,
                slug VARCHAR(200) NOT NULL,
                subtitle VARCHAR(255) DEFAULT NULL,
                space_type ENUM('kabana','sub_villa','block','wing','other') NOT NULL DEFAULT 'kabana',
                short_description TEXT DEFAULT NULL,
                description LONGTEXT DEFAULT NULL,
                featured_image_path VARCHAR(255) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_villa_space_slug (villa_id, slug),
                KEY idx_villa_spaces_villa (villa_id),
                CONSTRAINT fk_villa_spaces_villa FOREIGN KEY (villa_id) REFERENCES villas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS villa_gallery_images (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                villa_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_villa_gallery_villa (villa_id),
                CONSTRAINT fk_villa_gallery_villa FOREIGN KEY (villa_id) REFERENCES villas(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS villa_space_gallery_images (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                villa_space_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_villa_space_gallery_space (villa_space_id),
                CONSTRAINT fk_villa_space_gallery_space FOREIGN KEY (villa_space_id) REFERENCES villa_spaces(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS villa_space_showcase_images (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                villa_space_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_villa_space_showcase_space (villa_space_id),
                CONSTRAINT fk_villa_space_showcase_space FOREIGN KEY (villa_space_id) REFERENCES villa_spaces(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bookable_units (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                villa_id INT UNSIGNED NOT NULL,
                villa_space_id INT UNSIGNED DEFAULT NULL,
                name VARCHAR(180) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                subtitle VARCHAR(255) DEFAULT NULL,
                unit_type ENUM('room','suite','family_room','entire_villa','custom') NOT NULL DEFAULT 'room',
                summary TEXT DEFAULT NULL,
                description LONGTEXT DEFAULT NULL,
                max_guests VARCHAR(60) DEFAULT NULL,
                bed_info VARCHAR(120) DEFAULT NULL,
                size_label VARCHAR(120) DEFAULT NULL,
                featured_image_path VARCHAR(255) DEFAULT NULL,
                pricing_note TEXT DEFAULT NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_bookable_units_villa (villa_id),
                KEY idx_bookable_units_space (villa_space_id),
                CONSTRAINT fk_bookable_units_villa FOREIGN KEY (villa_id) REFERENCES villas(id) ON DELETE CASCADE,
                CONSTRAINT fk_bookable_units_space FOREIGN KEY (villa_space_id) REFERENCES villa_spaces(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bookable_unit_gallery_images (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bookable_unit_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_bookable_unit_gallery_unit (bookable_unit_id),
                CONSTRAINT fk_bookable_unit_gallery_unit FOREIGN KEY (bookable_unit_id) REFERENCES bookable_units(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS unit_pricing (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bookable_unit_id INT UNSIGNED NOT NULL,
                label VARCHAR(80) NOT NULL,
                days VARCHAR(120) NOT NULL,
                price_lkr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                features TEXT DEFAULT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_unit_pricing_unit (bookable_unit_id),
                CONSTRAINT fk_unit_pricing_unit FOREIGN KEY (bookable_unit_id) REFERENCES bookable_units(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS unit_images (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bookable_unit_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_unit_images_unit (bookable_unit_id),
                CONSTRAINT fk_unit_images_unit FOREIGN KEY (bookable_unit_id) REFERENCES bookable_units(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS amenities (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                icon VARCHAR(80) DEFAULT NULL,
                category VARCHAR(80) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS unit_amenity_map (
                bookable_unit_id INT UNSIGNED NOT NULL,
                amenity_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (bookable_unit_id, amenity_id),
                KEY idx_unit_amenity_amenity (amenity_id),
                CONSTRAINT fk_unit_amenity_unit FOREIGN KEY (bookable_unit_id) REFERENCES bookable_units(id) ON DELETE CASCADE,
                CONSTRAINT fk_unit_amenity_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM inquiries') as $col) {
            $cols[$col['Field']] = true;
        }
        if (empty($cols['inquiry_type'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN inquiry_type ENUM('general','stay','tour') NOT NULL DEFAULT 'general' AFTER id");
        }
        if (empty($cols['villa_id'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN villa_id INT UNSIGNED NULL AFTER inquiry_type");
        }
        if (empty($cols['villa_space_id'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN villa_space_id INT UNSIGNED NULL AFTER villa_id");
        }
        if (empty($cols['bookable_unit_id'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN bookable_unit_id INT UNSIGNED NULL AFTER villa_space_id");
        }
        if (empty($cols['guest_count'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN guest_count VARCHAR(60) NULL AFTER checkout");
        }
        if (empty($cols['source_page'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN source_page VARCHAR(120) NULL AFTER message");
        }
        if (empty($cols['subject_label'])) {
            $pdo->exec("ALTER TABLE inquiries ADD COLUMN subject_label VARCHAR(255) NULL AFTER source_page");
        }

        stay_seed_legacy_data($pdo);
    }
}

if (!function_exists('stay_seed_legacy_data')) {
    function stay_seed_legacy_data(PDO $pdo): void
    {
        $villa_count = (int)$pdo->query("SELECT COUNT(*) FROM villas")->fetchColumn();
        if ($villa_count > 0) {
            return;
        }

        $settings = $pdo->query("SELECT setting_key, setting_val FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $villa_name = trim((string)($settings['about_heading'] ?? 'Arugam Bay Villa'));
        if ($villa_name === '') {
            $villa_name = 'Arugam Bay Villa';
        }

        $hero_image = trim((string)($settings['hero_image'] ?? ''));
        $villa_stmt = $pdo->prepare("
            INSERT INTO villas
                (name, slug, location_label, tagline, short_description, description, hero_image_path, featured_image_path,
                 checkin_time, checkout_time, min_stay, extra_guest_charge, pricing_note, max_guests, bedrooms, pool_label,
                 is_featured, is_homepage, is_active, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $villa_stmt->execute([
            'Arugam Bay Villa',
            'arugam-bay-villa',
            'Ella, Sri Lanka',
            $settings['about_label'] ?? 'Nature Beach Escape',
            $settings['about_paragraph1'] ?? '',
            trim((string)(($settings['about_paragraph1'] ?? '') . "\n\n" . ($settings['about_paragraph2'] ?? ''))),
            $hero_image ?: null,
            $hero_image ?: null,
            $settings['checkin_time'] ?? null,
            $settings['checkout_time'] ?? null,
            $settings['min_stay'] ?? null,
            $settings['extra_guest_charge'] ?? null,
            $settings['pricing_note'] ?? null,
            $settings['villa_capacity'] ?? null,
            $settings['villa_bedrooms'] ?? null,
            $settings['villa_pool'] ?? null,
            1,
            1,
            1,
            1,
        ]);
        $villa_id = (int)$pdo->lastInsertId();

        $space_stmt = $pdo->prepare("
            INSERT INTO villa_spaces
                (villa_id, name, slug, subtitle, space_type, short_description, description, featured_image_path, is_active, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $space_stmt->execute([
            $villa_id,
            'Main Villa',
            'main-villa',
            'Primary stay space',
            'sub_villa',
            'Default stay area migrated from the legacy single-villa site.',
            'This space was created automatically during migration from the legacy single-villa structure.',
            $hero_image ?: null,
            1,
            1,
        ]);
        $space_id = (int)$pdo->lastInsertId();

        $unit_stmt = $pdo->prepare("
            INSERT INTO bookable_units
                (villa_id, villa_space_id, name, slug, subtitle, unit_type, summary, description, max_guests, bed_info, featured_image_path, pricing_note, is_featured, is_active, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $unit_stmt->execute([
            $villa_id,
            $space_id,
            'Entire Villa',
            'entire-villa',
            'Legacy migrated stay option',
            'entire_villa',
            'Complete villa booking',
            'This unit was created automatically from the previous single-villa booking model.',
            $settings['villa_capacity'] ?? null,
            $settings['villa_bedrooms'] ?? null,
            $hero_image ?: null,
            $settings['pricing_note'] ?? null,
            1,
            1,
            1,
        ]);
        $unit_id = (int)$pdo->lastInsertId();

        if ((int)$pdo->query("SELECT COUNT(*) FROM unit_pricing")->fetchColumn() === 0 && (int)$pdo->query("SELECT COUNT(*) FROM villa_pricing")->fetchColumn() > 0) {
            $legacy_rows = $pdo->query("SELECT * FROM villa_pricing ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $price_stmt = $pdo->prepare("
                INSERT INTO unit_pricing
                    (bookable_unit_id, label, days, price_lkr, price_usd, is_featured, features, sort_order)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            foreach ($legacy_rows as $row) {
                $price_stmt->execute([
                    $unit_id,
                    $row['label'],
                    $row['days'],
                    $row['price_lkr'],
                    $row['price_usd'],
                    $row['is_featured'],
                    $row['features'],
                    $row['sort_order'],
                ]);
            }
        }

        if ((int)$pdo->query("SELECT COUNT(*) FROM amenities")->fetchColumn() === 0) {
            $insertAmenity = $pdo->prepare("INSERT INTO amenities (name, icon, category, description, is_active, sort_order) VALUES (?,?,?,?,?,?)");
            $defaults = [
                ['Wi-Fi', 'fa-wifi', 'General', 'High-speed Wi-Fi access.', 1, 1],
                ['Air Conditioning', 'fa-fan', 'Comfort', 'Air-conditioned comfort for every stay.', 1, 2],
                ['Private Pool', 'fa-swimming-pool', 'Outdoor', 'Private pool access where available.', 1, 3],
                ['Breakfast Included', 'fa-utensils', 'Dining', 'Breakfast included with selected stays.', 1, 4],
            ];
            foreach ($defaults as $amenity) {
                $insertAmenity->execute($amenity);
            }
        }
    }
}

if (!function_exists('stay_fetch_villa_options')) {
    function stay_fetch_villa_options(PDO $pdo): array
    {
        stay_ensure_schema($pdo);
        return $pdo->query("SELECT id, name FROM villas WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('stay_fetch_space_options')) {
    function stay_fetch_space_options(PDO $pdo, ?int $villa_id = null): array
    {
        stay_ensure_schema($pdo);
        if ($villa_id) {
            $stmt = $pdo->prepare("SELECT id, name, villa_id FROM villa_spaces WHERE villa_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$villa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $pdo->query("SELECT id, name, villa_id FROM villa_spaces ORDER BY villa_id ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
