<?php
require_once __DIR__ . '/../config/theme.php';

// Maintenance mode check 
if (!isset($__maintenance_checked)) {
    $__maintenance_checked = true;
    try {
        require_once __DIR__ . '/../config/db.php';
        $__pdo  = db();
        $__stmt = $__pdo->prepare("SELECT setting_val FROM site_settings WHERE setting_key = 'maintenance_mode'");
        $__stmt->execute();
        if ($__stmt->fetchColumn() === '1') {
            // Allow admin panel pages and maintenance.php itself through
            $__script   = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
            $__is_admin = strpos($__script, '/admin/') !== false;
            $__is_maint = basename($__script) === 'maintenance.php';
            if (!$__is_admin && !$__is_maint) {
                // Include the maintenance page directly - no redirect URL needed
                include __DIR__ . '/../maintenance.php';
                exit;
            }
        }
    } catch (Exception $e) {
        // DB unavailable - don't block the page
    }
}

$__site_url = 'https://wetrail.lk';
$__seo_logo_url = $__site_url . '/assets/images/logo.png';
$__ga_id = '';
$__fb_pixel_id = '';
$__site_desc = '';
$__seo_email = '';
$__seo_phone = '';
$__seo_maps_url = '';
$__seo_same_as = [];
$__sticky_header_bg = '#111111';
$__sticky_menu_item_color = '#FFFFFF';
$__sticky_header_button_bg = '#C8961E';
$__sticky_header_button_text = '#1A1A1A';

try {
    require_once __DIR__ . '/../config/db.php';
    if (!isset($__pdo)) $__pdo = db();

    $__tkeys = [
        'ga_id',
        'fb_pixel_id',
        'site_meta_description',
        'email',
        'phone',
        'maps_url',
        'facebook',
        'instagram',
        'youtube',
        'tiktok',
        'tripadvisor',
        'twitter',
        'sticky_header_bg',
        'sticky_menu_item_color',
        'sticky_header_button_bg',
        'sticky_header_button_text'
    ];
    $__tph = implode(',', array_fill(0, count($__tkeys), '?'));
    $__tst = $__pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($__tph)");
    $__tst->execute($__tkeys);
    $__ts = $__tst->fetchAll(PDO::FETCH_KEY_PAIR);

    $__ga_id = $__ts['ga_id'] ?? '';
    $__fb_pixel_id = $__ts['fb_pixel_id'] ?? '';
    $__site_desc = $__ts['site_meta_description'] ?? '';
    $__seo_email = trim((string)($__ts['email'] ?? ''));
    $__seo_phone = trim((string)($__ts['phone'] ?? ''));
    $__seo_maps_url = trim((string)($__ts['maps_url'] ?? ''));

    $__seo_same_as = array_values(array_filter([
        trim((string)($__ts['facebook'] ?? '')),
        trim((string)($__ts['instagram'] ?? '')),
        trim((string)($__ts['youtube'] ?? '')),
        trim((string)($__ts['tiktok'] ?? '')),
        trim((string)($__ts['tripadvisor'] ?? '')),
        trim((string)($__ts['twitter'] ?? ''))
    ]));

    $__sticky_header_bg = site_theme_normalize_hex($__ts['sticky_header_bg'] ?? $__sticky_header_bg, '#111111');
    $__sticky_menu_item_color = site_theme_normalize_hex($__ts['sticky_menu_item_color'] ?? $__sticky_menu_item_color, '#FFFFFF');
    $__sticky_header_button_bg = site_theme_normalize_hex($__ts['sticky_header_button_bg'] ?? $__sticky_header_button_bg, $__theme['theme_gold'] ?? '#C8961E');
    $__sticky_header_button_text = site_theme_normalize_hex($__ts['sticky_header_button_text'] ?? $__sticky_header_button_text, $__theme['theme_dark'] ?? '#1A1A1A');
} catch (Exception $e) {}

$__theme = site_theme_load($__pdo ?? null);

if (empty($page_description) && $__site_desc) {
    $page_description = $__site_desc;
}

$__request_path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$__canonical_fallback = $__site_url . preg_replace('#/index\.php$#', '/', $__request_path);
$__seo_title = $og_title ?? $page_title ?? 'We Trail (Pvt) Ltd | Panama, Sri Lanka';
$__seo_description = $og_description ?? $page_description ?? '';
$__seo_canonical = $canonical_url ?? ($og_url ?? $__canonical_fallback);
$__seo_image = $og_image ?? $__seo_logo_url;
$__seo_image_alt = $og_image_alt ?? 'We Trail (Pvt) Ltd logo';
$__seo_robots = $page_robots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
$__seo_address = [
    '@type' => 'PostalAddress',
    'addressLocality' => 'Panama',
    'addressRegion' => 'Ampara',
    'addressCountry' => 'LK'
];

$__seo_graph = [
    [
        '@type' => 'WebSite',
        '@id' => $__site_url . '/#website',
        'url' => $__site_url . '/',
        'name' => 'We Trail (Pvt) Ltd',
        'description' => $__site_desc ?: ($page_description ?? ''),
        'slogan' => 'Immerse Yourself in the Essence of Sri Lanka',
        'inLanguage' => 'en',
        'image' => $__seo_logo_url
    ],
    [
        '@type' => 'TravelAgency',
        '@id' => $__site_url . '/#business',
        'name' => 'We Trail (Pvt) Ltd',
        'url' => $__site_url . '/',
        'logo' => $__seo_logo_url,
        'image' => $__seo_logo_url,
        'address' => $__seo_address
    ]
];

if ($__seo_phone !== '') {
    $__seo_graph[1]['telephone'] = $__seo_phone;
}
if ($__seo_email !== '') {
    $__seo_graph[1]['email'] = $__seo_email;
}
if ($__seo_maps_url !== '') {
    $__seo_graph[1]['hasMap'] = $__seo_maps_url;
}
if (!empty($__seo_same_as)) {
    $__seo_graph[1]['sameAs'] = $__seo_same_as;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'We Trail (Pvt) Ltd | Panama, Sri Lanka'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description ?? ''); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars($__seo_robots); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($__seo_canonical); ?>">

    <!-- Open Graph -->
    <meta property="og:type"         content="website">
    <meta property="og:locale"       content="en_LK">
    <meta property="og:site_name"    content="We Trail (Pvt) Ltd">
    <meta property="og:title"        content="<?php echo htmlspecialchars($__seo_title); ?>">
    <meta property="og:description"  content="<?php echo htmlspecialchars($__seo_description); ?>">
    <meta property="og:url"          content="<?php echo htmlspecialchars($__seo_canonical); ?>">
    <meta property="og:image"        content="<?php echo htmlspecialchars($__seo_image); ?>">
    <meta property="og:image:alt"    content="<?php echo htmlspecialchars($__seo_image_alt); ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?php echo htmlspecialchars($__seo_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($__seo_description); ?>">
    <meta name="twitter:image"       content="<?php echo htmlspecialchars($__seo_image); ?>">
    <meta name="twitter:image:alt"   content="<?php echo htmlspecialchars($__seo_image_alt); ?>">

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/images/logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/logo.png">
    <meta name="theme-color" content="<?php echo htmlspecialchars($__theme['theme_green']); ?>">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($page_css)): ?>
    <link rel="stylesheet" href="assets/css/<?php echo htmlspecialchars($page_css); ?>">
    <?php endif; ?>
    <style id="site-theme-vars"><?php echo site_theme_css_vars($__theme); ?></style>
    <style id="sticky-header-vars">:root{--sticky-header-bg:<?php echo htmlspecialchars($__sticky_header_bg); ?>;--sticky-menu-item-color:<?php echo htmlspecialchars($__sticky_menu_item_color); ?>;--sticky-header-button-bg:<?php echo htmlspecialchars($__sticky_header_button_bg); ?>;--sticky-header-button-text:<?php echo htmlspecialchars($__sticky_header_button_text); ?>;}</style>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script type="application/ld+json"><?php echo json_encode(['@context' => 'https://schema.org', '@graph' => $__seo_graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

    <?php if ($__ga_id): ?>
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($__ga_id); ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo htmlspecialchars($__ga_id); ?>');</script>
    <?php endif; ?>

    <?php if ($__fb_pixel_id): ?>
    <!-- Meta Pixel -->
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?php echo htmlspecialchars($__fb_pixel_id); ?>');fbq('track','PageView');</script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($__fb_pixel_id); ?>&ev=PageView&noscript=1"></noscript>
    <?php endif; ?>
</head>
<body class="site-loading">

    <div class="site-preloader" id="sitePreloader" aria-hidden="true">
        <div class="site-preloader-mark">
            <span class="site-preloader-ring"></span>
            <span class="site-preloader-dot"></span>
        </div>
    </div>

    <div class="scroll-progress" id="scrollProgress"></div>
    <div class="nav-overlay"     id="navOverlay"></div>

    <!-- NAVBAR -->
    <?php
    // $nav_base = "" on homepage (uses hash links), "index.php" on sub-pages
    $base        = htmlspecialchars($nav_base ?? 'index.php');
    $is_homepage = ($nav_base === '');
    $home_href   = $is_homepage ? '#home' : $base;
    ?>
    <nav class="navbar<?php if (!empty($navbar_class)) echo ' ' . htmlspecialchars($navbar_class); ?>" id="navbar">
        <div class="nav-container">
            <a href="<?php echo $home_href; ?>" class="nav-logo">
                <img src="assets/images/logo.png" alt="We Trail (Pvt) Ltd Logo">
            </a>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="<?php echo $home_href; ?>"                                      class="nav-link">Home</a></li>
                <li><a href="<?php echo $base; ?>#about"                                     class="nav-link">About</a></li>
                <li><a href="<?php echo $is_homepage ? '#villa'    : 'villa.php'; ?>"        class="nav-link">Villas</a></li>
                <li><a href="<?php echo $is_homepage ? '#services' : 'services.php'; ?>"     class="nav-link">Services</a></li>
                <li><a href="<?php echo $is_homepage ? '#tours'    : 'tours.php'; ?>"        class="nav-link">Tours</a></li>
                <li><a href="<?php echo $is_homepage ? '#destinations' : 'destinations.php'; ?>" class="nav-link">Destinations</a></li>
                <li><a href="<?php echo $is_homepage ? '#gallery'  : 'gallery.php'; ?>"      class="nav-link">Gallery</a></li>
                <li><a href="<?php echo $base; ?>#contact"                                   class="nav-link nav-cta">Inquire Now</a></li>
            </ul>
        </div>
    </nav>
