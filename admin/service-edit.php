<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

// Default values
$svc = [
    'id' => 0, 'title' => '', 'description' => '',
    'category' => 'included', 'icon' => '',
    'features' => '', 'is_active' => 1,
    'sort_order' => 0, 'image_path' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found.'];
        header('Location: ' . admin_url('services.php'));
        exit;
    }
    $svc = $row;
    $ft = json_decode($svc['features'] ?? '[]', true);
    $svc['features_text'] = is_array($ft) ? implode("\n", $ft) : '';
} else {
    $svc['features_text'] = '';
}

$errors = [];

if (!function_exists('detect_upload_mime_type')) {
    function detect_upload_mime_type($tmp_file) {
        if (!is_string($tmp_file) || $tmp_file === '' || !is_file($tmp_file)) {
            return '';
        }

        if (function_exists('finfo_open') && function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmp_file);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmp_file);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
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
                return strtolower((string)$info['mime']);
            }
        }

        return '';
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $category   = $_POST['category'] ?? 'included';
        $icon       = trim($_POST['icon'] ?? '');
        $ft_text    = trim($_POST['features'] ?? '');
        $is_active  = isset($_POST['is_active'])  ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        $allowed_cats = ['included', 'request', 'extra'];
        if (!in_array($category, $allowed_cats)) $category = 'included';

        // Validate
        if ($title === '') $errors[] = 'Title is required.';
        if ($desc  === '') $errors[] = 'Description is required.';

        // Features â†’ JSON array
        $features_arr  = array_filter(array_map('trim', explode("\n", $ft_text)));
        $features_json = json_encode(array_values($features_arr));

        // Handle image upload
        $image_path = $svc['image_path'];
        if (!empty($_FILES['image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $mime = detect_upload_mime_type($_FILES['image']['tmp_name'] ?? '');

            if (!in_array($mime, $allowed_types)) {
                $errors[] = 'Invalid image type. Only JPG, PNG, and WebP are allowed.';
            } elseif ($_FILES['image']['size'] > 25 * 1024 * 1024) {
                $errors[] = 'Image must be under 25MB.';
            } else {
                $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'svc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $dest_dir = '../assets/images/services/';
                if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
                $dest = $dest_dir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    if ($image_path && file_exists('../' . $image_path)) unlink('../' . $image_path);
                    $image_path = 'assets/images/services/' . $filename;
                } else {
                    $errors[] = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (empty($errors)) {
            if ($is_edit) {
                $pdo->prepare('
                    UPDATE services SET
                        title=?, description=?, category=?, icon=?,
                        features=?, is_active=?, sort_order=?, image_path=?
                    WHERE id=?
                ')->execute([
                    $title, $desc, $category, $icon,
                    $features_json, $is_active, $sort_order, $image_path, $id
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$title}\" updated successfully."];
            } else {
                $pdo->prepare('
                    INSERT INTO services
                        (title, description, category, icon, features, is_active, sort_order, image_path)
                    VALUES (?,?,?,?,?,?,?,?)
                ')->execute([
                    $title, $desc, $category, $icon,
                    $features_json, $is_active, $sort_order, $image_path
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$title}\" added successfully."];
            }
            header('Location: ' . admin_url('services.php'));
            exit;
        }

        // Repopulate on error
        $svc = array_merge($svc, compact(
            'title', 'desc', 'category', 'icon', 'is_active', 'sort_order'
        ));
        $svc['description']   = $desc;
        $svc['features_text'] = $ft_text;
    }
}

// Icon suggestions for the picker
$icon_suggestions = [
    'fa-wifi'           => 'Wi-Fi',
    'fa-swimming-pool'  => 'Pool',
    'fa-utensils'       => 'Dining',
    'fa-car'            => 'Transport',
    'fa-spa'            => 'Spa',
    'fa-concierge-bell' => 'Concierge',
    'fa-leaf'           => 'Nature',
    'fa-campfire'       => 'Campfire',
    'fa-binoculars'     => 'Safari',
    'fa-camera'         => 'Photography',
    'fa-dumbbell'       => 'Fitness',
    'fa-umbrella-beach' => 'Beach',
    'fa-wine-glass-alt' => 'Bar',
    'fa-bed'            => 'Bedroom',
    'fa-shower'         => 'Shower',
    'fa-tv'             => 'TV',
    'fa-fan'            => 'Fan/AC',
    'fa-fire'           => 'Fire',
    'fa-star'           => 'Featured',
    'fa-heart'          => 'Favourite',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Service' : 'Add Service'; ?> | 7 Art Villa Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Service' : 'Add Service'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars($svc['title']) : 'Create a new service'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="services.php" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Services
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
                                <label>Service Title *</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($svc['title']); ?>" placeholder="e.g. Complimentary Breakfast" required>
                            </div>
                            <div class="form-group form-full">
                                <label>Description *</label>
                                <textarea name="description" rows="4" placeholder="Full description of the serviceâ€¦" required><?php echo htmlspecialchars($svc['description']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category">
                                    <option value="included" <?php echo $svc['category'] === 'included' ? 'selected' : ''; ?>>Included (free with stay)</option>
                                    <option value="request"  <?php echo $svc['category'] === 'request'  ? 'selected' : ''; ?>>On Request (arrange in advance)</option>
                                    <option value="extra"    <?php echo $svc['category'] === 'extra'    ? 'selected' : ''; ?>>Extra (additional charge)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" value="<?php echo (int)$svc['sort_order']; ?>" min="0" placeholder="0">
                                <span class="form-hint">Lower = shown first</span>
                            </div>
                        </div>
                    </div>

                    <!-- Icon -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Service Icon</div>
                        <div style="margin-top:20px">
                            <div class="form-group">
                                <label>Font Awesome Icon Class</label>
                                <div style="display:flex;gap:10px;align-items:center">
                                    <span class="icon-preview-box" id="iconPreview">
                                        <i class="fas <?php echo htmlspecialchars($svc['icon'] ?: 'fa-concierge-bell'); ?>" id="iconPreviewIcon"></i>
                                    </span>
                                    <input type="text" name="icon" id="iconInput"
                                           value="<?php echo htmlspecialchars($svc['icon'] ?? ''); ?>"
                                           placeholder="e.g. fa-wifi">
                                </div>
                                <span class="form-hint">Enter a Font Awesome class or click a suggestion below.</span>
                            </div>
                            <!-- Icon suggestions -->
                            <div class="icon-suggestions">
                                <?php foreach ($icon_suggestions as $cls => $label): ?>
                                <button type="button" class="icon-suggestion-btn"
                                        data-icon="<?php echo htmlspecialchars($cls); ?>"
                                        title="<?php echo htmlspecialchars($label); ?>">
                                    <i class="fas <?php echo htmlspecialchars($cls); ?>"></i>
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="form-card">
                        <div class="form-card-title">Service Features</div>
                        <div style="margin-top:20px">
                            <div class="form-group">
                                <label>Features</label>
                                <textarea name="features" rows="6" placeholder="Daily housekeeping&#10;Premium bed linen&#10;Towels provided&#10;Welcome drink"><?php echo htmlspecialchars($svc['features_text'] ?? ''); ?></textarea>
                                <span class="form-hint">One feature per line â€” each becomes a bullet point on the website.</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT: Image + Options -->
                <div class="edit-sidebar">

                    <!-- Publish -->
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Publish</div>
                        <div style="margin-top:16px">
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_active" value="1" <?php echo $svc['is_active'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Active</strong>
                                    <small>Show this service on the website</small>
                                </span>
                            </label>
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%">
                                <i class="fas fa-<?php echo $is_edit ? 'save' : 'plus'; ?>"></i>
                                <?php echo $is_edit ? 'Save Changes' : 'Add Service'; ?>
                            </button>
                            <a href="services.php" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="form-card">
                        <div class="form-card-title">Service Image</div>
                        <div style="margin-top:16px">
                            <?php if ($svc['image_path'] && file_exists('../' . $svc['image_path'])): ?>
                            <div class="img-preview-wrap" style="margin-bottom:12px">
                                <img id="imgPreview" src="../<?php echo htmlspecialchars($svc['image_path']); ?>"
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
                                <span class="form-hint">JPG, PNG or WebP â€” max 25MB<br>Optional: icon is used if no image uploaded.</span>
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
// Icon picker
const iconInput = document.getElementById('iconInput');
const iconPreviewIcon = document.getElementById('iconPreviewIcon');

document.querySelectorAll('.icon-suggestion-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const cls = this.dataset.icon;
        iconInput.value = cls;
        iconPreviewIcon.className = 'fas ' + cls;
        document.querySelectorAll('.icon-suggestion-btn').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
    });
});

iconInput.addEventListener('input', function() {
    const cls = this.value.trim();
    iconPreviewIcon.className = 'fas ' + (cls || 'fa-concierge-bell');
});

// Highlight matching suggestion on load
const currentIcon = iconInput.value.trim();
if (currentIcon) {
    document.querySelectorAll('.icon-suggestion-btn').forEach(btn => {
        if (btn.dataset.icon === currentIcon) btn.classList.add('selected');
    });
}

// Image preview
document.getElementById('imageInput')?.addEventListener('change', function() {
    const placeholder = document.getElementById('imgPlaceholder');
    if (placeholder) placeholder.style.display = 'none';
});
</script>
</body>
</html>
