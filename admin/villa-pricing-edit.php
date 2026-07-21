<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();
$id      = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$tier = [
    'id' => 0, 'label' => '', 'days' => '',
    'price_lkr' => '', 'price_usd' => '',
    'is_featured' => 0, 'features' => '',
    'sort_order' => 0
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM villa_pricing WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Pricing tier not found.'];
        header('Location: ' . admin_url('villa-pricing.php'));
        exit;
    }
    $tier = $row;
    $ft = json_decode($tier['features'] ?? '[]', true);
    $tier['features_text'] = is_array($ft) ? implode("\n", $ft) : '';
} else {
    $tier['features_text'] = '';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $label       = trim($_POST['label'] ?? '');
        $days        = trim($_POST['days'] ?? '');
        $price_lkr   = (float)($_POST['price_lkr'] ?? 0);
        $price_usd   = (float)($_POST['price_usd'] ?? 0);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $ft_text     = trim($_POST['features'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);

        if ($label === '')   $errors[] = 'Label is required.';
        if ($days  === '')   $errors[] = 'Days / period is required.';
        if ($price_lkr <= 0) $errors[] = 'LKR price must be greater than 0.';
        if ($price_usd <= 0) $errors[] = 'USD price must be greater than 0.';

        $features_arr  = array_filter(array_map('trim', explode("\n", $ft_text)));
        $features_json = json_encode(array_values($features_arr));

        if (empty($errors)) {
            if ($is_edit) {
                $pdo->prepare('
                    UPDATE villa_pricing SET
                        label=?, days=?, price_lkr=?, price_usd=?,
                        is_featured=?, features=?, sort_order=?
                    WHERE id=?
                ')->execute([
                    $label, $days, $price_lkr, $price_usd,
                    $is_featured, $features_json, $sort_order, $id
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Pricing tier \"{$label}\" updated."];
            } else {
                $pdo->prepare('
                    INSERT INTO villa_pricing
                        (label, days, price_lkr, price_usd, is_featured, features, sort_order)
                    VALUES (?,?,?,?,?,?,?)
                ')->execute([
                    $label, $days, $price_lkr, $price_usd,
                    $is_featured, $features_json, $sort_order
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Pricing tier \"{$label}\" added."];
            }
            header('Location: ' . admin_url('villa-pricing.php'));
            exit;
        }

        // Repopulate
        $tier = array_merge($tier, compact(
            'label', 'days', 'price_lkr', 'price_usd',
            'is_featured', 'sort_order'
        ));
        $tier['features_text'] = $ft_text;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Pricing Tier' : 'Add Pricing Tier'; ?> | We Trail Admin</title>
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
                <div class="topbar-title"><?php echo $is_edit ? 'Edit Pricing Tier' : 'Add Pricing Tier'; ?></div>
                <div class="topbar-sub"><?php echo $is_edit ? htmlspecialchars($tier['label']) : 'Create a new pricing tier'; ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="villa-pricing.php" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Pricing
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

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="edit-layout">

                <!-- LEFT -->
                <div class="edit-main">

                    <!-- Basic Info -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Tier Information</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group">
                                <label>Label *</label>
                                <input type="text" name="label" value="<?php echo htmlspecialchars($tier['label']); ?>"
                                       placeholder="e.g. Weekday, Weekend, Special Occasion" required>
                                <span class="form-hint">Displayed as the tier heading on the website.</span>
                            </div>
                            <div class="form-group">
                                <label>Days / Period *</label>
                                <input type="text" name="days" value="<?php echo htmlspecialchars($tier['days']); ?>"
                                       placeholder="e.g. Monday â€“ Thursday" required>
                                <span class="form-hint">Shown below the label (e.g. the days it applies to).</span>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="form-card" style="margin-bottom:20px">
                        <div class="form-card-title">Nightly Rate</div>
                        <div class="form-grid-2" style="margin-top:20px">
                            <div class="form-group">
                                <label>Price (LKR) *</label>
                                <input type="number" name="price_lkr"
                                       value="<?php echo htmlspecialchars($tier['price_lkr']); ?>"
                                       placeholder="e.g. 45000" min="0" step="0.01" required>
                                <span class="form-hint">Per villa per night in Sri Lankan Rupees.</span>
                            </div>
                            <div class="form-group">
                                <label>Price (USD) *</label>
                                <input type="number" name="price_usd"
                                       value="<?php echo htmlspecialchars($tier['price_usd']); ?>"
                                       placeholder="e.g. 150" min="0" step="0.01" required>
                                <span class="form-hint">Approximate USD equivalent shown as reference.</span>
                            </div>
                        </div>
                    </div>

                    <!-- What's Included -->
                    <div class="form-card">
                        <div class="form-card-title">What's Included</div>
                        <div style="margin-top:20px">
                            <div class="form-group">
                                <label>Included Items</label>
                                <textarea name="features" rows="8"
                                          placeholder="Entire private villa&#10;Private pool access&#10;Butler service&#10;In-villa breakfast for 2&#10;Daily housekeeping&#10;Welcome amenity"><?php echo htmlspecialchars($tier['features_text'] ?? ''); ?></textarea>
                                <span class="form-hint">One item per line â€” each becomes a bullet point on the pricing card.</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT -->
                <div class="edit-sidebar">

                    <!-- Options -->
                    <div class="form-card" style="margin-bottom:16px">
                        <div class="form-card-title">Options</div>
                        <div style="margin-top:16px;display:flex;flex-direction:column;gap:14px">
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_featured" value="1" <?php echo $tier['is_featured'] ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Most Popular</strong>
                                    <small>Highlights this tier with a "Most Popular" badge and featured styling</small>
                                </span>
                            </label>
                        </div>
                        <div class="form-group" style="margin-top:16px">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?php echo (int)$tier['sort_order']; ?>" min="0" placeholder="0">
                            <span class="form-hint">Lower = shown first (e.g. 1, 2, 3)</span>
                        </div>
                        <div class="form-actions" style="margin-top:16px;padding-top:16px">
                            <button type="submit" class="btn-admin btn-gold" style="width:100%">
                                <i class="fas fa-<?php echo $is_edit ? 'save' : 'plus'; ?>"></i>
                                <?php echo $is_edit ? 'Save Changes' : 'Add Tier'; ?>
                            </button>
                            <a href="villa-pricing.php" class="btn-admin btn-outline" style="width:100%;text-align:center">Cancel</a>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="form-card">
                        <div class="form-card-title">Preview</div>
                        <div class="pac-mini-preview" style="margin-top:16px">
                            <div class="pac-mini-label" id="previewLabel">Tier Label</div>
                            <div class="pac-mini-days" id="previewDays">Days / Period</div>
                            <div class="pac-mini-lkr">
                                LKR <strong id="previewLKR">â€”</strong> <span>/ night</span>
                            </div>
                            <div class="pac-mini-usd">â‰ˆ USD <span id="previewUSD">â€”</span> / night</div>
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
// Live preview
const fields = {
    label:  { el: document.querySelector('[name="label"]'),     out: document.getElementById('previewLabel') },
    days:   { el: document.querySelector('[name="days"]'),      out: document.getElementById('previewDays') },
    lkr:    { el: document.querySelector('[name="price_lkr"]'), out: document.getElementById('previewLKR') },
    usd:    { el: document.querySelector('[name="price_usd"]'), out: document.getElementById('previewUSD') },
};

function formatNum(v) {
    const n = parseFloat(v);
    return isNaN(n) ? 'â€”' : n.toLocaleString();
}

Object.entries(fields).forEach(([key, {el, out}]) => {
    if (!el) return;
    el.addEventListener('input', () => {
        if (key === 'lkr' || key === 'usd') {
            out.textContent = formatNum(el.value) || 'â€”';
        } else {
            out.textContent = el.value.trim() || (key === 'label' ? 'Tier Label' : 'Days / Period');
        }
    });
    // Init
    if (key === 'lkr' || key === 'usd') {
        out.textContent = formatNum(el.value) || 'â€”';
    } else {
        out.textContent = el.value.trim() || (key === 'label' ? 'Tier Label' : 'Days / Period');
    }
});
</script>
</body>
</html>
