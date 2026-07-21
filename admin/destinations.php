<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS destination_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS destinations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_summary TEXT DEFAULT NULL,
    description LONGTEXT NOT NULL,
    map_embed_html LONGTEXT DEFAULT NULL,
    distance_from_villa VARCHAR(120) DEFAULT NULL,
    travel_time_from_villa VARCHAR(120) DEFAULT NULL,
    best_time_to_visit VARCHAR(160) DEFAULT NULL,
    things_to_do LONGTEXT DEFAULT NULL,
    featured_image_path VARCHAR(255) DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_homepage TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS destination_category_map (
    destination_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (destination_id, category_id),
    INDEX idx_dcm_category (category_id),
    CONSTRAINT fk_dcm_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE,
    CONSTRAINT fk_dcm_category FOREIGN KEY (category_id) REFERENCES destination_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
        header('Location: destinations.php');
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $did = (int)$_POST['delete_id'];
        $row = $pdo->prepare('SELECT featured_image_path FROM destinations WHERE id = ?');
        $row->execute([$did]);
        $img = $row->fetchColumn();
        if ($img && file_exists('../' . $img)) unlink('../' . $img);

        $gallery = $pdo->prepare('SELECT image_path FROM destination_gallery_images WHERE destination_id = ?');
        try {
            $gallery->execute([$did]);
            foreach ($gallery->fetchAll(PDO::FETCH_COLUMN) as $gimg) {
                if ($gimg && file_exists('../' . $gimg)) unlink('../' . $gimg);
            }
        } catch (Throwable $e) {
        }

        $pdo->prepare('DELETE FROM destinations WHERE id = ?')->execute([$did]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Destination deleted successfully.'];
        header('Location: destinations.php');
        exit;
    }

    if (isset($_POST['toggle_id'])) {
        $did = (int)$_POST['toggle_id'];
        $active = (int)$_POST['active'];
        $pdo->prepare('UPDATE destinations SET is_active = ? WHERE id = ?')->execute([$active, $did]);
        header('Location: destinations.php');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'all';
$allowed = ['all', 'featured', 'homepage', 'active', 'inactive'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$where = '';
if ($filter === 'featured') $where = 'WHERE d.is_featured = 1';
if ($filter === 'homepage') $where = 'WHERE d.is_homepage = 1';
if ($filter === 'active') $where = 'WHERE d.is_active = 1';
if ($filter === 'inactive') $where = 'WHERE d.is_active = 0';

$sql = "
SELECT d.*,
       GROUP_CONCAT(c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names
FROM destinations d
LEFT JOIN destination_category_map m ON m.destination_id = d.id
LEFT JOIN destination_categories c ON c.id = m.category_id
$where
GROUP BY d.id
ORDER BY d.sort_order ASC, d.id DESC
";
$destinations = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total = (int)$pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn();
$total_featured = (int)$pdo->query('SELECT COUNT(*) FROM destinations WHERE is_featured = 1')->fetchColumn();
$total_homepage = (int)$pdo->query('SELECT COUNT(*) FROM destinations WHERE is_homepage = 1')->fetchColumn();
$total_active = (int)$pdo->query('SELECT COUNT(*) FROM destinations WHERE is_active = 1')->fetchColumn();
$total_inactive = $total - $total_active;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destinations | 7 Art Villa Admin</title>
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
                <div class="topbar-title">Destinations</div>
                <div class="topbar-sub"><?php echo $total; ?> total destination posts</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="destination-categories.php" class="topbar-btn topbar-btn-outline"><i class="fas fa-tags"></i> Categories</a>
            <a href="destination-edit.php" class="topbar-btn topbar-btn-gold"><i class="fas fa-plus"></i> Add Destination</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" data-auto-dismiss>
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
        <?php endif; ?>

        <div class="filter-row" style="margin-bottom:20px">
            <div class="filter-btn-group">
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All <span class="filter-count"><?php echo $total; ?></span></a>
                <a href="?filter=featured" class="filter-tab <?php echo $filter === 'featured' ? 'active' : ''; ?>">Featured <span class="filter-count"><?php echo $total_featured; ?></span></a>
                <a href="?filter=homepage" class="filter-tab <?php echo $filter === 'homepage' ? 'active' : ''; ?>">Homepage <span class="filter-count"><?php echo $total_homepage; ?></span></a>
                <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">Active <span class="filter-count"><?php echo $total_active; ?></span></a>
                <a href="?filter=inactive" class="filter-tab <?php echo $filter === 'inactive' ? 'active' : ''; ?>">Inactive <span class="filter-count"><?php echo $total_inactive; ?></span></a>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title">Destination Posts</span>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Destination</th>
                            <th>Distance / Time</th>
                            <th>Categories</th>
                            <th>Flags</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($destinations)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted)">No destinations found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($destinations as $d): ?>
                        <tr>
                            <td>
                                <div class="fw"><?php echo htmlspecialchars($d['title']); ?></div>
                                <div style="font-size:0.78rem;color:var(--text-muted)">/<?php echo htmlspecialchars($d['slug']); ?></div>
                                <?php if (!empty($d['short_summary'])): ?><div style="font-size:0.78rem;color:var(--text-muted)"><?php echo htmlspecialchars($d['short_summary']); ?></div><?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($d['distance_from_villa'] ?: '-'); ?></div>
                                <div style="font-size:0.78rem;color:var(--text-muted)"><?php echo htmlspecialchars($d['travel_time_from_villa'] ?: '-'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($d['category_names'] ?: 'Uncategorized'); ?></td>
                            <td>
                                <?php if ((int)$d['is_featured']): ?><span class="badge badge-gold" style="margin-right:6px">Featured</span><?php endif; ?>
                                <?php if ((int)$d['is_homepage']): ?><span class="badge badge-read">Homepage</span><?php endif; ?>
                                <?php if (!(int)$d['is_featured'] && !(int)$d['is_homepage']): ?><span style="color:var(--text-muted)">-</span><?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo (int)$d['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo (int)$d['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td>
                                <div class="tbl-actions">
                                    <form method="POST" style="display:contents">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="toggle_id" value="<?php echo (int)$d['id']; ?>">
                                        <input type="hidden" name="active" value="<?php echo (int)$d['is_active'] ? 0 : 1; ?>">
                                        <button type="submit" class="tbl-btn" title="Toggle"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <a href="destination-edit.php?id=<?php echo (int)$d['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                    <form method="POST" style="display:contents">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$d['id']; ?>">
                                        <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete" data-confirm="Delete '<?php echo htmlspecialchars($d['title']); ?>'? This cannot be undone."><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
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
