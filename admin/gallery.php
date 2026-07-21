<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$filter_query = '';
if (isset($_GET['filter']) && $_GET['filter'] !== '') {
    $filter_query = '?filter=' . urlencode((string)$_GET['filter']);
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $did = (int)$_POST['delete_id'];
        $row = $pdo->prepare('SELECT image_path FROM gallery_images WHERE id = ?');
        $row->execute([$did]);
        $img = $row->fetchColumn();
        if ($img && file_exists('../' . $img)) unlink('../' . $img);
        $pdo->prepare('DELETE FROM gallery_images WHERE id = ?')->execute([$did]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Image deleted.'];
    }
    header('Location: gallery.php' . $filter_query);
    exit;
}

// Handle inline toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'], $_POST['toggle_field'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $tid   = (int)$_POST['toggle_id'];
        $field = $_POST['toggle_field'] === 'show_on_home' ? 'show_on_home' : 'is_active';
        $val   = (int)$_POST['toggle_val'];
        $pdo->prepare("UPDATE gallery_images SET {$field} = ? WHERE id = ?")->execute([$val, $tid]);
    }
    header('Location: gallery.php' . $filter_query);
    exit;
}

// Handle drag-and-drop ordering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gallery_order'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $order_ids = [];
        foreach (explode(',', (string)$_POST['gallery_order']) as $image_id) {
            $image_id = (int)$image_id;
            if ($image_id > 0) $order_ids[] = $image_id;
        }

        if (!empty($order_ids)) {
            $pdo->beginTransaction();
            try {
                $update_sort = $pdo->prepare('UPDATE gallery_images SET sort_order = ? WHERE id = ?');
                foreach ($order_ids as $index => $image_id) {
                    $update_sort->execute([$index + 1, $image_id]);
                }
                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Gallery image order updated.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Unable to save gallery order. Please try again.'];
            }
        }
    }
    header('Location: gallery.php' . $filter_query);
    exit;
}

// Fetch
$filter  = $_GET['filter'] ?? 'all';
$allowed = ['all', 'villa', 'pool', 'views', 'nature', 'dining'];
if (!in_array($filter, $allowed)) $filter = 'all';

$where  = $filter !== 'all' ? "WHERE category = " . $pdo->quote($filter) : '';
$images = $pdo->query("SELECT * FROM gallery_images $where ORDER BY sort_order ASC, id ASC")->fetchAll();

$counts = $pdo->query("SELECT category, COUNT(*) n FROM gallery_images GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
$total  = array_sum($counts);
$can_reorder = $filter === 'all' && !empty($images);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery | We Trail Admin</title>
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
                <div class="topbar-title">Gallery</div>
                <div class="topbar-sub"><?php echo $total; ?> image<?php echo $total !== 1 ? 's' : ''; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="gallery-upload.php" class="topbar-btn topbar-btn-gold">
                <i class="fas fa-cloud-upload-alt"></i> Upload Images
            </a>
        </div>
    </header>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" data-auto-dismiss>
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="page-header-text">
                <h2>Gallery</h2>
                <p>Upload and manage all photos displayed in the gallery and on the home page.</p>
            </div>
            <?php if ($can_reorder): ?>
            <form method="POST" id="galleryOrderForm">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="gallery_order" id="galleryOrderInput" value="<?php echo htmlspecialchars(implode(',', array_map(static fn($x) => (int)$x['id'], $images))); ?>">
                <button type="submit" class="btn-admin btn-gold btn-sm" id="galleryOrderSaveBtn" disabled>
                    <i class="fas fa-arrows-up-down-left-right"></i> Save Order
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="filter-row" style="margin-bottom:20px">
            <div class="filter-btn-group">
                <a href="?filter=all"    class="filter-tab <?php echo $filter==='all'    ?'active':''; ?>">All <span class="filter-count"><?php echo $total; ?></span></a>
                <a href="?filter=villa"  class="filter-tab <?php echo $filter==='villa'  ?'active':''; ?>">Villa <span class="filter-count"><?php echo $counts['villa']??0; ?></span></a>
                <a href="?filter=pool"   class="filter-tab <?php echo $filter==='pool'   ?'active':''; ?>">Pool <span class="filter-count"><?php echo $counts['pool']??0; ?></span></a>
                <a href="?filter=views"  class="filter-tab <?php echo $filter==='views'  ?'active':''; ?>">Views <span class="filter-count"><?php echo $counts['views']??0; ?></span></a>
                <a href="?filter=nature" class="filter-tab <?php echo $filter==='nature' ?'active':''; ?>">Nature <span class="filter-count"><?php echo $counts['nature']??0; ?></span></a>
                <a href="?filter=dining" class="filter-tab <?php echo $filter==='dining' ?'active':''; ?>">Dining <span class="filter-count"><?php echo $counts['dining']??0; ?></span></a>
            </div>
        </div>

        <?php if (!empty($images)): ?>
        <div class="admin-card" style="padding:14px 16px;margin-bottom:18px">
            <p style="margin:0;color:var(--text-muted);font-size:0.82rem">
                <?php if ($can_reorder): ?>
                Drag and drop gallery cards to change the display order, then click <strong style="color:var(--text-primary)">Save Order</strong>.
                <?php else: ?>
                Switch to the <strong style="color:var(--text-primary)">All</strong> filter to reorder gallery images with drag and drop.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (empty($images)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>No images yet</h3>
                <p>Upload your first photos to populate the gallery.</p>
                <a href="gallery-upload.php" class="btn-admin btn-gold btn-sm" style="margin-top:8px">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Images
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="gal-admin-grid" id="galleryAdminGrid">
            <?php foreach ($images as $img): ?>
            <div class="gal-admin-card <?php echo !$img['is_active'] ? 'inactive' : ''; ?><?php echo $can_reorder ? ' gal-admin-card-sortable' : ''; ?>"
                 <?php if ($can_reorder): ?>draggable="true" data-image-id="<?php echo (int)$img['id']; ?>"<?php endif; ?>>

                <!-- Thumbnail -->
                <div class="gal-admin-thumb">
                    <?php if (file_exists('../' . $img['image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($img['image_path']); ?>"
                         alt="<?php echo htmlspecialchars($img['caption'] ?? ''); ?>"
                         loading="lazy">
                    <?php else: ?>
                    <div class="gal-admin-thumb-placeholder">
                        <i class="fas fa-image"></i>
                    </div>
                    <?php endif; ?>

                    <!-- Badges overlay -->
                    <div class="gal-admin-badges">
                        <?php $cat_icons = ['villa'=>'fa-home','pool'=>'fa-swimming-pool','views'=>'fa-mountain','nature'=>'fa-leaf','dining'=>'fa-utensils']; ?>
                        <span class="gal-cat-badge">
                            <i class="fas <?php echo $cat_icons[$img['category']] ?? 'fa-image'; ?>"></i>
                            <?php echo ucfirst($img['category']); ?>
                        </span>
                        <?php if ($img['span_col']): ?><span class="gal-span-badge" title="Spans 2 columns">2col</span><?php endif; ?>
                        <?php if ($img['span_row']): ?><span class="gal-span-badge" title="Spans 2 rows">2row</span><?php endif; ?>
                    </div>
                </div>

                <!-- Body -->
                <div class="gal-admin-body">
                    <p class="gal-admin-caption">
                        <?php echo $img['caption'] ? htmlspecialchars($img['caption']) : '<em style="color:var(--text-muted)">No caption</em>'; ?>
                    </p>
                    <div class="gal-admin-toggles">
                        <!-- Active toggle -->
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="toggle_id" value="<?php echo $img['id']; ?>">
                            <input type="hidden" name="toggle_field" value="is_active">
                            <input type="hidden" name="toggle_val" value="<?php echo $img['is_active'] ? 0 : 1; ?>">
                            <button type="submit" class="gal-toggle-btn <?php echo $img['is_active'] ? 'on' : 'off'; ?>" title="<?php echo $img['is_active'] ? 'Active â€” click to hide' : 'Inactive â€” click to show'; ?>">
                                <i class="fas <?php echo $img['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                <?php echo $img['is_active'] ? 'Active' : 'Hidden'; ?>
                            </button>
                        </form>
                        <!-- Home page toggle -->
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="toggle_id" value="<?php echo $img['id']; ?>">
                            <input type="hidden" name="toggle_field" value="show_on_home">
                            <input type="hidden" name="toggle_val" value="<?php echo $img['show_on_home'] ? 0 : 1; ?>">
                            <button type="submit" class="gal-toggle-btn <?php echo $img['show_on_home'] ? 'home-on' : 'off'; ?>" title="<?php echo $img['show_on_home'] ? 'Shown on home page' : 'Not on home page'; ?>">
                                <i class="fas fa-home"></i>
                                <?php echo $img['show_on_home'] ? 'Homepage' : 'Gallery Only'; ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="gal-admin-footer">
                    <span class="gal-sort-num">#<?php echo (int)$img['sort_order'] ?: $img['id']; ?></span>
                    <div class="tbl-actions">
                        <a href="gallery-edit.php?id=<?php echo $img['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $img['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete"
                                    data-confirm="Delete this image permanently? This cannot be undone.">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
<script>
const galleryAdminGrid = document.getElementById('galleryAdminGrid');
const galleryOrderInput = document.getElementById('galleryOrderInput');
const galleryOrderSaveBtn = document.getElementById('galleryOrderSaveBtn');

if (galleryAdminGrid && galleryOrderInput && galleryOrderSaveBtn) {
    let dragItem = null;
    const originalOrder = galleryOrderInput.value;

    function syncGalleryOrder() {
        const ids = Array.from(galleryAdminGrid.querySelectorAll('.gal-admin-card-sortable'))
            .map((element) => element.getAttribute('data-image-id'))
            .filter(Boolean);
        galleryOrderInput.value = ids.join(',');
        galleryOrderSaveBtn.disabled = galleryOrderInput.value === originalOrder;
    }

    galleryAdminGrid.addEventListener('dragstart', (event) => {
        const item = event.target.closest('.gal-admin-card-sortable');
        if (!item) return;
        dragItem = item;
        item.classList.add('dragging');
    });

    galleryAdminGrid.addEventListener('dragend', () => {
        if (!dragItem) return;
        dragItem.classList.remove('dragging');
        dragItem = null;
        syncGalleryOrder();
    });

    galleryAdminGrid.addEventListener('dragover', (event) => {
        event.preventDefault();
        const target = event.target.closest('.gal-admin-card-sortable');
        if (!dragItem || !target || target === dragItem) return;

        const rect = target.getBoundingClientRect();
        const after = event.clientY > rect.top + (rect.height / 2);
        if (after) target.parentNode.insertBefore(dragItem, target.nextSibling);
        else target.parentNode.insertBefore(dragItem, target);
    });

    syncGalleryOrder();
}
</script>
</body>
</html>
