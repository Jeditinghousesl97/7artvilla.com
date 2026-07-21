<?php
http_response_code(404);
require_once __DIR__ . '/config/theme.php';

// Pull contact info for the WhatsApp / contact link
$_404_wa = '94773870850';
$_404_theme = site_theme_defaults();
try {
    require_once __DIR__ . '/config/db.php';
    $__pdo = db();
    $__r   = $__pdo->prepare("SELECT setting_val FROM site_settings WHERE setting_key = 'whatsapp' LIMIT 1");
    $__r->execute();
    $v = $__r->fetchColumn();
    if ($v) $_404_wa = preg_replace('/[^0-9]/', '', $v);
    $_404_theme = site_theme_load($__pdo);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | 7 Art Villa</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/logo.png">
    <meta name="theme-color" content="<?php echo htmlspecialchars($_404_theme['theme_green']); ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style id="site-theme-vars"><?php echo site_theme_css_vars($_404_theme); ?></style>
    <style>
        .error-page {
            min-height: 100vh;
            background: var(--dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 24px;
            position: relative;
            overflow: hidden;
        }
        .error-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 0%,   rgba(var(--green-rgb),0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(var(--gold-rgb),0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        .error-content {
            position: relative;
            z-index: 1;
            max-width: 580px;
        }
        .error-logo {
            height: 90px;
            width: auto;
            margin-bottom: 48px;
            opacity: 0;
            animation: fadeInDown 0.7s ease 0.2s forwards;
        }
        .error-number {
            font-family: var(--font-heading);
            font-size: clamp(7rem, 20vw, 13rem);
            font-weight: 700;
            line-height: 1;
            background: linear-gradient(135deg, var(--gold) 0%, rgba(var(--gold-rgb),0.72) 50%, var(--gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            opacity: 0;
            animation: fadeInUp 0.8s cubic-bezier(0.4,0,0.2,1) 0.4s forwards;
        }
        .error-divider {
            width: 60px;
            height: 2px;
            background: var(--gold);
            margin: 0 auto 28px;
            opacity: 0;
            animation: lineGrow 0.6s ease 0.8s forwards;
        }
        .error-title {
            font-family: var(--font-heading);
            font-size: clamp(1.4rem, 4vw, 2rem);
            color: var(--white);
            font-weight: 600;
            margin-bottom: 16px;
            opacity: 0;
            animation: fadeInUp 0.7s ease 0.9s forwards;
        }
        .error-desc {
            font-size: 1rem;
            color: rgba(var(--cream-rgb),0.6);
            line-height: 1.8;
            margin-bottom: 40px;
            opacity: 0;
            animation: fadeInUp 0.7s ease 1.0s forwards;
        }
        .error-actions {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
            opacity: 0;
            animation: fadeInUp 0.7s ease 1.1s forwards;
        }
        .error-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 24px;
            justify-content: center;
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid rgba(var(--white-rgb),0.08);
            opacity: 0;
            animation: fadeInUp 0.7s ease 1.3s forwards;
        }
        .error-links a {
            font-size: 0.82rem;
            color: rgba(var(--cream-rgb),0.45);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            transition: color 0.2s;
            text-decoration: none;
        }
        .error-links a:hover { color: var(--gold); }
        .error-links a i { font-size: 0.7rem; margin-right: 5px; }

        @keyframes lineGrow {
            from { transform: scaleX(0); opacity: 0; }
            to   { transform: scaleX(1); opacity: 1; }
        }
        @media (max-width: 480px) {
            .error-actions .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <section class="error-page">
        <div class="error-content">

            <a href="index.php">
                <img src="assets/images/logo.png" alt="7 Art Villa" class="error-logo">
            </a>

            <div class="error-number">404</div>
            <div class="error-divider"></div>

            <h1 class="error-title">Page Not Found</h1>
            <p class="error-desc">
                The page you're looking for doesn't exist or may have been moved.<br>
                Let us guide you back to the retreat.
            </p>

            <div class="error-actions">
                <a href="index.php" class="btn btn-gold">
                    <i class="fas fa-home"></i> Back to Home
                </a>
                <a href="https://wa.me/<?php echo htmlspecialchars($_404_wa); ?>" target="_blank" rel="noopener" class="btn btn-outline">
                    <i class="fab fa-whatsapp"></i> WhatsApp Us
                </a>
            </div>

            <nav class="error-links">
                <a href="villa.php"><i class="fas fa-chevron-right"></i>The Villa</a>
                <a href="services.php"><i class="fas fa-chevron-right"></i>Services</a>
                <a href="tours.php"><i class="fas fa-chevron-right"></i>Tours</a>
                <a href="gallery.php"><i class="fas fa-chevron-right"></i>Gallery</a>
                <a href="index.php#contact"><i class="fas fa-chevron-right"></i>Inquire</a>
            </nav>

        </div>
    </section>

</body>
</html>
