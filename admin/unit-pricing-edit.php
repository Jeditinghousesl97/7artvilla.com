<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

$unit_id = (int)($_GET['unit_id'] ?? 0);
if ($unit_id <= 0) {
    header('Location: bookable-units.php');
    exit;
}

$unit_stmt = $pdo->prepare('SELECT id, name FROM bookable_units WHERE id = ?');
$unit_stmt->execute([$unit_id]);
$unit = $unit_stmt->fetch(PDO::FETCH_ASSOC);
if (!$unit) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bookable unit not found.'];
    header('Location: bookable-units.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$row = [
    'label' => '',
    'days' => '',
    'price_lkr' => '',
    'price_usd' => '',
    'is_featured' => 0,
    'features_text' => '',
    'sort_order' => 0,
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM unit_pricing WHERE id = ? AND bookable_unit_id = ?');
    $stmt->execute([$id, $unit_id]);
    $db_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$db_row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Pricing row not found.'];
        header('Location: unit-pricing.php?unit_id=' . $unit_id);
        exit;
    }
    $row = array_merge($row, $db_row);
    $features = json_decode($db_row['features'] ?? '[]', true);
    $row['features_text'] = is_array($features) ? implode("\n", $features) : '';
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $label = trim($_POST['label'] ?? '');
        $days = trim($_POST['days'] ?? '');
        $price_lkr = (float)($_POST['price_lkr'] ?? 0);
        $price_usd = (float)($_POST['price_usd'] ?? 0);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $features_text = trim($_POST['features'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($label === '') $errors[] = 'Label is required.';
        if ($days === '') $errors[] = 'Days / period is required.';
        if ($price_lkr <= 0) $errors[] = 'LKR price must be greater than 0.';
        if ($price_usd < 0) $errors[] = 'USD price cannot be negative.';

        $features = array_values(array_filter(array_map('trim', explode("\n", $features_text))));
        $features_json = json_encode($features);

        if (empty($errors)) {
            if ($is_edit) {
                $stmt = $pdo->prepare('UPDATE unit_pricing SET label=?, days=?, price_lkr=?, price_usd=?, is_featured=?, features=?, sort_order=? WHERE id=? AND bookable_unit_id=?');
                $stmt->execute([$label, $days, $price_lkr, $price_usd, $is_featured, $features_json, $sort_order, $id, $unit_id]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Pricing row updated successfully.'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO unit_pricing (bookable_unit_id, label, days, price_lkr, price_usd, is_featured, features, sort_order) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$unit_id, $label, $days, $price_lkr, $price_usd, $is_featured, $features_json, $sort_order]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Pricing row added successfully.'];
            }
            header('Location: unit-pricing.php?unit_id=' . $unit_id);
            exit;
        }

        $row = array_merge($row, compact('label', 'days', 'price_lkr', 'price_usd', 'is_featured', 'features_text', 'sort_order'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Unit Pricing' : 'Add Unit Pricing'; ?> | 7 Art Villa Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Unit Pricing' : 'Add Unit Pricing'; ?></div>
                <div class="topbar-sub"><?php echo htmlspecialchars($unit['name']); ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="unit-pricing.php?unit_id=<?php echo $unit_id; ?>" class="topbar-btn topbar-btn-outline"><i class="fas fa-arrow-left"></i> Back to Pricing</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="edit-layout">
                <div class="edit-main">
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Pricing Details</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group"><label>Label *</label><input type="text" name="label" value="<?php echo htmlspecialchars((string)$row['label']); ?>" required></div>
                            <div class="form-group"><label>Days / Period *</label><input type="text" name="days" value="<?php echo htmlspecialchars((string)$row['days']); ?>" required></div>
                            <div class="form-group"><label>Price (LKR) *</label><input type="number" name="price_lkr" value="<?php echo htmlspecialchars((string)$row['price_lkr']); ?>" min="0" step="0.01" required></div>
                            <div class="form-group"><label>Price (USD)</label><input type="number" name="price_usd" value="<?php echo htmlspecialchars((string)$row['price_usd']); ?>" min="0" step="0.01"></div>
                            <div class="form-group form-full"><label>Included Items</label><textarea name="features" rows="8"><?php echo htmlspecialchars((string)$row['features_text']); ?></textarea></div>
                        </div>
                    </div>
                </div>
                <div class="edit-sidebar">
                    <div class="form-card">
                        <div class="form-card-title">Options</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row"><input type="checkbox" name="is_featured" value="1" <?php echo $row['is_featured'] ? 'checked' : ''; ?>><span><strong>Featured</strong><small>Highlight this pricing row</small></span></label>
                        </div>
                        <div class="form-group" style="margin-top:16px">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?php echo (int)$row['sort_order']; ?>" min="0">
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%"><i class="fas fa-save"></i> <?php echo $is_edit ? 'Save Changes' : 'Add Pricing'; ?></button>
                            <a href="unit-pricing.php?unit_id=<?php echo $unit_id; ?>" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
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
