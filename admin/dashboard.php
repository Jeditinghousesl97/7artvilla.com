<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

// Stats 
$total_inquiries  = $pdo->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();
$unread_inquiries = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'unread'")->fetchColumn();
$this_month       = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();
$total_tours      = $pdo->query("SELECT COUNT(*) FROM tours WHERE is_active = 1")->fetchColumn();
$total_destinations = 0;
$has_destinations_table = (bool)$pdo->query("SHOW TABLES LIKE 'destinations'")->fetchColumn();
if ($has_destinations_table) {
    $total_destinations = (int)$pdo->query("SELECT COUNT(*) FROM destinations WHERE is_active = 1")->fetchColumn();
}
$total_services   = $pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();
$total_gallery    = $pdo->query("SELECT COUNT(*) FROM gallery_images WHERE is_active = 1")->fetchColumn();
$total_villas     = (int)$pdo->query("SELECT COUNT(*) FROM villas WHERE is_active = 1")->fetchColumn();
$total_units      = (int)$pdo->query("SELECT COUNT(*) FROM bookable_units WHERE is_active = 1")->fetchColumn();

// Recent inquiries (last 8) 
$recent = $pdo->query("
    SELECT id, first_name, last_name, email, checkin, checkout, status, created_at
    FROM inquiries
    ORDER BY created_at DESC
    LIMIT 8
")->fetchAll();

// Monthly chart data (last 6 months) 
$chart_data = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b') AS month_label,
           COUNT(*) AS total
    FROM inquiries
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | 7 Art Villa Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta name="theme-color" content="#1a1a1a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-body">

<!-- SIDEBAR -->
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN  -->
<div class="admin-main">

    <!-- Top Bar -->
    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="topbar-title">Dashboard</div>
                <div class="topbar-sub">Welcome back, <?php echo admin_name(); ?></div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-external-link-alt"></i> View Site
            </a>
            <a href="inquiries.php" class="topbar-btn topbar-btn-gold">
                <i class="fas fa-inbox"></i> Inquiries
                <?php if ($unread_inquiries > 0): ?>
                <span class="topbar-badge"><?php echo $unread_inquiries; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <!-- Content -->
    <div class="admin-content">

        <!-- STAT CARDS  -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon stat-icon-red">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $unread_inquiries; ?></div>
                    <div class="stat-label">Unread Inquiries</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-gold">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_inquiries; ?></div>
                    <div class="stat-label">Total Inquiries</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $this_month; ?></div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <i class="fas fa-compass"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_tours; ?></div>
                    <div class="stat-label">Active Tours</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_destinations; ?></div>
                    <div class="stat-label">Active Destinations</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-gold">
                    <i class="fas fa-house"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_villas; ?></div>
                    <div class="stat-label">Active Villas</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_units; ?></div>
                    <div class="stat-label">Bookable Units</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-gold">
                    <i class="fas fa-concierge-bell"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_services; ?></div>
                    <div class="stat-label">Active Services</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-images"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $total_gallery; ?></div>
                    <div class="stat-label">Gallery Photos</div>
                </div>
            </div>

        </div>

        <!-- BOTTOM GRID: Chart + Recent Inquiries  -->
        <div class="dashboard-grid">

            <!-- Monthly Chart -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Inquiries â€” Last 6 Months</span>
                </div>
                <div class="chart-wrap">
                    <?php if (empty($chart_data)): ?>
                    <div class="empty-state" style="padding:40px 24px">
                        <i class="fas fa-chart-bar"></i>
                        <p>No inquiry data yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="bar-chart" id="barChart">
                        <?php
                        $max = max(array_column($chart_data, 'total')) ?: 1;
                        foreach ($chart_data as $row):
                            $pct = round(($row['total'] / $max) * 100);
                        ?>
                        <div class="bar-col">
                            <div class="bar-val"><?php echo $row['total']; ?></div>
                            <div class="bar-track">
                                <div class="bar-fill" style="--h:<?php echo $pct; ?>%"></div>
                            </div>
                            <div class="bar-label"><?php echo htmlspecialchars($row['month_label']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Inquiries -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="admin-card-title">Recent Inquiries</span>
                    <a href="inquiries.php" class="btn-admin btn-outline btn-sm">View All</a>
                </div>
                <?php if (empty($recent)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No inquiries yet</h3>
                    <p>Inquiries submitted through the website will appear here.</p>
                </div>
                <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Check-In</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $inq): ?>
                            <tr>
                                <td>
                                    <div class="fw"><?php echo htmlspecialchars($inq['first_name'] . ' ' . $inq['last_name']); ?></div>
                                    <div style="font-size:0.78rem;color:var(--text-muted)"><?php echo htmlspecialchars($inq['email']); ?></div>
                                </td>
                                <td>
                                    <?php if ($inq['checkin']): ?>
                                        <?php echo date('d M Y', strtotime($inq['checkin'])); ?>
                                        <?php if ($inq['checkout']): ?>
                                        <div style="font-size:0.78rem;color:var(--text-muted)">â†’ <?php echo date('d M Y', strtotime($inq['checkout'])); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted)">â€”</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $inq['status']; ?>">
                                        <?php echo ucfirst($inq['status']); ?>
                                    </span>
                                </td>
                                <td style="color:var(--text-muted);font-size:0.82rem">
                                    <?php echo date('d M', strtotime($inq['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="tbl-actions">
                                        <a href="inquiry-view.php?id=<?php echo $inq['id']; ?>" class="tbl-btn tbl-btn-view" title="View"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /dashboard-grid -->

        <!-- QUICK LINKS -->
        <div class="quick-links-grid">
            <a href="tours.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-compass"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Manage Tours</span>
                    <span class="ql-sub">Add, edit, or remove tour packages</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="services.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-concierge-bell"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Manage Services</span>
                    <span class="ql-sub">Update service listings and categories</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="destinations.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-map-marked-alt"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Manage Destinations</span>
                    <span class="ql-sub">Create destination guides for guests</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="villas.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-house"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Manage Villas</span>
                    <span class="ql-sub">Edit main villa/property records</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="bookable-units.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-bed"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Bookable Units</span>
                    <span class="ql-sub">Manage rooms and stay options</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="gallery.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-images"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Manage Gallery</span>
                    <span class="ql-sub">Upload photos and manage categories</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="settings.php" class="quick-link-card">
                <div class="ql-icon"><i class="fas fa-cog"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Site Settings</span>
                    <span class="ql-sub">Phone, email, social links</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
            <a href="inquiries.php?status=unread" class="quick-link-card">
                <div class="ql-icon ql-icon-red"><i class="fas fa-envelope-open-text"></i></div>
                <div class="ql-text">
                    <span class="ql-title">Unread Inquiries</span>
                    <span class="ql-sub"><?php echo $unread_inquiries; ?> awaiting your response</span>
                </div>
                <i class="fas fa-chevron-right ql-arrow"></i>
            </a>
        </div>

    </div><!-- /admin-content -->
</div><!-- /admin-main -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="assets/js/admin.js"></script>
</body>
</html>
