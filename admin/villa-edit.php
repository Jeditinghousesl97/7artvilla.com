<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$villa = [
    'name' => '',
    'slug' => '',
    'location_label' => '',
    'tagline' => '',
    'short_description' => '',
    'description' => '',
    'hero_image_path' => '',
    'featured_image_path' => '',
    'checkin_time' => '',
    'checkout_time' => '',
    'min_stay' => '',
    'extra_guest_charge' => '',
    'pricing_note' => '',
    'max_guests' => '',
    'bedrooms' => '',
    'pool_label' => '',
    'is_featured' => 0,
    'is_homepage' => 0,
    'is_active' => 1,
    'sort_order' => 0,
];
$gallery_images = [];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM villas WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Villa not found.'];
        header('Location: villas.php');
        exit;
    }
    $villa = array_merge($villa, $row);

    $gallery_stmt = $pdo->prepare('SELECT * FROM villa_gallery_images WHERE villa_id = ? ORDER BY sort_order ASC, id ASC');
    $gallery_stmt->execute([$id]);
    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = stay_slugify($_POST['slug'] ?? $name, 'villa');
        $location_label = trim($_POST['location_label'] ?? '');
        $tagline = trim($_POST['tagline'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $checkin_time = trim($_POST['checkin_time'] ?? '');
        $checkout_time = trim($_POST['checkout_time'] ?? '');
        $min_stay = trim($_POST['min_stay'] ?? '');
        $extra_guest_charge = trim($_POST['extra_guest_charge'] ?? '');
        $pricing_note = trim($_POST['pricing_note'] ?? '');
        $max_guests = trim($_POST['max_guests'] ?? '');
        $bedrooms = trim($_POST['bedrooms'] ?? '');
        $pool_label = trim($_POST['pool_label'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_homepage = isset($_POST['is_homepage']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') $errors[] = 'Villa name is required.';
        if ($description === '') $errors[] = 'Description is required.';

        $hero_image_path = (string)$villa['hero_image_path'];
        $featured_image_path = (string)$villa['featured_image_path'];
        $old_hero_image_path = $hero_image_path;
        $old_featured_image_path = $featured_image_path;

        if (!empty($_FILES['hero_image']['name'])) {
            $new = stay_save_uploaded_image($_FILES['hero_image'], '../assets/images/villas/', 'assets/images/villas', 'villa_hero', $errors);
            if ($new) {
                $hero_image_path = $new;
            }
        }
        if (!empty($_FILES['featured_image']['name'])) {
            $new = stay_save_uploaded_image($_FILES['featured_image'], '../assets/images/villas/', 'assets/images/villas', 'villa_featured', $errors);
            if ($new) {
                $featured_image_path = $new;
            }
        }

        $gallery_files = $_FILES['gallery_images'] ?? null;
        if ($gallery_files && !empty($gallery_files['name']) && is_array($gallery_files['name'])) {
            $gallery_count = count($gallery_files['name']);
            for ($i = 0; $i < $gallery_count; $i++) {
                $gallery_name = (string)($gallery_files['name'][$i] ?? '');
                $gallery_tmp = (string)($gallery_files['tmp_name'][$i] ?? '');
                $gallery_error = (int)($gallery_files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                $gallery_size = (int)($gallery_files['size'][$i] ?? 0);

                if ($gallery_name === '' || $gallery_error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($gallery_error !== UPLOAD_ERR_OK) {
                    $errors[] = 'Gallery image "' . $gallery_name . '" failed to upload.';
                    continue;
                }

                $gallery_mime = stay_detect_upload_mime_type($gallery_tmp, $gallery_name);
                if (!in_array($gallery_mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $errors[] = 'Gallery image "' . $gallery_name . '" must be JPG, PNG, or WebP.';
                } elseif ($gallery_size > 25 * 1024 * 1024) {
                    $errors[] = 'Gallery image "' . $gallery_name . '" must be under 25MB.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $villa_id = $id;
                if ($is_edit) {
                    $stmt = $pdo->prepare('
                        UPDATE villas
                        SET name=?, slug=?, location_label=?, tagline=?, short_description=?, description=?, hero_image_path=?, featured_image_path=?,
                            checkin_time=?, checkout_time=?, min_stay=?, extra_guest_charge=?, pricing_note=?, max_guests=?, bedrooms=?, pool_label=?,
                            is_featured=?, is_homepage=?, is_active=?, sort_order=?
                        WHERE id=?
                    ');
                    $stmt->execute([
                        $name, $slug, $location_label, $tagline, $short_description, $description, $hero_image_path, $featured_image_path,
                        $checkin_time, $checkout_time, $min_stay, $extra_guest_charge, $pricing_note, $max_guests, $bedrooms, $pool_label,
                        $is_featured, $is_homepage, $is_active, $sort_order, $id,
                    ]);
                    if ($hero_image_path !== $old_hero_image_path) {
                        stay_delete_public_file($old_hero_image_path);
                    }
                    if ($featured_image_path !== $old_featured_image_path) {
                        stay_delete_public_file($old_featured_image_path);
                    }
                    $villa_id = $id;
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO villas
                            (name, slug, location_label, tagline, short_description, description, hero_image_path, featured_image_path,
                             checkin_time, checkout_time, min_stay, extra_guest_charge, pricing_note, max_guests, bedrooms, pool_label,
                             is_featured, is_homepage, is_active, sort_order)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ');
                    $stmt->execute([
                        $name, $slug, $location_label, $tagline, $short_description, $description, $hero_image_path, $featured_image_path,
                        $checkin_time, $checkout_time, $min_stay, $extra_guest_charge, $pricing_note, $max_guests, $bedrooms, $pool_label,
                        $is_featured, $is_homepage, $is_active, $sort_order,
                    ]);
                    $villa_id = (int)$pdo->lastInsertId();
                }

                $gallery_order_csv = trim((string)($_POST['gallery_order'] ?? ''));
                if ($gallery_order_csv !== '') {
                    $gallery_order_ids = [];
                    foreach (explode(',', $gallery_order_csv) as $gallery_id) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id > 0) $gallery_order_ids[] = $gallery_id;
                    }
                    foreach ($gallery_order_ids as $index => $gallery_id) {
                        $pdo->prepare('UPDATE villa_gallery_images SET sort_order = ? WHERE id = ? AND villa_id = ?')
                            ->execute([$index + 1, $gallery_id, $villa_id]);
                    }
                }

                $gallery_captions = $_POST['gallery_caption'] ?? [];
                if (is_array($gallery_captions)) {
                    foreach ($gallery_captions as $gallery_id => $caption) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id <= 0) continue;
                        $pdo->prepare('UPDATE villa_gallery_images SET caption = ? WHERE id = ? AND villa_id = ?')
                            ->execute([trim((string)$caption), $gallery_id, $villa_id]);
                    }
                }

                $gallery_delete_ids = $_POST['gallery_delete_ids'] ?? [];
                if (is_array($gallery_delete_ids)) {
                    $delete_stmt = $pdo->prepare('SELECT image_path FROM villa_gallery_images WHERE id = ? AND villa_id = ?');
                    foreach ($gallery_delete_ids as $gallery_id) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id <= 0) continue;
                        $delete_stmt->execute([$gallery_id, $villa_id]);
                        $image_path = (string)$delete_stmt->fetchColumn();
                        $pdo->prepare('DELETE FROM villa_gallery_images WHERE id = ? AND villa_id = ?')->execute([$gallery_id, $villa_id]);
                        if ($image_path !== '') {
                            stay_delete_public_file($image_path);
                        }
                    }
                }

                if ($gallery_files && !empty($gallery_files['name']) && is_array($gallery_files['name'])) {
                    $max_sort_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM villa_gallery_images WHERE villa_id = ?');
                    $max_sort_stmt->execute([$villa_id]);
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
                        $path = stay_save_uploaded_image($file, '../assets/images/villas/gallery/', 'assets/images/villas/gallery', 'villa_gallery', $errors);
                        if (!$path) continue;

                        $current_sort++;
                        $caption = trim((string)($new_captions[$i] ?? ''));
                        $pdo->prepare('INSERT INTO villa_gallery_images (villa_id, image_path, caption, sort_order) VALUES (?,?,?,?)')
                            ->execute([$villa_id, $path, $caption, $current_sort]);
                    }
                }

                if (empty($errors)) {
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => $is_edit ? 'Villa updated successfully.' : 'Villa added successfully.'];
                    header('Location: villas.php');
                    exit;
                }

                if ($villa_id > 0) {
                    $id = $villa_id;
                    $is_edit = true;
                    $gallery_stmt = $pdo->prepare('SELECT * FROM villa_gallery_images WHERE villa_id = ? ORDER BY sort_order ASC, id ASC');
                    $gallery_stmt->execute([$villa_id]);
                    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) $errors[] = 'Slug already exists. Use a unique slug.';
                else $errors[] = 'Unable to save villa.';
            }
        }

        $villa = array_merge($villa, compact(
            'name', 'slug', 'location_label', 'tagline', 'short_description', 'description', 'checkin_time', 'checkout_time',
            'min_stay', 'extra_guest_charge', 'pricing_note', 'max_guests', 'bedrooms', 'pool_label', 'is_featured', 'is_homepage', 'is_active', 'sort_order'
        ));
        $villa['hero_image_path'] = $hero_image_path;
        $villa['featured_image_path'] = $featured_image_path;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Villa' : 'Add Villa'; ?> | 7 Art Villa Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Villa' : 'Add Villa'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars($villa['name']) : 'Create a new villa record'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="villas.php" class="topbar-btn topbar-btn-outline"><i class="fas fa-arrow-left"></i> Back to Villas</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="villaEditForm" action="villa-edit.php<?php echo $is_edit ? '?id=' . (int)$id : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="edit-layout">
                <div class="edit-main">
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Basic Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group"><label>Villa Name *</label><input type="text" name="name" value="<?php echo htmlspecialchars((string)$villa['name']); ?>" required></div>
                            <div class="form-group"><label>Slug</label><input type="text" name="slug" value="<?php echo htmlspecialchars((string)$villa['slug']); ?>" placeholder="auto-generated-if-empty"></div>
                            <div class="form-group"><label>Location Label</label><input type="text" name="location_label" value="<?php echo htmlspecialchars((string)$villa['location_label']); ?>" placeholder="e.g. Ella, Sri Lanka"></div>
                            <div class="form-group"><label>Tagline</label><input type="text" name="tagline" value="<?php echo htmlspecialchars((string)$villa['tagline']); ?>"></div>
                            <div class="form-group form-full"><label>Short Description</label><textarea name="short_description" rows="3"><?php echo htmlspecialchars((string)$villa['short_description']); ?></textarea></div>
                            <div class="form-group form-full"><label>Full Description *</label><textarea name="description" rows="8" required><?php echo htmlspecialchars((string)$villa['description']); ?></textarea></div>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Stay Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group"><label>Check-In Time</label><input type="text" name="checkin_time" value="<?php echo htmlspecialchars((string)$villa['checkin_time']); ?>"></div>
                            <div class="form-group"><label>Check-Out Time</label><input type="text" name="checkout_time" value="<?php echo htmlspecialchars((string)$villa['checkout_time']); ?>"></div>
                            <div class="form-group"><label>Minimum Stay</label><input type="text" name="min_stay" value="<?php echo htmlspecialchars((string)$villa['min_stay']); ?>"></div>
                            <div class="form-group"><label>Extra Guest Charge</label><input type="text" name="extra_guest_charge" value="<?php echo htmlspecialchars((string)$villa['extra_guest_charge']); ?>"></div>
                            <div class="form-group"><label>Max Guests</label><input type="text" name="max_guests" value="<?php echo htmlspecialchars((string)$villa['max_guests']); ?>"></div>
                            <div class="form-group"><label>Bedrooms</label><input type="text" name="bedrooms" value="<?php echo htmlspecialchars((string)$villa['bedrooms']); ?>"></div>
                            <div class="form-group"><label>Pool Label</label><input type="text" name="pool_label" value="<?php echo htmlspecialchars((string)$villa['pool_label']); ?>"></div>
                            <div class="form-group form-full"><label>Pricing Note</label><textarea name="pricing_note" rows="4"><?php echo htmlspecialchars((string)$villa['pricing_note']); ?></textarea></div>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Villa Gallery</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">These images appear in the gallery slider inside the public villa overview section. Drag existing photos to reorder, add captions, and remove any you no longer need.</p>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Add Gallery Images</label>
                            <input type="file" name="gallery_images[]" id="galleryImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each. Selected images can be previewed, captioned, and removed before saving.</span>
                            <div id="galleryUploadSummary" style="margin-top:10px;font-size:0.78rem;color:var(--text-muted)"></div>
                            <div id="galleryNewPreview" class="tour-album-new-previews"></div>
                        </div>

                        <input type="hidden" name="gallery_order" id="galleryOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(static fn($x) => (int)$x['id'], $gallery_images))); ?>">

                        <?php if (!empty($gallery_images)): ?>
                        <div id="villaGallerySortable" class="tour-album-sortable">
                            <?php foreach ($gallery_images as $img): ?>
                            <div class="tour-album-item" data-image-id="<?php echo (int)$img['id']; ?>" draggable="true">
                                <div class="tour-album-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></div>
                                <img src="../<?php echo htmlspecialchars((string)$img['image_path']); ?>" alt="" class="tour-album-thumb">
                                <div class="tour-album-fields">
                                    <input type="text" name="gallery_caption[<?php echo (int)$img['id']; ?>]" value="<?php echo htmlspecialchars((string)($img['caption'] ?? '')); ?>" placeholder="Caption shown in the villa lightbox">
                                    <button type="button" class="btn-admin btn-outline btn-sm gallery-remove" data-delete-id="<?php echo (int)$img['id']; ?>">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:16px;border:1px dashed var(--border);border-radius:8px">
                            <p style="margin:0;color:var(--text-muted);font-size:0.8rem">No gallery images yet. Upload a few to activate the slider on the villa page.</p>
                        </div>
                        <?php endif; ?>

                        <div id="galleryDeleteIds"></div>
                    </div>
                </div>

                <div class="edit-sidebar">
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Options</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?php echo $villa['is_active'] ? 'checked' : ''; ?>><span><strong>Active</strong><small>Display this villa on the site</small></span></label>
                            <label class="checkbox-row"><input type="checkbox" name="is_featured" value="1" <?php echo $villa['is_featured'] ? 'checked' : ''; ?>><span><strong>Featured</strong><small>Highlight in listings</small></span></label>
                            <label class="checkbox-row"><input type="checkbox" name="is_homepage" value="1" <?php echo $villa['is_homepage'] ? 'checked' : ''; ?>><span><strong>Show on Homepage</strong><small>Include in homepage featured stays</small></span></label>
                        </div>
                        <div class="form-group" style="margin-top:16px">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?php echo (int)$villa['sort_order']; ?>" min="0">
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%" id="villaSaveBtn"><i class="fas fa-save"></i> <?php echo $is_edit ? 'Save Changes' : 'Add Villa'; ?></button>
                            <a href="villas.php" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Upload Progress</div>
                        <div style="margin-top:16px">
                            <div style="height:10px;border-radius:999px;background:var(--dark-4);overflow:hidden;border:1px solid var(--border)">
                                <div id="villaUploadProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg, var(--gold), #7bc6a4);transition:width 0.2s ease"></div>
                            </div>
                            <div id="villaUploadProgressText" style="margin-top:10px;font-size:0.8rem;color:var(--text-muted)">Ready to save. Progress appears here when uploading images.</div>
                        </div>
                    </div>

                    <div class="form-card">
                        <div class="form-card-title">Villa Images</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group">
                                <label>Hero Image</label>
                                <?php if (!empty($villa['hero_image_path']) && file_exists('../' . $villa['hero_image_path'])): ?>
                                <div class="img-preview-wrap" style="margin-bottom:12px"><img id="heroImgPreview" src="../<?php echo htmlspecialchars($villa['hero_image_path']); ?>" alt="" class="img-preview"></div>
                                <?php else: ?>
                                <div class="img-preview-wrap" style="margin-bottom:12px">
                                    <img id="heroImgPreview" src="" alt="" class="img-preview" style="display:none">
                                    <div class="img-placeholder-box" id="heroImgPlaceholder"><i class="fas fa-image"></i><span>No hero image</span></div>
                                </div>
                                <?php endif; ?>
                                <input type="file" name="hero_image" id="heroImageInput" accept="image/jpeg,image/png,image/webp" data-preview="heroImgPreview">
                            </div>
                            <div class="form-group">
                                <label>Featured Image</label>
                                <?php if (!empty($villa['featured_image_path']) && file_exists('../' . $villa['featured_image_path'])): ?>
                                <div class="img-preview-wrap" style="margin-bottom:12px"><img id="featuredImgPreview" src="../<?php echo htmlspecialchars($villa['featured_image_path']); ?>" alt="" class="img-preview"></div>
                                <?php else: ?>
                                <div class="img-preview-wrap" style="margin-bottom:12px">
                                    <img id="featuredImgPreview" src="" alt="" class="img-preview" style="display:none">
                                    <div class="img-placeholder-box" id="featuredImgPlaceholder"><i class="fas fa-image"></i><span>No featured image</span></div>
                                </div>
                                <?php endif; ?>
                                <input type="file" name="featured_image" id="featuredImageInput" accept="image/jpeg,image/png,image/webp" data-preview="featuredImgPreview">
                            </div>
                        </div>
                        <p style="margin:14px 0 0;font-size:0.78rem;color:var(--text-muted)">Hero image powers the top banner. Featured image is used in the overview card and listings.</p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
<script>
const villaEditForm = document.getElementById('villaEditForm');
const villaSaveBtn = document.getElementById('villaSaveBtn');
const progressBar = document.getElementById('villaUploadProgressBar');
const progressText = document.getElementById('villaUploadProgressText');
const galleryInput = document.getElementById('galleryImagesInput');
const galleryNewPreview = document.getElementById('galleryNewPreview');
const galleryUploadSummary = document.getElementById('galleryUploadSummary');
const galleryDeleteWrap = document.getElementById('galleryDeleteIds');
const gallerySortable = document.getElementById('villaGallerySortable');
const galleryOrderInput = document.getElementById('galleryOrderInput');

function humanFileSize(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    return size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1) + ' ' + units[unitIndex];
}

function previewSingleImage(inputId, previewId, placeholderId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const placeholder = document.getElementById(placeholderId);
    if (!input || !preview) return;

    input.addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (!file) return;
        preview.src = URL.createObjectURL(file);
        preview.style.display = '';
        if (placeholder) placeholder.style.display = 'none';
    });
}

previewSingleImage('heroImageInput', 'heroImgPreview', 'heroImgPlaceholder');
previewSingleImage('featuredImageInput', 'featuredImgPreview', 'featuredImgPlaceholder');

if (galleryInput && galleryNewPreview) {
    galleryInput.addEventListener('change', function() {
        const files = Array.from(this.files || []);
        galleryNewPreview.innerHTML = '';

        if (!files.length) {
            if (galleryUploadSummary) galleryUploadSummary.textContent = '';
            return;
        }

        let totalSize = 0;
        files.forEach((file, index) => {
            totalSize += file.size || 0;

            const item = document.createElement('div');
            item.className = 'tour-album-new-item';
            item.dataset.fileIndex = String(index);

            const img = document.createElement('img');
            img.alt = '';
            img.src = URL.createObjectURL(file);

            const name = document.createElement('div');
            name.style.fontSize = '0.72rem';
            name.style.color = 'var(--text-secondary)';
            name.textContent = file.name + ' (' + humanFileSize(file.size || 0) + ')';

            const cap = document.createElement('input');
            cap.type = 'text';
            cap.name = 'gallery_new_caption[' + index + ']';
            cap.placeholder = 'Caption for new photo';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-admin btn-outline btn-sm';
            removeBtn.innerHTML = '<i class="fas fa-times"></i> Remove';
            removeBtn.addEventListener('click', () => {
                const keptFiles = Array.from(galleryInput.files || []).filter((_, fileIndex) => fileIndex !== index);
                const dt = new DataTransfer();
                keptFiles.forEach((entry) => dt.items.add(entry));
                galleryInput.files = dt.files;
                galleryInput.dispatchEvent(new Event('change'));
            });

            item.appendChild(img);
            item.appendChild(name);
            item.appendChild(cap);
            item.appendChild(removeBtn);
            galleryNewPreview.appendChild(item);
        });

        if (galleryUploadSummary) {
            galleryUploadSummary.textContent = files.length + ' image' + (files.length !== 1 ? 's' : '') + ' selected, total ' + humanFileSize(totalSize) + '.';
        }
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

if (villaEditForm && window.XMLHttpRequest) {
    villaEditForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const xhr = new XMLHttpRequest();
        const formData = new FormData(villaEditForm);
        const originalBtnHtml = villaSaveBtn ? villaSaveBtn.innerHTML : '';

        if (villaSaveBtn) {
            villaSaveBtn.disabled = true;
            villaSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
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
            if (villaSaveBtn) {
                villaSaveBtn.disabled = false;
                villaSaveBtn.innerHTML = originalBtnHtml;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                if (progressBar) progressBar.style.width = '100%';
                if (progressText) progressText.textContent = 'Processing saved data...';

                const responseUrl = xhr.responseURL || '';
                if (responseUrl && !responseUrl.includes('villa-edit.php')) {
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
            if (villaSaveBtn) {
                villaSaveBtn.disabled = false;
                villaSaveBtn.innerHTML = originalBtnHtml;
            }
            if (progressText) progressText.textContent = 'Network error while uploading. Please try again.';
        });

        xhr.send(formData);
    });
}
</script>
</body>
</html>
