<?php
header('Content-Type: application/json');

require_once 'config/db.php';
require_once 'config/mailer.php';
require_once 'config/turnstile.php';
require_once 'includes/stay-module.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
$now = time();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$key = 'tour_inq_attempts_' . md5($ip);
$attempts = $_SESSION[$key] ?? [];
$attempts = array_filter($attempts, fn($t) => ($now - $t) < 600);
if (count($attempts) >= 5) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'msg' => 'Too many submissions. Please wait a few minutes and try again.']);
    exit;
}

$first_name     = trim(strip_tags($_POST['first_name'] ?? ''));
$last_name      = trim(strip_tags($_POST['last_name'] ?? ''));
$email          = trim($_POST['email'] ?? '');
$phone          = trim(strip_tags($_POST['phone'] ?? ''));
$tour_id        = (int)($_POST['tour_id'] ?? 0);
$tour_title_in  = trim(strip_tags($_POST['tour_title'] ?? ''));
$preferred_date = trim($_POST['preferred_date'] ?? '');
$guests         = trim(strip_tags($_POST['guests'] ?? ''));
$message        = trim(strip_tags($_POST['message'] ?? ''));
$page_source    = trim(strip_tags($_POST['page_source'] ?? 'tours'));
$ts_token       = trim($_POST['cf-turnstile-response'] ?? '');

$errors = [];
if ($first_name === '') $errors[] = 'First name is required.';
if ($last_name === '') $errors[] = 'Last name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if ($tour_id <= 0 && $tour_title_in === '') $errors[] = 'Please select a tour package.';
if ($preferred_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferred_date)) $errors[] = 'Preferred date is invalid.';
if ($message !== '' && strlen($message) > 5000) $errors[] = 'Message is too long.';
if (!turnstile_verify_token($ts_token, $ip)) $errors[] = 'Security verification failed. Please try again.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = db();
    stay_ensure_schema($pdo);

    $tour_title = $tour_title_in;
    if ($tour_id > 0) {
        $st = $pdo->prepare("SELECT title FROM tours WHERE id = ? LIMIT 1");
        $st->execute([$tour_id]);
        $db_title = $st->fetchColumn();
        if ($db_title) $tour_title = $db_title;
    }
    if ($tour_title === '') $tour_title = 'Tour Package';

    $internal_message = "Tour Inquiry\n";
    $internal_message .= "Tour: {$tour_title}" . ($tour_id > 0 ? " (ID: {$tour_id})" : "") . "\n";
    $internal_message .= "Preferred Date: " . ($preferred_date ?: 'Not provided') . "\n";
    $internal_message .= "Guests: " . ($guests !== '' ? $guests : 'Not provided') . "\n";
    $internal_message .= "Source: {$page_source}\n\n";
    $internal_message .= "Guest Message:\n" . ($message !== '' ? $message : 'No additional message.');

    $pdo->prepare("
        INSERT INTO inquiries (inquiry_type, first_name, last_name, email, phone, checkin, checkout, guest_count, message, source_page, subject_label, status, ip_address)
        VALUES ('tour',?,?,?,?,NULL,NULL,?,?,?,?,'unread',?)
    ")->execute([
        $first_name, $last_name, $email, $phone, $guests !== '' ? $guests : null, $internal_message, $page_source, $tour_title, $ip
    ]);
    $inquiry_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('smtp_notify_email','email','smtp_from_name','phone','whatsapp')");
    $stmt->execute();
    $s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $notify_email = $s['smtp_notify_email'] ?? $s['email'] ?? '';
    $resort_name  = $s['smtp_from_name'] ?? 'We Trail (Pvt) Ltd';
    $contact_email = $s['email'] ?? '';
    $contact_phone = $s['phone'] ?? '';
    $contact_whatsapp = $s['whatsapp'] ?? '';
    $wa_digits = preg_replace('/[^0-9]/', '', $contact_whatsapp);
    $wa_url = $wa_digits ? ("https://wa.me/" . $wa_digits) : '';

    if ($notify_email) {
        $admin_html = "
        <div style='font-family:sans-serif;max-width:640px;margin:0 auto'>
            <div style='background:#111;padding:24px 28px;border-radius:10px 10px 0 0'>
                <h2 style='color:#C8961E;margin:0;font-size:1.2rem'>New Tour Inquiry - {$resort_name}</h2>
                <p style='color:rgba(255,255,255,0.5);margin:4px 0 0;font-size:0.85rem'>Inquiry #{$inquiry_id}</p>
            </div>
            <div style='background:#1a1a1a;padding:24px 28px;border-radius:0 0 10px 10px;border:1px solid rgba(255,255,255,0.07)'>
                <p><strong style='color:#C8961E'>Guest:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($first_name . ' ' . $last_name) . "</span></p>
                <p><strong style='color:#C8961E'>Email:</strong> <a href='mailto:" . htmlspecialchars($email) . "' style='color:#f0ebe0'>" . htmlspecialchars($email) . "</a></p>
                " . ($phone ? "<p><strong style='color:#C8961E'>Phone:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($phone) . "</span></p>" : "") . "
                <p><strong style='color:#C8961E'>Tour:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($tour_title) . "</span></p>
                <p><strong style='color:#C8961E'>Preferred Date:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($preferred_date ?: 'Not provided') . "</span></p>
                <p><strong style='color:#C8961E'>Guests:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($guests !== '' ? $guests : 'Not provided') . "</span></p>
                " . ($message !== '' ? "<hr style='border:none;border-top:1px solid rgba(255,255,255,0.07);margin:16px 0'><p><strong style='color:#C8961E'>Message:</strong></p><p style='color:rgba(240,235,224,0.75);line-height:1.6'>" . nl2br(htmlspecialchars($message)) . "</p>" : "") . "
                <hr style='border:none;border-top:1px solid rgba(255,255,255,0.07);margin:16px 0'>
                <p style='text-align:center'>
                    <a href='mailto:" . htmlspecialchars($email) . "' style='display:inline-block;background:#C8961E;color:#111;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:700;font-size:0.85rem'>Reply to Guest</a>
                </p>
                <p style='color:rgba(240,235,224,0.3);font-size:0.75rem;text-align:center;margin-top:16px'>Received from IP: {$ip}</p>
            </div>
        </div>";

        send_mail(
            $notify_email,
            $resort_name,
            "New Tour Inquiry #{$inquiry_id} - " . $first_name . ' ' . $last_name,
            $admin_html
        );
    }

    $guest_html = "
    <div style='font-family:sans-serif;max-width:600px;margin:0 auto'>
        <div style='background:#111;padding:24px 28px;border-radius:10px 10px 0 0;text-align:center'>
            <h2 style='color:#C8961E;margin:0;font-size:1.3rem'>{$resort_name}</h2>
            <p style='color:rgba(255,255,255,0.45);margin:4px 0 0;font-size:0.8rem'>Tour Inquiry Confirmation</p>
        </div>
        <div style='background:#1a1a1a;padding:32px 28px;border-radius:0 0 10px 10px;border:1px solid rgba(255,255,255,0.07)'>
            <p style='color:#f0ebe0;font-size:1rem'>Dear " . htmlspecialchars($first_name) . ",</p>
            <p style='color:rgba(240,235,224,0.78);line-height:1.7'>Thank you for your tour inquiry. We have received your request and our team will contact you shortly with availability and details.</p>
            <div style='background:rgba(200,150,30,0.08);border:1px solid rgba(200,150,30,0.2);border-radius:8px;padding:16px 20px;margin:20px 0'>
                <p style='color:#C8961E;font-weight:700;margin:0 0 8px'>Inquiry Summary</p>
                <p style='color:rgba(240,235,224,0.7);margin:0;line-height:1.6'><strong>Tour:</strong> " . htmlspecialchars($tour_title) . "<br><strong>Preferred Date:</strong> " . htmlspecialchars($preferred_date ?: 'Not provided') . "<br><strong>Guests:</strong> " . htmlspecialchars($guests !== '' ? $guests : 'Not provided') . "</p>
            </div>
            <p style='color:rgba(240,235,224,0.72);line-height:1.7'>Need anything urgent? Reply to this email or contact us directly:</p>
            " . ($contact_phone ? "<p style='color:rgba(240,235,224,0.75)'>Phone: <a href='tel:" . htmlspecialchars(preg_replace('/\s+/', '', $contact_phone)) . "' style='color:#C8961E'>" . htmlspecialchars($contact_phone) . "</a></p>" : "") . "
            " . ($contact_whatsapp ? "<p style='color:rgba(240,235,224,0.75)'>WhatsApp: " . ($wa_url ? ("<a href='" . htmlspecialchars($wa_url) . "' style='color:#C8961E'>" . htmlspecialchars($contact_whatsapp) . "</a>") : htmlspecialchars($contact_whatsapp)) . "</p>" : "") . "
            " . ($contact_email ? "<p style='color:rgba(240,235,224,0.75)'>Email: <a href='mailto:" . htmlspecialchars($contact_email) . "' style='color:#C8961E'>" . htmlspecialchars($contact_email) . "</a></p>" : "") . "
        </div>
    </div>";

    send_mail(
        $email,
        $first_name . ' ' . $last_name,
        "We received your tour inquiry - {$resort_name}",
        $guest_html
    );

    $attempts[] = $now;
    $_SESSION[$key] = array_values($attempts);

    echo json_encode(['ok' => true, 'msg' => 'Your tour inquiry has been sent successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Something went wrong. Please try again in a moment.']);
}
