<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

// Filters 
$status  = $_GET['status']  ?? 'all';
$type    = $_GET['type']    ?? 'all';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

$allowed_statuses = ['all', 'unread', 'read', 'replied'];
if (!in_array($status, $allowed_statuses)) $status = 'all';
$allowed_types = ['all', 'general', 'stay', 'tour'];
if (!in_array($type, $allowed_types)) $type = 'all';

// Build query 
$where  = [];
$params = [];

if ($status !== 'all') {
    $where[]  = 'status = ?';
    $params[] = $status;
}

if ($type !== 'all') {
    $where[] = "(CASE WHEN inquiry_type = 'general' AND message LIKE 'Tour Inquiry%' THEN 'tour' ELSE inquiry_type END) = ?";
    $params[] = $type;
}

if ($search !== '') {
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries $where_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Fetch rows
$stmt = $pdo->prepare("
    SELECT i.id, i.first_name, i.last_name, i.email, i.phone, i.checkin, i.checkout, i.status, i.created_at,
           i.inquiry_type, i.subject_label, i.guest_count,
           v.name AS villa_name, s.name AS space_name, u.name AS unit_name,
           CASE WHEN i.inquiry_type = 'general' AND i.message LIKE 'Tour Inquiry%' THEN 'tour' ELSE i.inquiry_type END AS inquiry_type_view
    FROM inquiries i
    LEFT JOIN villas v ON v.id = i.villa_id
    LEFT JOIN villa_spaces s ON s.id = i.villa_space_id
    LEFT JOIN bookable_units u ON u.id = i.bookable_unit_id
    $where_sql
    ORDER BY
        CASE i.status WHEN 'unread' THEN 0 WHEN 'read' THEN 1 ELSE 2 END,
        i.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Status counts
$counts = $pdo->query("
    SELECT status, COUNT(*) AS n FROM inquiries GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
$count_all     = array_sum($counts);
$count_unread  = $counts['unread']  ?? 0;
$count_read    = $counts['read']    ?? 0;
$count_replied = $counts['replied'] ?? 0;

$type_counts = $pdo->query("
    SELECT
        SUM(CASE WHEN inquiry_type = 'tour' OR (inquiry_type = 'general' AND message LIKE 'Tour Inquiry%') THEN 1 ELSE 0 END) AS tour_n,
        SUM(CASE WHEN inquiry_type = 'stay' THEN 1 ELSE 0 END) AS stay_n,
        SUM(CASE WHEN inquiry_type = 'general' AND message NOT LIKE 'Tour Inquiry%' THEN 1 ELSE 0 END) AS general_n
    FROM inquiries
")->fetch(PDO::FETCH_ASSOC);
$count_tour = (int)($type_counts['tour_n'] ?? 0);
$count_stay = (int)($type_counts['stay_n'] ?? 0);
$count_general = (int)($type_counts['general_n'] ?? 0);

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries | 7 Art Villa Admin</title>
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

    <!-- Top Bar -->
    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div>
                <div class="topbar-title">Inquiries</div>
            <div class="topbar-sub"><?php echo $total; ?> total &mdash; <?php echo $count_unread; ?> unread</div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="inquiry-export.php?status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="topbar-btn topbar-btn-outline" title="Export current view to CSV">
                <i class="fas fa-download"></i> Export CSV
            </a>
            <a href="../index.php" target="_blank" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-external-link-alt"></i> View Site
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

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-text">
                <h2>Guest Inquiries</h2>
                <p>All inquiries submitted through the website across stays, tours, and general contact forms.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-row" style="margin-bottom:20px">
            <div class="filter-btn-group">
                <a href="?status=all&type=<?php echo urlencode($type); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                    All <span class="filter-count"><?php echo $count_all; ?></span>
                </a>
                <a href="?status=unread&type=<?php echo urlencode($type); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $status === 'unread' ? 'active' : ''; ?>">
                    Unread <span class="filter-count"><?php echo $count_unread; ?></span>
                </a>
                <a href="?status=read&type=<?php echo urlencode($type); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $status === 'read' ? 'active' : ''; ?>">
                    Read <span class="filter-count"><?php echo $count_read; ?></span>
                </a>
                <a href="?status=replied&type=<?php echo urlencode($type); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $status === 'replied' ? 'active' : ''; ?>">
                    Replied <span class="filter-count"><?php echo $count_replied; ?></span>
                </a>
            </div>

            <div class="filter-btn-group">
                <a href="?status=<?php echo urlencode($status); ?>&type=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $type === 'all' ? 'active' : ''; ?>">
                    All Types <span class="filter-count"><?php echo $count_all; ?></span>
                </a>
                <a href="?status=<?php echo urlencode($status); ?>&type=general<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $type === 'general' ? 'active' : ''; ?>">
                    General <span class="filter-count"><?php echo $count_general; ?></span>
                </a>
                <a href="?status=<?php echo urlencode($status); ?>&type=stay<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $type === 'stay' ? 'active' : ''; ?>">
                    Stay <span class="filter-count"><?php echo $count_stay; ?></span>
                </a>
                <a href="?status=<?php echo urlencode($status); ?>&type=tour<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
                   class="filter-tab <?php echo $type === 'tour' ? 'active' : ''; ?>">
                    Tour <span class="filter-count"><?php echo $count_tour; ?></span>
                </a>
            </div>

            <!-- Search -->
            <form method="GET" action="" style="margin-left:auto">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search name, email, phoneâ€¦"
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="admin-card">
            <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No inquiries found</h3>
                <p><?php echo $search ? 'No results match your search.' : 'Inquiries submitted through the website will appear here.'; ?></p>
                <?php if ($search || $status !== 'all'): ?>
                <a href="inquiries.php" class="btn-admin btn-outline btn-sm" style="margin-top:8px">Clear Filters</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Guest</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Received</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="<?php echo $row['status'] === 'unread' ? 'row-unread' : ''; ?>">
                            <td style="color:var(--text-muted);font-size:0.8rem">#<?php echo $row['id']; ?></td>
                            <td>
                                <div class="fw"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                            </td>
                            <td>
                                <div style="font-size:0.85rem"><?php echo htmlspecialchars($row['email']); ?></div>
                                <?php if ($row['phone']): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted)"><?php echo htmlspecialchars($row['phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['inquiry_type_view'] === 'tour'): ?>
                                <span class="badge badge-extra">Tour</span>
                                <?php elseif ($row['inquiry_type_view'] === 'stay'): ?>
                                <span class="badge badge-gold">Stay</span>
                                <?php else: ?>
                                <span class="badge badge-read">General</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['checkin']): ?>
                                <div style="font-size:0.85rem"><?php echo date('d M Y', strtotime($row['checkin'])); ?></div>
                                <?php if ($row['checkout']): ?>
                                <div style="font-size:0.78rem;color:var(--text-muted)">â†’ <?php echo date('d M Y', strtotime($row['checkout'])); ?></div>
                                <?php endif; ?>
                                <?php else: ?>
                                <span style="color:var(--text-muted)">â€”</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem;color:var(--text-muted);white-space:nowrap">
                                <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                <span style="font-size:0.75rem"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                                <?php if (!empty($row['unit_name']) || !empty($row['space_name']) || !empty($row['villa_name'])): ?>
                                <div style="font-size:0.75rem;margin-top:4px">
                                    <?php echo htmlspecialchars(implode(' / ', array_values(array_filter([$row['villa_name'], $row['space_name'], $row['unit_name']])))); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="tbl-actions">
                                    <a href="inquiry-view.php?id=<?php echo $row['id']; ?>"
                                       class="tbl-btn tbl-btn-view" title="View inquiry">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="inquiry-action.php?action=delete&id=<?php echo $row['id']; ?>&csrf=<?php echo csrf_token(); ?>"
                                       class="tbl-btn tbl-btn-delete"
                                       title="Delete"
                                       data-confirm="Delete this inquiry from <?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?>? This cannot be undone.">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $base_url = '?status=' . urlencode($status) . '&type=' . urlencode($type) . ($search ? '&search=' . urlencode($search) : '') . '&page=';
                ?>
                <a href="<?php echo $base_url . max(1, $page - 1); ?>"
                   class="page-btn" <?php echo $page <= 1 ? 'style="pointer-events:none;opacity:0.3"' : ''; ?>>
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo $base_url . $i; ?>"
                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                <a href="<?php echo $base_url . min($total_pages, $page + 1); ?>"
                   class="page-btn" <?php echo $page >= $total_pages ? 'style="pointer-events:none;opacity:0.3"' : ''; ?>>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
</body>
</html>
