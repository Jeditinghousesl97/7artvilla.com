<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

if (!function_exists('ensure_tour_price_columns')) {
    function ensure_tour_price_columns(PDO $pdo) {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM tours') as $col) {
            $cols[$col['Field']] = true;
        }
        if (empty($cols['price_lkr'])) {
            $pdo->exec('ALTER TABLE tours ADD COLUMN price_lkr DECIMAL(10,2) NULL DEFAULT NULL AFTER price');
        }
        if (empty($cols['price_usd'])) {
            $pdo->exec('ALTER TABLE tours ADD COLUMN price_usd DECIMAL(10,2) NULL DEFAULT NULL AFTER price_lkr');
        }
    }
}

ensure_tour_price_columns($pdo);

// Ensure tour album table exists (safe for existing installs)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tour_gallery_images (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tour_id    INT UNSIGNED NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        caption    VARCHAR(255) DEFAULT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tour_gallery_tour (tour_id),
        CONSTRAINT fk_tour_gallery_tour FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS tour_itinerary_items (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tour_id      INT UNSIGNED NOT NULL,
        title        VARCHAR(180) NOT NULL,
        description  TEXT DEFAULT NULL,
        image_1_path VARCHAR(255) DEFAULT NULL,
        image_2_path VARCHAR(255) DEFAULT NULL,
        sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tour_itinerary_tour (tour_id),
        CONSTRAINT fk_tour_itinerary_tour FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (!function_exists('detect_upload_mime_type')) {
    function detect_upload_mime_type($tmp_file) {
        if (!is_string($tmp_file) || $tmp_file === '' || !is_file($tmp_file)) return '';

        if (function_exists('finfo_open') && function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmp_file);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') return strtolower($mime);
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmp_file);
            if (is_string($mime) && $mime !== '') return strtolower($mime);
        }

        if (function_exists('exif_imagetype')) {
            $img_type = @exif_imagetype($tmp_file);
            if ($img_type !== false) {
                $mime = image_type_to_mime_type($img_type);
                if (is_string($mime) && $mime !== '') return strtolower($mime);
            }
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($tmp_file);
            if (is_array($info) && !empty($info['mime'])) return strtolower((string)$info['mime']);
        }

        return '';
    }
}

if (!function_exists('uploaded_file_from_field')) {
    function uploaded_file_from_field($files, $key = null) {
        if (!$files || !isset($files['name'])) return null;
        if ($key === null) {
            return [
                'name' => $files['name'] ?? '',
                'tmp_name' => $files['tmp_name'] ?? '',
                'size' => (int)($files['size'] ?? 0),
                'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
            ];
        }
        return [
            'name' => $files['name'][$key] ?? '',
            'tmp_name' => $files['tmp_name'][$key] ?? '',
            'size' => (int)($files['size'][$key] ?? 0),
            'error' => (int)($files['error'][$key] ?? UPLOAD_ERR_NO_FILE),
        ];
    }
}

if (!function_exists('save_tour_image_upload')) {
    function save_tour_image_upload($file, $dest_dir, $public_dir, $prefix, array &$errors) {
        if (!$file || empty($file['name']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = htmlspecialchars((string)$file['name']) . ': Upload failed.';
            return null;
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = detect_upload_mime_type($file['tmp_name'] ?? '');
        if (!in_array($mime, $allowed_types, true)) {
            $errors[] = htmlspecialchars((string)$file['name']) . ': Invalid image type. Only JPG, PNG, and WebP are allowed.';
            return null;
        }
        if ((int)$file['size'] > 25 * 1024 * 1024) {
            $errors[] = htmlspecialchars((string)$file['name']) . ': Image must be under 25MB.';
            return null;
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        }

        if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errors[] = htmlspecialchars((string)$file['name']) . ': Failed to save image.';
            return null;
        }

        return rtrim($public_dir, '/') . '/' . $filename;
    }
}

// Load existing tour
$tour = [
    'id' => 0, 'title' => '', 'tagline' => '', 'description' => '',
    'category' => 'half-day', 'duration' => '', 'difficulty' => '',
    'max_guests' => '', 'price' => '', 'price_lkr' => '', 'price_usd' => '', 'highlights' => '',
    'is_popular' => 0, 'is_must_do' => 0, 'is_active' => 1,
    'sort_order' => 0, 'image_path' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM tours WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Tour not found.'];
        header('Location: ' . admin_url('tours.php'));
        exit;
    }
    $tour = $row;
    // Decode highlights JSON to newline-separated string for textarea
    $hl = json_decode($tour['highlights'] ?? '[]', true);
    $tour['highlights_text'] = is_array($hl) ? implode("\n", $hl) : '';
} else {
    $tour['highlights_text'] = '';
}

$errors = [];
$tour_album = [];
$tour_itinerary = [];

if ($is_edit) {
    $album_stmt = $pdo->prepare('SELECT * FROM tour_gallery_images WHERE tour_id = ? ORDER BY sort_order ASC, id ASC');
    $album_stmt->execute([$id]);
    $tour_album = $album_stmt->fetchAll(PDO::FETCH_ASSOC);

    $itinerary_stmt = $pdo->prepare('SELECT * FROM tour_itinerary_items WHERE tour_id = ? ORDER BY sort_order ASC, id ASC');
    $itinerary_stmt->execute([$id]);
    $tour_itinerary = $itinerary_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Sanitise inputs
        $title      = trim($_POST['title'] ?? '');
        $tagline    = trim($_POST['tagline'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $category   = $_POST['category'] ?? 'half-day';
        $duration   = trim($_POST['duration'] ?? '');
        $difficulty = trim($_POST['difficulty'] ?? '');
        $max_guests = trim($_POST['max_guests'] ?? '');
        $price_lkr_raw = trim($_POST['price_lkr'] ?? '');
        $price_usd_raw = trim($_POST['price_usd'] ?? '');
        $price_lkr     = $price_lkr_raw === '' ? null : (float)$price_lkr_raw;
        $price_usd     = $price_usd_raw === '' ? null : (float)$price_usd_raw;
        $price         = $price_lkr ?? 0;
        $hl_text    = trim($_POST['highlights'] ?? '');
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $is_must_do = isset($_POST['is_must_do']) ? 1 : 0;
        $is_active  = isset($_POST['is_active'])  ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        $allowed_cats = ['half-day', 'full-day', 'sunrise'];
        if (!in_array($category, $allowed_cats)) $category = 'half-day';

        // Validate
        if ($title === '')   $errors[] = 'Title is required.';
        if ($desc  === '')   $errors[] = 'Description is required.';
        if ($price_lkr !== null && $price_lkr < 0) $errors[] = 'LKR price cannot be negative.';
        if ($price_usd !== null && $price_usd < 0) $errors[] = 'USD price cannot be negative.';

        // Highlights â†’ JSON array
        $highlights_arr = array_filter(array_map('trim', explode("\n", $hl_text)));
        $highlights_json = json_encode(array_values($highlights_arr));

        // Handle image upload
        $image_path = $tour['image_path'];
        if (!empty($_FILES['image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $mime = detect_upload_mime_type($_FILES['image']['tmp_name'] ?? '');

            if (!in_array($mime, $allowed_types, true)) {
                $errors[] = 'Invalid image type. Only JPG, PNG, and WebP are allowed.';
            } elseif ($_FILES['image']['size'] > 25 * 1024 * 1024) {
                $errors[] = 'Image must be under 25MB.';
            } else {
                $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                }
                $filename = 'tour_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $dest_dir = '../assets/images/tours/';
                if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
                $dest = $dest_dir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    // Delete old image
                    if ($image_path && file_exists('../' . $image_path)) unlink('../' . $image_path);
                    $image_path = 'assets/images/tours/' . $filename;
                } else {
                    $errors[] = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (empty($errors)) {
            $tour_id = $id;
            if ($is_edit) {
                $pdo->prepare('
                    UPDATE tours SET
                        title=?, tagline=?, description=?, category=?,
                        duration=?, difficulty=?, max_guests=?, price=?, price_lkr=?, price_usd=?,
                        highlights=?, is_popular=?, is_must_do=?, is_active=?,
                        sort_order=?, image_path=?
                    WHERE id=?
                ')->execute([
                    $title, $tagline, $desc, $category,
                    $duration, $difficulty, $max_guests, $price, $price_lkr, $price_usd,
                    $highlights_json, $is_popular, $is_must_do, $is_active,
                    $sort_order, $image_path, $id
                ]);
                $success_msg = "Tour \"{$title}\" updated successfully.";
            } else {
                $pdo->prepare('
                    INSERT INTO tours
                        (title, tagline, description, category, duration, difficulty,
                         max_guests, price, price_lkr, price_usd, highlights, is_popular, is_must_do, is_active,
                         sort_order, image_path)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ')->execute([
                    $title, $tagline, $desc, $category,
                    $duration, $difficulty, $max_guests, $price, $price_lkr, $price_usd,
                    $highlights_json, $is_popular, $is_must_do, $is_active,
                    $sort_order, $image_path
                ]);
                $tour_id = (int)$pdo->lastInsertId();
                $success_msg = "Tour \"{$title}\" added successfully.";
            }

            // Album ordering + captions for existing images
            $album_order_csv = trim($_POST['album_order'] ?? '');
            $album_order_ids = [];
            if ($album_order_csv !== '') {
                foreach (explode(',', $album_order_csv) as $oid) {
                    $oid = (int)$oid;
                    if ($oid > 0) $album_order_ids[] = $oid;
                }
            }
            foreach ($album_order_ids as $idx => $img_id) {
                $pdo->prepare('UPDATE tour_gallery_images SET sort_order = ? WHERE id = ? AND tour_id = ?')
                    ->execute([$idx + 1, $img_id, $tour_id]);
            }

            $album_captions = $_POST['album_caption'] ?? [];
            if (is_array($album_captions)) {
                foreach ($album_captions as $img_id => $caption) {
                    $img_id = (int)$img_id;
                    if ($img_id <= 0) continue;
                    $caption = trim((string)$caption);
                    $pdo->prepare('UPDATE tour_gallery_images SET caption = ? WHERE id = ? AND tour_id = ?')
                        ->execute([$caption, $img_id, $tour_id]);
                }
            }

            $album_delete_ids = $_POST['album_delete_ids'] ?? [];
            if (is_array($album_delete_ids) && !empty($album_delete_ids)) {
                $del_stmt = $pdo->prepare('SELECT id, image_path FROM tour_gallery_images WHERE id = ? AND tour_id = ?');
                foreach ($album_delete_ids as $img_id) {
                    $img_id = (int)$img_id;
                    if ($img_id <= 0) continue;
                    $del_stmt->execute([$img_id, $tour_id]);
                    $row = $del_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        if (!empty($row['image_path']) && file_exists('../' . $row['image_path'])) {
                            @unlink('../' . $row['image_path']);
                        }
                        $pdo->prepare('DELETE FROM tour_gallery_images WHERE id = ? AND tour_id = ?')->execute([$img_id, $tour_id]);
                    }
                }
            }

            // New album uploads
            $album_files = $_FILES['album_images'] ?? null;
            if ($album_files && !empty($album_files['name']) && is_array($album_files['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $dest_dir = '../assets/images/tours/album/';
                if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

                $max_sort_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM tour_gallery_images WHERE tour_id = ?');
                $max_sort_stmt->execute([$tour_id]);
                $current_sort = (int)$max_sort_stmt->fetchColumn();

                $new_caps = $_POST['album_new_caption'] ?? [];
                $count = count($album_files['name']);
                for ($i = 0; $i < $count; $i++) {
                    $name = $album_files['name'][$i] ?? '';
                    $tmp  = $album_files['tmp_name'][$i] ?? '';
                    $size = (int)($album_files['size'][$i] ?? 0);
                    if ($name === '' || $tmp === '') continue;

                    $mime = detect_upload_mime_type($tmp);
                    if (!in_array($mime, $allowed_types, true)) continue;
                    if ($size > 25 * 1024 * 1024) continue;

                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                    }

                    $filename = 'tour_album_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $dest_dir . $filename;
                    if (!move_uploaded_file($tmp, $dest)) continue;

                    $current_sort++;
                    $cap = trim((string)($new_caps[$i] ?? ''));
                    $pdo->prepare('INSERT INTO tour_gallery_images (tour_id, image_path, caption, sort_order) VALUES (?,?,?,?)')
                        ->execute([$tour_id, 'assets/images/tours/album/' . $filename, $cap, $current_sort]);
                }
            }

            // Existing itinerary items
            $itinerary_delete_ids = $_POST['itinerary_delete_ids'] ?? [];
            $itinerary_delete_lookup = [];
            if (is_array($itinerary_delete_ids)) {
                foreach ($itinerary_delete_ids as $delete_id) {
                    $delete_id = (int)$delete_id;
                    if ($delete_id > 0) $itinerary_delete_lookup[$delete_id] = true;
                }
            }

            if (!empty($itinerary_delete_lookup)) {
                $it_del_stmt = $pdo->prepare('SELECT * FROM tour_itinerary_items WHERE id = ? AND tour_id = ?');
                foreach (array_keys($itinerary_delete_lookup) as $item_id) {
                    $it_del_stmt->execute([$item_id, $tour_id]);
                    $row = $it_del_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) continue;
                    foreach (['image_1_path', 'image_2_path'] as $img_field) {
                        if (!empty($row[$img_field]) && file_exists('../' . $row[$img_field])) {
                            @unlink('../' . $row[$img_field]);
                        }
                    }
                    $pdo->prepare('DELETE FROM tour_itinerary_items WHERE id = ? AND tour_id = ?')->execute([$item_id, $tour_id]);
                }
            }

            $it_titles = $_POST['itinerary_title'] ?? [];
            if (is_array($it_titles)) {
                $it_descs = $_POST['itinerary_description'] ?? [];
                $it_sorts = $_POST['itinerary_sort_order'] ?? [];
                $it_img_1_files = $_FILES['itinerary_image_1'] ?? null;
                $it_img_2_files = $_FILES['itinerary_image_2'] ?? null;
                $it_fetch_stmt = $pdo->prepare('SELECT * FROM tour_itinerary_items WHERE id = ? AND tour_id = ?');
                $it_update_stmt = $pdo->prepare('
                    UPDATE tour_itinerary_items
                    SET title = ?, description = ?, image_1_path = ?, image_2_path = ?, sort_order = ?
                    WHERE id = ? AND tour_id = ?
                ');

                foreach ($it_titles as $item_id => $item_title) {
                    $item_id = (int)$item_id;
                    if ($item_id <= 0 || isset($itinerary_delete_lookup[$item_id])) continue;

                    $it_fetch_stmt->execute([$item_id, $tour_id]);
                    $existing = $it_fetch_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$existing) continue;

                    $item_title = trim((string)$item_title);
                    $item_desc = trim((string)($it_descs[$item_id] ?? ''));
                    $item_sort = max(0, (int)($it_sorts[$item_id] ?? 0));
                    if ($item_title === '' && $item_desc === '') continue;

                    $image_1_path = $existing['image_1_path'];
                    $image_2_path = $existing['image_2_path'];
                    $upload_error_count = count($errors);
                    $new_image_1 = save_tour_image_upload(uploaded_file_from_field($it_img_1_files, $item_id), '../assets/images/tours/itinerary/', 'assets/images/tours/itinerary', 'tour_itinerary', $errors);
                    $new_image_2 = save_tour_image_upload(uploaded_file_from_field($it_img_2_files, $item_id), '../assets/images/tours/itinerary/', 'assets/images/tours/itinerary', 'tour_itinerary', $errors);
                    if (count($errors) > $upload_error_count) {
                        foreach ([$new_image_1, $new_image_2] as $new_path) {
                            if ($new_path && file_exists('../' . $new_path)) @unlink('../' . $new_path);
                        }
                        continue;
                    }
                    if ($new_image_1) {
                        if ($image_1_path && file_exists('../' . $image_1_path)) @unlink('../' . $image_1_path);
                        $image_1_path = $new_image_1;
                    }
                    if ($new_image_2) {
                        if ($image_2_path && file_exists('../' . $image_2_path)) @unlink('../' . $image_2_path);
                        $image_2_path = $new_image_2;
                    }

                    $it_update_stmt->execute([$item_title, $item_desc, $image_1_path, $image_2_path, $item_sort, $item_id, $tour_id]);
                }
            }

            // New itinerary items
            $new_it_titles = $_POST['new_itinerary_title'] ?? [];
            if (is_array($new_it_titles)) {
                $new_it_descs = $_POST['new_itinerary_description'] ?? [];
                $new_it_sorts = $_POST['new_itinerary_sort_order'] ?? [];
                $new_it_img_1_files = $_FILES['new_itinerary_image_1'] ?? null;
                $new_it_img_2_files = $_FILES['new_itinerary_image_2'] ?? null;
                $it_insert_stmt = $pdo->prepare('
                    INSERT INTO tour_itinerary_items
                        (tour_id, title, description, image_1_path, image_2_path, sort_order)
                    VALUES (?,?,?,?,?,?)
                ');

                foreach ($new_it_titles as $idx => $item_title) {
                    $item_title = trim((string)$item_title);
                    $item_desc = trim((string)($new_it_descs[$idx] ?? ''));
                    $item_sort = max(0, (int)($new_it_sorts[$idx] ?? 0));
                    $has_image_1 = !empty($new_it_img_1_files['name'][$idx] ?? '');
                    $has_image_2 = !empty($new_it_img_2_files['name'][$idx] ?? '');
                    if ($item_title === '' && $item_desc === '' && !$has_image_1 && !$has_image_2) continue;
                    if ($item_title === '') {
                        $errors[] = 'Itinerary title is required when adding an itinerary item.';
                        continue;
                    }

                    $upload_error_count = count($errors);
                    $image_1_path = save_tour_image_upload(uploaded_file_from_field($new_it_img_1_files, $idx), '../assets/images/tours/itinerary/', 'assets/images/tours/itinerary', 'tour_itinerary', $errors);
                    $image_2_path = save_tour_image_upload(uploaded_file_from_field($new_it_img_2_files, $idx), '../assets/images/tours/itinerary/', 'assets/images/tours/itinerary', 'tour_itinerary', $errors);
                    if (count($errors) > $upload_error_count) {
                        foreach ([$image_1_path, $image_2_path] as $new_path) {
                            if ($new_path && file_exists('../' . $new_path)) @unlink('../' . $new_path);
                        }
                        continue;
                    }
                    $it_insert_stmt->execute([$tour_id, $item_title, $item_desc, $image_1_path, $image_2_path, $item_sort]);
                }
            }

            if (!empty($errors)) {
                $tour = array_merge($tour, compact(
                    'title','tagline','desc','category','duration','difficulty',
                    'max_guests','price','price_lkr','price_usd','is_popular','is_must_do','is_active','sort_order'
                ));
                $tour['description']     = $desc;
                $tour['highlights_text'] = $hl_text;
            } else {
                $_SESSION['flash'] = ['type' => 'success', 'msg' => $success_msg];
                header('Location: ' . admin_url('tours.php'));
                exit;
            }

        }

        // Repopulate form on error
        $tour = array_merge($tour, compact(
            'title','tagline','desc','category','duration','difficulty',
            'max_guests','price','price_lkr','price_usd','is_popular','is_must_do','is_active','sort_order'
        ));
        $tour['description']     = $desc;
        $tour['highlights_text'] = $hl_text;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Tour' : 'Add Tour'; ?> | 7 Art Villa Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<div class="admin-main">

    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div>
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Tour' : 'Add Tour'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars($tour['title']) : 'Create a new tour package'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="tours.php" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Tours
            </a>
        </div>
    </header>

    <div class="admin-content">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="edit-layout">

                <!-- LEFT: Main fields -->
                <div class="edit-main">

                    <!-- Basic Info -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Basic Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group form-full">
                                <label>Tour Title *</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($tour['title']); ?>" placeholder="e.g. Diyaluma Falls Trek" required>
                            </div>
                            <div class="form-group form-full">
                                <label>Tagline</label>
                                <input type="text" name="tagline" value="<?php echo htmlspecialchars($tour['tagline'] ?? ''); ?>" placeholder="Short one-liner shown under the title">
                            </div>
                            <div class="form-group form-full">
                                <label>Description *</label>
                                <textarea name="description" rows="5" placeholder="Full description of the tourâ€¦" required><?php echo htmlspecialchars($tour['description']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Tour Details</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category">
                                    <option value="half-day" <?php echo $tour['category'] === 'half-day' ? 'selected' : ''; ?>>Half Day</option>
                                    <option value="full-day" <?php echo $tour['category'] === 'full-day' ? 'selected' : ''; ?>>Full Day</option>
                                    <option value="sunrise"  <?php echo $tour['category'] === 'sunrise'  ? 'selected' : ''; ?>>Sunrise</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Price (LKR)</label>
                                <input type="number" name="price_lkr" value="<?php echo htmlspecialchars($tour['price_lkr'] ?? ($tour['price'] ?: '')); ?>" placeholder="e.g. 5500" min="0" step="0.01">
                                <span class="form-hint">Optional. Per person.</span>
                            </div>
                            <div class="form-group">
                                <label>Price (USD)</label>
                                <input type="number" name="price_usd" value="<?php echo htmlspecialchars($tour['price_usd'] ?? ''); ?>" placeholder="e.g. 18" min="0" step="0.01">
                                <span class="form-hint">Optional. Approximate per person.</span>
                            </div>
                            <div class="form-group">
                                <label>Duration</label>
                                <input type="text" name="duration" value="<?php echo htmlspecialchars($tour['duration'] ?? ''); ?>" placeholder="e.g. 3â€“4 Hours">
                            </div>
                            <div class="form-group">
                                <label>Difficulty</label>
                                <input type="text" name="difficulty" value="<?php echo htmlspecialchars($tour['difficulty'] ?? ''); ?>" placeholder="e.g. Easy, Moderate">
                            </div>
                            <div class="form-group">
                                <label>Max Guests</label>
                                <input type="text" name="max_guests" value="<?php echo htmlspecialchars($tour['max_guests'] ?? ''); ?>" placeholder="e.g. 2â€“4 Guests">
                            </div>
                            <div class="form-group">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" value="<?php echo (int)$tour['sort_order']; ?>" min="0" placeholder="0">
                                <span class="form-hint">Lower = shown first</span>
                            </div>
                        </div>
                    </div>

                    <!-- Highlights -->
                    <div class="form-card">
                        <div class="form-card-title">Tour Highlights</div>
                        <div style="margin-top:20px">
                            <div class="form-group">
                                <label>Highlights</label>
                                <textarea name="highlights" rows="6" placeholder="Trek to upper natural pools&#10;Guided swimming in rock pools&#10;Panoramic valley viewpoints&#10;Refreshments on the trail"><?php echo htmlspecialchars($tour['highlights_text'] ?? ''); ?></textarea>
                                <span class="form-hint">One highlight per line â€” each becomes a bullet point on the website.</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_edit): ?>
                    <div class="form-card" style="margin-top:20px">
                        <div class="form-card-title">Tour Photo Album</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">Upload multiple photos, add captions, and drag to reorder. Captions show on the tour details page.</p>

                        <div class="form-group" style="margin-bottom:14px">
                            <label>Add Album Photos</label>
                            <input type="file" name="album_images[]" id="albumImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each.</span>
                            <div id="albumNewPreview" class="tour-album-new-previews"></div>
                        </div>

                        <input type="hidden" name="album_order" id="albumOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(function($x) { return (int)$x['id']; }, $tour_album))); ?>">

                        <?php if (!empty($tour_album)): ?>
                        <div id="tourAlbumSortable" class="tour-album-sortable">
                            <?php foreach ($tour_album as $img): ?>
                            <div class="tour-album-item" data-image-id="<?php echo (int)$img['id']; ?>" draggable="true">
                                <div class="tour-album-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
                                <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="" class="tour-album-thumb">
                                <div class="tour-album-fields">
                                    <input type="text" name="album_caption[<?php echo (int)$img['id']; ?>]" value="<?php echo htmlspecialchars($img['caption'] ?? ''); ?>" placeholder="Caption (shown on tour details page)">
                                    <button type="button" class="btn-admin btn-outline btn-sm tour-album-remove" data-delete-id="<?php echo (int)$img['id']; ?>">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="tourAlbumDeleteIds"></div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:16px;border:1px dashed var(--border);border-radius:8px">
                            <p style="margin:0;color:var(--text-muted);font-size:0.8rem">No album photos yet. Upload photos above, then save.</p>
                        </div>
                        <div id="tourAlbumDeleteIds"></div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="form-card" style="margin-top:20px">
                        <div class="form-card-title">Tour Photo Album</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">You can upload album photos while creating this tour. After saving, you can drag to reorder and edit captions anytime.</p>
                        <div class="form-group" style="margin-bottom:0">
                            <label>Add Album Photos</label>
                            <input type="file" name="album_images[]" id="albumImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each.</span>
                            <div id="albumNewPreview" class="tour-album-new-previews"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-card" style="margin-top:20px">
                        <div class="form-card-title">Day By Day Tour Itinerary</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">Optional. Add itinerary sections with a title, description, and up to two photos each.</p>

                        <?php if ($is_edit && !empty($tour_itinerary)): ?>
                        <div class="tour-itinerary-list">
                            <?php foreach ($tour_itinerary as $item): ?>
                            <div class="tour-itinerary-item" data-itinerary-id="<?php echo (int)$item['id']; ?>">
                                <div class="tour-itinerary-item-head">
                                    <strong>Itinerary Item</strong>
                                    <button type="button" class="btn-admin btn-outline btn-sm tour-itinerary-remove" data-delete-id="<?php echo (int)$item['id']; ?>">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                                <div class="form-grid-2" style="margin-top:14px">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="itinerary_title[<?php echo (int)$item['id']; ?>]" value="<?php echo htmlspecialchars($item['title']); ?>" placeholder="e.g. Day 1 - Waterfall Trek">
                                    </div>
                                    <div class="form-group">
                                        <label>Sort Order</label>
                                        <input type="number" name="itinerary_sort_order[<?php echo (int)$item['id']; ?>]" value="<?php echo (int)$item['sort_order']; ?>" min="0">
                                    </div>
                                    <div class="form-group form-full">
                                        <label>Description</label>
                                        <textarea name="itinerary_description[<?php echo (int)$item['id']; ?>]" rows="4" placeholder="Describe this part of the tour"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Photo 1</label>
                                        <?php if (!empty($item['image_1_path']) && file_exists('../' . $item['image_1_path'])): ?>
                                        <img class="tour-itinerary-preview" src="../<?php echo htmlspecialchars($item['image_1_path']); ?>" alt="">
                                        <?php endif; ?>
                                        <input type="file" name="itinerary_image_1[<?php echo (int)$item['id']; ?>]" accept="image/jpeg,image/png,image/webp">
                                    </div>
                                    <div class="form-group">
                                        <label>Photo 2</label>
                                        <?php if (!empty($item['image_2_path']) && file_exists('../' . $item['image_2_path'])): ?>
                                        <img class="tour-itinerary-preview" src="../<?php echo htmlspecialchars($item['image_2_path']); ?>" alt="">
                                        <?php endif; ?>
                                        <input type="file" name="itinerary_image_2[<?php echo (int)$item['id']; ?>]" accept="image/jpeg,image/png,image/webp">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:16px;border:1px dashed var(--border);border-radius:8px;margin-bottom:14px">
                            <p style="margin:0;color:var(--text-muted);font-size:0.8rem">No itinerary items yet. Add one below if this package needs a day-by-day plan.</p>
                        </div>
                        <?php endif; ?>

                        <div id="tourItineraryDeleteIds"></div>
                        <div id="newItineraryWrap" class="tour-itinerary-list">
                            <div class="tour-itinerary-item new-itinerary-item">
                                <div class="tour-itinerary-item-head">
                                    <strong>New Itinerary Item</strong>
                                    <button type="button" class="btn-admin btn-outline btn-sm remove-new-itinerary">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                                <div class="form-grid-2" style="margin-top:14px">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="new_itinerary_title[]" placeholder="e.g. Day 1 - Arrival & Waterfall Visit">
                                    </div>
                                    <div class="form-group">
                                        <label>Sort Order</label>
                                        <input type="number" name="new_itinerary_sort_order[]" value="0" min="0">
                                    </div>
                                    <div class="form-group form-full">
                                        <label>Description</label>
                                        <textarea name="new_itinerary_description[]" rows="4" placeholder="Describe this itinerary item"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Photo 1</label>
                                        <input type="file" name="new_itinerary_image_1[]" accept="image/jpeg,image/png,image/webp">
                                    </div>
                                    <div class="form-group">
                                        <label>Photo 2</label>
                                        <input type="file" name="new_itinerary_image_2[]" accept="image/jpeg,image/png,image/webp">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-admin btn-outline btn-sm" id="addItineraryItem" style="margin-top:12px">
                            <i class="fas fa-plus"></i> Add Another Itinerary Item
                        </button>
                    </div>

                </div>

                <!-- RIGHT: Image + Options  -->
                <div class="edit-sidebar">

                    <!-- Publish -->
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Publish</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_active" value="1" <?php echo $tour['is_active'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Active</strong>
                                    <small>Show this tour on the website</small>
                                </span>
                            </label>
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_popular" value="1" <?php echo $tour['is_popular'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Most Popular</strong>
                                    <small>Show "Most Popular" badge</small>
                                </span>
                            </label>
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_must_do" value="1" <?php echo $tour['is_must_do'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Must Do</strong>
                                    <small>Show "Must Do" badge</small>
                                </span>
                            </label>
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%">
                                <i class="fas fa-<?php echo $is_edit ? 'save' : 'plus'; ?>"></i>
                                <?php echo $is_edit ? 'Save Changes' : 'Add Tour'; ?>
                            </button>
                            <a href="tours.php" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="form-card">
                        <div class="form-card-title">Tour Image</div>
                        <div style="margin-top:16px">
                            <!-- Current image preview -->
                            <?php if ($tour['image_path'] && file_exists('../' . $tour['image_path'])): ?>
                            <div class="img-preview-wrap" style="margin-bottom:12px">
                                <img id="imgPreview" src="../<?php echo htmlspecialchars($tour['image_path']); ?>"
                                     alt="Current image" class="img-preview">
                            </div>
                            <?php else: ?>
                            <div class="img-preview-wrap" style="margin-bottom:12px">
                                <img id="imgPreview" src="" alt="" class="img-preview" style="display:none">
                                <div class="img-placeholder-box" id="imgPlaceholder">
                                    <i class="fas fa-image"></i>
                                    <span>No image uploaded</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Upload Image</label>
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                                       data-preview="imgPreview" id="imageInput">
                                <span class="form-hint">JPG, PNG or WebP â€” max 25MB</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div><!-- /edit-layout -->

        </form>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
<script>
// Show/hide placeholder on image preview
document.getElementById('imageInput')?.addEventListener('change', function() {
    const placeholder = document.getElementById('imgPlaceholder');
    if (placeholder) placeholder.style.display = 'none';
});

// Album new upload preview with caption inputs (matching file index)
const albumInput = document.getElementById('albumImagesInput');
const albumNewPreview = document.getElementById('albumNewPreview');
if (albumInput && albumNewPreview) {
    albumInput.addEventListener('change', function() {
        albumNewPreview.innerHTML = '';
        const files = Array.from(this.files || []);
        files.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'tour-album-new-item';
            const img = document.createElement('img');
            img.alt = '';
            img.src = URL.createObjectURL(file);
            const cap = document.createElement('input');
            cap.type = 'text';
            cap.name = 'album_new_caption[' + idx + ']';
            cap.placeholder = 'Caption for new photo';
            item.appendChild(img);
            item.appendChild(cap);
            albumNewPreview.appendChild(item);
        });
    });
}

// Drag and drop ordering for existing album photos
const sortable = document.getElementById('tourAlbumSortable');
const orderInput = document.getElementById('albumOrderInput');
if (sortable && orderInput) {
    let dragItem = null;

    function syncOrder() {
        const ids = Array.from(sortable.querySelectorAll('.tour-album-item'))
            .map(el => el.getAttribute('data-image-id'))
            .filter(Boolean);
        orderInput.value = ids.join(',');
    }

    sortable.addEventListener('dragstart', (e) => {
        const item = e.target.closest('.tour-album-item');
        if (!item) return;
        dragItem = item;
        item.classList.add('dragging');
    });

    sortable.addEventListener('dragend', () => {
        if (dragItem) dragItem.classList.remove('dragging');
        dragItem = null;
        syncOrder();
    });

    sortable.addEventListener('dragover', (e) => {
        e.preventDefault();
        const target = e.target.closest('.tour-album-item');
        if (!dragItem || !target || target === dragItem) return;
        const rect = target.getBoundingClientRect();
        const after = (e.clientY - rect.top) > (rect.height / 2);
        if (after) {
            target.parentNode.insertBefore(dragItem, target.nextSibling);
        } else {
            target.parentNode.insertBefore(dragItem, target);
        }
    });

    syncOrder();
}

// Mark album image for deletion
const deleteWrap = document.getElementById('tourAlbumDeleteIds');
if (deleteWrap) {
    document.querySelectorAll('.tour-album-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-delete-id');
            const row = btn.closest('.tour-album-item');
            if (!id || !row) return;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'album_delete_ids[]';
            hidden.value = id;
            deleteWrap.appendChild(hidden);
            row.remove();

            const orderInputLocal = document.getElementById('albumOrderInput');
            const sortableLocal = document.getElementById('tourAlbumSortable');
            if (orderInputLocal && sortableLocal) {
                const ids = Array.from(sortableLocal.querySelectorAll('.tour-album-item'))
                    .map(el => el.getAttribute('data-image-id'))
                    .filter(Boolean);
                orderInputLocal.value = ids.join(',');
            }
        });
    });
}

// Optional day-by-day itinerary items
const itineraryDeleteWrap = document.getElementById('tourItineraryDeleteIds');
if (itineraryDeleteWrap) {
    document.querySelectorAll('.tour-itinerary-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-delete-id');
            const row = btn.closest('.tour-itinerary-item');
            if (!id || !row) return;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'itinerary_delete_ids[]';
            hidden.value = id;
            itineraryDeleteWrap.appendChild(hidden);
            row.remove();
        });
    });
}

const newItineraryWrap = document.getElementById('newItineraryWrap');
const addItineraryBtn = document.getElementById('addItineraryItem');

function wireNewItineraryRemove(scope = document) {
    scope.querySelectorAll('.remove-new-itinerary').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => {
            btn.closest('.new-itinerary-item')?.remove();
        });
    });
}

wireNewItineraryRemove();

if (newItineraryWrap && addItineraryBtn) {
    addItineraryBtn.addEventListener('click', () => {
        const first = newItineraryWrap.querySelector('.new-itinerary-item');
        if (!first) return;
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input, textarea').forEach(field => {
            if (field.type === 'file') {
                field.value = '';
            } else if (field.name.includes('new_itinerary_sort_order')) {
                field.value = '0';
            } else {
                field.value = '';
            }
        });
        clone.querySelectorAll('[data-bound]').forEach(el => delete el.dataset.bound);
        newItineraryWrap.appendChild(clone);
        wireNewItineraryRemove(clone);
    });
}
</script>
</body>
</html>
