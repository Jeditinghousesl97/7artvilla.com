<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$villa_id = (int)($_GET['villa_id'] ?? 0);
$space_id = (int)($_GET['space_id'] ?? 0);
$status = trim((string)($_GET['status'] ?? ''));
$unit_type = trim((string)($_GET['unit_type'] ?? ''));

$villa_options = stay_fetch_villa_options($pdo);
$space_options = stay_fetch_space_options($pdo);
$type_labels = stay_unit_type_labels();

$where = [];
$params = [];
$context_title = 'All Bookable Units';
if ($space_id > 0) {
    $where[] = 'u.villa_space_id = ?';
    $params[] = $space_id;
    $stmt = $pdo->prepare('SELECT s.name, v.name AS villa_name FROM villa_spaces s JOIN villas v ON v.id = s.villa_id WHERE s.id = ?');
    $stmt->execute([$space_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $context_title = $row['villa_name'] . ' / ' . $row['name'];
    }
} elseif ($villa_id > 0) {
    $where[] = 'u.villa_id = ?';
    $params[] = $villa_id;
    $stmt = $pdo->prepare('SELECT name FROM villas WHERE id = ?');
    $stmt->execute([$villa_id]);
    $name = $stmt->fetchColumn();
    if ($name) $context_title = $name;
}
if ($status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'u.is_active = 0';
}
if ($unit_type !== '' && array_key_exists($unit_type, $type_labels)) {
    $where[] = 'u.unit_type = ?';
    $params[] = $unit_type;
} else {
    $unit_type = '';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$unit_slug_exists = static function (PDO $pdo, string $slug): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookable_units WHERE slug = ?');
    $stmt->execute([$slug]);
    return (int)$stmt->fetchColumn() > 0;
};

$unit_unique_slug = static function (PDO $pdo, string $name, string $base_slug = '') use ($unit_slug_exists): string {
    $slug_base = stay_slugify($base_slug !== '' ? $base_slug : $name, 'unit');
    $slug = $slug_base;
    $suffix = 2;
    while ($unit_slug_exists($pdo, $slug)) {
        $slug = $slug_base . '-' . $suffix;
        $suffix++;
    }
    return $slug;
};

$filter_params = [];
if ($villa_id > 0) $filter_params['villa_id'] = $villa_id;
if ($space_id > 0) $filter_params['space_id'] = $space_id;
if ($status !== '') $filter_params['status'] = $status;
if ($unit_type !== '') $filter_params['unit_type'] = $unit_type;
$filter_query = $filter_params ? ('?' . http_build_query($filter_params)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $id = (int)$_POST['duplicate_id'];
        $stmt = $pdo->prepare('SELECT * FROM bookable_units WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($source) {
            try {
                $pdo->beginTransaction();

                $copy_name = trim((string)$source['name']) . ' Copy';
                $copy_slug = $unit_unique_slug($pdo, $copy_name, (string)$source['slug'] . '-copy');

                $insert = $pdo->prepare('
                    INSERT INTO bookable_units
                        (villa_id, villa_space_id, name, slug, subtitle, unit_type, summary, description, max_guests, bed_info, size_label, featured_image_path, pricing_note, is_featured, is_active, sort_order)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ');
                $insert->execute([
                    (int)$source['villa_id'],
                    $source['villa_space_id'] !== null ? (int)$source['villa_space_id'] : null,
                    $copy_name,
                    $copy_slug,
                    (string)($source['subtitle'] ?? ''),
                    (string)$source['unit_type'],
                    (string)($source['summary'] ?? ''),
                    (string)($source['description'] ?? ''),
                    (string)($source['max_guests'] ?? ''),
                    (string)($source['bed_info'] ?? ''),
                    (string)($source['size_label'] ?? ''),
                    (string)($source['featured_image_path'] ?? ''),
                    (string)($source['pricing_note'] ?? ''),
                    0,
                    0,
                    (int)$source['sort_order'],
                ]);

                $new_unit_id = (int)$pdo->lastInsertId();

                $pricing_stmt = $pdo->prepare('
                    SELECT label, days, price_lkr, price_usd, is_featured, features, sort_order
                    FROM unit_pricing
                    WHERE bookable_unit_id = ?
                    ORDER BY sort_order ASC, id ASC
                ');
                $pricing_stmt->execute([$id]);
                $pricing_rows = $pricing_stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($pricing_rows) {
                    $pricing_insert = $pdo->prepare('
                        INSERT INTO unit_pricing
                            (bookable_unit_id, label, days, price_lkr, price_usd, is_featured, features, sort_order)
                        VALUES (?,?,?,?,?,?,?,?)
                    ');
                    foreach ($pricing_rows as $row) {
                        $pricing_insert->execute([
                            $new_unit_id,
                            (string)$row['label'],
                            (string)$row['days'],
                            (float)$row['price_lkr'],
                            (float)$row['price_usd'],
                            (int)$row['is_featured'],
                            (string)($row['features'] ?? ''),
                            (int)$row['sort_order'],
                        ]);
                    }
                }

                $gallery_stmt = $pdo->prepare('
                    SELECT image_path, caption, sort_order
                    FROM bookable_unit_gallery_images
                    WHERE bookable_unit_id = ?
                    ORDER BY sort_order ASC, id ASC
                ');
                $gallery_stmt->execute([$id]);
                $gallery_rows = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($gallery_rows) {
                    $gallery_insert = $pdo->prepare('
                        INSERT INTO bookable_unit_gallery_images
                            (bookable_unit_id, image_path, caption, sort_order)
                        VALUES (?,?,?,?)
                    ');
                    foreach ($gallery_rows as $row) {
                        $gallery_insert->execute([
                            $new_unit_id,
                            (string)$row['image_path'],
                            (string)($row['caption'] ?? ''),
                            (int)$row['sort_order'],
                        ]);
                    }
                }

                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Bookable unit duplicated successfully.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Unable to duplicate the bookable unit.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bookable unit not found.'];
        }
    }
    header('Location: bookable-units.php' . $filter_query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $id = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare('SELECT featured_image_path FROM bookable_units WHERE id = ?');
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        $gallery_stmt = $pdo->prepare('SELECT image_path FROM bookable_unit_gallery_images WHERE bookable_unit_id = ?');
        $gallery_stmt->execute([$id]);
        $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare('DELETE FROM bookable_units WHERE id = ?')->execute([$id]);
        if ($img) stay_delete_public_file((string)$img);
        foreach ($gallery_images as $gallery_img) {
            if ($gallery_img) stay_delete_public_file((string)$gallery_img);
        }
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Bookable unit deleted successfully.'];
    }
    header('Location: bookable-units.php' . $filter_query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare('UPDATE bookable_units SET is_active = ? WHERE id = ?')->execute([(int)$_POST['active'], (int)$_POST['toggle_id']]);
    }
    header('Location: bookable-units.php' . $filter_query);
    exit;
}

$sql = "
    SELECT u.*,
           v.name AS villa_name,
           s.name AS space_name,
           (SELECT COUNT(*) FROM unit_pricing p WHERE p.bookable_unit_id = u.id) AS pricing_count
    FROM bookable_units u
    JOIN villas v ON v.id = u.villa_id
    LEFT JOIN villa_spaces s ON s.id = u.villa_space_id
";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY v.sort_order ASC, COALESCE(s.sort_order, 0) ASC, u.sort_order ASC, u.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookable Units | We Trail Admin</title>
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
                <div class="topbar-title">Bookable Units</div>
                <div class="topbar-sub"><?php echo htmlspecialchars($context_title); ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="bookable-unit-edit.php<?php echo $space_id ? '?space_id=' . $space_id : ($villa_id ? '?villa_id=' . $villa_id : ''); ?>" class="topbar-btn topbar-btn-gold"><i class="fas fa-plus"></i> Add Unit</a>
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
                <h2>Bookable Units</h2>
                <p>Manage actual room, family room, and entire villa booking options.</p>
            </div>
        </div>

        <div class="form-card" style="margin-bottom:20px">
            <div class="form-card-title">Filter Units</div>
            <form method="GET" style="margin-top:18px">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Villa</label>
                        <select name="villa_id" id="filterVillaId">
                            <option value="">All villas</option>
                            <?php foreach ($villa_options as $villa): ?>
                            <option value="<?php echo (int)$villa['id']; ?>" <?php echo $villa_id === (int)$villa['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($villa['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Villa Space</label>
                        <select name="space_id" id="filterSpaceId">
                            <option value="">All villa spaces</option>
                            <?php foreach ($space_options as $space): ?>
                            <option value="<?php echo (int)$space['id']; ?>" data-villa-id="<?php echo (int)$space['villa_id']; ?>" <?php echo $space_id === (int)$space['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($space['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All statuses</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unit Type</label>
                        <select name="unit_type">
                            <option value="">All unit types</option>
                            <?php foreach ($type_labels as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $unit_type === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:16px;padding-top:16px">
                    <button type="submit" class="btn-admin btn-gold"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="bookable-units.php" class="btn-admin btn-outline">Reset</a>
                </div>
            </form>
        </div>

        <?php if (empty($units)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-bed"></i>
                <h3>No bookable units yet</h3>
                <p>Add your first room or booking option.</p>
                <a href="bookable-unit-edit.php<?php echo $space_id ? '?space_id=' . $space_id : ($villa_id ? '?villa_id=' . $villa_id : ''); ?>" class="btn-admin btn-gold btn-sm" style="margin-top:8px"><i class="fas fa-plus"></i> Add Unit</a>
            </div>
        </div>
        <?php else: ?>
        <div class="tours-admin-grid">
            <?php foreach ($units as $unit): ?>
            <div class="tour-admin-card <?php echo !$unit['is_active'] ? 'inactive' : ''; ?>">
                <div class="tac-image">
                    <?php if (!empty($unit['featured_image_path']) && file_exists('../' . $unit['featured_image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($unit['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($unit['name']); ?>">
                    <?php else: ?>
                    <div class="tac-img-placeholder"><i class="fas fa-bed"></i></div>
                    <?php endif; ?>
                    <span class="tac-category-badge"><?php echo htmlspecialchars($type_labels[$unit['unit_type']] ?? ucfirst($unit['unit_type'])); ?></span>
                    <?php if ((int)$unit['is_featured'] === 1): ?><span class="tac-badge-popular">Featured</span><?php endif; ?>
                </div>
                <div class="tac-body">
                    <div class="tac-header">
                        <h3><?php echo htmlspecialchars($unit['name']); ?></h3>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="toggle_id" value="<?php echo $unit['id']; ?>">
                            <input type="hidden" name="active" value="<?php echo $unit['is_active'] ? 0 : 1; ?>">
                            <label class="toggle-switch" title="<?php echo $unit['is_active'] ? 'Active' : 'Inactive'; ?>">
                                <input type="checkbox" <?php echo $unit['is_active'] ? 'checked' : ''; ?> onchange="this.closest('form').submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </div>
                    <?php if (!empty($unit['subtitle'])): ?><p class="tac-tagline"><?php echo htmlspecialchars($unit['subtitle']); ?></p><?php endif; ?>
                    <div class="tac-meta">
                        <span><i class="fas fa-house"></i> <?php echo htmlspecialchars($unit['villa_name']); ?></span>
                        <?php if (!empty($unit['space_name'])): ?><span><i class="fas fa-sitemap"></i> <?php echo htmlspecialchars($unit['space_name']); ?></span><?php endif; ?>
                        <?php if (!empty($unit['max_guests'])): ?><span><i class="fas fa-users"></i> <?php echo htmlspecialchars($unit['max_guests']); ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="tac-footer">
                    <span class="badge badge-read"><?php echo (int)$unit['pricing_count']; ?> pricing rows</span>
                    <div class="tbl-actions">
                        <a href="unit-pricing.php?unit_id=<?php echo $unit['id']; ?>" class="tbl-btn tbl-btn-view" title="Pricing"><i class="fas fa-tag"></i></a>
                        <a href="bookable-unit-edit.php?id=<?php echo $unit['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="duplicate_id" value="<?php echo $unit['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-view" title="Duplicate" data-confirm="Create a duplicate of '<?php echo htmlspecialchars($unit['name']); ?>'?"><i class="fas fa-copy"></i></button>
                        </form>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $unit['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete" data-confirm="Delete '<?php echo htmlspecialchars($unit['name']); ?>' and its pricing?"><i class="fas fa-trash"></i></button>
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
const filterVillaSelect = document.getElementById('filterVillaId');
const filterSpaceSelect = document.getElementById('filterSpaceId');

function filterUnitSpaces() {
    const villaId = filterVillaSelect ? filterVillaSelect.value : '';
    if (!filterSpaceSelect) return;

    Array.from(filterSpaceSelect.options).forEach((option, index) => {
        if (index === 0) {
            option.hidden = false;
            return;
        }

        const matches = !villaId || option.dataset.villaId === villaId;
        option.hidden = !matches;

        if (!matches && option.selected) {
            filterSpaceSelect.value = '';
        }
    });
}

if (filterVillaSelect && filterSpaceSelect) {
    filterVillaSelect.addEventListener('change', filterUnitSpaces);
    filterUnitSpaces();
}
</script>
</body>
</html>
