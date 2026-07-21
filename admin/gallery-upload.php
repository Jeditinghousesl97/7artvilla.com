<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo    = db();
$errors  = [];
$success = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $category     = $_POST['category'] ?? 'villa';
        $caption      = trim($_POST['caption'] ?? '');
        $show_on_home = isset($_POST['show_on_home']) ? 1 : 0;
        $is_active    = isset($_POST['is_active']) ? 1 : 0;
        $sort_order   = (int)($_POST['sort_order'] ?? 0);

        $allowed_cats = ['villa', 'pool', 'views', 'nature', 'dining'];
        if (!in_array($category, $allowed_cats)) $category = 'villa';

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $dest_dir = '../assets/images/gallery/';
        if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

        // Handle multiple file uploads
        $files = $_FILES['images'] ?? [];
        $file_count = is_array($files['name']) ? count($files['name']) : 0;

        if ($file_count === 0) {
            $errors[] = 'Please select at least one image to upload.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO gallery_images
                    (caption, category, image_path, show_on_home, is_active, sort_order)
                VALUES (?,?,?,?,?,?)
            ');

            $mime_from_extension = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
            ];

            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

                $tmp_path = $files['tmp_name'][$i];
                $mime = '';

                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime = (string)finfo_file($finfo, $tmp_path);
                        finfo_close($finfo);
                    }
                }

                if ($mime === '' && function_exists('exif_imagetype')) {
                    $img_type = @exif_imagetype($tmp_path);
                    if ($img_type !== false) {
                        $mime = (string)image_type_to_mime_type($img_type);
                    }
                }

                if ($mime === '' && function_exists('getimagesize')) {
                    $img_info = @getimagesize($tmp_path);
                    if (!empty($img_info['mime'])) {
                        $mime = (string)$img_info['mime'];
                    }
                }

                if ($mime === '') {
                    $ext_guess = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    $mime = $mime_from_extension[$ext_guess] ?? '';
                }

                if (!in_array($mime, $allowed_types)) {
                    $errors[] = htmlspecialchars($files['name'][$i]) . ': Invalid type (JPG, PNG, WebP only).';
                    continue;
                }
                if ($files['size'][$i] > 25 * 1024 * 1024) {
                    $errors[] = htmlspecialchars($files['name'][$i]) . ': File exceeds 25MB limit.';
                    continue;
                }

                $ext      = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $filename = 'gal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest     = $dest_dir . $filename;

                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    // Use individual captions if multiple files, else shared caption
                    $this_caption = $file_count > 1 ? '' : $caption;
                    $stmt->execute([
                        $this_caption,
                        $category,
                        'assets/images/gallery/' . $filename,
                        $show_on_home,
                        $is_active,
                        $sort_order
                    ]);
                    $success++;
                } else {
                    $errors[] = htmlspecialchars($files['name'][$i]) . ': Failed to save. Check folder permissions.';
                }
            }

            if ($success > 0) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'msg'  => $success . ' image' . ($success !== 1 ? 's' : '') . ' uploaded successfully.'
                ];
                if (empty($errors)) {
                    header('Location: ' . admin_url('gallery.php'));
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Images | We Trail Admin</title>
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
                <div class="topbar-title">Upload Images</div>
                <div class="topbar-sub">Add new photos to the gallery</div>
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
            <div>
                <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success > 0 && !empty($errors)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?> image<?php echo $success !== 1 ? 's' : ''; ?> uploaded â€” some files had errors above.
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="edit-layout">

                <!-- LEFT -->
                <div class="edit-main">

                    <!-- Drop Zone -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Select Images</div>
                        <div style="margin-top:20px">
                            <div class="gal-drop-zone" id="dropZone">
                                <input type="file" name="images[]" id="imageFiles"
                                       accept="image/jpeg,image/png,image/webp"
                                       multiple style="display:none">
                                <div class="gal-drop-inner" id="dropInner">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Click to browse</strong> or drag &amp; drop images here</p>
                                    <span>JPG, PNG or WebP â€” max 25MB per file â€” multiple files allowed</span>
                                </div>
                                <div class="gal-drop-previews" id="dropPreviews"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Caption (single upload only) -->
                    <div class="form-card" id="captionCard">
                        <div class="form-card-title">Caption</div>
                        <div style="margin-top:20px">
                            <div class="form-group">
                                <label>Caption</label>
                                <input type="text" name="caption"
                                       placeholder="e.g. A-Frame Villa Exterior at Night"
                                       value="<?php echo htmlspecialchars($_POST['caption'] ?? ''); ?>">
                                <span class="form-hint" id="captionHint">Shown in the gallery lightbox and as alt text. When uploading multiple images, captions can be set individually after upload via the edit button.</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT -->
                <div class="edit-sidebar">

                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Image Settings</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:16px">
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category">
                                    <?php
                                    $cats = ['villa'=>'Villa','pool'=>'Pool','views'=>'Views','nature'=>'Nature','dining'=>'Dining'];
                                    $sel  = $_POST['category'] ?? 'villa';
                                    foreach ($cats as $val => $label):
                                    ?>
                                    <option value="<?php echo $val; ?>" <?php echo $sel === $val ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" min="0" placeholder="0"
                                       value="<?php echo (int)($_POST['sort_order'] ?? 0); ?>">
                                <span class="form-hint">Lower = shown first</span>
                            </div>
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_active" value="1" <?php echo ($_POST['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Active</strong>
                                    <small>Show in gallery on website</small>
                                </span>
                            </label>
                            <label class="checkbox-row">
                                <input type="checkbox" name="show_on_home" value="1" <?php echo isset($_POST['show_on_home']) ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Show on Home Page</strong>
                                    <small>Include in home page gallery strip</small>
                                </span>
                            </label>
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%" id="uploadBtn">
                                <i class="fas fa-cloud-upload-alt"></i> Upload Images
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
<script>
const dropZone    = document.getElementById('dropZone');
const fileInput   = document.getElementById('imageFiles');
const dropInner   = document.getElementById('dropInner');
const dropPreviews = document.getElementById('dropPreviews');
const uploadBtn   = document.getElementById('uploadBtn');

// Click to open file picker
dropZone.addEventListener('click', e => {
    if (e.target.closest('.gal-drop-previews')) return;
    fileInput.click();
});

// Drag over
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
});

fileInput.addEventListener('change', () => handleFiles(fileInput.files));

function handleFiles(files) {
    if (!files.length) return;
    dropInner.style.display = files.length > 0 ? 'none' : '';
    dropPreviews.innerHTML = '';

    Array.from(files).forEach(file => {
        const wrap = document.createElement('div');
        wrap.className = 'gal-drop-preview-item';

        const reader = new FileReader();
        reader.onload = e => {
            wrap.innerHTML = `<img src="${e.target.result}" alt="${file.name}">
                              <span>${file.name}</span>`;
        };
        reader.readAsDataURL(file);
        dropPreviews.appendChild(wrap);
    });

    const count = files.length;
    uploadBtn.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${count} Image${count !== 1 ? 's' : ''}`;

    // Transfer files to input (when dropped)
    if (fileInput.files !== files) {
        const dt = new DataTransfer();
        Array.from(files).forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }
}
</script>
</body>
</html>
