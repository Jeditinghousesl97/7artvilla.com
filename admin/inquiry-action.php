<?php
// Handles delete action (GET with CSRF token)
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$csrf   = $_GET['csrf'] ?? '';

if (!verify_csrf($csrf) || !$id) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
    header('Location: ' . admin_url('inquiries.php'));
    exit;
}

if ($action === 'delete') {
    db()->prepare('DELETE FROM inquiries WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Inquiry #' . $id . ' deleted.'];
}

header('Location: ' . admin_url('inquiries.php'));
exit;
