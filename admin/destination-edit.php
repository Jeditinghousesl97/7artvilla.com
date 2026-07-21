<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$pdo->exec("CREATE TABLE IF NOT EXISTS destination_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS destinations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_summary TEXT DEFAULT NULL,
    description LONGTEXT NOT NULL,
    map_embed_html LONGTEXT DEFAULT NULL,
    distance_from_villa VARCHAR(120) DEFAULT NULL,
    travel_time_from_villa VARCHAR(120) DEFAULT NULL,
    best_time_to_visit VARCHAR(160) DEFAULT NULL,
    things_to_do LONGTEXT DEFAULT NULL,
    featured_image_path VARCHAR(255) DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_homepage TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS destination_category_map (
    destination_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (destination_id, category_id),
    INDEX idx_dcm_category (category_id),
    CONSTRAINT fk_dcm_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE,
    CONSTRAINT fk_dcm_category FOREIGN KEY (category_id) REFERENCES destination_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS destination_gallery_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    destination_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_destination_gallery (destination_id),
    CONSTRAINT fk_destination_gallery_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function destination_slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'destination';
}

function detect_upload_mime_type(string $tmp_file, string $original_name = ''): string {
    if ($tmp_file === '' || !is_file($tmp_file)) return '';

    $normalize = static function (string $mime): string {
        $mime = strtolower(trim($mime));
        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    };

    if (function_exists('finfo_open') && function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp_file);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') return $normalize($mime);
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp_file);
        if (is_string($mime) && $mime !== '') return $normalize($mime);
    }

    if (function_exists('exif_imagetype')) {
        $img_type = @exif_imagetype($tmp_file);
        $map = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG  => 'image/png',
            IMAGETYPE_WEBP => 'image/webp',
        ];
        if ($img_type && isset($map[$img_type])) return $map[$img_type];
    }

    if (function_exists('getimagesize')) {
        $info = @getimagesize($tmp_file);
        if (is_array($info) && !empty($info['mime'])) return $normalize((string)$info['mime']);
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

$categories = $pdo->query('SELECT id, name FROM destination_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
$destination = [
    'id' => 0, 'title' => '', 'slug' => '', 'short_summary' => '', 'description' => '',
    'map_embed_html' => '', 'distance_from_villa' => '', 'travel_time_from_villa' => '',
    'best_time_to_visit' => '', 'things_to_do_text' => '', 'featured_image_path' => '',
    'is_featured' => 0, 'is_homepage' => 0, 'is_active' => 1, 'sort_order' => 0,
];
$selected_categories = [];
$gallery_images = [];
$errors = [];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM destinations WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Destination not found.'];
        header('Location: destinations.php');
        exit;
    }
    $destination = $row;
    $things = json_decode($destination['things_to_do'] ?? '[]', true);
    $destination['things_to_do_text'] = is_array($things) ? implode("\n", $things) : '';

    $cat_stmt = $pdo->prepare('SELECT category_id FROM destination_category_map WHERE destination_id = ?');
    $cat_stmt->execute([$id]);
    $selected_categories = array_map('intval', $cat_stmt->fetchAll(PDO::FETCH_COLUMN));

    $g_stmt = $pdo->prepare('SELECT * FROM destination_gallery_images WHERE destination_id = ? ORDER BY sort_order ASC, id ASC');
    $g_stmt->execute([$id]);
    $gallery_images = $g_stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = destination_slugify($_POST['slug'] ?? $title);
        $short_summary = trim($_POST['short_summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $map_embed_html = trim($_POST['map_embed_html'] ?? '');
        $distance_from_villa = trim($_POST['distance_from_villa'] ?? '');
        $travel_time_from_villa = trim($_POST['travel_time_from_villa'] ?? '');
        $best_time_to_visit = trim($_POST['best_time_to_visit'] ?? '');
        $things_to_do_text = trim($_POST['things_to_do'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_homepage = isset($_POST['is_homepage']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $selected_categories = array_map('intval', $_POST['category_ids'] ?? []);
        $selected_categories = array_values(array_filter($selected_categories, static fn($v) => $v > 0));

        if ($title === '') $errors[] = 'Destination title is required.';
        if ($description === '') $errors[] = 'Description is required.';

        $todo_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $things_to_do_text) ?: [])));
        $things_to_do_json = json_encode($todo_lines);

        $featured_image_path = $destination['featured_image_path'] ?? '';
        if (!empty($_FILES['featured_image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $mime = detect_upload_mime_type(
                $_FILES['featured_image']['tmp_name'] ?? '',
                (string)($_FILES['featured_image']['name'] ?? '')
            );
            if (!in_array($mime, $allowed_types, true)) {
                $errors[] = 'Featured image must be JPG, PNG, or WebP.';
            } elseif ((int)($_FILES['featured_image']['size'] ?? 0) > 25 * 1024 * 1024) {
                $errors[] = 'Featured image must be under 25MB.';
            } else {
                $ext = strtolower(pathinfo((string)$_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                }
                $filename = 'destination_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest_dir = '../assets/images/destinations/';
                if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
                $dest = $dest_dir . $filename;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $dest)) {
                    if ($featured_image_path && file_exists('../' . $featured_image_path)) unlink('../' . $featured_image_path);
                    $featured_image_path = 'assets/images/destinations/' . $filename;
                } else {
                    $errors[] = 'Failed to upload featured image.';
                }
            }
        }

        if (empty($errors)) {
            try {
                if ($is_edit) {
                    $pdo->prepare('UPDATE destinations SET title=?, slug=?, short_summary=?, description=?, map_embed_html=?, distance_from_villa=?, travel_time_from_villa=?, best_time_to_visit=?, things_to_do=?, featured_image_path=?, is_featured=?, is_homepage=?, is_active=?, sort_order=? WHERE id=?')
                        ->execute([$title, $slug, $short_summary, $description, $map_embed_html, $distance_from_villa, $travel_time_from_villa, $best_time_to_visit, $things_to_do_json, $featured_image_path, $is_featured, $is_homepage, $is_active, $sort_order, $id]);
                    $destination_id = $id;
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Destination updated successfully.'];
                } else {
                    $pdo->prepare('INSERT INTO destinations (title, slug, short_summary, description, map_embed_html, distance_from_villa, travel_time_from_villa, best_time_to_visit, things_to_do, featured_image_path, is_featured, is_homepage, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$title, $slug, $short_summary, $description, $map_embed_html, $distance_from_villa, $travel_time_from_villa, $best_time_to_visit, $things_to_do_json, $featured_image_path, $is_featured, $is_homepage, $is_active, $sort_order]);
                    $destination_id = (int)$pdo->lastInsertId();
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Destination added successfully.'];
                }
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) $errors[] = 'Slug already exists. Use a unique slug.';
                else $errors[] = 'Unable to save destination.';
            }
        }

        if (empty($errors)) {
            $pdo->prepare('DELETE FROM destination_category_map WHERE destination_id = ?')->execute([$destination_id]);
            if (!empty($selected_categories)) {
                $map_stmt = $pdo->prepare('INSERT INTO destination_category_map (destination_id, category_id) VALUES (?,?)');
                foreach ($selected_categories as $cid) $map_stmt->execute([$destination_id, $cid]);
            }

            $gallery_order_csv = trim($_POST['gallery_order'] ?? '');
            $gallery_order_ids = [];
            if ($gallery_order_csv !== '') {
                foreach (explode(',', $gallery_order_csv) as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0) $gallery_order_ids[] = $gid;
                }
            }
            foreach ($gallery_order_ids as $index => $gid) {
                $pdo->prepare('UPDATE destination_gallery_images SET sort_order = ? WHERE id = ? AND destination_id = ?')->execute([$index + 1, $gid, $destination_id]);
            }

            $gallery_captions = $_POST['gallery_caption'] ?? [];
            if (is_array($gallery_captions)) {
                foreach ($gallery_captions as $gid => $caption) {
                    $gid = (int)$gid;
                    if ($gid <= 0) continue;
                    $pdo->prepare('UPDATE destination_gallery_images SET caption = ? WHERE id = ? AND destination_id = ?')->execute([trim((string)$caption), $gid, $destination_id]);
                }
            }

            $gallery_delete_ids = $_POST['gallery_delete_ids'] ?? [];
            if (is_array($gallery_delete_ids)) {
                $img_stmt = $pdo->prepare('SELECT image_path FROM destination_gallery_images WHERE id = ? AND destination_id = ?');
                foreach ($gallery_delete_ids as $gid) {
                    $gid = (int)$gid;
                    if ($gid <= 0) continue;
                    $img_stmt->execute([$gid, $destination_id]);
                    $img_path = $img_stmt->fetchColumn();
                    if ($img_path && file_exists('../' . $img_path)) unlink('../' . $img_path);
                    $pdo->prepare('DELETE FROM destination_gallery_images WHERE id = ? AND destination_id = ?')->execute([$gid, $destination_id]);
                }
            }

            $album_files = $_FILES['gallery_images'] ?? null;
            if ($album_files && !empty($album_files['name']) && is_array($album_files['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $dest_dir = '../assets/images/destinations/gallery/';
                if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

                $max_sort_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM destination_gallery_images WHERE destination_id = ?');
                $max_sort_stmt->execute([$destination_id]);
                $current_sort = (int)$max_sort_stmt->fetchColumn();
                $new_caps = $_POST['gallery_new_caption'] ?? [];
                $count = count($album_files['name']);

                for ($i = 0; $i < $count; $i++) {
                    $name = (string)($album_files['name'][$i] ?? '');
                    $tmp = (string)($album_files['tmp_name'][$i] ?? '');
                    $size = (int)($album_files['size'][$i] ?? 0);
                    if ($name === '' || $tmp === '') continue;
                    $mime = detect_upload_mime_type($tmp, $name);
                    if (!in_array($mime, $allowed_types, true) || $size > 25 * 1024 * 1024) continue;

                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                    }

                    $filename = 'destination_gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $dest_dir . $filename;
                    if (!move_uploaded_file($tmp, $dest)) continue;

                    $current_sort++;
                    $cap = trim((string)($new_caps[$i] ?? ''));
                    $pdo->prepare('INSERT INTO destination_gallery_images (destination_id, image_path, caption, sort_order) VALUES (?,?,?,?)')
                        ->execute([$destination_id, 'assets/images/destinations/gallery/' . $filename, $cap, $current_sort]);
                }
            }

            header('Location: destinations.php');
            exit;
        }

        $destination = array_merge($destination, [
            'title' => $title, 'slug' => $slug, 'short_summary' => $short_summary, 'description' => $description,
            'map_embed_html' => $map_embed_html, 'distance_from_villa' => $distance_from_villa,
            'travel_time_from_villa' => $travel_time_from_villa, 'best_time_to_visit' => $best_time_to_visit,
            'things_to_do_text' => $things_to_do_text, 'is_featured' => $is_featured,
            'is_homepage' => $is_homepage, 'is_active' => $is_active, 'sort_order' => $sort_order,
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Destination' : 'Add Destination'; ?> | We Trail Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Destination' : 'Add Destination'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars((string)$destination['title']) : 'Create a new destination post'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="destinations.php" class="topbar-btn topbar-btn-outline"><i class="fas fa-arrow-left"></i> Back to Destinations</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="edit-layout">
                <div class="edit-main">
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Basic Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group form-full"><label>Destination Title *</label><input type="text" name="title" required value="<?php echo htmlspecialchars((string)$destination['title']); ?>" placeholder="e.g. Diyaluma Falls Upper Pools"></div>
                            <div class="form-group"><label>Slug</label><input type="text" name="slug" value="<?php echo htmlspecialchars((string)$destination['slug']); ?>" placeholder="auto-generated if empty"></div>
                            <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" min="0" value="<?php echo (int)$destination['sort_order']; ?>"></div>
                            <div class="form-group form-full"><label>Short Summary</label><textarea name="short_summary" rows="3" placeholder="Short card summary shown in destination listing"><?php echo htmlspecialchars((string)$destination['short_summary']); ?></textarea></div>
                            <div class="form-group form-full"><label>Main Description *</label><textarea name="description" rows="8" required placeholder="Full destination description"><?php echo htmlspecialchars((string)$destination['description']); ?></textarea></div>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Travel & Location</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group"><label>Distance From Our Villa</label><input type="text" name="distance_from_villa" value="<?php echo htmlspecialchars((string)$destination['distance_from_villa']); ?>" placeholder="e.g. 18 km"></div>
                            <div class="form-group"><label>Expected Travel Time</label><input type="text" name="travel_time_from_villa" value="<?php echo htmlspecialchars((string)$destination['travel_time_from_villa']); ?>" placeholder="e.g. 35 minutes by tuk"></div>
                            <div class="form-group form-full"><label>Best Time to Visit</label><input type="text" name="best_time_to_visit" value="<?php echo htmlspecialchars((string)$destination['best_time_to_visit']); ?>" placeholder="e.g. Early morning or late afternoon"></div>
                            <div class="form-group form-full"><label>Google My Map Embed Code (HTML)</label><textarea name="map_embed_html" rows="6" placeholder="Paste Google My Map iframe/embed HTML here"><?php echo htmlspecialchars((string)$destination['map_embed_html']); ?></textarea></div>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Things To Do</div>
                        <div class="form-group" style="margin-top:20px">
                            <label>Activity List</label>
                            <textarea name="things_to_do" rows="6" placeholder="One activity per line&#10;Swim in natural pools&#10;Enjoy cliff viewpoints&#10;Take drone photography"><?php echo htmlspecialchars((string)$destination['things_to_do_text']); ?></textarea>
                            <span class="form-hint">Enter one activity per line.</span>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-card-title">Gallery Images</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">Upload multiple photos, add captions, and drag to reorder.</p>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Add Gallery Images</label>
                            <input type="file" name="gallery_images[]" id="galleryImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each.</span>
                            <div id="galleryNewPreview" class="tour-album-new-previews"></div>
                        </div>
                        <input type="hidden" name="gallery_order" id="galleryOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(static fn($x) => (int)$x['id'], $gallery_images))); ?>">
                        <?php if (!empty($gallery_images)): ?>
                        <div id="destinationGallerySortable" class="tour-album-sortable">
                            <?php foreach ($gallery_images as $img): ?>
                            <div class="tour-album-item" data-image-id="<?php echo (int)$img['id']; ?>" draggable="true">
                                <div class="tour-album-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
                                <img src="../<?php echo htmlspecialchars((string)$img['image_path']); ?>" alt="" class="tour-album-thumb">
                                <div class="tour-album-fields">
                                    <input type="text" name="gallery_caption[<?php echo (int)$img['id']; ?>]" value="<?php echo htmlspecialchars((string)($img['caption'] ?? '')); ?>" placeholder="Caption">
                                    <button type="button" class="btn-admin btn-outline btn-sm gallery-remove" data-delete-id="<?php echo (int)$img['id']; ?>"><i class="fas fa-trash"></i> Remove</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:16px;border:1px dashed var(--border);border-radius:8px"><p style="margin:0;color:var(--text-muted);font-size:0.8rem">No gallery images yet.</p></div>
                        <?php endif; ?>
                        <div id="galleryDeleteIds"></div>
                    </div>
                </div>

                <div class="edit-sidebar">
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Publish</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?php echo (int)$destination['is_active'] ? 'checked' : ''; ?>><span><strong>Active</strong><small>Show destination on website</small></span></label>
                            <label class="checkbox-row"><input type="checkbox" name="is_featured" value="1" <?php echo (int)$destination['is_featured'] ? 'checked' : ''; ?>><span><strong>Featured Destination</strong><small>Mark as highlighted destination</small></span></label>
                            <label class="checkbox-row"><input type="checkbox" name="is_homepage" value="1" <?php echo (int)$destination['is_homepage'] ? 'checked' : ''; ?>><span><strong>Show on Homepage</strong><small>Include in homepage destination section</small></span></label>
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%"><i class="fas fa-save"></i> <?php echo $is_edit ? 'Save Changes' : 'Add Destination'; ?></button>
                            <a href="destinations.php" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Destination Categories</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
                            <?php if (empty($categories)): ?>
                            <p style="font-size:0.82rem;color:var(--text-muted)">No active categories. Add categories first.</p>
                            <a href="destination-categories.php" class="btn-admin btn-outline btn-sm" style="text-align:center"><i class="fas fa-plus"></i> Add Categories</a>
                            <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                            <label class="checkbox-row" style="padding:4px 0"><input type="checkbox" name="category_ids[]" value="<?php echo (int)$cat['id']; ?>" <?php echo in_array((int)$cat['id'], $selected_categories, true) ? 'checked' : ''; ?>><span><strong><?php echo htmlspecialchars((string)$cat['name']); ?></strong></span></label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-card-title">Featured Image</div>
                        <div style="margin-top:16px">
                            <?php if (!empty($destination['featured_image_path']) && file_exists('../' . $destination['featured_image_path'])): ?>
                            <div class="img-preview-wrap" style="margin-bottom:12px"><img id="featuredImgPreview" src="../<?php echo htmlspecialchars((string)$destination['featured_image_path']); ?>" alt="Featured image" class="img-preview"></div>
                            <?php else: ?>
                            <div class="img-preview-wrap" style="margin-bottom:12px">
                                <img id="featuredImgPreview" src="" alt="" class="img-preview" style="display:none">
                                <div class="img-placeholder-box" id="featuredImgPlaceholder"><i class="fas fa-image"></i><span>No featured image</span></div>
                            </div>
                            <?php endif; ?>
                            <div class="form-group"><label>Upload Featured Image</label><input type="file" name="featured_image" id="featuredImageInput" accept="image/jpeg,image/png,image/webp" data-preview="featuredImgPreview"><span class="form-hint">JPG, PNG or WebP - max 25MB.</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
<script>
document.getElementById('featuredImageInput')?.addEventListener('change', function() {
    const placeholder = document.getElementById('featuredImgPlaceholder');
    if (placeholder) placeholder.style.display = 'none';
});
const galleryInput = document.getElementById('galleryImagesInput');
const galleryNewPreview = document.getElementById('galleryNewPreview');
if (galleryInput && galleryNewPreview) {
    galleryInput.addEventListener('change', function() {
        galleryNewPreview.innerHTML = '';
        const files = Array.from(this.files || []);
        files.forEach((file, idx) => {
            const item = document.createElement('div');
            item.className = 'tour-album-new-item';
            const img = document.createElement('img');
            img.alt = '';
            img.src = URL.createObjectURL(file);
            const cap = document.createElement('input');
            cap.type = 'text';
            cap.name = 'gallery_new_caption[' + idx + ']';
            cap.placeholder = 'Caption for new photo';
            item.appendChild(img);
            item.appendChild(cap);
            galleryNewPreview.appendChild(item);
        });
    });
}
const sortable = document.getElementById('destinationGallerySortable');
const orderInput = document.getElementById('galleryOrderInput');
if (sortable && orderInput) {
    let dragItem = null;
    function syncOrder() {
        const ids = Array.from(sortable.querySelectorAll('.tour-album-item')).map(el => el.getAttribute('data-image-id')).filter(Boolean);
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
        if (after) target.parentNode.insertBefore(dragItem, target.nextSibling);
        else target.parentNode.insertBefore(dragItem, target);
    });
    syncOrder();
}
const deleteWrap = document.getElementById('galleryDeleteIds');
if (deleteWrap) {
    document.querySelectorAll('.gallery-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-delete-id');
            const row = btn.closest('.tour-album-item');
            if (!id || !row) return;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'gallery_delete_ids[]';
            hidden.value = id;
            deleteWrap.appendChild(hidden);
            row.remove();
            const localOrderInput = document.getElementById('galleryOrderInput');
            const localSortable = document.getElementById('destinationGallerySortable');
            if (localOrderInput && localSortable) {
                const ids = Array.from(localSortable.querySelectorAll('.tour-album-item')).map(el => el.getAttribute('data-image-id')).filter(Boolean);
                localOrderInput.value = ids.join(',');
            }
        });
    });
}
</script>
</body>
</html>
