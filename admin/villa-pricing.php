<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare('DELETE FROM villa_pricing WHERE id = ?')->execute([(int)$_POST['delete_id']]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Pricing tier deleted.'];
    }
    header('Location: villa-pricing.php');
    exit;
}

// Handle featured toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_featured'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $tid = (int)$_POST['toggle_featured'];
        $val = (int)$_POST['featured'];
        $pdo->prepare('UPDATE villa_pricing SET is_featured = ? WHERE id = ?')->execute([$val, $tid]);
    }
    header('Location: villa-pricing.php');
    exit;
}

$tiers = $pdo->query('SELECT * FROM villa_pricing ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villa Pricing | 7 Art Villa Admin</title>
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
                <div class="topbar-title">Villa Pricing</div>
                <div class="topbar-sub"><?php echo count($tiers); ?> pricing tier<?php echo count($tiers) !== 1 ? 's' : ''; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="villa-pricing-edit.php" class="topbar-btn topbar-btn-gold">
                <i class="fas fa-plus"></i> Add Tier
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
                <h2>Villa Pricing</h2>
                <p>Manage nightly rates displayed on the villa page. Mark one tier as "Most Popular" to highlight it.</p>
            </div>
        </div>

        <?php if (empty($tiers)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-tag"></i>
                <h3>No pricing tiers yet</h3>
                <p>Add your first pricing tier to display rates on the villa page.</p>
                <a href="villa-pricing-edit.php" class="btn-admin btn-gold btn-sm" style="margin-top:8px">
                    <i class="fas fa-plus"></i> Add Tier
                </a>
            </div>
        </div>
        <?php else: ?>

        <!-- Pricing Cards Grid -->
        <div class="pricing-admin-grid">
            <?php foreach ($tiers as $tier):
                $features = json_decode($tier['features'] ?? '[]', true) ?: [];
            ?>
            <div class="pricing-admin-card <?php echo $tier['is_featured'] ? 'is-featured' : ''; ?>">

                <?php if ($tier['is_featured']): ?>
                <div class="pac-featured-ribbon"><i class="fas fa-star"></i> Most Popular</div>
                <?php endif; ?>

                <div class="pac-header">
                    <div>
                        <div class="pac-label"><?php echo htmlspecialchars($tier['label']); ?></div>
                        <div class="pac-days"><?php echo htmlspecialchars($tier['days']); ?></div>
                    </div>
                    <div class="pac-sort">#<?php echo (int)$tier['sort_order'] ?: $tier['id']; ?></div>
                </div>

                <div class="pac-price-block">
                    <div class="pac-price-lkr">
                        <span class="pac-currency">LKR</span>
                        <span class="pac-amount"><?php echo number_format($tier['price_lkr']); ?></span>
                        <span class="pac-per">/ night</span>
                    </div>
                    <div class="pac-price-usd">â‰ˆ USD <?php echo number_format($tier['price_usd']); ?> / night</div>
                </div>

                <?php if (!empty($features)): ?>
                <ul class="pac-features">
                    <?php foreach ($features as $f): ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($f); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <div class="pac-footer">
                    <!-- Featured toggle -->
                    <form method="POST" style="display:contents">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="toggle_featured" value="<?php echo $tier['id']; ?>">
                        <input type="hidden" name="featured" value="<?php echo $tier['is_featured'] ? 0 : 1; ?>">
                        <button type="submit" class="btn-admin btn-sm <?php echo $tier['is_featured'] ? 'btn-gold' : 'btn-outline'; ?>"
                                title="<?php echo $tier['is_featured'] ? 'Remove featured' : 'Mark as featured'; ?>">
                            <i class="fas fa-star"></i>
                            <?php echo $tier['is_featured'] ? 'Featured' : 'Set Featured'; ?>
                        </button>
                    </form>

                    <div class="tbl-actions">
                        <a href="villa-pricing-edit.php?id=<?php echo $tier['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $tier['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete"
                                    data-confirm="Delete '<?php echo htmlspecialchars($tier['label']); ?>' pricing tier? This cannot be undone.">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <div class="pricing-admin-note">
            <i class="fas fa-info-circle"></i>
            <span>Drag-to-reorder is not available â€” use the <strong>Sort Order</strong> field in each tier's edit form to control display order.</span>
        </div>

        <?php endif; ?>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
</body>
</html>
