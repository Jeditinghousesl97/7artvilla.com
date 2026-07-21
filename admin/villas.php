<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $id = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare('SELECT hero_image_path, featured_image_path FROM villas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare('DELETE FROM villas WHERE id = ?')->execute([$id]);
            stay_delete_public_file((string)($row['hero_image_path'] ?? ''));
            if (($row['featured_image_path'] ?? '') !== ($row['hero_image_path'] ?? '')) {
                stay_delete_public_file((string)($row['featured_image_path'] ?? ''));
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Villa deleted successfully.'];
        }
    }
    header('Location: villas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $id = (int)$_POST['toggle_id'];
        $active = (int)$_POST['active'];
        $pdo->prepare('UPDATE villas SET is_active = ? WHERE id = ?')->execute([$active, $id]);
    }
    header('Location: villas.php');
    exit;
}

$villas = $pdo->query("
    SELECT v.*,
           (SELECT COUNT(*) FROM villa_spaces s WHERE s.villa_id = v.id) AS spaces_count,
           (SELECT COUNT(*) FROM bookable_units u WHERE u.villa_id = v.id) AS units_count
    FROM villas v
    ORDER BY v.sort_order ASC, v.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villas | 7 Art Villa Admin</title>
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
                <div class="topbar-title">Villas</div>
                <div class="topbar-sub"><?php echo count($villas); ?> total villa records</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="villa-edit.php" class="topbar-btn topbar-btn-gold"><i class="fas fa-plus"></i> Add Villa</a>
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
                <h2>Villas</h2>
                <p>Create and manage main villa or property records.</p>
            </div>
        </div>

        <?php if (empty($villas)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-house"></i>
                <h3>No villas yet</h3>
                <p>Add your first villa to start building the new stay structure.</p>
                <a href="villa-edit.php" class="btn-admin btn-gold btn-sm" style="margin-top:8px"><i class="fas fa-plus"></i> Add Villa</a>
            </div>
        </div>
        <?php else: ?>
        <div class="tours-admin-grid">
            <?php foreach ($villas as $villa): ?>
            <div class="tour-admin-card <?php echo !$villa['is_active'] ? 'inactive' : ''; ?>">
                <div class="tac-image">
                    <?php if (!empty($villa['featured_image_path']) && file_exists('../' . $villa['featured_image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($villa['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($villa['name']); ?>">
                    <?php else: ?>
                    <div class="tac-img-placeholder"><i class="fas fa-house"></i></div>
                    <?php endif; ?>
                    <?php if ((int)$villa['is_homepage'] === 1): ?><span class="tac-category-badge">Homepage</span><?php endif; ?>
                    <?php if ((int)$villa['is_featured'] === 1): ?><span class="tac-badge-popular">Featured</span><?php endif; ?>
                </div>

                <div class="tac-body">
                    <div class="tac-header">
                        <h3><?php echo htmlspecialchars($villa['name']); ?></h3>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="toggle_id" value="<?php echo $villa['id']; ?>">
                            <input type="hidden" name="active" value="<?php echo $villa['is_active'] ? 0 : 1; ?>">
                            <label class="toggle-switch" title="<?php echo $villa['is_active'] ? 'Active' : 'Inactive'; ?>">
                                <input type="checkbox" <?php echo $villa['is_active'] ? 'checked' : ''; ?> onchange="this.closest('form').submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </div>
                    <?php if (!empty($villa['tagline'])): ?><p class="tac-tagline"><?php echo htmlspecialchars($villa['tagline']); ?></p><?php endif; ?>
                    <div class="tac-meta">
                        <?php if (!empty($villa['location_label'])): ?><span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($villa['location_label']); ?></span><?php endif; ?>
                        <span><i class="fas fa-layer-group"></i> <?php echo (int)$villa['spaces_count']; ?> spaces</span>
                        <span><i class="fas fa-bed"></i> <?php echo (int)$villa['units_count']; ?> bookable units</span>
                    </div>
                </div>

                <div class="tac-footer">
                    <span class="badge <?php echo $villa['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $villa['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <div class="tbl-actions">
                        <a href="../villa.php?slug=<?php echo urlencode((string)$villa['slug']); ?>" class="tbl-btn tbl-btn-view" title="View" target="_blank" rel="noopener noreferrer"><i class="fas fa-eye"></i></a>
                        <a href="villa-spaces.php?villa_id=<?php echo $villa['id']; ?>" class="tbl-btn tbl-btn-view" title="Spaces"><i class="fas fa-sitemap"></i></a>
                        <a href="villa-edit.php?id=<?php echo $villa['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $villa['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete" data-confirm="Delete '<?php echo htmlspecialchars($villa['name']); ?>'? This will remove its spaces, units, and pricing.">
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
</body>
</html>
