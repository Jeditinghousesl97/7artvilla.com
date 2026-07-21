<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . admin_url('inquiries.php'));
    exit;
}

$inq = $pdo->prepare('
    SELECT i.*, v.name AS villa_name, s.name AS space_name, u.name AS unit_name
    FROM inquiries i
    LEFT JOIN villas v ON v.id = i.villa_id
    LEFT JOIN villa_spaces s ON s.id = i.villa_space_id
    LEFT JOIN bookable_units u ON u.id = i.bookable_unit_id
    WHERE i.id = ?
');
$inq->execute([$id]);
$inq = $inq->fetch();

if (!$inq) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Inquiry not found.'];
    header('Location: ' . admin_url('inquiries.php'));
    exit;
}

// Auto mark as read when opened
if ($inq['status'] === 'unread') {
    $pdo->prepare("UPDATE inquiries SET status = 'read' WHERE id = ?")->execute([$id]);
    $inq['status'] = 'read';
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
        header('Location: inquiry-view.php?id=' . $id);
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'mark_replied') {
        $pdo->prepare("UPDATE inquiries SET status = 'replied' WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Marked as replied.'];
    } elseif ($action === 'mark_unread') {
        $pdo->prepare("UPDATE inquiries SET status = 'unread' WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Marked as unread.'];
    }

    header('Location: inquiry-view.php?id=' . $id);
    exit;
}

// Prev / Next inquiry IDs
$prev = $pdo->prepare("SELECT id FROM inquiries WHERE id < ? ORDER BY id DESC LIMIT 1");
$prev->execute([$id]);
$prev_id = $prev->fetchColumn();

$next = $pdo->prepare("SELECT id FROM inquiries WHERE id > ? ORDER BY id ASC LIMIT 1");
$next->execute([$id]);
$next_id = $next->fetchColumn();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$inquiry_type_view = ($inq['inquiry_type'] === 'general' && strpos((string)$inq['message'], 'Tour Inquiry') === 0) ? 'tour' : $inq['inquiry_type'];
$is_tour_inquiry = $inquiry_type_view === 'tour';
$tour_info = [
    'tour' => '',
    'preferred_date' => '',
    'guests' => '',
    'source' => '',
];
if ($is_tour_inquiry) {
    $lines = preg_split('/\r\n|\r|\n/', (string)$inq['message']);
    foreach ($lines as $line) {
        if (stripos($line, 'Tour:') === 0) $tour_info['tour'] = trim(substr($line, strlen('Tour:')));
        if (stripos($line, 'Preferred Date:') === 0) $tour_info['preferred_date'] = trim(substr($line, strlen('Preferred Date:')));
        if (stripos($line, 'Guests:') === 0) $tour_info['guests'] = trim(substr($line, strlen('Guests:')));
        if (stripos($line, 'Source:') === 0) $tour_info['source'] = trim(substr($line, strlen('Source:')));
    }
}

// Build WhatsApp & email links
$wa_msg  = urlencode("Hello {$inq['first_name']}, thank you for your inquiry about 7 Art Villa. We would be happy to assist you.");
$wa_link = "https://wa.me/{$inq['phone']}?text={$wa_msg}";
$mail_subject = urlencode("Re: Your Inquiry - 7 Art Villa");
$mail_body    = urlencode("Dear {$inq['first_name']},\n\nThank you for reaching out to us.\n\nBest regards,\n7 Art Villa");
$mail_link = "mailto:{$inq['email']}?subject={$mail_subject}&body={$mail_body}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry #<?php echo $id; ?> | 7 Art Villa Admin</title>
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
                <div class="topbar-title">Inquiry #<?php echo $id; ?></div>
                <div class="topbar-sub">
                    <?php echo htmlspecialchars($inq['first_name'] . ' ' . $inq['last_name']); ?>
                    &mdash; <?php echo date('d M Y, h:i A', strtotime($inq['created_at'])); ?>
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Prev / Next -->
            <?php if ($prev_id): ?>
            <a href="inquiry-view.php?id=<?php echo $prev_id; ?>" class="topbar-btn topbar-btn-outline" title="Previous inquiry">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            <?php if ($next_id): ?>
            <a href="inquiry-view.php?id=<?php echo $next_id; ?>" class="topbar-btn topbar-btn-outline" title="Next inquiry">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
            <a href="inquiries.php" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-arrow-left"></i> Back
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

        <div class="inq-view-grid">

            <!-- LEFT: Inquiry Detail  -->
            <div class="inq-main">

                <!-- Guest Info Card -->
                <div class="admin-card inq-guest-card">
                    <div class="inq-guest-header">
                        <div class="inq-avatar">
                            <?php echo strtoupper(substr($inq['first_name'], 0, 1) . substr($inq['last_name'], 0, 1)); ?>
                        </div>
                        <div class="inq-guest-info">
                            <h2><?php echo htmlspecialchars($inq['first_name'] . ' ' . $inq['last_name']); ?></h2>
                            <div style="margin-top:4px;margin-bottom:4px">
                                <?php if ($is_tour_inquiry): ?>
                                <span class="badge badge-extra">Tour Inquiry</span>
                                <?php elseif ($inquiry_type_view === 'stay'): ?>
                                <span class="badge badge-gold">Stay Inquiry</span>
                                <?php else: ?>
                                <span class="badge badge-read">General Inquiry</span>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-<?php echo $inq['status']; ?>" style="margin-top:4px">
                                <?php echo ucfirst($inq['status']); ?>
                            </span>
                        </div>
                        <div class="inq-guest-actions">
                            <a href="<?php echo $mail_link; ?>" class="btn-admin btn-outline btn-sm">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                            <?php if ($inq['phone']): ?>
                            <a href="<?php echo $wa_link; ?>" target="_blank" rel="noopener" class="btn-admin btn-green btn-sm">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="inq-contact-row">
                        <div class="inq-contact-item">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo htmlspecialchars($inq['email']); ?>">
                                <?php echo htmlspecialchars($inq['email']); ?>
                            </a>
                        </div>
                        <?php if ($inq['phone']): ?>
                        <div class="inq-contact-item">
                            <i class="fas fa-phone"></i>
                            <a href="tel:<?php echo htmlspecialchars($inq['phone']); ?>">
                                <?php echo htmlspecialchars($inq['phone']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="inq-contact-item">
                            <i class="fas fa-clock"></i>
                            <span>Received <?php echo date('d M Y \a\t h:i A', strtotime($inq['created_at'])); ?></span>
                        </div>
                        <?php if ($inq['ip_address']): ?>
                        <div class="inq-contact-item">
                            <i class="fas fa-globe"></i>
                            <span><?php echo htmlspecialchars($inq['ip_address']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stay Dates -->
                <?php if ($inq['checkin'] || $inq['checkout']): ?>
                <div class="admin-card inq-dates-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title"><i class="fas fa-calendar-alt" style="color:var(--gold);margin-right:8px"></i>Requested Stay Dates</span>
                    </div>
                    <div class="inq-dates-body">
                        <div class="inq-date-box">
                            <span class="date-label">Check-In</span>
                            <span class="date-val">
                                <?php echo $inq['checkin'] ? date('l, d M Y', strtotime($inq['checkin'])) : ' - '; ?>
                            </span>
                        </div>
                        <div class="inq-date-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="inq-date-box">
                            <span class="date-label">Check-Out</span>
                            <span class="date-val">
                                <?php echo $inq['checkout'] ? date('l, d M Y', strtotime($inq['checkout'])) : ' - '; ?>
                            </span>
                        </div>
                        <?php if ($inq['checkin'] && $inq['checkout']): ?>
                        <div class="inq-date-box">
                            <span class="date-label">Nights</span>
                            <span class="date-val date-nights">
                                <?php
                                $nights = (strtotime($inq['checkout']) - strtotime($inq['checkin'])) / 86400;
                                echo max(0, (int)$nights);
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($inquiry_type_view === 'stay' || !empty($inq['villa_name']) || !empty($inq['space_name']) || !empty($inq['unit_name'])): ?>
                <div class="admin-card inq-dates-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title"><i class="fas fa-house" style="color:var(--gold);margin-right:8px"></i>Stay Selection</span>
                    </div>
                    <div class="inq-meta-list" style="padding:18px">
                        <div class="inq-meta-item"><span class="meta-label">Villa</span><span class="meta-val"><?php echo htmlspecialchars($inq['villa_name'] ?: 'Not selected'); ?></span></div>
                        <div class="inq-meta-item"><span class="meta-label">Space</span><span class="meta-val"><?php echo htmlspecialchars($inq['space_name'] ?: 'Not selected'); ?></span></div>
                        <div class="inq-meta-item"><span class="meta-label">Unit</span><span class="meta-val"><?php echo htmlspecialchars($inq['unit_name'] ?: ($inq['subject_label'] ?: 'Not selected')); ?></span></div>
                        <div class="inq-meta-item"><span class="meta-label">Guests</span><span class="meta-val"><?php echo htmlspecialchars($inq['guest_count'] ?: 'Not provided'); ?></span></div>
                        <div class="inq-meta-item"><span class="meta-label">Source Page</span><span class="meta-val"><?php echo htmlspecialchars($inq['source_page'] ?: 'Not recorded'); ?></span></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($is_tour_inquiry): ?>
                <div class="admin-card inq-dates-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title"><i class="fas fa-map-signs" style="color:var(--gold);margin-right:8px"></i>Tour Inquiry Details</span>
                    </div>
                    <div class="inq-meta-list" style="padding:18px">
                        <div class="inq-meta-item">
                            <span class="meta-label">Tour</span>
                            <span class="meta-val"><?php echo htmlspecialchars($tour_info['tour'] ?: ' - '); ?></span>
                        </div>
                        <div class="inq-meta-item">
                            <span class="meta-label">Preferred Date</span>
                            <span class="meta-val"><?php echo htmlspecialchars($tour_info['preferred_date'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="inq-meta-item">
                            <span class="meta-label">Guests</span>
                            <span class="meta-val"><?php echo htmlspecialchars($tour_info['guests'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="inq-meta-item">
                            <span class="meta-label">Source</span>
                            <span class="meta-val"><?php echo htmlspecialchars($tour_info['source'] ?: 'tour form'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Message -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title"><i class="fas fa-comment-dots" style="color:var(--gold);margin-right:8px"></i>Message</span>
                    </div>
                    <div class="inq-message">
                        <?php echo nl2br(htmlspecialchars($inq['message'])); ?>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Actions  -->
            <div class="inq-sidebar">

                <!-- Status Actions -->
                <div class="admin-card inq-action-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title">Actions</span>
                    </div>
                    <div class="inq-actions-body">

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                            <?php if ($inq['status'] !== 'replied'): ?>
                            <button type="submit" name="action" value="mark_replied" class="btn-admin btn-green" style="width:100%">
                                <i class="fas fa-check-double"></i> Mark as Replied
                            </button>
                            <?php endif; ?>

                            <?php if ($inq['status'] !== 'unread'): ?>
                            <button type="submit" name="action" value="mark_unread" class="btn-admin btn-outline" style="width:100%;margin-top:8px">
                                <i class="fas fa-envelope"></i> Mark as Unread
                            </button>
                            <?php endif; ?>
                        </form>

                        <div class="inq-action-divider"></div>

                        <a href="<?php echo $mail_link; ?>" class="btn-admin btn-outline" style="width:100%">
                            <i class="fas fa-paper-plane"></i> Reply via Email
                        </a>

                        <?php if ($inq['phone']): ?>
                        <a href="<?php echo $wa_link; ?>" target="_blank" rel="noopener" class="btn-admin btn-green" style="width:100%;margin-top:8px">
                            <i class="fab fa-whatsapp"></i> Reply via WhatsApp
                        </a>
                        <a href="tel:<?php echo htmlspecialchars($inq['phone']); ?>" class="btn-admin btn-outline" style="width:100%;margin-top:8px">
                            <i class="fas fa-phone"></i> Call Guest
                        </a>
                        <?php endif; ?>

                        <div class="inq-action-divider"></div>

                        <a href="inquiry-action.php?action=delete&id=<?php echo $id; ?>&csrf=<?php echo csrf_token(); ?>"
                           class="btn-admin btn-danger" style="width:100%"
                           data-confirm="Delete this inquiry? This cannot be undone.">
                            <i class="fas fa-trash"></i> Delete Inquiry
                        </a>

                    </div>
                </div>

                <!-- Inquiry Meta -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <span class="admin-card-title">Details</span>
                    </div>
                    <div class="inq-meta-list">
                        <div class="inq-meta-item">
                            <span class="meta-label">Inquiry ID</span>
                            <span class="meta-val">#<?php echo $id; ?></span>
                        </div>
                        <div class="inq-meta-item">
                            <span class="meta-label">Status</span>
                            <span class="badge badge-<?php echo $inq['status']; ?>"><?php echo ucfirst($inq['status']); ?></span>
                        </div>
                        <div class="inq-meta-item">
                            <span class="meta-label">Received</span>
                            <span class="meta-val"><?php echo date('d M Y', strtotime($inq['created_at'])); ?></span>
                        </div>
                        <div class="inq-meta-item">
                            <span class="meta-label">Time</span>
                            <span class="meta-val"><?php echo date('h:i A', strtotime($inq['created_at'])); ?></span>
                        </div>
                        <?php if ($inq['checkin'] && $inq['checkout']): ?>
                        <div class="inq-meta-item">
                            <span class="meta-label">Nights</span>
                            <span class="meta-val">
                                <?php echo max(0, (int)(strtotime($inq['checkout']) - strtotime($inq['checkin'])) / 86400); ?> nights
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
</body>
</html>
