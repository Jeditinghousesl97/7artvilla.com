<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$unit_id = (int)($_GET['unit_id'] ?? 0);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($unit_id <= 0) {
    $units = $pdo->query("
        SELECT u.id, u.name, v.name AS villa_name, s.name AS space_name,
               (SELECT COUNT(*) FROM unit_pricing p WHERE p.bookable_unit_id = u.id) AS pricing_count
        FROM bookable_units u
        JOIN villas v ON v.id = u.villa_id
        LEFT JOIN villa_spaces s ON s.id = u.villa_space_id
        ORDER BY v.sort_order ASC, COALESCE(s.sort_order, 0) ASC, u.sort_order ASC, u.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unit Pricing | 7 Art Villa Admin</title>
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
                    <div class="topbar-title">Unit Pricing</div>
                    <div class="topbar-sub">Choose a bookable unit to manage pricing rows</div>
                </div>
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
                    <h2>Select a Unit</h2>
                    <p>Open any unit below to manage its pricing tiers.</p>
                </div>
            </div>
            <div class="admin-card">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Villa</th><th>Space</th><th>Unit</th><th>Pricing Rows</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($units as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['villa_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['space_name'] ?: 'General'); ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo (int)$u['pricing_count']; ?></td>
                            <td><a href="unit-pricing.php?unit_id=<?php echo (int)$u['id']; ?>" class="tbl-btn tbl-btn-view" title="Open"><i class="fas fa-arrow-right"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <script src="assets/js/admin.js"></script>
    </body>
    </html>
    <?php
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.*, v.name AS villa_name, s.name AS space_name
    FROM bookable_units u
    JOIN villas v ON v.id = u.villa_id
    LEFT JOIN villa_spaces s ON s.id = u.villa_space_id
    WHERE u.id = ?
");
$stmt->execute([$unit_id]);
$unit = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$unit) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bookable unit not found.'];
    header('Location: bookable-units.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare('DELETE FROM unit_pricing WHERE id = ? AND bookable_unit_id = ?')->execute([(int)$_POST['delete_id'], $unit_id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Pricing row deleted.'];
    }
    header('Location: unit-pricing.php?unit_id=' . $unit_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare('UPDATE unit_pricing SET is_featured = ? WHERE id = ? AND bookable_unit_id = ?')->execute([(int)$_POST['featured'], (int)$_POST['toggle_featured'], $unit_id]);
    }
    header('Location: unit-pricing.php?unit_id=' . $unit_id);
    exit;
}

$rows = $pdo->prepare('SELECT * FROM unit_pricing WHERE bookable_unit_id = ? ORDER BY sort_order ASC, id ASC');
$rows->execute([$unit_id]);
$pricing_rows = $rows->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Pricing | 7 Art Villa Admin</title>
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
                <div class="topbar-title">Unit Pricing</div>
                <div class="topbar-sub"><?php echo htmlspecialchars($unit['villa_name'] . ' / ' . ($unit['space_name'] ?: 'General') . ' / ' . $unit['name']); ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="unit-pricing-edit.php?unit_id=<?php echo $unit_id; ?>" class="topbar-btn topbar-btn-gold"><i class="fas fa-plus"></i> Add Pricing</a>
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
                <h2>Pricing Rows</h2>
                <p>Manage pricing tiers for this bookable unit.</p>
            </div>
        </div>

        <?php if (empty($pricing_rows)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-tag"></i>
                <h3>No pricing rows yet</h3>
                <p>Add the first pricing tier for this unit.</p>
                <a href="unit-pricing-edit.php?unit_id=<?php echo $unit_id; ?>" class="btn-admin btn-gold btn-sm" style="margin-top:8px"><i class="fas fa-plus"></i> Add Pricing</a>
            </div>
        </div>
        <?php else: ?>
        <div class="pricing-admin-grid">
            <?php foreach ($pricing_rows as $row): $features = json_decode($row['features'] ?? '[]', true) ?: []; ?>
            <div class="pricing-admin-card <?php echo $row['is_featured'] ? 'is-featured' : ''; ?>">
                <?php if ($row['is_featured']): ?><div class="pac-featured-ribbon"><i class="fas fa-star"></i> Featured</div><?php endif; ?>
                <div class="pac-header">
                    <div>
                        <div class="pac-label"><?php echo htmlspecialchars($row['label']); ?></div>
                        <div class="pac-days"><?php echo htmlspecialchars($row['days']); ?></div>
                    </div>
                    <div class="pac-sort">#<?php echo (int)$row['sort_order'] ?: $row['id']; ?></div>
                </div>
                <div class="pac-price-block">
                    <div class="pac-price-lkr"><span class="pac-currency">LKR</span><span class="pac-amount"><?php echo number_format((float)$row['price_lkr']); ?></span><span class="pac-per">/ stay</span></div>
                    <div class="pac-price-usd">~ USD <?php echo number_format((float)$row['price_usd']); ?></div>
                </div>
                <?php if ($features): ?>
                <ul class="pac-features">
                    <?php foreach ($features as $feature): ?><li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="pac-footer">
                    <form method="POST" style="display:contents">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="toggle_featured" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="featured" value="<?php echo $row['is_featured'] ? 0 : 1; ?>">
                        <button type="submit" class="btn-admin btn-sm <?php echo $row['is_featured'] ? 'btn-gold' : 'btn-outline'; ?>">
                            <i class="fas fa-star"></i> <?php echo $row['is_featured'] ? 'Featured' : 'Set Featured'; ?>
                        </button>
                    </form>
                    <div class="tbl-actions">
                        <a href="unit-pricing-edit.php?unit_id=<?php echo $unit_id; ?>&id=<?php echo $row['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete" data-confirm="Delete pricing row '<?php echo htmlspecialchars($row['label']); ?>'?"><i class="fas fa-trash"></i></button>
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
