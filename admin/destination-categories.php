<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS destination_categories (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(120) NOT NULL,
    slug         VARCHAR(140) NOT NULL UNIQUE,
    description  TEXT         DEFAULT NULL,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function category_slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'category';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid request. Please try again.'];
        header('Location: destination-categories.php');
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $pdo->prepare('DELETE FROM destination_categories WHERE id = ?')->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Category deleted successfully.'];
        header('Location: destination-categories.php');
        exit;
    }

    if (isset($_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        $active = (int)$_POST['active'];
        $pdo->prepare('UPDATE destination_categories SET is_active = ? WHERE id = ?')->execute([$active, $id]);
        header('Location: destination-categories.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = category_slugify($_POST['slug'] ?? $name);
    $description = trim($_POST['description'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Category name is required.'];
        header('Location: destination-categories.php');
        exit;
    }

    try {
        if ($id > 0) {
            $pdo->prepare('UPDATE destination_categories SET name=?, slug=?, description=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([$name, $slug, $description, $sort_order, $is_active, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Category updated successfully.'];
        } else {
            $pdo->prepare('INSERT INTO destination_categories (name, slug, description, sort_order, is_active) VALUES (?,?,?,?,?)')
                ->execute([$name, $slug, $description, $sort_order, $is_active]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Category added successfully.'];
        }
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Slug already exists. Please use a unique slug.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Unable to save category.'];
        }
    }

    header('Location: destination-categories.php');
    exit;
}

$categories = $pdo->query('SELECT * FROM destination_categories ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC);
$editing_id = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editing_id > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $editing_id) {
            $editing = $cat;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destination Categories | We Trail Admin</title>
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
                <div class="topbar-title">Destination Categories</div>
                <div class="topbar-sub"><?php echo count($categories); ?> total categories</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="destinations.php" class="topbar-btn topbar-btn-outline"><i class="fas fa-map-marked-alt"></i> Destinations</a>
        </div>
    </header>

    <div class="admin-content">
        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" data-auto-dismiss>
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
        <?php endif; ?>

        <div class="edit-layout">
            <div class="edit-main">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title">All Categories</span>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No categories found.</td></tr>
                                <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <div class="fw"><?php echo htmlspecialchars($cat['name']); ?></div>
                                        <?php if (!empty($cat['description'])): ?><div style="font-size:0.78rem;color:var(--text-muted)"><?php echo htmlspecialchars($cat['description']); ?></div><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cat['slug']); ?></td>
                                    <td><span class="badge <?php echo (int)$cat['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo (int)$cat['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td><?php echo (int)$cat['sort_order']; ?></td>
                                    <td>
                                        <div class="tbl-actions">
                                            <form method="POST" style="display:contents">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="toggle_id" value="<?php echo (int)$cat['id']; ?>">
                                                <input type="hidden" name="active" value="<?php echo (int)$cat['is_active'] ? 0 : 1; ?>">
                                                <button type="submit" class="tbl-btn" title="Toggle"><i class="fas fa-power-off"></i></button>
                                            </form>
                                            <a href="destination-categories.php?edit=<?php echo (int)$cat['id']; ?>" class="tbl-btn tbl-btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                            <form method="POST" style="display:contents">
                                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="delete_id" value="<?php echo (int)$cat['id']; ?>">
                                                <button type="submit" class="tbl-btn tbl-btn-delete" title="Delete" data-confirm="Delete this category? Linked category mapping will be removed."><i class="fas fa-trash"></i></button>
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

            <div class="edit-sidebar">
                <div class="form-card">
                    <div class="form-card-title"><?php echo $editing ? 'Edit Category' : 'Add Category'; ?></div>
                    <form method="POST" style="margin-top:16px">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)($editing['id'] ?? 0); ?>">

                        <div class="form-group" style="margin-bottom:12px">
                            <label>Category Name *</label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" placeholder="e.g. Nature Trails">
                        </div>

                        <div class="form-group" style="margin-bottom:12px">
                            <label>Slug</label>
                            <input type="text" name="slug" value="<?php echo htmlspecialchars($editing['slug'] ?? ''); ?>" placeholder="auto-generated if empty">
                        </div>

                        <div class="form-group" style="margin-bottom:12px">
                            <label>Description</label>
                            <textarea name="description" rows="4" placeholder="Optional short description"><?php echo htmlspecialchars($editing['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:12px">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" min="0" value="<?php echo (int)($editing['sort_order'] ?? 0); ?>">
                        </div>

                        <label class="checkbox-row" style="margin-bottom:16px">
                            <input type="checkbox" name="is_active" value="1" <?php echo !isset($editing['is_active']) || (int)$editing['is_active'] ? 'checked' : ''; ?>>
                            <span><strong>Active</strong><small>Visible for destination assignment</small></span>
                        </label>

                        <button type="submit" class="btn-admin btn-gold" style="width:100%"><i class="fas fa-save"></i> Save Category</button>
                        <?php if ($editing): ?>
                        <a href="destination-categories.php" class="btn-admin btn-outline" style="width:100%;margin-top:8px;text-align:center">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
</body>
</html>
