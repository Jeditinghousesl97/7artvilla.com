<?php

header('Content-Type: application/json');

require_once 'config/db.php';
require_once 'config/mailer.php';
require_once 'config/turnstile.php';
require_once 'includes/stay-module.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed.']);
    exit;
}

// Rate limit: max 3 submissions per IP per 10 minutes (via session)
if (session_status() === PHP_SESSION_NONE) session_start();
$now = time();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$key = 'inq_attempts_' . md5($ip);
$attempts = $_SESSION[$key] ?? [];
// Remove attempts older than 10 minutes
$attempts = array_filter($attempts, fn($t) => ($now - $t) < 600);
if (count($attempts) >= 3) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'msg' => 'Too many submissions. Please wait a few minutes and try again.']);
    exit;
}

// Sanitise inputs
$first_name = trim(strip_tags($_POST['first_name'] ?? ''));
$last_name  = trim(strip_tags($_POST['last_name']  ?? ''));
$email      = trim($_POST['email']   ?? '');
$phone      = trim(strip_tags($_POST['phone']   ?? ''));
$checkin    = trim($_POST['checkin']  ?? '');
$checkout   = trim($_POST['checkout'] ?? '');
$guest_count = trim(strip_tags($_POST['guest_count'] ?? ''));
$villa_id   = (int)($_POST['villa_id'] ?? 0);
$villa_space_id = (int)($_POST['villa_space_id'] ?? 0);
$bookable_unit_id = (int)($_POST['bookable_unit_id'] ?? 0);
$subject_label = trim(strip_tags($_POST['subject_label'] ?? ''));
$pricing_label = trim(strip_tags($_POST['pricing_label'] ?? ''));
$source_page = trim(strip_tags($_POST['source_page'] ?? 'index.php'));
$inquiry_type = trim(strip_tags($_POST['inquiry_type'] ?? 'general'));
$message    = trim(strip_tags($_POST['message'] ?? ''));
$ts_token   = trim($_POST['cf-turnstile-response'] ?? '');

// Validate
$errors = [];
if ($first_name === '')                          $errors[] = 'First name is required.';
if ($last_name  === '')                          $errors[] = 'Last name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if ($message === '')                             $errors[] = 'Message is required.';
if (strlen($message) > 5000)                    $errors[] = 'Message is too long.';
if (!in_array($inquiry_type, ['general', 'stay'], true)) $inquiry_type = 'general';

// Validate dates if provided
if ($checkin  !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin))  $checkin  = '';
if ($checkout !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) $checkout = '';
if ($checkin && $checkout && $checkout <= $checkin) $errors[] = 'Check-out must be after check-in.';
if (!turnstile_verify_token($ts_token, $ip)) $errors[] = 'Security verification failed. Please try again.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = db();
    stay_ensure_schema($pdo);

    $villa = $space = $unit = null;
    if ($villa_id > 0) {
        $stmt = $pdo->prepare('SELECT id, name FROM villas WHERE id = ?');
        $stmt->execute([$villa_id]);
        $villa = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$villa) $villa_id = 0;
    }
    if ($villa_space_id > 0) {
        $stmt = $pdo->prepare('SELECT id, villa_id, name FROM villa_spaces WHERE id = ?');
        $stmt->execute([$villa_space_id]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$space) {
            $villa_space_id = 0;
        } else {
            $villa_id = (int)$space['villa_id'];
        }
    }
    if ($bookable_unit_id > 0) {
        $stmt = $pdo->prepare('SELECT id, villa_id, villa_space_id, name FROM bookable_units WHERE id = ?');
        $stmt->execute([$bookable_unit_id]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$unit) {
            $bookable_unit_id = 0;
        } else {
            $villa_id = (int)$unit['villa_id'];
            $villa_space_id = (int)($unit['villa_space_id'] ?? 0);
        }
    }
    if (!$villa && $villa_id > 0) {
        $stmt = $pdo->prepare('SELECT id, name FROM villas WHERE id = ?');
        $stmt->execute([$villa_id]);
        $villa = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$space && $villa_space_id > 0) {
        $stmt = $pdo->prepare('SELECT id, villa_id, name FROM villa_spaces WHERE id = ?');
        $stmt->execute([$villa_space_id]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($subject_label === '') {
        $subject_label = $unit['name'] ?? $space['name'] ?? $villa['name'] ?? 'General Inquiry';
    }
    if ($pricing_label !== '' && stripos($subject_label, $pricing_label) === false) {
        $subject_label .= ' - ' . $pricing_label;
    }
    if ($bookable_unit_id > 0 || $villa_space_id > 0 || $villa_id > 0) {
        $inquiry_type = 'stay';
    }

    // Save inquiry
    $pdo->prepare('
        INSERT INTO inquiries (inquiry_type, villa_id, villa_space_id, bookable_unit_id, first_name, last_name, email, phone, checkin, checkout, guest_count, message, source_page, subject_label, status, ip_address)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'unread\',?)
    ')->execute([
        $inquiry_type,
        $villa_id ?: null,
        $villa_space_id ?: null,
        $bookable_unit_id ?: null,
        $first_name, $last_name, $email, $phone,
        $checkin ?: null, $checkout ?: null,
        $guest_count ?: null,
        $message,
        $source_page ?: null,
        $subject_label ?: null,
        $ip
    ]);
    $inquiry_id = $pdo->lastInsertId();

    // Load notification email from settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('smtp_notify_email','email','smtp_from_name')");
    $stmt->execute();
    $s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $notify_email = $s['smtp_notify_email'] ?? $s['email'] ?? '';
    $resort_name  = $s['smtp_from_name'] ?? 'We Trail (Pvt) Ltd';

    // 1. Admin notification email 
    $admin_mail_sent = true;
    if ($notify_email) {
        $stay_line = '';
        if ($checkin && $checkout) {
            $nights    = max(0, (int)((strtotime($checkout) - strtotime($checkin)) / 86400));
            $stay_line = '<p><strong>Stay:</strong> ' . date('d M Y', strtotime($checkin)) . ' to ' . date('d M Y', strtotime($checkout)) . " ({$nights} night" . ($nights !== 1 ? 's' : '') . ')</p>';
        }
        $selection_lines = [];
        if ($villa) $selection_lines[] = "<p><strong style='color:#C8961E'>Villa:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($villa['name']) . "</span></p>";
        if ($space) $selection_lines[] = "<p><strong style='color:#C8961E'>Space:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($space['name']) . "</span></p>";
        if ($unit) $selection_lines[] = "<p><strong style='color:#C8961E'>Unit:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($unit['name']) . "</span></p>";
        if ($pricing_label !== '') $selection_lines[] = "<p><strong style='color:#C8961E'>Package:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($pricing_label) . "</span></p>";
        if ($guest_count !== '') $selection_lines[] = "<p><strong style='color:#C8961E'>Guests:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($guest_count) . "</span></p>";

        $admin_html = "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto'>
            <div style='background:#111;padding:24px 28px;border-radius:10px 10px 0 0'>
                <h2 style='color:#C8961E;margin:0;font-size:1.2rem'>New Inquiry - {$resort_name}</h2>
                <p style='color:rgba(255,255,255,0.5);margin:4px 0 0;font-size:0.85rem'>Inquiry #{$inquiry_id}</p>
            </div>
            <div style='background:#1a1a1a;padding:24px 28px;border-radius:0 0 10px 10px;border:1px solid rgba(255,255,255,0.07)'>
                <p><strong style='color:#C8961E'>Guest:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($first_name . ' ' . $last_name) . "</span></p>
                <p><strong style='color:#C8961E'>Email:</strong> <a href='mailto:" . htmlspecialchars($email) . "' style='color:#f0ebe0'>" . htmlspecialchars($email) . "</a></p>
                " . ($phone ? "<p><strong style='color:#C8961E'>Phone:</strong> <span style='color:#f0ebe0'>" . htmlspecialchars($phone) . "</span></p>" : '') . "
                " . implode('', $selection_lines) . "
                {$stay_line}
                <hr style='border:none;border-top:1px solid rgba(255,255,255,0.07);margin:16px 0'>
                <p><strong style='color:#C8961E'>Message:</strong></p>
                <p style='color:rgba(240,235,224,0.75);line-height:1.6'>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr style='border:none;border-top:1px solid rgba(255,255,255,0.07);margin:16px 0'>
                <p style='text-align:center'>
                    <a href='mailto:" . htmlspecialchars($email) . "' style='display:inline-block;background:#C8961E;color:#111;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:700;font-size:0.85rem'>Reply via Email</a>
                </p>
                <p style='color:rgba(240,235,224,0.3);font-size:0.75rem;text-align:center;margin-top:16px'>Received from IP: {$ip}</p>
            </div>
        </div>";

        $admin_mail_sent = send_mail(
            $notify_email,
            $resort_name,
            "New Inquiry #{$inquiry_id} - " . $first_name . ' ' . $last_name,
            $admin_html
        );
    }

    // 2. Guest auto-reply 
    $guest_html = "
    <div style='font-family:sans-serif;max-width:600px;margin:0 auto'>
        <div style='background:#111;padding:24px 28px;border-radius:10px 10px 0 0;text-align:center'>
            <h2 style='color:#C8961E;margin:0;font-size:1.3rem'>{$resort_name}</h2>
            <p style='color:rgba(255,255,255,0.4);margin:4px 0 0;font-size:0.8rem'>Panama, Sri Lanka</p>
        </div>
        <div style='background:#1a1a1a;padding:32px 28px;border-radius:0 0 10px 10px;border:1px solid rgba(255,255,255,0.07)'>
            <p style='color:#f0ebe0;font-size:1rem'>Dear " . htmlspecialchars($first_name) . ",</p>
            <p style='color:rgba(240,235,224,0.75);line-height:1.7'>Thank you for reaching out to <strong style='color:#C8961E'>{$resort_name}</strong>. We have received your inquiry and one of our team members will get back to you within <strong style='color:#f0ebe0'>24 hours</strong>.</p>
            " . ($subject_label ? "<p style='color:rgba(240,235,224,0.72);line-height:1.6'><strong>Your selection:</strong> " . htmlspecialchars($subject_label) . "</p>" : "") . "
            <div style='background:rgba(200,150,30,0.08);border:1px solid rgba(200,150,30,0.2);border-radius:8px;padding:16px 20px;margin:20px 0'>
                <p style='color:#C8961E;font-weight:700;margin:0 0 8px'>Your Inquiry Summary</p>
                <p style='color:rgba(240,235,224,0.65);margin:0;line-height:1.6;font-size:0.9rem'>" . nl2br(htmlspecialchars($message)) . "</p>
            </div>
            <p style='color:rgba(240,235,224,0.75);line-height:1.7'>If you need to reach us urgently, please don't hesitate to contact us directly:</p>
            <p style='color:rgba(240,235,224,0.75)'>Phone: <a href='tel:+94777388810' style='color:#C8961E'>+94 777 388810</a></p>
            <p style='color:rgba(240,235,224,0.75)'>WhatsApp: <a href='https://wa.me/94777388810' style='color:#C8961E'>+94 777 388810</a></p>
            <hr style='border:none;border-top:1px solid rgba(255,255,255,0.07);margin:24px 0'>
            <p style='color:rgba(240,235,224,0.4);font-size:0.75rem;text-align:center'>We Trail (Pvt) Ltd - Panama, Sri Lanka</p>
        </div>
    </div>";

    $guest_mail_sent = send_mail(
        $email,
        $first_name . ' ' . $last_name,
        "We received your inquiry - {$resort_name}",
        $guest_html
    );

    if (!$admin_mail_sent || !$guest_mail_sent) {
        error_log(sprintf(
            '[send-inquiry] mail failure for inquiry #%s | admin_sent=%s | guest_sent=%s | notify_email=%s | guest_email=%s',
            (string)$inquiry_id,
            $admin_mail_sent ? 'yes' : 'no',
            $guest_mail_sent ? 'yes' : 'no',
            $notify_email !== '' ? $notify_email : '[empty]',
            $email
        ));

        http_response_code(502);
        if (!$admin_mail_sent && !$guest_mail_sent) {
            echo json_encode(['ok' => false, 'msg' => 'Your booking request was saved, but both emails could not be sent right now. Please contact us directly.']);
        } elseif (!$admin_mail_sent) {
            echo json_encode(['ok' => false, 'msg' => 'Your booking request was saved, but the admin notification email could not be sent right now. Please contact us directly.']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Your booking request was saved, but the confirmation email to your address could not be sent right now.']);
        }
        exit;
    }

    // Record rate-limit attempt
    $attempts[] = $now;
    $_SESSION[$key] = array_values($attempts);

    echo json_encode(['ok' => true, 'msg' => 'Your inquiry has been sent successfully.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Something went wrong. Please try again or contact us directly.']);
}
