<?php
// This file is shown directly - do not include header/footer (they'd cause a loop)
require_once 'config/db.php';
require_once 'config/theme.php';

http_response_code(503);
header('Retry-After: 3600');

$message    = 'We\'re currently performing scheduled maintenance. We\'ll be back shortly. Thank you for your patience.';
$wa_number  = '94773870850';
$contact_email = 'info@7artvilla.com';
$theme = site_theme_defaults();

try {
    $pdo  = db();
    $rows = $pdo->query("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('maintenance_message','whatsapp','email')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($rows['maintenance_message'])) $message       = $rows['maintenance_message'];
    if (!empty($rows['whatsapp']))            $wa_number     = preg_replace('/[^0-9]/', '', $rows['whatsapp']);
    if (!empty($rows['email']))               $contact_email = $rows['email'];
    $theme = site_theme_load($pdo);
} catch (Exception $e) {
    // DB unavailable - use defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance | 7 Art Villa</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?php echo htmlspecialchars($theme['theme_green']); ?>">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        <?php echo site_theme_css_vars($theme); ?>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Lato', sans-serif;
            background: var(--dark2);
            color: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        /* Background image */
        .bg {
            position: fixed; inset: 0; z-index: 0;
        }
        .bg img {
            width: 100%; height: 100%; object-fit: cover;
            filter: brightness(0.25) saturate(0.6);
        }

        .card {
            position: relative; z-index: 1;
            background: rgba(17,17,17,0.85);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 52px 48px;
            max-width: 560px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(12px);
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
        }

        .logo img {
            height: 90px;
            width: auto;
            margin-bottom: 28px;
        }

        .icon-wrap {
            width: 72px; height: 72px;
            background: rgba(var(--gold-rgb),0.12);
            border: 1px solid rgba(var(--gold-rgb),0.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            font-size: 1.6rem;
            color: var(--gold);
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--cream);
            margin-bottom: 14px;
            line-height: 1.2;
        }
        h1 span { color: var(--gold); }

        .divider {
            width: 48px; height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 18px auto;
        }

        p {
            font-size: 0.95rem;
            color: rgba(var(--cream-rgb),0.65);
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .contact-row {
            display: flex; flex-wrap: wrap; gap: 12px;
            justify-content: center;
            margin-top: 8px;
        }
        .contact-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .contact-btn:hover { opacity: 0.85; }
        .btn-whatsapp { background: #25D366; color: #fff; }
        .btn-email    { background: rgba(var(--gold-rgb),0.15); color: var(--gold); border: 1px solid rgba(var(--gold-rgb),0.35); }

        .footer-note {
            margin-top: 32px;
            font-size: 0.75rem;
            color: rgba(var(--cream-rgb),0.3);
        }
    </style>
</head>
<body>

    <div class="bg">
        <img src="assets/images/villa/resort.jpg" alt="">
    </div>

    <div class="card">
        <div class="logo">
            <img src="assets/images/logo.png" alt="7 Art Villa">
        </div>

        <div class="icon-wrap">
            <i class="fas fa-wrench"></i>
        </div>

        <h1>Back <span>Soon</span></h1>
        <div class="divider"></div>

        <p><?php echo nl2br(htmlspecialchars($message)); ?></p>

        <div class="contact-row">
            <a href="https://wa.me/<?php echo htmlspecialchars($wa_number); ?>" class="contact-btn btn-whatsapp" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp"></i> WhatsApp Us
            </a>
            <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-btn btn-email">
                <i class="fas fa-envelope"></i> Send Email
            </a>
        </div>

        <p class="footer-note">7 Art Villa &mdash; Ella, Sri Lanka</p>
    </div>

</body>
</html>
