<?php
// Current page for active link detection
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="../assets/images/logo.png" alt="7 Art Villa">
        <div class="sidebar-logo-text">
            <span class="sidebar-resort">7 Art Villa</span>
            <span class="sidebar-panel">Admin Panel</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <div class="sidebar-section-label">Overview</div>
        <a href="dashboard.php" class="sidebar-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <div class="sidebar-section-label">Inquiries</div>
        <a href="inquiries.php" class="sidebar-link <?php echo $current === 'inquiries.php' ? 'active' : ''; ?>">
            <i class="fas fa-inbox"></i> All Inquiries
            <?php
            // Show unread count badge
            try {
                $u = db()->query("SELECT COUNT(*) FROM inquiries WHERE status = 'unread'")->fetchColumn();
                if ($u > 0) echo '<span class="sidebar-badge">' . $u . '</span>';
            } catch (Exception $e) {}
            ?>
        </a>

        <div class="sidebar-section-label">Content</div>
        <a href="tours.php" class="sidebar-link <?php echo in_array($current, ['tours.php','tour-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-compass"></i> Tours
        </a>
        <a href="destinations.php" class="sidebar-link <?php echo in_array($current, ['destinations.php','destination-edit.php','destination-categories.php']) ? 'active' : ''; ?>">
            <i class="fas fa-map-marked-alt"></i> Destinations
        </a>
        <a href="services.php" class="sidebar-link <?php echo in_array($current, ['services.php','service-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-concierge-bell"></i> Services
        </a>
        <a href="villas.php" class="sidebar-link <?php echo in_array($current, ['villas.php','villa-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-house"></i> Villas
        </a>
        <a href="villa-spaces.php" class="sidebar-link <?php echo in_array($current, ['villa-spaces.php','villa-space-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-sitemap"></i> Villa Spaces
        </a>
        <a href="bookable-units.php" class="sidebar-link <?php echo in_array($current, ['bookable-units.php','bookable-unit-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-bed"></i> Bookable Units
        </a>
        <a href="unit-pricing.php" class="sidebar-link <?php echo in_array($current, ['unit-pricing.php','unit-pricing-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-tag"></i> Unit Pricing
        </a>
        <a href="gallery.php" class="sidebar-link <?php echo in_array($current, ['gallery.php','gallery-upload.php','gallery-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-images"></i> Gallery
        </a>

        <div class="sidebar-section-label">System</div>
        <a href="settings.php" class="sidebar-link <?php echo $current === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="change-password.php" class="sidebar-link <?php echo $current === 'change-password.php' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i> Change Password
        </a>
        <a href="../index.php" target="_blank" class="sidebar-link">
            <i class="fas fa-external-link-alt"></i> View Website
        </a>

    </nav>

    <!-- User + Logout -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div class="sidebar-user-name"><?php echo admin_name(); ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </div>

</aside>
