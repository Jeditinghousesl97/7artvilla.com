<?php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/mailer.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request.']);
    exit;
}

// Send to notify email or fallback to contact email
$pdo  = db();
$stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('smtp_notify_email','email','smtp_from_name')");
$stmt->execute();
$s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$to   = $s['smtp_notify_email'] ?? $s['email'] ?? '';
$name = $s['smtp_from_name'] ?? 'We Trail (Pvt) Ltd';

if (!$to) {
    echo json_encode(['ok' => false, 'msg' => 'No notification email address configured. Set Admin Notification Email first.']);
    exit;
}

$html = "
<div style='font-family:sans-serif;max-width:500px;margin:0 auto'>
    <div style='background:#111;padding:20px 24px;border-radius:10px 10px 0 0'>
        <h2 style='color:#C8961E;margin:0'>Test Email - {$name}</h2>
    </div>
    <div style='background:#1a1a1a;padding:24px;border-radius:0 0 10px 10px;border:1px solid rgba(255,255,255,0.07)'>
        <p style='color:#f0ebe0'>This is a test email from your admin panel.</p>
        <p style='color:rgba(240,235,224,0.6)'>If you received this, your email configuration is working correctly.</p>
        <p style='color:rgba(240,235,224,0.4);font-size:0.75rem;margin-top:20px'>Sent: " . date('d M Y H:i:s') . "</p>
    </div>
</div>";

$ok = send_mail($to, $name, "Test Email - {$name}", $html);
echo json_encode([
    'ok'  => $ok,
    'msg' => $ok ? "Test email sent to {$to}" : 'Failed to send. Check your SMTP settings and try again.'
]);
