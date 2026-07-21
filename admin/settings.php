<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/theme.php';
require_login();

$pdo = db();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Load all settings
$rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$theme_defaults = site_theme_defaults();
$theme = site_theme_resolve($rows);

/**
 * Best-effort MIME detection for uploaded files.
 * Falls back gracefully when the fileinfo extension is unavailable.
 */
function detect_uploaded_mime(string $tmp_path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmp_path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmp_path);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }

    if (function_exists('getimagesize')) {
        $info = @getimagesize($tmp_path);
        if (is_array($info) && !empty($info['mime'])) {
            return strtolower((string) $info['mime']);
        }
    }

    return '';
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid request. Please try again.'];
        header('Location: settings.php');
        exit;
    }

    $fields = [
        // Contact
        'phone', 'whatsapp', 'email',
        // Social
        'facebook', 'instagram', 'youtube', 'tiktok', 'tripadvisor', 'twitter',
        // Villa operations
        'checkin_time', 'checkout_time', 'min_stay', 'extra_guest_charge',
        'villa_capacity', 'villa_bedrooms', 'villa_pool',
        // Pricing
        'pricing_note',
        // Location
        'maps_url', 'maps_embed_url',
        // Maintenance
        'maintenance_message',
        // SMTP
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user',
        'smtp_from_name', 'smtp_from_email', 'smtp_notify_email',
        'turnstile_site_key', 'turnstile_secret_key',
        // Tracking
        'ga_id', 'fb_pixel_id',
        // SEO
        'site_meta_description',
        // About
        'about_label', 'about_heading', 'about_paragraph1', 'about_paragraph2',
        'about_stat1_number', 'about_stat1_label',
        'about_stat2_number', 'about_stat2_label',
        'about_stat3_number', 'about_stat3_label',
        // Sticky header colors
        'sticky_header_bg',
        'sticky_menu_item_color',
        'sticky_header_button_bg',
        'sticky_header_button_text',
    ];

    $theme_fields = site_theme_setting_keys();

    $stmt = $pdo->prepare('
        INSERT INTO site_settings (setting_key, setting_val)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)
    ');

    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $stmt->execute([$key, $val]);
        $rows[$key] = $val;
    }

    foreach ($theme_fields as $key) {
        $val = site_theme_normalize_hex($_POST[$key] ?? '', $theme_defaults[$key]);
        $stmt->execute([$key, $val]);
        $rows[$key] = $val;
    }
    $theme = site_theme_resolve($rows);

    // SMTP password - only update if a new value was entered
    $smtp_pass_new = trim($_POST['smtp_pass'] ?? '');
    if ($smtp_pass_new !== '') {
        $stmt->execute(['smtp_pass', $smtp_pass_new]);
        $rows['smtp_pass'] = $smtp_pass_new;
    }

    // Maintenance toggle - checkbox
    $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
    $stmt->execute(['maintenance_mode', $maintenance]);
    $rows['maintenance_mode'] = $maintenance;

    $turnstile_enabled = isset($_POST['turnstile_enabled']) ? '1' : '0';
    $stmt->execute(['turnstile_enabled', $turnstile_enabled]);
    $rows['turnstile_enabled'] = $turnstile_enabled;

    // Hero image upload
    if (!empty($_FILES['hero_image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $tmp_file = $_FILES['hero_image']['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp_file)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Hero image upload failed. Please try again.'];
            header('Location: settings.php');
            exit;
        }
        $mime = detect_uploaded_mime($tmp_file);

        if (!in_array($mime, $allowed_types, true)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Hero image: invalid type. JPG, PNG or WebP only.'];
            header('Location: settings.php');
            exit;
        } elseif ($_FILES['hero_image']['size'] > 25 * 1024 * 1024) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Hero image must be under 25MB.'];
            header('Location: settings.php');
            exit;
        } else {
            $ext      = strtolower(pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION));
            $filename = 'hero_' . time() . '.' . $ext;
            $dest_dir = '../assets/images/hero/';
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
            $dest = $dest_dir . $filename;

            if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $dest)) {
                // Delete old hero image if it exists and is in the hero folder
                $old = $rows['hero_image'] ?? '';
                if ($old && strpos($old, 'assets/images/hero/') === 0 && file_exists('../' . $old)) {
                    unlink('../' . $old);
                }
                $stmt->execute(['hero_image', 'assets/images/hero/' . $filename]);
                $rows['hero_image'] = 'assets/images/hero/' . $filename;
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Failed to upload hero image. Check folder permissions.'];
                header('Location: settings.php');
                exit;
            }
        }
    }

    // About images upload helper
    $about_img_fields = ['about_image_main', 'about_image_accent'];
    foreach ($about_img_fields as $field) {
        if (!empty($_FILES[$field]['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $tmp_file = $_FILES[$field]['tmp_name'] ?? '';
            if (!is_uploaded_file($tmp_file)) continue;
            $mime = detect_uploaded_mime($tmp_file);
            if (!in_array($mime, $allowed_types, true) || $_FILES[$field]['size'] > 25 * 1024 * 1024) continue;
            $ext      = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $filename = $field . '_' . time() . '.' . $ext;
            $dest_dir = '../assets/images/about/';
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest_dir . $filename)) {
                $old = $rows[$field] ?? '';
                if ($old && strpos($old, 'assets/images/about/') === 0 && file_exists('../' . $old)) unlink('../' . $old);
                $stmt->execute([$field, 'assets/images/about/' . $filename]);
                $rows[$field] = 'assets/images/about/' . $filename;
            }
        }
    }

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Settings saved successfully.'];
    header('Location: settings.php');
    exit;
}

$is_maintenance = ($rows['maintenance_mode'] ?? '0') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | We Trail Admin</title>
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

    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div>
                <div class="topbar-title">Settings</div>
                <div class="topbar-sub">Site-wide contact, social, and operational details</div>
            </div>
        </div>
        <div class="topbar-right">
            <?php if ($is_maintenance): ?>
            <span class="topbar-maintenance-badge">
                <i class="fas fa-wrench"></i> Maintenance ON
            </span>
            <?php endif; ?>
            <a href="../index.php" target="_blank" rel="noopener" class="topbar-btn topbar-btn-outline">
                <i class="fas fa-external-link-alt"></i> View Website
            </a>
            <button type="button" class="topbar-btn topbar-btn-gold" onclick="document.getElementById('settingsForm').requestSubmit()">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </header>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" data-auto-dismiss>
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
        <?php endif; ?>

        <?php if ($is_maintenance): ?>
        <div class="alert alert-warning" style="margin-bottom:20px">
            <i class="fas fa-wrench"></i>
            <div>
                <strong>Maintenance Mode is ON.</strong>
                Visitors are currently seeing the maintenance page instead of the website. Turn it off when you're done.
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION NAV -->
        <nav class="settings-section-nav" id="settingsSectionNav">
            <a href="#s-hero"        class="ssn-link active"><i class="fas fa-image"></i> Hero</a>
            <a href="#s-about"       class="ssn-link"><i class="fas fa-leaf"></i> About</a>
            <a href="#s-theme"       class="ssn-link"><i class="fas fa-palette"></i> Theme</a>
            <a href="#s-maintenance" class="ssn-link"><i class="fas fa-wrench"></i> Maintenance</a>
            <a href="#s-contact"     class="ssn-link"><i class="fas fa-phone-alt"></i> Contact</a>
            <a href="#s-social"      class="ssn-link"><i class="fas fa-share-alt"></i> Social</a>
            <a href="#s-villa"       class="ssn-link"><i class="fas fa-home"></i> Villa</a>
            <a href="#s-pricing"     class="ssn-link"><i class="fas fa-tag"></i> Pricing</a>
            <a href="#s-location"    class="ssn-link"><i class="fas fa-map-marker-alt"></i> Location</a>
            <a href="#s-seo"         class="ssn-link"><i class="fas fa-search"></i> SEO</a>
            <a href="#s-tracking"    class="ssn-link"><i class="fas fa-chart-line"></i> Tracking</a>
            <a href="#s-smtp"        class="ssn-link"><i class="fas fa-paper-plane"></i> Email</a>
            <a href="#s-security"    class="ssn-link"><i class="fas fa-shield-alt"></i> Security</a>
        </nav>

        <form method="POST" enctype="multipart/form-data" id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

            <div class="settings-layout">

                <!-- HERO IMAGE -->
                <div class="form-card" id="s-hero">
                    <div class="form-card-title">
                        <i class="fas fa-image" style="color:var(--gold);margin-right:8px"></i>
                        Homepage Hero Image
                    </div>
                    <div class="hero-image-setting" style="margin-top:20px">
                        <!-- Current image preview -->
                        <div class="hero-img-preview-wrap">
                            <?php $heroImg = $rows['hero_image'] ?? ''; ?>
                            <?php if ($heroImg && file_exists('../' . $heroImg)): ?>
                            <img id="heroPreview" src="../<?php echo htmlspecialchars($heroImg); ?>" alt="Current hero image">
                            <div class="hero-img-overlay">
                                <span><i class="fas fa-check-circle"></i> Image set</span>
                            </div>
                            <?php else: ?>
                            <img id="heroPreview" src="" alt="" style="display:none">
                            <div class="hero-img-placeholder" id="heroPlaceholder">
                                <i class="fas fa-image"></i>
                                <span>No hero image uploaded</span>
                                <small>The hero section will show a dark background until an image is set.</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Upload -->
                        <div class="form-group" style="margin-top:14px">
                            <label>Upload New Hero Image</label>
                            <input type="file" name="hero_image" id="heroImageInput"
                                   accept="image/jpeg,image/png,image/webp">
                            <span class="form-hint">JPG, PNG or WebP - max 25MB Size!. Recommended size: 1920Ãƒ - 1080px or wider. Leave empty to keep current image.</span>
                        </div>
                    </div>
                </div>

                <!-- ABOUT -->
                <div class="form-card" id="s-about">
                    <div class="form-card-title">
                        <i class="fas fa-leaf" style="color:var(--gold);margin-right:8px"></i>
                        About Section
                    </div>
                    <p class="settings-section-note" style="margin-top:12px">Controls the "About the Resort" section on the homepage.</p>

                    <div class="form-grid-2" style="margin-top:20px">
                        <div class="form-group">
                            <label>Section Label</label>
                            <input type="text" name="about_label"
                                   value="<?php echo htmlspecialchars($rows['about_label'] ?? 'About the Resort'); ?>"
                                   placeholder="About the Resort">
                            <span class="form-hint">Small uppercase text above the heading.</span>
                        </div>
                        <div class="form-group">
                            <label>Heading</label>
                            <input type="text" name="about_heading"
                                   value="<?php echo htmlspecialchars($rows['about_heading'] ?? 'A Hidden Sanctuary in the Hills'); ?>"
                                   placeholder="A Hidden Sanctuary in the Hills">
                        </div>
                        <div class="form-group form-full">
                            <label>Paragraph 1</label>
                            <textarea name="about_paragraph1" rows="3"
                                      placeholder="Nestled in the lush landscapes of Panama..."><?php echo htmlspecialchars($rows['about_paragraph1'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group form-full">
                            <label>Paragraph 2</label>
                            <textarea name="about_paragraph2" rows="3"
                                      placeholder="Designed for couples and families..."><?php echo htmlspecialchars($rows['about_paragraph2'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div style="margin-top:8px">
                        <label style="font-size:0.82rem;color:var(--text-muted);display:block;margin-bottom:12px">Stats Bar (3 figures shown below the text)</label>
                        <div class="about-stats-edit-grid">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="about-stat-edit-card">
                                <div class="form-group">
                                    <label>Stat <?php echo $i; ?> - Number / Value</label>
                                    <input type="text" name="about_stat<?php echo $i; ?>_number"
                                           value="<?php echo htmlspecialchars($rows["about_stat{$i}_number"] ?? ['100%','1','24/7'][$i-1]); ?>"
                                           placeholder="<?php echo ['100%','1','24/7'][$i-1]; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Stat <?php echo $i; ?> - Label</label>
                                    <input type="text" name="about_stat<?php echo $i; ?>_label"
                                           value="<?php echo htmlspecialchars($rows["about_stat{$i}_label"] ?? ['Private','Exclusive Villa','Butler Service'][$i-1]); ?>"
                                           placeholder="<?php echo ['Private','Exclusive Villa','Butler Service'][$i-1]; ?>">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Images -->
                    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
                        <label style="font-size:0.82rem;color:var(--text-muted);display:block;margin-bottom:16px">About Section Images</label>
                        <div class="about-images-grid">

                            <!-- Main image -->
                            <div>
                                <label style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:8px;display:block">Main Image (large)</label>
                                <div class="img-preview-wrap" style="margin-bottom:10px;height:140px">
                                    <?php $aboutMain = $rows['about_image_main'] ?? ''; ?>
                                    <?php if ($aboutMain && file_exists('../' . $aboutMain)): ?>
                                    <img id="aboutMainPreview" src="../<?php echo htmlspecialchars($aboutMain); ?>" alt="" class="img-preview" style="height:140px">
                                    <?php else: ?>
                                    <img id="aboutMainPreview" src="" alt="" class="img-preview" style="display:none;height:140px">
                                    <div class="img-placeholder-box" id="aboutMainPlaceholder" style="height:140px">
                                        <i class="fas fa-image"></i><span>No image</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="about_image_main" id="aboutMainInput"
                                       accept="image/jpeg,image/png,image/webp">
                                <span class="form-hint">Recommended: landscape, 800Ãƒ - 600px+</span>
                            </div>

                            <!-- Accent image -->
                            <div>
                                <label style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:8px;display:block">Accent Image (small overlay)</label>
                                <div class="img-preview-wrap" style="margin-bottom:10px;height:140px">
                                    <?php $aboutAccent = $rows['about_image_accent'] ?? ''; ?>
                                    <?php if ($aboutAccent && file_exists('../' . $aboutAccent)): ?>
                                    <img id="aboutAccentPreview" src="../<?php echo htmlspecialchars($aboutAccent); ?>" alt="" class="img-preview" style="height:140px">
                                    <?php else: ?>
                                    <img id="aboutAccentPreview" src="" alt="" class="img-preview" style="display:none;height:140px">
                                    <div class="img-placeholder-box" id="aboutAccentPlaceholder" style="height:140px">
                                        <i class="fas fa-image"></i><span>No image</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="about_image_accent" id="aboutAccentInput"
                                       accept="image/jpeg,image/png,image/webp">
                                <span class="form-hint">Recommended: square or portrait, 400Ãƒ - 400px+</span>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- THEME COLORS -->
                <div class="form-card" id="s-theme">
                    <div class="form-card-title">
                        <i class="fas fa-palette" style="color:var(--gold);margin-right:8px"></i>
                        Theme Colors
                    </div>
                    <p class="settings-section-note" style="margin-top:12px">These are the standard brand colors used across the reusable website palette. Some special gradients, overlays, and social colors remain section-specific on purpose for easier management.</p>

                    <div class="theme-preview-bar"
                         style="--green:<?php echo htmlspecialchars($theme['theme_green']); ?>;--gold:<?php echo htmlspecialchars($theme['theme_gold']); ?>;--brown:<?php echo htmlspecialchars($theme['theme_brown']); ?>;--dark3:<?php echo htmlspecialchars($theme['theme_dark_soft']); ?>;--cream:<?php echo htmlspecialchars($theme['theme_cream']); ?>;--text:<?php echo htmlspecialchars($theme['theme_text']); ?>;">
                        <span class="theme-preview-chip theme-preview-chip-green">Green</span>
                        <span class="theme-preview-chip theme-preview-chip-gold">Gold</span>
                        <span class="theme-preview-chip theme-preview-chip-brown">Brown</span>
                        <span class="theme-preview-chip theme-preview-chip-dark">Dark</span>
                        <span class="theme-preview-chip theme-preview-chip-cream">Cream</span>
                    </div>

                    <div class="theme-colors-grid">
                        <?php
                        $theme_labels = [
                            'theme_green' => 'Primary Green',
                            'theme_green_dark' => 'Green Dark',
                            'theme_gold' => 'Primary Gold',
                            'theme_gold_dark' => 'Gold Dark',
                            'theme_brown' => 'Primary Brown',
                            'theme_brown_dark' => 'Brown Dark',
                            'theme_dark' => 'Dark Surface',
                            'theme_dark_alt' => 'Dark Surface Alt',
                            'theme_dark_soft' => 'Dark Surface Soft',
                            'theme_cream' => 'Cream Surface',
                            'theme_cream_alt' => 'Cream Surface Alt',
                            'theme_text' => 'Text Primary',
                            'theme_text_light' => 'Text Secondary',
                            'theme_text_muted' => 'Text Muted',
                        ];
                        foreach ($theme_labels as $key => $label):
                        ?>
                        <div class="theme-color-card">
                            <label for="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></label>
                            <div class="theme-color-input-row">
                                <input type="color"
                                       id="<?php echo $key; ?>_picker"
                                       value="<?php echo htmlspecialchars($theme[$key]); ?>"
                                       oninput="document.getElementById('<?php echo $key; ?>').value = this.value.toUpperCase()">
                                <input type="text"
                                       id="<?php echo $key; ?>"
                                       name="<?php echo $key; ?>"
                                       value="<?php echo htmlspecialchars($theme[$key]); ?>"
                                       pattern="^#([A-Fa-f0-9]{6})$"
                                       maxlength="7"
                                       placeholder="<?php echo htmlspecialchars($theme_defaults[$key]); ?>"
                                       oninput="this.value = this.value.toUpperCase(); var p = document.getElementById('<?php echo $key; ?>_picker'); if (/^#([A-F0-9]{6})$/.test(this.value)) p.value = this.value;">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="theme-subsection">
                        <div class="theme-subsection-title">Sticky Header Colors</div>
                        <p class="settings-section-note" style="margin-top:10px">These colors apply only when the header becomes sticky on scroll. Normal header colors are not affected.</p>

                        <div class="theme-colors-grid">
                            <div class="theme-color-card">
                                <label for="sticky_header_bg">Sticky Header Background</label>
                                <div class="theme-color-input-row">
                                    <input type="color"
                                           id="sticky_header_bg_picker"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_header_bg'] ?? '#111111', '#111111')); ?>"
                                           oninput="document.getElementById('sticky_header_bg').value = this.value.toUpperCase()">
                                    <input type="text"
                                           id="sticky_header_bg"
                                           name="sticky_header_bg"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_header_bg'] ?? '#111111', '#111111')); ?>"
                                           pattern="^#([A-Fa-f0-9]{6})$"
                                           maxlength="7"
                                           placeholder="#111111"
                                           oninput="this.value = this.value.toUpperCase(); var p = document.getElementById('sticky_header_bg_picker'); if (/^#([A-F0-9]{6})$/.test(this.value)) p.value = this.value;">
                                </div>
                            </div>

                            <div class="theme-color-card">
                                <label for="sticky_menu_item_color">Sticky Menu Item Color</label>
                                <div class="theme-color-input-row">
                                    <input type="color"
                                           id="sticky_menu_item_color_picker"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_menu_item_color'] ?? '#FFFFFF', '#FFFFFF')); ?>"
                                           oninput="document.getElementById('sticky_menu_item_color').value = this.value.toUpperCase()">
                                    <input type="text"
                                           id="sticky_menu_item_color"
                                           name="sticky_menu_item_color"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_menu_item_color'] ?? '#FFFFFF', '#FFFFFF')); ?>"
                                           pattern="^#([A-Fa-f0-9]{6})$"
                                           maxlength="7"
                                           placeholder="#FFFFFF"
                                           oninput="this.value = this.value.toUpperCase(); var p = document.getElementById('sticky_menu_item_color_picker'); if (/^#([A-F0-9]{6})$/.test(this.value)) p.value = this.value;">
                                </div>
                            </div>

                            <div class="theme-color-card">
                                <label for="sticky_header_button_bg">Sticky Button Background</label>
                                <div class="theme-color-input-row">
                                    <input type="color"
                                           id="sticky_header_button_bg_picker"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_header_button_bg'] ?? '#C8961E', '#C8961E')); ?>"
                                           oninput="document.getElementById('sticky_header_button_bg').value = this.value.toUpperCase()">
                                    <input type="text"
                                           id="sticky_header_button_bg"
                                           name="sticky_header_button_bg"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_header_button_bg'] ?? '#C8961E', '#C8961E')); ?>"
                                           pattern="^#([A-Fa-f0-9]{6})$"
                                           maxlength="7"
                                           placeholder="#C8961E"
                                           oninput="this.value = this.value.toUpperCase(); var p = document.getElementById('sticky_header_button_bg_picker'); if (/^#([A-F0-9]{6})$/.test(this.value)) p.value = this.value;">
                                </div>
                            </div>

                            <div class="theme-color-card">
                                <label for="sticky_header_button_text">Sticky Button Text</label>
                                <div class="theme-color-input-row">
                                    <input type="color"
                                           id="sticky_header_button_text_picker"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_header_button_text'] ?? '#1A1A1A', '#1A1A1A')); ?>"
                                           oninput="document.getElementById('sticky_header_button_text').value = this.value.toUpperCase()">
                                    <input type="text"
                                           id="sticky_header_button_text"
                                           name="sticky_header_button_text"
                                           value="<?php echo htmlspecialchars(site_theme_normalize_hex($rows['sticky_header_button_text'] ?? '#1A1A1A', '#1A1A1A')); ?>"
                                           pattern="^#([A-Fa-f0-9]{6})$"
                                           maxlength="7"
                                           placeholder="#1A1A1A"
                                           oninput="this.value = this.value.toUpperCase(); var p = document.getElementById('sticky_header_button_text_picker'); if (/^#([A-F0-9]{6})$/.test(this.value)) p.value = this.value;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAINTENANCE -->
                <div class="form-card settings-card-maintenance <?php echo $is_maintenance ? 'maintenance-active' : ''; ?>" id="s-maintenance">
                    <div class="form-card-title">
                        <i class="fas fa-wrench" style="color:var(--gold);margin-right:8px"></i>
                        Maintenance Mode
                    </div>
                    <div style="margin-top:20px;display:flex;flex-direction:column;gap:16px">
                        <label class="checkbox-row maintenance-toggle-row">
                            <input type="checkbox" name="maintenance_mode" value="1" <?php echo $is_maintenance ? 'checked' : ''; ?> id="maintenanceToggle">
                            <span>
                                <strong>Enable Maintenance Mode</strong>
                                <small>When ON, visitors see the maintenance page instead of the website. Admins are not affected.</small>
                            </span>
                        </label>
                        <div class="form-group">
                            <label>Maintenance Message</label>
                            <textarea name="maintenance_message" rows="3"
                                      placeholder="We're currently performing scheduled maintenance. We'll be back shortly. Thank you for your patience."><?php echo htmlspecialchars($rows['maintenance_message'] ?? ''); ?></textarea>
                            <span class="form-hint">Displayed to visitors on the maintenance page.</span>
                        </div>
                    </div>
                </div>

                <!-- CONTACT INFORMATION -->
                <div class="form-card" id="s-contact">
                    <div class="form-card-title">
                        <i class="fas fa-phone-alt" style="color:var(--gold);margin-right:8px"></i>
                        Contact Information
                    </div>
                    <div class="form-grid-2" style="margin-top:20px">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <div class="input-with-icon">
                                <i class="fas fa-phone"></i>
                                <input type="text" name="phone"
                                       value="<?php echo htmlspecialchars($rows['phone'] ?? ''); ?>"
                                       placeholder="+94777388810">
                            </div>
                            <span class="form-hint">Displayed in footer and contact section. Include country code.</span>
                        </div>
                        <div class="form-group">
                            <label>WhatsApp Number</label>
                            <div class="input-with-icon">
                                <i class="fab fa-whatsapp"></i>
                                <input type="text" name="whatsapp"
                                       value="<?php echo htmlspecialchars($rows['whatsapp'] ?? ''); ?>"
                                       placeholder="94777388810">
                            </div>
                            <span class="form-hint">Digits only, no spaces (e.g. 94777388810).</span>
                        </div>
                        <div class="form-group form-full">
                            <label>Email Address</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email"
                                       value="<?php echo htmlspecialchars($rows['email'] ?? ''); ?>"
                                       placeholder="info@wetrail.lk">
                            </div>
                            <span class="form-hint">Displayed in footer and used in inquiry reply links.</span>
                        </div>
                    </div>
                </div>

                <!-- SOCIAL MEDIA -->
                <div class="form-card" id="s-social">
                    <div class="form-card-title">
                        <i class="fas fa-share-alt" style="color:var(--gold);margin-right:8px"></i>
                        Social Media
                    </div>
                    <p class="settings-section-note">Leave any field empty to hide that icon on the website automatically.</p>
                    <div class="form-grid-2" style="margin-top:16px">
                        <div class="form-group">
                            <label><i class="fab fa-facebook" style="color:#1877F2;margin-right:6px"></i>Facebook</label>
                            <div class="input-with-icon">
                                <i class="fab fa-facebook"></i>
                                <input type="url" name="facebook"
                                       value="<?php echo htmlspecialchars($rows['facebook'] ?? ''); ?>"
                                       placeholder="https://www.facebook.com/...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-instagram" style="color:#E4405F;margin-right:6px"></i>Instagram</label>
                            <div class="input-with-icon">
                                <i class="fab fa-instagram"></i>
                                <input type="url" name="instagram"
                                       value="<?php echo htmlspecialchars($rows['instagram'] ?? ''); ?>"
                                       placeholder="https://www.instagram.com/...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-youtube" style="color:#FF0000;margin-right:6px"></i>YouTube</label>
                            <div class="input-with-icon">
                                <i class="fab fa-youtube"></i>
                                <input type="url" name="youtube"
                                       value="<?php echo htmlspecialchars($rows['youtube'] ?? ''); ?>"
                                       placeholder="https://www.youtube.com/@...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-tiktok" style="color:var(--text-primary);margin-right:6px"></i>TikTok</label>
                            <div class="input-with-icon">
                                <i class="fab fa-tiktok"></i>
                                <input type="url" name="tiktok"
                                       value="<?php echo htmlspecialchars($rows['tiktok'] ?? ''); ?>"
                                       placeholder="https://www.tiktok.com/@...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-tripadvisor" style="color:#34E0A1;margin-right:6px"></i>TripAdvisor</label>
                            <div class="input-with-icon">
                                <i class="fab fa-tripadvisor"></i>
                                <input type="url" name="tripadvisor"
                                       value="<?php echo htmlspecialchars($rows['tripadvisor'] ?? ''); ?>"
                                       placeholder="https://www.tripadvisor.com/...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-x-twitter" style="color:var(--text-primary);margin-right:6px"></i>Twitter / X</label>
                            <div class="input-with-icon">
                                <i class="fab fa-x-twitter"></i>
                                <input type="url" name="twitter"
                                       value="<?php echo htmlspecialchars($rows['twitter'] ?? ''); ?>"
                                       placeholder="https://x.com/...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VILLA OPERATIONS -->
                <div class="form-card" id="s-villa">
                    <div class="form-card-title">
                        <i class="fas fa-home" style="color:var(--gold);margin-right:8px"></i>
                        Villa Operations
                    </div>
                    <div class="form-grid-2" style="margin-top:20px">
                        <div class="form-group">
                            <label>Check-In Time</label>
                            <div class="input-with-icon">
                                <i class="fas fa-sign-in-alt"></i>
                                <input type="text" name="checkin_time"
                                       value="<?php echo htmlspecialchars($rows['checkin_time'] ?? ''); ?>"
                                       placeholder="2:00 PM">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Check-Out Time</label>
                            <div class="input-with-icon">
                                <i class="fas fa-sign-out-alt"></i>
                                <input type="text" name="checkout_time"
                                       value="<?php echo htmlspecialchars($rows['checkout_time'] ?? ''); ?>"
                                       placeholder="11:00 AM">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Minimum Stay</label>
                            <div class="input-with-icon">
                                <i class="fas fa-moon"></i>
                                <input type="text" name="min_stay"
                                       value="<?php echo htmlspecialchars($rows['min_stay'] ?? ''); ?>"
                                       placeholder="1 Night">
                            </div>
                            <span class="form-hint">Displayed in the villa quick info bar.</span>
                        </div>
                        <div class="form-group">
                            <label>Extra Guest Charge</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-plus"></i>
                                <input type="text" name="extra_guest_charge"
                                       value="<?php echo htmlspecialchars($rows['extra_guest_charge'] ?? ''); ?>"
                                       placeholder="LKR 5,000 per person per night">
                            </div>
                            <span class="form-hint">Shown in the pricing disclaimer note.</span>
                        </div>
                        <div class="form-group">
                            <label>Capacity</label>
                            <div class="input-with-icon">
                                <i class="fas fa-users"></i>
                                <input type="text" name="villa_capacity"
                                       value="<?php echo htmlspecialchars($rows['villa_capacity'] ?? ''); ?>"
                                       placeholder="2 - 4 Guests">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Bedrooms</label>
                            <div class="input-with-icon">
                                <i class="fas fa-bed"></i>
                                <input type="text" name="villa_bedrooms"
                                       value="<?php echo htmlspecialchars($rows['villa_bedrooms'] ?? ''); ?>"
                                       placeholder="2 Bedrooms">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Pool</label>
                            <div class="input-with-icon">
                                <i class="fas fa-swimming-pool"></i>
                                <input type="text" name="villa_pool"
                                       value="<?php echo htmlspecialchars($rows['villa_pool'] ?? ''); ?>"
                                       placeholder="Private">
                            </div>
                            <span class="form-hint">All 6 fields display in the quick info bar on the villa page.</span>
                        </div>
                    </div>
                </div>

                <!-- PRICING NOTE -->
                <div class="form-card" id="s-pricing">
                    <div class="form-card-title">
                        <i class="fas fa-tag" style="color:var(--gold);margin-right:8px"></i>
                        Pricing Disclaimer
                    </div>
                    <div style="margin-top:20px">
                        <div class="form-group">
                            <label>Pricing Note</label>
                            <textarea name="pricing_note" rows="3"
                                      placeholder="All rates are subject to change. Prices are per villa per night and inclusive of applicable taxes. Additional guests (beyond 2) charged at LKR 5,000 per person per night. Contact us for long-stay discounts."><?php echo htmlspecialchars($rows['pricing_note'] ?? ''); ?></textarea>
                            <span class="form-hint">Small disclaimer shown below the pricing cards on the villa page.</span>
                        </div>
                    </div>
                </div>

                <!-- LOCATION -->
                <div class="form-card" id="s-location">
                    <div class="form-card-title">
                        <i class="fas fa-map-marker-alt" style="color:var(--gold);margin-right:8px"></i>
                        Location
                    </div>
                    <div style="margin-top:20px">
                        <div class="form-group">
                            <label>Google Maps URL</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marked-alt"></i>
                                <input type="url" name="maps_url"
                                       value="<?php echo htmlspecialchars($rows['maps_url'] ?? ''); ?>"
                                       placeholder="https://maps.app.goo.gl/...">
                            </div>
                            <span class="form-hint">Used for "Get Directions" links in the footer and contact section.</span>
                        </div>
                        <div class="form-group" style="margin-top:16px">
                            <label>Google Maps Embed URL</label>
                            <input type="url" name="maps_embed_url"
                                   value="<?php echo htmlspecialchars($rows['maps_embed_url'] ?? ''); ?>"
                                   placeholder="https://www.google.com/maps/embed?pb=...">
                            <span class="form-hint">Paste the <strong>src</strong> URL from Google Maps  to  Share  to  Embed a map. This shows a live map in the contact section.</span>
                        </div>
                    </div>
                </div>

                <!-- SEO -->
                <div class="form-card" id="s-seo">
                    <div class="form-card-title">
                        <i class="fas fa-search" style="color:var(--gold);margin-right:8px"></i>
                        SEO - Search Engine
                    </div>
                    <div style="margin-top:20px">
                        <div class="form-group">
                            <label>Homepage Meta Description</label>
                            <textarea name="site_meta_description" rows="3"
                                      placeholder="A fully private modern A-Frame eco resort near Panama Beach, Panama. Designed for couples and families seeking privacy, nature, and luxury."><?php echo htmlspecialchars($rows['site_meta_description'] ?? ''); ?></textarea>
                            <span class="form-hint">Shown in Google search results. Aim for 150-160 characters. <span id="metaCount" style="color:var(--gold)"></span></span>
                        </div>
                    </div>
                </div>

                <!-- TRACKING -->
                <div class="form-card" id="s-tracking">
                    <div class="form-card-title">
                        <i class="fas fa-chart-line" style="color:var(--gold);margin-right:8px"></i>
                        Analytics &amp; Tracking
                    </div>
                    <p class="settings-section-note" style="margin-top:12px">Leave empty to disable. Scripts are only injected when an ID is set.</p>
                    <div class="form-grid-2" style="margin-top:16px">
                        <div class="form-group">
                            <label><i class="fab fa-google" style="margin-right:6px;color:#4285F4"></i>Google Analytics 4 ID</label>
                            <div class="input-with-icon">
                                <i class="fas fa-chart-bar"></i>
                                <input type="text" name="ga_id"
                                       value="<?php echo htmlspecialchars($rows['ga_id'] ?? ''); ?>"
                                       placeholder="G-XXXXXXXXXX">
                            </div>
                            <span class="form-hint">Found in GA4  to  Admin  to  Data Streams.</span>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-facebook" style="margin-right:6px;color:#1877F2"></i>Facebook Pixel ID</label>
                            <div class="input-with-icon">
                                <i class="fas fa-ad"></i>
                                <input type="text" name="fb_pixel_id"
                                       value="<?php echo htmlspecialchars($rows['fb_pixel_id'] ?? ''); ?>"
                                       placeholder="123456789012345">
                            </div>
                            <span class="form-hint">Found in Meta Events Manager.</span>
                        </div>
                    </div>
                </div>

                <!-- SMTP -->
                <div class="form-card" id="s-smtp">
                    <div class="form-card-title">
                        <i class="fas fa-paper-plane" style="color:var(--gold);margin-right:8px"></i>
                        Email (SMTP)
                    </div>
                    <p class="settings-section-note" style="margin-top:12px">Used to send inquiry notifications and guest auto-replies. Leave SMTP Host empty to use the server's built-in mail.</p>
                    <div class="form-grid-2" style="margin-top:16px">
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <div class="input-with-icon">
                                <i class="fas fa-server"></i>
                                <input type="text" name="smtp_host"
                                       value="<?php echo htmlspecialchars($rows['smtp_host'] ?? ''); ?>"
                                       placeholder="mail.yourdomain.com or smtp.gmail.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>SMTP Port</label>
                            <div class="input-with-icon">
                                <i class="fas fa-plug"></i>
                                <input type="number" name="smtp_port"
                                       value="<?php echo htmlspecialchars($rows['smtp_port'] ?? '587'); ?>"
                                       placeholder="587">
                            </div>
                            <span class="form-hint">587 (TLS) - 465 (SSL) - 25 (plain)</span>
                        </div>
                        <div class="form-group">
                            <label>Encryption</label>
                            <select name="smtp_encryption">
                                <?php foreach (['tls'=>'TLS (STARTTLS - port 587)', 'ssl'=>'SSL (port 465)', 'none'=>'None (port 25)'] as $v => $l): ?>
                                <option value="<?php echo $v; ?>" <?php echo ($rows['smtp_encryption'] ?? 'tls') === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>SMTP Username</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" name="smtp_user" autocomplete="off"
                                       value="<?php echo htmlspecialchars($rows['smtp_user'] ?? ''); ?>"
                                       placeholder="your@email.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>SMTP Password</label>
                            <div class="input-with-icon">
                                <i class="fas fa-key"></i>
                                <input type="password" name="smtp_pass" autocomplete="new-password"
                                       placeholder="<?php echo !empty($rows['smtp_pass']) ? 'Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢Ã¢â‚¬Â¢  (leave blank to keep)' : 'Enter password'; ?>">
                            </div>
                            <span class="form-hint">Leave blank to keep the existing password.</span>
                        </div>
                        <div class="form-group">
                            <label>From Name</label>
                            <div class="input-with-icon">
                                <i class="fas fa-signature"></i>
                                <input type="text" name="smtp_from_name"
                                       value="<?php echo htmlspecialchars($rows['smtp_from_name'] ?? 'We Trail (Pvt) Ltd'); ?>"
                                       placeholder="We Trail (Pvt) Ltd">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>From Email</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="smtp_from_email"
                                       value="<?php echo htmlspecialchars($rows['smtp_from_email'] ?? ''); ?>"
                                       placeholder="noreply@yourdomain.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Admin Notification Email</label>
                            <div class="input-with-icon">
                                <i class="fas fa-bell"></i>
                                <input type="email" name="smtp_notify_email"
                                       value="<?php echo htmlspecialchars($rows['smtp_notify_email'] ?? ''); ?>"
                                       placeholder="admin@yourdomain.com">
                            </div>
                            <span class="form-hint">New inquiry alerts are sent here. Defaults to Contact Email if blank.</span>
                        </div>
                    </div>

                    <!-- Test Email -->
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                        <button type="button" class="btn-admin btn-outline" id="testEmailBtn">
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </button>
                        <span id="testEmailResult" style="font-size:0.82rem;margin-left:12px"></span>
                    </div>
                </div>

                <!-- SECURITY -->
                <div class="form-card" id="s-security">
                    <div class="form-card-title">
                        <i class="fas fa-shield-alt" style="color:var(--gold);margin-right:8px"></i>
                        Cloudflare Turnstile
                    </div>
                    <p class="settings-section-note" style="margin-top:12px">Enable bot protection for admin login and inquiry forms.</p>
                    <div class="form-grid-2" style="margin-top:16px">
                        <div class="form-group">
                            <label>Enable Turnstile</label>
                            <label class="checkbox-row" style="padding-top:8px">
                                <input type="checkbox" name="turnstile_enabled" value="1" <?php echo (($rows['turnstile_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                <span>
                                    <strong>Turnstile Protection</strong>
                                    <small>Admin Login + Contact Inquiry + Tour Inquiry</small>
                                </span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>Site Key</label>
                            <div class="input-with-icon">
                                <i class="fas fa-key"></i>
                                <input type="text" name="turnstile_site_key"
                                       value="<?php echo htmlspecialchars($rows['turnstile_site_key'] ?? ''); ?>"
                                       placeholder="0x4AAAAA...">
                            </div>
                        </div>
                        <div class="form-group form-full">
                            <label>Secret Key</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="text" name="turnstile_secret_key"
                                       value="<?php echo htmlspecialchars($rows['turnstile_secret_key'] ?? ''); ?>"
                                       placeholder="0x4AAAAA...">
                            </div>
                            <span class="form-hint">Create keys in Cloudflare Dashboard  to  Turnstile for your domain.</span>
                        </div>
                    </div>
                </div>

                <!-- SAVE -->
                <div class="settings-save-bar">
                    <button type="submit" class="btn-admin btn-gold btn-lg">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <span class="settings-save-note">Changes take effect immediately on the website.</span>
                </div>

            </div>
        </form>

    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
<script>
// Maintenance card highlight
document.getElementById('maintenanceToggle')?.addEventListener('change', function() {
    document.querySelector('.settings-card-maintenance').classList.toggle('maintenance-active', this.checked);
});

// Section nav: highlight active link on scroll 
(function() {
    const nav     = document.getElementById('settingsSectionNav');
    const links   = nav ? Array.from(nav.querySelectorAll('.ssn-link')) : [];
    const sections = links.map(l => document.querySelector(l.getAttribute('href'))).filter(Boolean);

    function onScroll() {
        const scrollY = window.scrollY + 120; // offset for sticky nav height
        let active = sections[0];
        sections.forEach(sec => { if (sec.offsetTop <= scrollY) active = sec; });
        links.forEach(l => {
            l.classList.toggle('active', l.getAttribute('href') === '#' + active.id);
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Smooth scroll with offset
    links.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            const top = target.getBoundingClientRect().top + window.scrollY - 80;
            window.scrollTo({ top, behavior: 'smooth' });
        });
    });
})();

// Meta description character counter
const metaArea  = document.querySelector('[name="site_meta_description"]');
const metaCount = document.getElementById('metaCount');
function updateMetaCount() {
    if (!metaArea || !metaCount) return;
    const len = metaArea.value.length;
    metaCount.textContent = len + ' / 160';
    metaCount.style.color = len > 160 ? 'var(--red)' : len > 130 ? 'var(--gold)' : 'var(--green)';
}
metaArea?.addEventListener('input', updateMetaCount);
updateMetaCount();

// Test email button
document.getElementById('testEmailBtn')?.addEventListener('click', async function() {
    const btn = this;
    const result = document.getElementById('testEmailResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    result.textContent = '';
    try {
        const res  = await fetch('ajax/test-email.php', { method: 'POST',
            body: new URLSearchParams({
                csrf_token: document.querySelector('[name="csrf_token"]').value
            })
        });
        const data = await res.json();
        result.textContent = data.ok ? 'Ã¢Å“" Test email sent successfully.' : 'Ã¢Å“ - ' + data.msg;
        result.style.color = data.ok ? 'var(--green)' : 'var(--red)';
    } catch {
        result.textContent = 'Ã¢Å“ - Request failed.';
        result.style.color = 'var(--red)';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test Email';
});

// Generic image preview helper
function setupImgPreview(inputId, previewId, placeholderId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            const preview     = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });
}
setupImgPreview('heroImageInput',    'heroPreview',        'heroPlaceholder');
setupImgPreview('aboutMainInput',    'aboutMainPreview',   'aboutMainPlaceholder');
setupImgPreview('aboutAccentInput',  'aboutAccentPreview', 'aboutAccentPlaceholder');
</script>
</body>
</html>
