<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle quick toggle active via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $tid    = (int)$_POST['toggle_id'];
        $active = (int)$_POST['active'];
        $pdo->prepare('UPDATE services SET is_active = ? WHERE id = ?')->execute([$active, $tid]);
    }
    header('Location: services.php');
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $did = (int)$_POST['delete_id'];
        $row = $pdo->prepare('SELECT image_path FROM services WHERE id = ?');
        $row->execute([$did]);
        $img = $row->fetchColumn();
        if ($img && file_exists('../' . $img)) unlink('../' . $img);
        $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$did]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Service deleted successfully.'];
    }
    header('Location: services.php');
    exit;
}

// Fetch all services
$filter  = $_GET['filter'] ?? 'all';
$allowed = ['all', 'included', 'request', 'extra'];
if (!in_array($filter, $allowed)) $filter = 'all';

$where    = $filter !== 'all' ? "WHERE category = " . $pdo->quote($filter) : '';
$services = $pdo->query("SELECT * FROM services $where ORDER BY sort_order ASC, id ASC")->fetchAll();

$counts = $pdo->query("SELECT category, COUNT(*) n FROM services GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
$total  = array_sum($counts);

if (!function_exists('svc_excerpt')) {
    function svc_excerpt($text, $width = 120, $trim = '...') {
        $text = (string)$text;

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $width, $trim);
        }

        if (strlen($text) <= $width) {
            return $text;
        }

        return substr($text, 0, max(0, $width - strlen($trim))) . $trim;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | We Trail Admin</title>
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
                <div class="topbar-title">Services</div>
                <div class="topbar-sub"><?php echo $total; ?> total services</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="service-edit.php" class="topbar-btn topbar-btn-gold">
                <i class="fas fa-plus"></i> Add Service
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
                <h2>Services</h2>
                <p>Manage all services and amenities shown on the website.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-row" style="margin-bottom:20px">
            <div class="filter-btn-group">
                <a href="?filter=all"      class="filter-tab <?php echo $filter === 'all'      ? 'active' : ''; ?>">All <span class="filter-count"><?php echo $total; ?></span></a>
                <a href="?filter=included" class="filter-tab <?php echo $filter === 'included' ? 'active' : ''; ?>">Included <span class="filter-count"><?php echo $counts['included'] ?? 0; ?></span></a>
                <a href="?filter=request"  class="filter-tab <?php echo $filter === 'request'  ? 'active' : ''; ?>">On Request <span class="filter-count"><?php echo $counts['request'] ?? 0; ?></span></a>
                <a href="?filter=extra"    class="filter-tab <?php echo $filter === 'extra'    ? 'active' : ''; ?>">Extra <span class="filter-count"><?php echo $counts['extra'] ?? 0; ?></span></a>
            </div>
        </div>

        <!-- Services List -->
        <?php if (empty($services)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-concierge-bell"></i>
                <h3>No services yet</h3>
                <p>Add your first service to get started.</p>
                <a href="service-edit.php" class="btn-admin btn-gold btn-sm" style="margin-top:8px">
                    <i class="fas fa-plus"></i> Add Service
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="svc-admin-list">
            <?php
            $cat_labels = ['included' => 'Included', 'request' => 'On Request', 'extra' => 'Extra'];
            $cat_colors = ['included' => 'badge-active', 'request' => 'badge-read', 'extra' => 'badge-gold'];
            foreach ($services as $svc):
                $features = json_decode($svc['features'] ?? '[]', true) ?: [];
                $cat_label = $cat_labels[$svc['category']] ?? $svc['category'];
                $cat_color = $cat_colors[$svc['category']] ?? 'badge-inactive';
            ?>
            <div class="svc-admin-row <?php echo !$svc['is_active'] ? 'inactive' : ''; ?>">

                <!-- Icon / Image -->
                <div class="svc-icon-wrap">
                    <?php if ($svc['image_path'] && file_exists('../' . $svc['image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($svc['image_path']); ?>" alt="<?php echo htmlspecialchars($svc['title']); ?>" class="svc-thumb">
                    <?php elseif ($svc['icon']): ?>
                    <div class="svc-icon-box">
                        <i class="fas <?php echo htmlspecialchars($svc['icon']); ?>"></i>
                    </div>
                    <?php else: ?>
                    <div class="svc-icon-box svc-icon-blank">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Main content -->
                <div class="svc-row-body">
                    <div class="svc-row-top">
                        <div>
                            <h3><?php echo htmlspecialchars($svc['title']); ?></h3>
                            <p class="svc-desc"><?php echo htmlspecialchars(svc_excerpt($svc['description'], 120, '...')); ?></p>
                        </div>
                        <div class="svc-row-badges">
                            <span class="badge <?php echo $cat_color; ?>"><?php echo htmlspecialchars($cat_label); ?></span>
                            <span class="badge <?php echo $svc['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $svc['is_active'] ? 'Active' : 'Inactive'; ?></span>
                        </div>
                    </div>
                    <?php if (!empty($features)): ?>
                    <div class="svc-features-preview">
                        <?php foreach (array_slice($features, 0, 4) as $f): ?>
                        <span class="svc-feature-tag"><i class="fas fa-check"></i> <?php echo htmlspecialchars($f); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($features) > 4): ?>
                        <span class="svc-feature-more">+<?php echo count($features) - 4; ?> more</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="svc-row-actions">
                    <!-- Toggle -->
                    <form method="POST" class="svc-inline-form">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="toggle_id" value="<?php echo $svc['id']; ?>">
                        <input type="hidden" name="active" value="<?php echo $svc['is_active'] ? 0 : 1; ?>">
                        <label class="toggle-switch" title="<?php echo $svc['is_active'] ? 'Active' : 'Inactive'; ?>">
                            <input type="checkbox" <?php echo $svc['is_active'] ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span class="toggle-slider"></span>
                        </label>
                    </form>
                    <!-- Edit -->
                    <a href="service-edit.php?id=<?php echo $svc['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit">
                        <i class="fas fa-pen"></i>
                    </a>
                    <!-- Delete -->
                    <form method="POST" class="svc-inline-form">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="delete_id" value="<?php echo $svc['id']; ?>">
                        <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete"
                                data-confirm="Delete '<?php echo htmlspecialchars($svc['title']); ?>'? This cannot be undone.">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
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
