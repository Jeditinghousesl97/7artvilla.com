<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();

if (!function_exists('ensure_tour_price_columns')) {
    function ensure_tour_price_columns(PDO $pdo) {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM tours') as $col) {
            $cols[$col['Field']] = true;
        }
        if (empty($cols['price_lkr'])) {
            $pdo->exec('ALTER TABLE tours ADD COLUMN price_lkr DECIMAL(10,2) NULL DEFAULT NULL AFTER price');
        }
        if (empty($cols['price_usd'])) {
            $pdo->exec('ALTER TABLE tours ADD COLUMN price_usd DECIMAL(10,2) NULL DEFAULT NULL AFTER price_lkr');
        }
    }
}

if (!function_exists('tour_price_lines')) {
    function tour_price_lines(array $tour) {
        $lkr = isset($tour['price_lkr']) && $tour['price_lkr'] !== null && (float)$tour['price_lkr'] > 0
            ? (float)$tour['price_lkr']
            : ((isset($tour['price']) && (float)$tour['price'] > 0) ? (float)$tour['price'] : null);
        $usd = isset($tour['price_usd']) && $tour['price_usd'] !== null && (float)$tour['price_usd'] > 0
            ? (float)$tour['price_usd']
            : null;

        $lines = [];
        if ($lkr !== null) $lines[] = 'LKR ' . number_format($lkr, 0);
        if ($usd !== null) $lines[] = 'USD ' . number_format($usd, 0);
        return $lines;
    }
}

ensure_tour_price_columns($pdo);

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle quick toggle active via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $tid    = (int)$_POST['toggle_id'];
        $active = (int)$_POST['active'];
        $pdo->prepare('UPDATE tours SET is_active = ? WHERE id = ?')->execute([$active, $tid]);
    }
    header('Location: tours.php');
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $did = (int)$_POST['delete_id'];
        // Delete image file if exists
        $row = $pdo->prepare('SELECT image_path FROM tours WHERE id = ?');
        $row->execute([$did]);
        $img = $row->fetchColumn();
        if ($img && file_exists('../' . $img)) unlink('../' . $img);
        $pdo->prepare('DELETE FROM tours WHERE id = ?')->execute([$did]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Tour deleted successfully.'];
    }
    header('Location: tours.php');
    exit;
}

// Fetch all tours
$filter   = $_GET['filter'] ?? 'all';
$allowed  = ['all', 'half-day', 'full-day', 'sunrise'];
if (!in_array($filter, $allowed)) $filter = 'all';

$where  = $filter !== 'all' ? "WHERE category = " . $pdo->quote($filter) : '';
$tours  = $pdo->query("SELECT * FROM tours $where ORDER BY sort_order ASC, id ASC")->fetchAll();

$counts = $pdo->query("SELECT category, COUNT(*) n FROM tours GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
$total  = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tours | We Trail Admin</title>
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
                <div class="topbar-title">Tour Packages</div>
                <div class="topbar-sub"><?php echo $total; ?> total packages</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="tour-edit.php" class="topbar-btn topbar-btn-gold">
                <i class="fas fa-plus"></i> Add Tour
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
                <h2>Tour Packages</h2>
                <p>Add, edit, and manage all tour packages shown on the website.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-row" style="margin-bottom:20px">
            <div class="filter-btn-group">
                <a href="?filter=all"      class="filter-tab <?php echo $filter === 'all'      ? 'active' : ''; ?>">All <span class="filter-count"><?php echo $total; ?></span></a>
                <a href="?filter=half-day" class="filter-tab <?php echo $filter === 'half-day' ? 'active' : ''; ?>">Half Day <span class="filter-count"><?php echo $counts['half-day'] ?? 0; ?></span></a>
                <a href="?filter=full-day" class="filter-tab <?php echo $filter === 'full-day' ? 'active' : ''; ?>">Full Day <span class="filter-count"><?php echo $counts['full-day'] ?? 0; ?></span></a>
                <a href="?filter=sunrise"  class="filter-tab <?php echo $filter === 'sunrise'  ? 'active' : ''; ?>">Sunrise <span class="filter-count"><?php echo $counts['sunrise'] ?? 0; ?></span></a>
            </div>
        </div>

        <!-- Tours Grid -->
        <?php if (empty($tours)): ?>
        <div class="admin-card">
            <div class="empty-state">
                <i class="fas fa-compass"></i>
                <h3>No tours yet</h3>
                <p>Add your first tour package to get started.</p>
                <a href="tour-edit.php" class="btn-admin btn-gold btn-sm" style="margin-top:8px">
                    <i class="fas fa-plus"></i> Add Tour
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="tours-admin-grid">
            <?php foreach ($tours as $tour):
                $highlights = json_decode($tour['highlights'] ?? '[]', true) ?: [];
                $cat_labels = ['half-day' => 'Half Day', 'full-day' => 'Full Day', 'sunrise' => 'Sunrise'];
                $cat_label  = $cat_labels[$tour['category']] ?? $tour['category'];
                $price_lines = tour_price_lines($tour);
            ?>
            <div class="tour-admin-card <?php echo !$tour['is_active'] ? 'inactive' : ''; ?>">

                <!-- Image -->
                <div class="tac-image">
                    <?php if ($tour['image_path'] && file_exists('../' . $tour['image_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($tour['image_path']); ?>" alt="<?php echo htmlspecialchars($tour['title']); ?>">
                    <?php else: ?>
                    <div class="tac-img-placeholder"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                    <span class="tac-category-badge"><?php echo htmlspecialchars($cat_label); ?></span>
                    <?php if ($tour['is_popular']): ?><span class="tac-badge-popular">Popular</span><?php endif; ?>
                    <?php if ($tour['is_must_do']): ?><span class="tac-badge-mustdo">Must Do</span><?php endif; ?>
                </div>

                <!-- Body -->
                <div class="tac-body">
                    <div class="tac-header">
                        <h3><?php echo htmlspecialchars($tour['title']); ?></h3>
                        <label class="toggle-switch" title="<?php echo $tour['is_active'] ? 'Active' : 'Inactive'; ?>">
                            <form method="POST" style="display:contents">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="toggle_id" value="<?php echo $tour['id']; ?>">
                                <input type="hidden" name="active" value="<?php echo $tour['is_active'] ? 0 : 1; ?>">
                                <input type="checkbox" <?php echo $tour['is_active'] ? 'checked' : ''; ?>
                                       onchange="this.closest('form').submit()">
                                <span class="toggle-slider"></span>
                            </form>
                        </label>
                    </div>

                    <?php if ($tour['tagline']): ?>
                    <p class="tac-tagline"><?php echo htmlspecialchars($tour['tagline']); ?></p>
                    <?php endif; ?>

                    <div class="tac-meta">
                        <?php if ($tour['duration']): ?>
                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($tour['duration']); ?></span>
                        <?php endif; ?>
                        <?php if ($tour['difficulty']): ?>
                        <span><i class="fas fa-walking"></i> <?php echo htmlspecialchars($tour['difficulty']); ?></span>
                        <?php endif; ?>
                        <?php if ($tour['max_guests']): ?>
                        <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($tour['max_guests']); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($price_lines)): ?>
                    <div class="tac-price">
                        <?php echo htmlspecialchars(implode(' / ', $price_lines)); ?>
                        <span>/ person</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Footer actions -->
                <div class="tac-footer">
                    <span class="badge <?php echo $tour['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $tour['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <div class="tbl-actions">
                        <a href="tour-edit.php?id=<?php echo $tour['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $tour['id']; ?>">
                            <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete"
                                    data-confirm="Delete '<?php echo htmlspecialchars($tour['title']); ?>'? This cannot be undone.">
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
