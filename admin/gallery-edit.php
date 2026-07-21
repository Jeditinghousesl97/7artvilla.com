<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . admin_url('gallery.php'));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM gallery_images WHERE id = ?');
$stmt->execute([$id]);
$img = $stmt->fetch();

if (!$img) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Image not found.'];
    header('Location: ' . admin_url('gallery.php'));
    exit;
}

$errors = [];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $caption      = trim($_POST['caption'] ?? '');
        $category     = $_POST['category'] ?? 'villa';
        $show_on_home = isset($_POST['show_on_home']) ? 1 : 0;
        $span_col     = isset($_POST['span_col']) ? 1 : 0;
        $span_row     = isset($_POST['span_row']) ? 1 : 0;
        $is_active    = isset($_POST['is_active']) ? 1 : 0;
        $sort_order   = (int)($_POST['sort_order'] ?? 0);

        $allowed_cats = ['villa', 'pool', 'views', 'nature', 'dining'];
        if (!in_array($category, $allowed_cats)) $category = 'villa';

        // Optional image replacement
        $image_path = $img['image_path'];
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
                $filename = 'gal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest_dir = '../assets/images/gallery/';
                if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
                $dest = $dest_dir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    if ($image_path && file_exists('../' . $image_path)) unlink('../' . $image_path);
                    $image_path = 'assets/images/gallery/' . $filename;
                } else {
                    $errors[] = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (empty($errors)) {
            $pdo->prepare('
                UPDATE gallery_images SET
                    caption=?, category=?, image_path=?,
                    show_on_home=?, span_col=?, span_row=?,
                    is_active=?, sort_order=?
                WHERE id=?
            ')->execute([
                $caption, $category, $image_path,
                $show_on_home, $span_col, $span_row,
                $is_active, $sort_order, $id
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Image updated successfully.'];
            header('Location: ' . admin_url('gallery.php'));
            exit;
        }

        // Repopulate
        $img = array_merge($img, compact(
            'caption', 'category', 'show_on_home',
            'span_col', 'span_row', 'is_active', 'sort_order'
        ));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Image | We Trail Admin</title>
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
                <div class="topbar-title">Edit Image</div>
                <div class="topbar-sub">ID #<?php echo $id; ?> &mdash; <?php echo htmlspecialchars($img['caption'] ?: 'No caption'); ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="gallery.php" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Gallery
            </a>
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

                <!-- LEFT -->
                <div class="edit-main">

                    <!-- Caption & Category -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Image Details</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group form-full">
                                <label>Caption</label>
                                <input type="text" name="caption"
                                       value="<?php echo htmlspecialchars($img['caption'] ?? ''); ?>"
                                       placeholder="e.g. A-Frame Villa Exterior at Night">
                                <span class="form-hint">Shown in the gallery lightbox and used as image alt text.</span>
                            </div>
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category">
                                    <?php foreach (['villa'=>'Villa','pool'=>'Pool','views'=>'Views','nature'=>'Nature','dining'=>'Dining'] as $val => $lbl): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $img['category'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" value="<?php echo (int)$img['sort_order']; ?>" min="0">
                                <span class="form-hint">Lower = shown first</span>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Spans -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Masonry Layout</div>
                        <p style="font-size:0.82rem;color:var(--text-muted);margin:14px 0 16px">Control how this image spans in the gallery masonry grid. Use sparingly for visual variety.</p>
                        <div style="display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row">
                                <input type="checkbox" name="span_col" value="1" <?php echo $img['span_col'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Wide (Span 2 Columns)</strong>
                                    <small>Makes this image twice as wide in the masonry grid</small>
                                </span>
                            </label>
                            <label class="checkbox-row">
                                <input type="checkbox" name="span_row" value="1" <?php echo $img['span_row'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Tall (Span 2 Rows)</strong>
                                    <small>Makes this image twice as tall in the masonry grid</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Replace Image -->
                    <div class="form-card">
                        <div class="form-card-title">Replace Image</div>
                        <div style="margin-top:16px">
                            <div class="form-group">
                                <label>Upload New Image</label>
                                <input type="file" name="image" id="imageInput"
                                       accept="image/jpeg,image/png,image/webp"
                                       data-preview="imgPreview">
                                <span class="form-hint">Leave empty to keep the current image. JPG, PNG or WebP â€” max 25MB.</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT -->
                <div class="edit-sidebar">

                    <!-- Current Image -->
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Current Image</div>
                        <div style="margin-top:16px">
                            <div class="img-preview-wrap" style="margin-bottom:0">
                                <?php if (file_exists('../' . $img['image_path'])): ?>
                                <img id="imgPreview" src="../<?php echo htmlspecialchars($img['image_path']); ?>"
                                     alt="Current" class="img-preview" style="height:200px">
                                <?php else: ?>
                                <img id="imgPreview" src="" alt="" class="img-preview" style="display:none;height:200px">
                                <div class="img-placeholder-box"><i class="fas fa-image"></i><span>File not found</span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Visibility -->
                    <div class="form-card">
                        <div class="form-card-title">Visibility</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_active" value="1" <?php echo $img['is_active'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Active</strong>
                                    <small>Show in gallery on website</small>
                                </span>
                            </label>
                            <label class="checkbox-row">
                                <input type="checkbox" name="show_on_home" value="1" <?php echo $img['show_on_home'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Show on Home Page</strong>
                                    <small>Include in home page gallery strip</small>
                                </span>
                            </label>
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="gallery.php" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                </div>
            </div>
        </form>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
</body>
</html>
