<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$villa_options = stay_fetch_villa_options($pdo);
$gallery_images = [];
$showcase_images = [];

$space = [
    'villa_id' => (int)($_GET['villa_id'] ?? 0),
    'name' => '',
    'slug' => '',
    'subtitle' => '',
    'space_type' => 'kabana',
    'short_description' => '',
    'description' => '',
    'featured_image_path' => '',
    'is_active' => 1,
    'sort_order' => 0,
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM villa_spaces WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Villa space not found.'];
        header('Location: villa-spaces.php');
        exit;
    }
    $space = array_merge($space, $row);
    $gallery_stmt = $pdo->prepare('SELECT * FROM villa_space_gallery_images WHERE villa_space_id = ? ORDER BY sort_order ASC, id ASC');
    $gallery_stmt->execute([$id]);
    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
    $showcase_stmt = $pdo->prepare('SELECT * FROM villa_space_showcase_images WHERE villa_space_id = ? ORDER BY sort_order ASC, id ASC');
    $showcase_stmt->execute([$id]);
    $showcase_images = $showcase_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$type_labels = stay_space_type_labels();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $villa_id = (int)($_POST['villa_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = stay_slugify($_POST['slug'] ?? $name, 'space');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $space_type = $_POST['space_type'] ?? 'kabana';
        $short_description = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $gallery_files = $_FILES['gallery_images'] ?? null;
        $showcase_files = $_FILES['showcase_images'] ?? null;

        if ($villa_id <= 0) $errors[] = 'Please select a villa.';
        if ($name === '') $errors[] = 'Space name is required.';
        if ($description === '') $errors[] = 'Description is required.';
        if (!isset($type_labels[$space_type])) $space_type = 'kabana';

        $featured_image_path = (string)$space['featured_image_path'];
        $old_featured_image_path = $featured_image_path;
        if (!empty($_FILES['featured_image']['name'])) {
            $new = stay_save_uploaded_image($_FILES['featured_image'], '../assets/images/villa-spaces/', 'assets/images/villa-spaces', 'space', $errors);
            if ($new) {
                $featured_image_path = $new;
            }
        }

        if (empty($errors)) {
            try {
                $space_id = $id;
                if ($is_edit) {
                    $stmt = $pdo->prepare('UPDATE villa_spaces SET villa_id=?, name=?, slug=?, subtitle=?, space_type=?, short_description=?, description=?, featured_image_path=?, is_active=?, sort_order=? WHERE id=?');
                    $stmt->execute([$villa_id, $name, $slug, $subtitle, $space_type, $short_description, $description, $featured_image_path, $is_active, $sort_order, $id]);
                    if ($featured_image_path !== $old_featured_image_path) {
                        stay_delete_public_file($old_featured_image_path);
                    }
                    $space_id = $id;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO villa_spaces (villa_id, name, slug, subtitle, space_type, short_description, description, featured_image_path, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$villa_id, $name, $slug, $subtitle, $space_type, $short_description, $description, $featured_image_path, $is_active, $sort_order]);
                    $space_id = (int)$pdo->lastInsertId();
                }

                $gallery_order_csv = trim((string)($_POST['gallery_order'] ?? ''));
                if ($gallery_order_csv !== '') {
                    $gallery_order_ids = [];
                    foreach (explode(',', $gallery_order_csv) as $gallery_id) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id > 0) $gallery_order_ids[] = $gallery_id;
                    }
                    foreach ($gallery_order_ids as $index => $gallery_id) {
                        $pdo->prepare('UPDATE villa_space_gallery_images SET sort_order = ? WHERE id = ? AND villa_space_id = ?')
                            ->execute([$index + 1, $gallery_id, $space_id]);
                    }
                }

                $gallery_captions = $_POST['gallery_caption'] ?? [];
                if (is_array($gallery_captions)) {
                    foreach ($gallery_captions as $gallery_id => $caption) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id <= 0) continue;
                        $pdo->prepare('UPDATE villa_space_gallery_images SET caption = ? WHERE id = ? AND villa_space_id = ?')
                            ->execute([trim((string)$caption), $gallery_id, $space_id]);
                    }
                }

                $gallery_delete_ids = $_POST['gallery_delete_ids'] ?? [];
                if (is_array($gallery_delete_ids)) {
                    $delete_stmt = $pdo->prepare('SELECT image_path FROM villa_space_gallery_images WHERE id = ? AND villa_space_id = ?');
                    foreach ($gallery_delete_ids as $gallery_id) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id <= 0) continue;
                        $delete_stmt->execute([$gallery_id, $space_id]);
                        $image_path = (string)$delete_stmt->fetchColumn();
                        $pdo->prepare('DELETE FROM villa_space_gallery_images WHERE id = ? AND villa_space_id = ?')->execute([$gallery_id, $space_id]);
                        if ($image_path !== '') {
                            stay_delete_public_file($image_path);
                        }
                    }
                }

                $showcase_order_csv = trim((string)($_POST['showcase_order'] ?? ''));
                if ($showcase_order_csv !== '') {
                    $showcase_order_ids = [];
                    foreach (explode(',', $showcase_order_csv) as $showcase_id) {
                        $showcase_id = (int)$showcase_id;
                        if ($showcase_id > 0) $showcase_order_ids[] = $showcase_id;
                    }
                    foreach ($showcase_order_ids as $index => $showcase_id) {
                        $pdo->prepare('UPDATE villa_space_showcase_images SET sort_order = ? WHERE id = ? AND villa_space_id = ?')
                            ->execute([$index + 1, $showcase_id, $space_id]);
                    }
                }

                $showcase_captions = $_POST['showcase_caption'] ?? [];
                if (is_array($showcase_captions)) {
                    foreach ($showcase_captions as $showcase_id => $caption) {
                        $showcase_id = (int)$showcase_id;
                        if ($showcase_id <= 0) continue;
                        $pdo->prepare('UPDATE villa_space_showcase_images SET caption = ? WHERE id = ? AND villa_space_id = ?')
                            ->execute([trim((string)$caption), $showcase_id, $space_id]);
                    }
                }

                $showcase_delete_ids = $_POST['showcase_delete_ids'] ?? [];
                if (is_array($showcase_delete_ids)) {
                    $delete_stmt = $pdo->prepare('SELECT image_path FROM villa_space_showcase_images WHERE id = ? AND villa_space_id = ?');
                    foreach ($showcase_delete_ids as $showcase_id) {
                        $showcase_id = (int)$showcase_id;
                        if ($showcase_id <= 0) continue;
                        $delete_stmt->execute([$showcase_id, $space_id]);
                        $image_path = (string)$delete_stmt->fetchColumn();
                        $pdo->prepare('DELETE FROM villa_space_showcase_images WHERE id = ? AND villa_space_id = ?')->execute([$showcase_id, $space_id]);
                        if ($image_path !== '') {
                            stay_delete_public_file($image_path);
                        }
                    }
                }

                if ($gallery_files && !empty($gallery_files['name']) && is_array($gallery_files['name'])) {
                    $max_sort_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM villa_space_gallery_images WHERE villa_space_id = ?');
                    $max_sort_stmt->execute([$space_id]);
                    $current_sort = (int)$max_sort_stmt->fetchColumn();
                    $new_captions = $_POST['gallery_new_caption'] ?? [];
                    $count = count($gallery_files['name']);

                    for ($i = 0; $i < $count; $i++) {
                        $file = [
                            'name' => $gallery_files['name'][$i] ?? '',
                            'type' => $gallery_files['type'][$i] ?? '',
                            'tmp_name' => $gallery_files['tmp_name'][$i] ?? '',
                            'error' => $gallery_files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                            'size' => $gallery_files['size'][$i] ?? 0,
                        ];
                        $path = stay_save_uploaded_image($file, '../assets/images/villa-spaces/gallery/', 'assets/images/villa-spaces/gallery', 'space_gallery', $errors);
                        if (!$path) continue;

                        $current_sort++;
                        $caption = trim((string)($new_captions[$i] ?? ''));
                        $pdo->prepare('INSERT INTO villa_space_gallery_images (villa_space_id, image_path, caption, sort_order) VALUES (?,?,?,?)')
                            ->execute([$space_id, $path, $caption, $current_sort]);
                    }
                }

                if ($showcase_files && !empty($showcase_files['name']) && is_array($showcase_files['name'])) {
                    $max_sort_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM villa_space_showcase_images WHERE villa_space_id = ?');
                    $max_sort_stmt->execute([$space_id]);
                    $current_sort = (int)$max_sort_stmt->fetchColumn();
                    $new_captions = $_POST['showcase_new_caption'] ?? [];
                    $count = count($showcase_files['name']);

                    for ($i = 0; $i < $count; $i++) {
                        $file = [
                            'name' => $showcase_files['name'][$i] ?? '',
                            'type' => $showcase_files['type'][$i] ?? '',
                            'tmp_name' => $showcase_files['tmp_name'][$i] ?? '',
                            'error' => $showcase_files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                            'size' => $showcase_files['size'][$i] ?? 0,
                        ];
                        $path = stay_save_uploaded_image($file, '../assets/images/villa-spaces/showcase/', 'assets/images/villa-spaces/showcase', 'space_showcase', $errors);
                        if (!$path) continue;

                        $current_sort++;
                        $caption = trim((string)($new_captions[$i] ?? ''));
                        $pdo->prepare('INSERT INTO villa_space_showcase_images (villa_space_id, image_path, caption, sort_order) VALUES (?,?,?,?)')
                            ->execute([$space_id, $path, $caption, $current_sort]);
                    }
                }

                if (empty($errors)) {
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => $is_edit ? 'Space updated successfully.' : 'Space added successfully.'];
                    header('Location: villa-spaces.php?villa_id=' . $villa_id);
                    exit;
                }

                if ($space_id > 0) {
                    $id = $space_id;
                    $is_edit = true;
                    $gallery_stmt = $pdo->prepare('SELECT * FROM villa_space_gallery_images WHERE villa_space_id = ? ORDER BY sort_order ASC, id ASC');
                    $gallery_stmt->execute([$space_id]);
                    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $showcase_stmt = $pdo->prepare('SELECT * FROM villa_space_showcase_images WHERE villa_space_id = ? ORDER BY sort_order ASC, id ASC');
                    $showcase_stmt->execute([$space_id]);
                    $showcase_images = $showcase_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) $errors[] = 'Slug already exists for this villa.';
                else $errors[] = 'Unable to save villa space.';
            }
        }

        $space = array_merge($space, compact('villa_id', 'name', 'slug', 'subtitle', 'space_type', 'short_description', 'description', 'is_active', 'sort_order'));
        $space['featured_image_path'] = $featured_image_path;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Villa Space' : 'Add Villa Space'; ?> | 7 Art Villa Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Villa Space' : 'Add Villa Space'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars($space['name']) : 'Create a new kabana or section'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="villa-spaces.php<?php echo $space['villa_id'] ? '?villa_id=' . $space['villa_id'] : ''; ?>" class="topbar-btn topbar-btn-outline"><i class="fas fa-arrow-left"></i> Back to Spaces</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="villaSpaceEditForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="edit-layout">
                <div class="edit-main">
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Basic Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group">
                                <label>Villa *</label>
                                <select name="villa_id" required>
                                    <option value="">Select Villa</option>
                                    <?php foreach ($villa_options as $villa): ?>
                                    <option value="<?php echo (int)$villa['id']; ?>" <?php echo (int)$space['villa_id'] === (int)$villa['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($villa['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Space Type *</label>
                                <select name="space_type">
                                    <?php foreach ($type_labels as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $space['space_type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Space Name *</label><input type="text" name="name" value="<?php echo htmlspecialchars((string)$space['name']); ?>" required></div>
                            <div class="form-group"><label>Slug</label><input type="text" name="slug" value="<?php echo htmlspecialchars((string)$space['slug']); ?>"></div>
                            <div class="form-group form-full"><label>Subtitle</label><input type="text" name="subtitle" value="<?php echo htmlspecialchars((string)$space['subtitle']); ?>"></div>
                            <div class="form-group form-full"><label>Short Description</label><textarea name="short_description" rows="3"><?php echo htmlspecialchars((string)$space['short_description']); ?></textarea></div>
                            <div class="form-group form-full"><label>Full Description *</label><textarea name="description" rows="7" required><?php echo htmlspecialchars((string)$space['description']); ?></textarea></div>
                        </div>
                    </div>
                    <div class="form-card">
                        <div class="form-card-title">Featured Image</div>
                        <div class="form-group" style="margin-top:20px">
                            <label>Upload Image</label>
                            <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp">
                            <?php if (!empty($space['featured_image_path']) && file_exists('../' . $space['featured_image_path'])): ?>
                            <div style="margin-top:10px"><img src="../<?php echo htmlspecialchars($space['featured_image_path']); ?>" alt="" style="width:100%;max-width:220px;border-radius:10px"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-card" style="margin-top:20px">
                        <div class="form-card-title">Space Gallery</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">These images appear in the gallery slider at the end of the public space overview section. Drag existing photos to reorder, add captions, and remove any you no longer need.</p>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Add Gallery Images</label>
                            <input type="file" name="gallery_images[]" id="spaceGalleryImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each. Selected images can be previewed, captioned, and removed before saving.</span>
                            <div id="spaceGalleryUploadSummary" style="margin-top:10px;font-size:0.78rem;color:var(--text-muted)"></div>
                            <div id="spaceGalleryNewPreview" class="tour-album-new-previews"></div>
                        </div>

                        <input type="hidden" name="gallery_order" id="spaceGalleryOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(static fn($x) => (int)$x['id'], $gallery_images))); ?>">

                        <?php if (!empty($gallery_images)): ?>
                        <div id="spaceGallerySortable" class="tour-album-sortable">
                            <?php foreach ($gallery_images as $img): ?>
                            <div class="tour-album-item" data-image-id="<?php echo (int)$img['id']; ?>" draggable="true">
                                <div class="tour-album-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
                                <img src="../<?php echo htmlspecialchars((string)$img['image_path']); ?>" alt="" class="tour-album-thumb">
                                <div class="tour-album-fields">
                                    <input type="text" name="gallery_caption[<?php echo (int)$img['id']; ?>]" value="<?php echo htmlspecialchars((string)($img['caption'] ?? '')); ?>" placeholder="Caption shown in the lightbox">
                                    <button type="button" class="btn-admin btn-outline btn-sm gallery-remove" data-delete-id="<?php echo (int)$img['id']; ?>">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:16px;border:1px dashed var(--border);border-radius:8px">
                            <p style="margin:0;color:var(--text-muted);font-size:0.8rem">No gallery images yet. Upload a few to activate the slider on the villa space page.</p>
                        </div>
                        <?php endif; ?>

                        <div id="spaceGalleryDeleteIds"></div>
                    </div>

                    <div class="form-card" style="margin-top:20px">
                        <div class="form-card-title">Fullscreen Showcase Slider</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">These images appear in the full-width slider section near the bottom of the public villa space page. This slider is managed separately from the regular space gallery and does not use a lightbox.</p>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Add Showcase Images</label>
                            <input type="file" name="showcase_images[]" id="spaceShowcaseImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each. Selected images can be previewed, captioned, and removed before saving.</span>
                            <div id="spaceShowcaseUploadSummary" style="margin-top:10px;font-size:0.78rem;color:var(--text-muted)"></div>
                            <div id="spaceShowcaseNewPreview" class="tour-album-new-previews"></div>
                        </div>

                        <input type="hidden" name="showcase_order" id="spaceShowcaseOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(static fn($x) => (int)$x['id'], $showcase_images))); ?>">

                        <?php if (!empty($showcase_images)): ?>
                        <div id="spaceShowcaseSortable" class="tour-album-sortable">
                            <?php foreach ($showcase_images as $img): ?>
                            <div class="tour-album-item" data-image-id="<?php echo (int)$img['id']; ?>" draggable="true">
                                <div class="tour-album-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
                                <img src="../<?php echo htmlspecialchars((string)$img['image_path']); ?>" alt="" class="tour-album-thumb">
                                <div class="tour-album-fields">
                                    <input type="text" name="showcase_caption[<?php echo (int)$img['id']; ?>]" value="<?php echo htmlspecialchars((string)($img['caption'] ?? '')); ?>" placeholder="Caption shown on the full screen slider">
                                    <button type="button" class="btn-admin btn-outline btn-sm showcase-remove" data-delete-id="<?php echo (int)$img['id']; ?>">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:16px;border:1px dashed var(--border);border-radius:8px">
                            <p style="margin:0;color:var(--text-muted);font-size:0.8rem">No showcase images yet. Upload a few to create the full-width slider section on the villa space page.</p>
                        </div>
                        <?php endif; ?>

                        <div id="spaceShowcaseDeleteIds"></div>
                    </div>
                </div>
                <div class="edit-sidebar">
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Options</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?php echo $space['is_active'] ? 'checked' : ''; ?>><span><strong>Active</strong><small>Display this space on the site</small></span></label>
                        </div>
                        <div class="form-group" style="margin-top:16px">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?php echo (int)$space['sort_order']; ?>" min="0">
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%" id="villaSpaceSaveBtn"><i class="fas fa-save"></i> <?php echo $is_edit ? 'Save Changes' : 'Add Space'; ?></button>
                            <a href="villa-spaces.php<?php echo $space['villa_id'] ? '?villa_id=' . $space['villa_id'] : ''; ?>" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Upload Progress</div>
                        <div style="margin-top:16px">
                            <div style="height:10px;border-radius:999px;background:var(--dark-4);overflow:hidden;border:1px solid var(--border)">
                                <div id="villaSpaceUploadProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg, var(--gold), #7bc6a4);transition:width 0.2s ease"></div>
                            </div>
                            <div id="villaSpaceUploadProgressText" style="margin-top:10px;font-size:0.8rem;color:var(--text-muted)">Ready to save. Progress appears here when uploading images.</div>
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
const villaSpaceEditForm = document.getElementById('villaSpaceEditForm');
const villaSpaceSaveBtn = document.getElementById('villaSpaceSaveBtn');
const progressBar = document.getElementById('villaSpaceUploadProgressBar');
const progressText = document.getElementById('villaSpaceUploadProgressText');
const galleryInput = document.getElementById('spaceGalleryImagesInput');
const gallerySummary = document.getElementById('spaceGalleryUploadSummary');
const galleryPreview = document.getElementById('spaceGalleryNewPreview');
const gallerySortable = document.getElementById('spaceGallerySortable');
const galleryOrderInput = document.getElementById('spaceGalleryOrderInput');
const galleryDeleteWrap = document.getElementById('spaceGalleryDeleteIds');
const showcaseInput = document.getElementById('spaceShowcaseImagesInput');
const showcaseSummary = document.getElementById('spaceShowcaseUploadSummary');
const showcasePreview = document.getElementById('spaceShowcaseNewPreview');
const showcaseSortable = document.getElementById('spaceShowcaseSortable');
const showcaseOrderInput = document.getElementById('spaceShowcaseOrderInput');
const showcaseDeleteWrap = document.getElementById('spaceShowcaseDeleteIds');

if (galleryInput && gallerySummary && galleryPreview) {
    galleryInput.addEventListener('change', () => {
        const files = Array.from(galleryInput.files || []);
        galleryPreview.innerHTML = '';

        if (!files.length) {
            gallerySummary.textContent = '';
            return;
        }

        const totalSize = files.reduce((sum, file) => sum + (file.size || 0), 0);
        gallerySummary.textContent = files.length + ' image(s) selected - ' + (totalSize / (1024 * 1024)).toFixed(2) + ' MB total';

        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'tour-album-item';

            const thumb = document.createElement('img');
            thumb.className = 'tour-album-thumb';
            thumb.alt = file.name;
            thumb.src = URL.createObjectURL(file);
            thumb.addEventListener('load', () => URL.revokeObjectURL(thumb.src), { once: true });

            const fields = document.createElement('div');
            fields.className = 'tour-album-fields';

            const caption = document.createElement('input');
            caption.type = 'text';
            caption.name = 'gallery_new_caption[' + index + ']';
            caption.placeholder = 'Optional caption for this image';

            const meta = document.createElement('span');
            meta.className = 'form-hint';
            meta.textContent = file.name;

            fields.appendChild(caption);
            fields.appendChild(meta);
            item.appendChild(thumb);
            item.appendChild(fields);
            galleryPreview.appendChild(item);
        });
    });
}

if (showcaseInput && showcaseSummary && showcasePreview) {
    showcaseInput.addEventListener('change', () => {
        const files = Array.from(showcaseInput.files || []);
        showcasePreview.innerHTML = '';

        if (!files.length) {
            showcaseSummary.textContent = '';
            return;
        }

        const totalSize = files.reduce((sum, file) => sum + (file.size || 0), 0);
        showcaseSummary.textContent = files.length + ' image(s) selected - ' + (totalSize / (1024 * 1024)).toFixed(2) + ' MB total';

        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'tour-album-item';

            const thumb = document.createElement('img');
            thumb.className = 'tour-album-thumb';
            thumb.alt = file.name;
            thumb.src = URL.createObjectURL(file);
            thumb.addEventListener('load', () => URL.revokeObjectURL(thumb.src), { once: true });

            const fields = document.createElement('div');
            fields.className = 'tour-album-fields';

            const caption = document.createElement('input');
            caption.type = 'text';
            caption.name = 'showcase_new_caption[' + index + ']';
            caption.placeholder = 'Optional caption for this image';

            const meta = document.createElement('span');
            meta.className = 'form-hint';
            meta.textContent = file.name;

            fields.appendChild(caption);
            fields.appendChild(meta);
            item.appendChild(thumb);
            item.appendChild(fields);
            showcasePreview.appendChild(item);
        });
    });
}

if (gallerySortable && galleryOrderInput) {
    let dragItem = null;

    function syncGalleryOrder() {
        const ids = Array.from(gallerySortable.querySelectorAll('.tour-album-item'))
            .map((element) => element.getAttribute('data-image-id'))
            .filter(Boolean);
        galleryOrderInput.value = ids.join(',');
    }

    gallerySortable.addEventListener('dragstart', (event) => {
        const item = event.target.closest('.tour-album-item');
        if (!item) return;
        dragItem = item;
        item.classList.add('dragging');
    });

    gallerySortable.addEventListener('dragend', () => {
        if (dragItem) dragItem.classList.remove('dragging');
        dragItem = null;
        syncGalleryOrder();
    });

    gallerySortable.addEventListener('dragover', (event) => {
        event.preventDefault();
        const target = event.target.closest('.tour-album-item');
        if (!dragItem || !target || target === dragItem) return;
        const rect = target.getBoundingClientRect();
        const after = (event.clientY - rect.top) > (rect.height / 2);
        if (after) target.parentNode.insertBefore(dragItem, target.nextSibling);
        else target.parentNode.insertBefore(dragItem, target);
    });

    syncGalleryOrder();
}

if (showcaseSortable && showcaseOrderInput) {
    let dragItem = null;

    function syncShowcaseOrder() {
        const ids = Array.from(showcaseSortable.querySelectorAll('.tour-album-item'))
            .map((element) => element.getAttribute('data-image-id'))
            .filter(Boolean);
        showcaseOrderInput.value = ids.join(',');
    }

    showcaseSortable.addEventListener('dragstart', (event) => {
        const item = event.target.closest('.tour-album-item');
        if (!item) return;
        dragItem = item;
        item.classList.add('dragging');
    });

    showcaseSortable.addEventListener('dragend', () => {
        if (dragItem) dragItem.classList.remove('dragging');
        dragItem = null;
        syncShowcaseOrder();
    });

    showcaseSortable.addEventListener('dragover', (event) => {
        event.preventDefault();
        const target = event.target.closest('.tour-album-item');
        if (!dragItem || !target || target === dragItem) return;
        const rect = target.getBoundingClientRect();
        const after = (event.clientY - rect.top) > (rect.height / 2);
        if (after) target.parentNode.insertBefore(dragItem, target.nextSibling);
        else target.parentNode.insertBefore(dragItem, target);
    });

    syncShowcaseOrder();
}

if (galleryDeleteWrap) {
    document.querySelectorAll('.gallery-remove').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-delete-id');
            const row = button.closest('.tour-album-item');
            if (!id || !row) return;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'gallery_delete_ids[]';
            hidden.value = id;
            galleryDeleteWrap.appendChild(hidden);
            row.remove();

            if (gallerySortable && galleryOrderInput) {
                const ids = Array.from(gallerySortable.querySelectorAll('.tour-album-item'))
                    .map((element) => element.getAttribute('data-image-id'))
                    .filter(Boolean);
                galleryOrderInput.value = ids.join(',');
            }
        });
    });
}

if (showcaseDeleteWrap) {
    document.querySelectorAll('.showcase-remove').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-delete-id');
            const row = button.closest('.tour-album-item');
            if (!id || !row) return;

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'showcase_delete_ids[]';
            hidden.value = id;
            showcaseDeleteWrap.appendChild(hidden);
            row.remove();

            if (showcaseSortable && showcaseOrderInput) {
                const ids = Array.from(showcaseSortable.querySelectorAll('.tour-album-item'))
                    .map((element) => element.getAttribute('data-image-id'))
                    .filter(Boolean);
                showcaseOrderInput.value = ids.join(',');
            }
        });
    });
}

if (villaSpaceEditForm && window.XMLHttpRequest) {
    villaSpaceEditForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const xhr = new XMLHttpRequest();
        const formData = new FormData(villaSpaceEditForm);
        const originalBtnHtml = villaSpaceSaveBtn ? villaSpaceSaveBtn.innerHTML : '';

        if (villaSpaceSaveBtn) {
            villaSpaceSaveBtn.disabled = true;
            villaSpaceSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }
        if (progressBar) progressBar.style.width = '0%';
        if (progressText) progressText.textContent = 'Preparing upload...';

        xhr.open('POST', window.location.href, true);
        xhr.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) return;
            const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            if (progressBar) progressBar.style.width = percent + '%';
            if (progressText) progressText.textContent = 'Uploading files... ' + percent + '%';
        });

        xhr.addEventListener('load', () => {
            if (villaSpaceSaveBtn) {
                villaSpaceSaveBtn.disabled = false;
                villaSpaceSaveBtn.innerHTML = originalBtnHtml;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                if (progressBar) progressBar.style.width = '100%';
                if (progressText) progressText.textContent = 'Processing saved data...';

                const responseUrl = xhr.responseURL || '';
                if (responseUrl && !responseUrl.includes('villa-space-edit.php')) {
                    window.location.href = responseUrl;
                    return;
                }

                document.open();
                document.write(xhr.responseText);
                document.close();
                return;
            }

            if (progressText) progressText.textContent = 'Upload failed. Please try again.';
        });

        xhr.addEventListener('error', () => {
            if (villaSpaceSaveBtn) {
                villaSpaceSaveBtn.disabled = false;
                villaSpaceSaveBtn.innerHTML = originalBtnHtml;
            }
            if (progressText) progressText.textContent = 'Network error while uploading. Please try again.';
        });

        xhr.send(formData);
    });
}
</script>
</body>
</html>
