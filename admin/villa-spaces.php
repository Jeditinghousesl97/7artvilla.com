<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$villa_id = (int)($_GET['villa_id'] ?? 0);
$villa = null;
if ($villa_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM villas WHERE id = ?');
    $stmt->execute([$villa_id]);
    $villa = $stmt->fetch(PDO::FETCH_ASSOC);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$space_slug_exists = static function (PDO $pdo, int $villa_id, string $slug): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM villa_spaces WHERE villa_id = ? AND slug = ?');
    $stmt->execute([$villa_id, $slug]);
    return (int)$stmt->fetchColumn() > 0;
};

$space_unique_slug = static function (PDO $pdo, int $villa_id, string $name, string $base_slug = '') use ($space_slug_exists): string {
    $slug_base = stay_slugify($base_slug !== '' ? $base_slug : $name, 'space');
    $slug = $slug_base;
    $suffix = 2;
    while ($space_slug_exists($pdo, $villa_id, $slug)) {
        $slug = $slug_base . '-' . $suffix;
        $suffix++;
    }
    return $slug;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $id = (int)$_POST['duplicate_id'];
        $stmt = $pdo->prepare('SELECT * FROM villa_spaces WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($source) {
            try {
                $pdo->beginTransaction();

                $copy_name = trim((string)$source['name']) . ' Copy';
                $copy_slug = $space_unique_slug($pdo, (int)$source['villa_id'], $copy_name, (string)$source['slug'] . '-copy');

                $insert = $pdo->prepare('
                    INSERT INTO villa_spaces
                        (villa_id, name, slug, subtitle, space_type, short_description, description, featured_image_path, is_active, sort_order)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ');
                $insert->execute([
                    (int)$source['villa_id'],
                    $copy_name,
                    $copy_slug,
                    (string)($source['subtitle'] ?? ''),
                    (string)$source['space_type'],
                    (string)($source['short_description'] ?? ''),
                    (string)($source['description'] ?? ''),
                    (string)($source['featured_image_path'] ?? ''),
                    0,
                    (int)$source['sort_order'],
                ]);

                $new_space_id = (int)$pdo->lastInsertId();

                $gallery_stmt = $pdo->prepare('
                    SELECT image_path, caption, sort_order
                    FROM villa_space_gallery_images
                    WHERE villa_space_id = ?
                    ORDER BY sort_order ASC, id ASC
                ');
                $gallery_stmt->execute([$id]);
                $gallery_rows = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($gallery_rows) {
                    $gallery_insert = $pdo->prepare('
                        INSERT INTO villa_space_gallery_images
                            (villa_space_id, image_path, caption, sort_order)
                        VALUES (?,?,?,?)
                    ');
                    foreach ($gallery_rows as $row) {
                        $gallery_insert->execute([
                            $new_space_id,
                            (string)$row['image_path'],
                            (string)($row['caption'] ?? ''),
                            (int)$row['sort_order'],
                        ]);
                    }
                }

                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Space duplicated successfully.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Unable to duplicate the villa space.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Villa space not found.'];
        }
    }
    $redirect = 'villa-spaces.php' . ($villa_id ? '?villa_id=' . $villa_id : '');
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $id = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare('SELECT featured_image_path FROM villa_spaces WHERE id = ?');
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        $gallery_stmt = $pdo->prepare('SELECT image_path FROM villa_space_gallery_images WHERE villa_space_id = ?');
        $gallery_stmt->execute([$id]);
        $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_COLUMN);
        $showcase_stmt = $pdo->prepare('SELECT image_path FROM villa_space_showcase_images WHERE villa_space_id = ?');
        $showcase_stmt->execute([$id]);
        $showcase_images = $showcase_stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare('DELETE FROM villa_spaces WHERE id = ?')->execute([$id]);
        if ($img) stay_delete_public_file((string)$img);
        foreach ($gallery_images as $gallery_img) {
            if ($gallery_img) stay_delete_public_file((string)$gallery_img);
        }
        foreach ($showcase_images as $showcase_img) {
            if ($showcase_img) stay_delete_public_file((string)$showcase_img);
        }
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Space deleted successfully.'];
    }
    $redirect = 'villa-spaces.php' . ($villa_id ? '?villa_id=' . $villa_id : '');
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare('UPDATE villa_spaces SET is_active = ? WHERE id = ?')->execute([(int)$_POST['active'], (int)$_POST['toggle_id']]);
    }
    $redirect = 'villa-spaces.php' . ($villa_id ? '?villa_id=' . $villa_id : '');
    header('Location: ' . $redirect);
    exit;
}

$type_labels = stay_space_type_labels();

if ($villa_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*,
               (SELECT COUNT(*) FROM bookable_units u WHERE u.villa_space_id = s.id) AS units_count,
               v.name AS villa_name
        FROM villa_spaces s
        JOIN villas v ON v.id = s.villa_id
        WHERE s.villa_id = ?
        ORDER BY s.sort_order ASC, s.id ASC
    ");
    $stmt->execute([$villa_id]);
} else {
    $stmt = $pdo->query("
        SELECT s.*,
               (SELECT COUNT(*) FROM bookable_units u WHERE u.villa_space_id = s.id) AS units_count,
               v.name AS villa_name
        FROM villa_spaces s
        JOIN villas v ON v.id = s.villa_id
        ORDER BY v.sort_order ASC, s.sort_order ASC, s.id ASC
    ");
}
$spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa Spaces | We Trail Admin</title>
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
                <div class="topbar-title">Villa Spaces</div>
                <div class="topbar-sub"><?php echo $villa ? htmlspecialchars($villa['name']) : 'All villa spaces'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="villa-space-edit.php<?php echo $villa_id ? '?villa_id=' . $villa_id : ''; ?>" class="topbar-btn topbar-btn-gold"><i class="fas fa-plus"></i> Add Space</a>
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
                <h2>Villa Spaces</h2>
                <p>Manage kabanas, sub-villas, and other sections under each villa.</p>
            </div>
        </div>

        <?php if (empty($spaces)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-sitemap"></i>
                <h3>No spaces yet</h3>
                <p>Add your first kabana or villa section.</p>
                <a href="villa-space-edit.php<?php echo $villa_id ? '?villa_id=' . $villa_id : ''; ?>" class="btn-admin btn-gold btn-sm" style="margin-top:8px"><i class="fas fa-plus"></i> Add Space</a>
            </div>
        </div>
        <?php else: ?>
        <div class="tours-admin-grid">
            <?php foreach ($spaces as $space): ?>
            <div class="tour-admin-card <?php echo !$space['is_active'] ? 'inactive' : ''; ?>">
                <div class="tac-image">
                    <?php if (!empty($space['featured_image_path']) && file_exists('../' . $space['featured_image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($space['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($space['name']); ?>">
                    <?php else: ?>
                    <div class="tac-img-placeholder"><i class="fas fa-door-open"></i></div>
                    <?php endif; ?>
                    <span class="tac-category-badge"><?php echo htmlspecialchars($type_labels[$space['space_type']] ?? ucfirst($space['space_type'])); ?></span>
                </div>
                <div class="tac-body">
                    <div class="tac-header">
                        <h3><?php echo htmlspecialchars($space['name']); ?></h3>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="toggle_id" value="<?php echo $space['id']; ?>">
                            <input type="hidden" name="active" value="<?php echo $space['is_active'] ? 0 : 1; ?>">
                            <label class="toggle-switch" title="<?php echo $space['is_active'] ? 'Active' : 'Inactive'; ?>">
                                <input type="checkbox" <?php echo $space['is_active'] ? 'checked' : ''; ?> onchange="this.closest('form').submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </div>
                    <?php if (!empty($space['subtitle'])): ?><p class="tac-tagline"><?php echo htmlspecialchars($space['subtitle']); ?></p><?php endif; ?>
                    <div class="tac-meta">
                        <span><i class="fas fa-house"></i> <?php echo htmlspecialchars($space['villa_name']); ?></span>
                        <span><i class="fas fa-bed"></i> <?php echo (int)$space['units_count']; ?> units</span>
                    </div>
                </div>
                <div class="tac-footer">
                    <span class="badge <?php echo $space['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $space['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    <div class="tbl-actions">
                        <a href="bookable-units.php?space_id=<?php echo $space['id']; ?>" class="tbl-btn tbl-btn-view" title="Units"><i class="fas fa-bed"></i></a>
                        <a href="villa-space-edit.php?id=<?php echo $space['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="duplicate_id" value="<?php echo $space['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-view" title="Duplicate" data-confirm="Create a duplicate of '<?php echo htmlspecialchars($space['name']); ?>'?"><i class="fas fa-copy"></i></button>
                        </form>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $space['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete" data-confirm="Delete '<?php echo htmlspecialchars($space['name']); ?>' and its units?"><i class="fas fa-trash"></i></button>
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
