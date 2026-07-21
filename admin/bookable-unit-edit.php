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
$all_spaces = stay_fetch_space_options($pdo);
$gallery_images = [];

$unit = [
    'villa_id' => (int)($_GET['villa_id'] ?? 0),
    'villa_space_id' => (int)($_GET['space_id'] ?? 0),
    'name' => '',
    'slug' => '',
    'subtitle' => '',
    'unit_type' => 'room',
    'summary' => '',
    'description' => '',
    'max_guests' => '',
    'bed_info' => '',
    'size_label' => '',
    'featured_image_path' => '',
    'pricing_note' => '',
    'is_featured' => 0,
    'is_active' => 1,
    'sort_order' => 0,
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM bookable_units WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bookable unit not found.'];
        header('Location: bookable-units.php');
        exit;
    }
    $unit = array_merge($unit, $row);
    $gallery_stmt = $pdo->prepare('SELECT * FROM bookable_unit_gallery_images WHERE bookable_unit_id = ? ORDER BY sort_order ASC, id ASC');
    $gallery_stmt->execute([$id]);
    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$type_labels = stay_unit_type_labels();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $villa_id = (int)($_POST['villa_id'] ?? 0);
        $villa_space_id = (int)($_POST['villa_space_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = stay_slugify($_POST['slug'] ?? $name, 'unit');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $unit_type = $_POST['unit_type'] ?? 'room';
        $summary = trim($_POST['summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $max_guests = trim($_POST['max_guests'] ?? '');
        $bed_info = trim($_POST['bed_info'] ?? '');
        $size_label = trim($_POST['size_label'] ?? '');
        $pricing_note = trim($_POST['pricing_note'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $gallery_files = $_FILES['gallery_images'] ?? null;

        if ($villa_id <= 0) $errors[] = 'Please select a villa.';
        if ($name === '') $errors[] = 'Unit name is required.';
        if ($description === '') $errors[] = 'Description is required.';
        if (!isset($type_labels[$unit_type])) $unit_type = 'room';
        if ($villa_space_id <= 0) $villa_space_id = null;

        $featured_image_path = (string)$unit['featured_image_path'];
        $old_featured_image_path = $featured_image_path;
        if (!empty($_FILES['featured_image']['name'])) {
            $new = stay_save_uploaded_image($_FILES['featured_image'], '../assets/images/bookable-units/', 'assets/images/bookable-units', 'unit', $errors);
            if ($new) {
                $featured_image_path = $new;
            }
        }

        if (empty($errors)) {
            try {
                $was_edit = $is_edit;
                if ($was_edit) {
                    $stmt = $pdo->prepare('
                        UPDATE bookable_units
                        SET villa_id=?, villa_space_id=?, name=?, slug=?, subtitle=?, unit_type=?, summary=?, description=?, max_guests=?, bed_info=?, size_label=?, featured_image_path=?, pricing_note=?, is_featured=?, is_active=?, sort_order=?
                        WHERE id=?
                    ');
                    $stmt->execute([$villa_id, $villa_space_id, $name, $slug, $subtitle, $unit_type, $summary, $description, $max_guests, $bed_info, $size_label, $featured_image_path, $pricing_note, $is_featured, $is_active, $sort_order, $id]);
                    if ($featured_image_path !== $old_featured_image_path) {
                        stay_delete_public_file($old_featured_image_path);
                    }
                    $unit_id = $id;
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO bookable_units
                            (villa_id, villa_space_id, name, slug, subtitle, unit_type, summary, description, max_guests, bed_info, size_label, featured_image_path, pricing_note, is_featured, is_active, sort_order)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ');
                    $stmt->execute([$villa_id, $villa_space_id, $name, $slug, $subtitle, $unit_type, $summary, $description, $max_guests, $bed_info, $size_label, $featured_image_path, $pricing_note, $is_featured, $is_active, $sort_order]);
                    $unit_id = (int)$pdo->lastInsertId();
                    $id = $unit_id;
                    $is_edit = true;
                }

                $gallery_order_csv = trim((string)($_POST['gallery_order'] ?? ''));
                if ($gallery_order_csv !== '') {
                    $gallery_order_ids = [];
                    foreach (explode(',', $gallery_order_csv) as $gallery_id) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id > 0) $gallery_order_ids[] = $gallery_id;
                    }
                    foreach ($gallery_order_ids as $index => $gallery_id) {
                        $pdo->prepare('UPDATE bookable_unit_gallery_images SET sort_order = ? WHERE id = ? AND bookable_unit_id = ?')
                            ->execute([$index + 1, $gallery_id, $unit_id]);
                    }
                }

                $gallery_captions = $_POST['gallery_caption'] ?? [];
                if (is_array($gallery_captions)) {
                    foreach ($gallery_captions as $gallery_id => $caption) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id <= 0) continue;
                        $pdo->prepare('UPDATE bookable_unit_gallery_images SET caption = ? WHERE id = ? AND bookable_unit_id = ?')
                            ->execute([trim((string)$caption), $gallery_id, $unit_id]);
                    }
                }

                $gallery_delete_ids = $_POST['gallery_delete_ids'] ?? [];
                if (is_array($gallery_delete_ids)) {
                    $delete_stmt = $pdo->prepare('SELECT image_path FROM bookable_unit_gallery_images WHERE id = ? AND bookable_unit_id = ?');
                    foreach ($gallery_delete_ids as $gallery_id) {
                        $gallery_id = (int)$gallery_id;
                        if ($gallery_id <= 0) continue;
                        $delete_stmt->execute([$gallery_id, $unit_id]);
                        $image_path = (string)$delete_stmt->fetchColumn();
                        $pdo->prepare('DELETE FROM bookable_unit_gallery_images WHERE id = ? AND bookable_unit_id = ?')->execute([$gallery_id, $unit_id]);
                        if ($image_path !== '') {
                            stay_delete_public_file($image_path);
                        }
                    }
                }

                if ($gallery_files && !empty($gallery_files['name']) && is_array($gallery_files['name'])) {
                    $max_sort_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM bookable_unit_gallery_images WHERE bookable_unit_id = ?');
                    $max_sort_stmt->execute([$unit_id]);
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
                        $path = stay_save_uploaded_image($file, '../assets/images/bookable-units/gallery/', 'assets/images/bookable-units/gallery', 'unit_gallery', $errors);
                        if (!$path) continue;

                        $current_sort++;
                        $caption = trim((string)($new_captions[$i] ?? ''));
                        $pdo->prepare('INSERT INTO bookable_unit_gallery_images (bookable_unit_id, image_path, caption, sort_order) VALUES (?,?,?,?)')
                            ->execute([$unit_id, $path, $caption, $current_sort]);
                    }
                }

                if (empty($errors)) {
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => $was_edit ? 'Bookable unit updated successfully.' : 'Bookable unit added successfully.'];
                    $qs = $villa_space_id ? ('space_id=' . $villa_space_id) : ('villa_id=' . $villa_id);
                    header('Location: bookable-units.php?' . $qs);
                    exit;
                }

                $gallery_stmt = $pdo->prepare('SELECT * FROM bookable_unit_gallery_images WHERE bookable_unit_id = ? ORDER BY sort_order ASC, id ASC');
                $gallery_stmt->execute([$unit_id]);
                $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) $errors[] = 'Slug already exists. Use a unique slug.';
                else $errors[] = 'Unable to save bookable unit.';
            }
        }

        $unit = array_merge($unit, compact('villa_id', 'villa_space_id', 'name', 'slug', 'subtitle', 'unit_type', 'summary', 'description', 'max_guests', 'bed_info', 'size_label', 'pricing_note', 'is_featured', 'is_active', 'sort_order'));
        $unit['featured_image_path'] = $featured_image_path;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Bookable Unit' : 'Add Bookable Unit'; ?> | We Trail Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Bookable Unit' : 'Add Bookable Unit'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars($unit['name']) : 'Create a new booking option'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="bookable-units.php<?php echo $unit['villa_space_id'] ? '?space_id=' . $unit['villa_space_id'] : ($unit['villa_id'] ? '?villa_id=' . $unit['villa_id'] : ''); ?>" class="topbar-btn topbar-btn-outline"><i class="fas fa-arrow-left"></i> Back to Units</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="bookableUnitEditForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="edit-layout">
                <div class="edit-main">
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Basic Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group">
                                <label>Villa *</label>
                                <select name="villa_id" id="villaIdSelect" required>
                                    <option value="">Select Villa</option>
                                    <?php foreach ($villa_options as $villa): ?>
                                    <option value="<?php echo (int)$villa['id']; ?>" <?php echo (int)$unit['villa_id'] === (int)$villa['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($villa['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Villa Space</label>
                                <select name="villa_space_id" id="villaSpaceSelect">
                                    <option value="">No specific space</option>
                                    <?php foreach ($all_spaces as $space): ?>
                                    <option value="<?php echo (int)$space['id']; ?>" data-villa-id="<?php echo (int)$space['villa_id']; ?>" <?php echo (int)$unit['villa_space_id'] === (int)$space['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($space['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Unit Name *</label><input type="text" name="name" value="<?php echo htmlspecialchars((string)$unit['name']); ?>" required></div>
                            <div class="form-group"><label>Slug</label><input type="text" name="slug" value="<?php echo htmlspecialchars((string)$unit['slug']); ?>"></div>
                            <div class="form-group"><label>Unit Type *</label><select name="unit_type"><?php foreach ($type_labels as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $unit['unit_type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Subtitle</label><input type="text" name="subtitle" value="<?php echo htmlspecialchars((string)$unit['subtitle']); ?>"></div>
                            <div class="form-group"><label>Max Guests</label><input type="text" name="max_guests" value="<?php echo htmlspecialchars((string)$unit['max_guests']); ?>" placeholder="e.g. 2 Adults"></div>
                            <div class="form-group"><label>Bed Info</label><input type="text" name="bed_info" value="<?php echo htmlspecialchars((string)$unit['bed_info']); ?>" placeholder="e.g. 1 King Bed"></div>
                            <div class="form-group"><label>Size Label</label><input type="text" name="size_label" value="<?php echo htmlspecialchars((string)$unit['size_label']); ?>" placeholder="e.g. 450 sq ft"></div>
                            <div class="form-group form-full"><label>Summary</label><textarea name="summary" rows="3"><?php echo htmlspecialchars((string)$unit['summary']); ?></textarea></div>
                            <div class="form-group form-full"><label>Description *</label><textarea name="description" rows="7" required><?php echo htmlspecialchars((string)$unit['description']); ?></textarea></div>
                            <div class="form-group form-full"><label>Pricing Note</label><textarea name="pricing_note" rows="4"><?php echo htmlspecialchars((string)$unit['pricing_note']); ?></textarea></div>
                        </div>
                    </div>
                    <div class="form-card">
                        <div class="form-card-title">Featured Image</div>
                        <div class="form-group" style="margin-top:20px">
                            <label>Upload Image</label>
                            <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp">
                            <?php if (!empty($unit['featured_image_path']) && file_exists('../' . $unit['featured_image_path'])): ?>
                            <div style="margin-top:10px"><img src="../<?php echo htmlspecialchars($unit['featured_image_path']); ?>" alt="" style="width:100%;max-width:220px;border-radius:10px"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-card" style="margin-top:20px">
                        <div class="form-card-title">Unit Gallery</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 12px">These images appear in the Bookable Units slider on the public villa space page. The featured image shows first, then the gallery images continue in order. Drag existing photos to reorder, add captions, and remove any you no longer need.</p>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Add Gallery Images</label>
                            <input type="file" name="gallery_images[]" id="unitGalleryImagesInput" multiple accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG, WebP. Max 25MB each. Selected images can be previewed, captioned, and removed before saving.</span>
                            <div id="unitGalleryUploadSummary" style="margin-top:10px;font-size:0.78rem;color:var(--text-muted)"></div>
                            <div id="unitGalleryNewPreview" class="tour-album-new-previews"></div>
                        </div>

                        <input type="hidden" name="gallery_order" id="unitGalleryOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(static fn($x) => (int)$x['id'], $gallery_images))); ?>">

                        <?php if (!empty($gallery_images)): ?>
                        <div id="unitGallerySortable" class="tour-album-sortable">
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
                            <p style="margin:0;color:var(--text-muted);font-size:0.8rem">No gallery images yet. Upload a few to activate the slider on the unit card.</p>
                        </div>
                        <?php endif; ?>

                        <div id="unitGalleryDeleteIds"></div>
                    </div>
                </div>
                <div class="edit-sidebar">
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Options</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?php echo $unit['is_active'] ? 'checked' : ''; ?>><span><strong>Active</strong><small>Display this unit on the site</small></span></label>
                            <label class="checkbox-row"><input type="checkbox" name="is_featured" value="1" <?php echo $unit['is_featured'] ? 'checked' : ''; ?>><span><strong>Featured</strong><small>Highlight this unit in the frontend</small></span></label>
                        </div>
                        <div class="form-group" style="margin-top:16px">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?php echo (int)$unit['sort_order']; ?>" min="0">
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%" id="bookableUnitSaveBtn"><i class="fas fa-save"></i> <?php echo $is_edit ? 'Save Changes' : 'Add Unit'; ?></button>
                            <a href="bookable-units.php<?php echo $unit['villa_space_id'] ? '?space_id=' . $unit['villa_space_id'] : ($unit['villa_id'] ? '?villa_id=' . $unit['villa_id'] : ''); ?>" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Upload Progress</div>
                        <div style="margin-top:16px">
                            <div style="height:10px;border-radius:999px;background:var(--dark-4);overflow:hidden;border:1px solid var(--border)">
                                <div id="bookableUnitUploadProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg, var(--gold), #7bc6a4);transition:width 0.2s ease"></div>
                            </div>
                            <div id="bookableUnitUploadProgressText" style="margin-top:10px;font-size:0.8rem;color:var(--text-muted)">Ready to save. Progress appears here when uploading images.</div>
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
const bookableUnitEditForm = document.getElementById('bookableUnitEditForm');
const bookableUnitSaveBtn = document.getElementById('bookableUnitSaveBtn');
const progressBar = document.getElementById('bookableUnitUploadProgressBar');
const progressText = document.getElementById('bookableUnitUploadProgressText');
const villaSelect = document.getElementById('villaIdSelect');
const spaceSelect = document.getElementById('villaSpaceSelect');
const galleryInput = document.getElementById('unitGalleryImagesInput');
const gallerySummary = document.getElementById('unitGalleryUploadSummary');
const galleryPreview = document.getElementById('unitGalleryNewPreview');
const gallerySortable = document.getElementById('unitGallerySortable');
const galleryOrderInput = document.getElementById('unitGalleryOrderInput');
const galleryDeleteWrap = document.getElementById('unitGalleryDeleteIds');

function filterSpaces() {
    const villaId = villaSelect ? villaSelect.value : '';
    if (!spaceSelect) return;
    Array.from(spaceSelect.options).forEach((option, index) => {
        if (index === 0) {
            option.hidden = false;
            return;
        }
        const matches = !villaId || option.dataset.villaId === villaId;
        option.hidden = !matches;
    });
    const selected = spaceSelect.options[spaceSelect.selectedIndex];
    if (selected && selected.hidden) spaceSelect.value = '';
}
villaSelect?.addEventListener('change', filterSpaces);
filterSpaces();

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

if (bookableUnitEditForm && window.XMLHttpRequest) {
    bookableUnitEditForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const xhr = new XMLHttpRequest();
        const formData = new FormData(bookableUnitEditForm);
        const originalBtnHtml = bookableUnitSaveBtn ? bookableUnitSaveBtn.innerHTML : '';

        if (bookableUnitSaveBtn) {
            bookableUnitSaveBtn.disabled = true;
            bookableUnitSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
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
            if (bookableUnitSaveBtn) {
                bookableUnitSaveBtn.disabled = false;
                bookableUnitSaveBtn.innerHTML = originalBtnHtml;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                if (progressBar) progressBar.style.width = '100%';
                if (progressText) progressText.textContent = 'Processing saved data...';

                const responseUrl = xhr.responseURL || '';
                if (responseUrl && !responseUrl.includes('bookable-unit-edit.php')) {
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
            if (bookableUnitSaveBtn) {
                bookableUnitSaveBtn.disabled = false;
                bookableUnitSaveBtn.innerHTML = originalBtnHtml;
            }
            if (progressText) progressText.textContent = 'Network error while uploading. Please try again.';
        });

        xhr.send(formData);
    });
}
</script>
</body>
</html>
