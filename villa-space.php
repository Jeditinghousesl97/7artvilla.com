<?php
$page_css = 'villa.css';
$page_js = 'villa.js';
$nav_base = 'index.php';

require_once 'config/db.php';
require_once 'config/turnstile.php';
require_once 'includes/stay-module.php';

$pdo = db();
stay_ensure_schema($pdo);
$turnstile_cfg = turnstile_get_settings();
$turnstile_active = !empty($turnstile_cfg['enabled']);
$turnstile_site_key = (string)($turnstile_cfg['site_key'] ?? '');

$villa_slug = trim((string)($_GET['villa'] ?? ''));
$space_slug = trim((string)($_GET['space'] ?? ''));
$space_id = (int)($_GET['id'] ?? 0);

$villa = null;
$space = null;
$units = [];
$pricing_by_unit = [];
$unit_gallery_by_unit = [];
$space_gallery = [];
$space_showcase_gallery = [];

if ($space_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, v.id AS villa_id_ref, v.name AS villa_name, v.slug AS villa_slug, v.location_label, v.hero_image_path, v.min_stay, v.bedrooms, v.max_guests, v.pool_label
        FROM villa_spaces s
        JOIN villas v ON v.id = s.villa_id
        WHERE s.id = ? AND s.is_active = 1 AND v.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$space_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, v.id AS villa_id_ref, v.name AS villa_name, v.slug AS villa_slug, v.location_label, v.hero_image_path, v.min_stay, v.bedrooms, v.max_guests, v.pool_label
        FROM villa_spaces s
        JOIN villas v ON v.id = s.villa_id
        WHERE s.slug = ? AND v.slug = ? AND s.is_active = 1 AND v.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$space_slug, $villa_slug]);
}

$space = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if ($space) {
    $villa = [
        'id' => (int)$space['villa_id_ref'],
        'name' => (string)$space['villa_name'],
        'slug' => (string)$space['villa_slug'],
        'location_label' => (string)$space['location_label'],
        'hero_image_path' => (string)$space['hero_image_path'],
        'min_stay' => (string)$space['min_stay'],
        'bedrooms' => (string)$space['bedrooms'],
        'max_guests' => (string)$space['max_guests'],
        'pool_label' => (string)$space['pool_label'],
    ];

    $unit_stmt = $pdo->prepare("
        SELECT *
        FROM bookable_units
        WHERE villa_id = ? AND is_active = 1 AND (villa_space_id = ? OR villa_space_id IS NULL)
        ORDER BY
            is_featured DESC,
            CASE WHEN villa_space_id = ? THEN 0 ELSE 1 END,
            sort_order ASC,
            id ASC
    ");
    $unit_stmt->execute([(int)$villa['id'], (int)$space['id'], (int)$space['id']]);
    $units = $unit_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($units) {
        $unit_ids = array_map(static fn(array $item): int => (int)$item['id'], $units);
        $placeholders = implode(',', array_fill(0, count($unit_ids), '?'));
        $pricing_stmt = $pdo->prepare("SELECT * FROM unit_pricing WHERE bookable_unit_id IN ($placeholders) ORDER BY is_featured DESC, sort_order ASC, id ASC");
        $pricing_stmt->execute($unit_ids);
        foreach ($pricing_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pricing_by_unit[(int)$row['bookable_unit_id']][] = $row;
        }

        $unit_gallery_stmt = $pdo->prepare("SELECT * FROM bookable_unit_gallery_images WHERE bookable_unit_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
        $unit_gallery_stmt->execute($unit_ids);
        foreach ($unit_gallery_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $unit_gallery_by_unit[(int)$row['bookable_unit_id']][] = $row;
        }
    }

    $gallery_stmt = $pdo->prepare("
        SELECT *
        FROM villa_space_gallery_images
        WHERE villa_space_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $gallery_stmt->execute([(int)$space['id']]);
    $space_gallery = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

    $showcase_stmt = $pdo->prepare("
        SELECT *
        FROM villa_space_showcase_images
        WHERE villa_space_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $showcase_stmt->execute([(int)$space['id']]);
    $space_showcase_gallery = $showcase_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = $space ? ($space['name'] . ' | ' . $villa['name'] . ' | We Trail (Pvt) Ltd') : 'Villa Space Not Found | We Trail (Pvt) Ltd';
$page_description = $space
    ? trim((string)($space['short_description'] ?: $space['description']))
    : 'The requested villa space could not be found.';
$og_title = $page_title;
$og_description = $page_description;
$og_url = 'https://wetrail.lk/villa-space.php' . ($space ? ('?villa=' . urlencode($villa['slug']) . '&space=' . urlencode($space['slug'])) : '');
$og_image = ($space && !empty($space['featured_image_path'])) ? ('https://wetrail.lk/' . ltrim((string)$space['featured_image_path'], '/')) : 'https://wetrail.lk/assets/images/logo.png';
if (!$space) {
    http_response_code(404);
    $page_robots = 'noindex, nofollow';
}

include 'includes/header.php';

function villa_space_best_pricing(array $pricing_rows): ?array
{
    if (!$pricing_rows) return null;
    foreach ($pricing_rows as $row) {
        if (!empty($row['is_featured'])) return $row;
    }
    return $pricing_rows[0];
}

function villa_space_pricing_features(array $pricing_row): array
{
    $features = json_decode((string)($pricing_row['features'] ?? '[]'), true);
    return is_array($features) ? array_values(array_filter(array_map('trim', $features))) : [];
}
?>

<?php if (!$space): ?>
<section class="section section-light" style="padding-top:140px">
    <div class="container">
        <div class="villa-list-empty">
            <i class="fas fa-door-open"></i>
            <h3>Villa space not found</h3>
            <p>The space you requested is unavailable or no longer active.</p>
            <a href="villa.php" class="btn btn-gold">Back to Villas</a>
        </div>
    </div>
</section>
<?php else: ?>
<section class="villa-hero">
    <div class="villa-hero-bg">
        <?php if (!empty($space['featured_image_path']) && file_exists((string)$space['featured_image_path'])): ?>
        <img src="<?php echo htmlspecialchars((string)$space['featured_image_path']); ?>" alt="<?php echo htmlspecialchars((string)$space['name']); ?>" fetchpriority="high">
        <?php elseif (!empty($villa['hero_image_path']) && file_exists((string)$villa['hero_image_path'])): ?>
        <img src="<?php echo htmlspecialchars((string)$villa['hero_image_path']); ?>" alt="<?php echo htmlspecialchars((string)$space['name']); ?>" fetchpriority="high">
        <?php else: ?>
        <img src="assets/images/villa/hero-bg.jpg" alt="<?php echo htmlspecialchars((string)$space['name']); ?>" fetchpriority="high">
        <?php endif; ?>
    </div>
    <div class="villa-hero-overlay"></div>
    <div class="villa-hero-content">
        <a href="villa.php?slug=<?php echo urlencode((string)$villa['slug']); ?>#spaces" class="hero-back-link"><i class="fas fa-arrow-left"></i> Back to <?php echo htmlspecialchars((string)$villa['name']); ?></a>
        <p class="section-label"><?php echo htmlspecialchars(stay_space_type_labels()[$space['space_type']] ?? ucfirst((string)$space['space_type'])); ?></p>
        <h1><?php echo htmlspecialchars((string)$space['name']); ?></h1>
        <?php if (!empty($space['subtitle'])): ?><p class="villa-hero-sub"><?php echo htmlspecialchars((string)$space['subtitle']); ?></p><?php endif; ?>
        <div class="villa-hero-tags">
            <span><i class="fas fa-house"></i> <?php echo htmlspecialchars((string)$villa['name']); ?></span>
            <span><i class="fas fa-bed"></i> <?php echo count($units); ?> stay options</span>
            <?php if (!empty($villa['location_label'])): ?><span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars((string)$villa['location_label']); ?></span><?php endif; ?>
        </div>
    </div>
    <div class="villa-hero-scroll">
        <a href="#space-overview"><i class="fas fa-chevron-down"></i></a>
    </div>
</section>

<section class="section section-light" id="space-overview">
    <div class="container">
        <div class="villa-overview-grid">
            <div class="villa-overview-content">
                <p class="section-label">Space Overview</p>
                <h2 class="section-title"><?php echo htmlspecialchars((string)$space['name']); ?></h2>
                <?php if (!empty($space['short_description'])): ?><p class="section-text"><?php echo htmlspecialchars((string)$space['short_description']); ?></p><?php endif; ?>
                <?php if (!empty($space['description'])): ?>
                    <?php foreach (preg_split('/\r\n|\r|\n/', trim((string)$space['description'])) as $paragraph): ?>
                        <?php if (trim($paragraph) !== ''): ?><p class="section-text"><?php echo htmlspecialchars($paragraph); ?></p><?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="villa-overview-image">
                <div class="villa-img-frame">
                    <?php if (!empty($space['featured_image_path']) && file_exists((string)$space['featured_image_path'])): ?>
                    <img src="<?php echo htmlspecialchars((string)$space['featured_image_path']); ?>" alt="<?php echo htmlspecialchars((string)$space['name']); ?>" loading="lazy">
                    <?php else: ?>
                    <img src="assets/images/villa/resort.jpg" alt="<?php echo htmlspecialchars((string)$space['name']); ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="villa-img-badge">
                        <i class="fas fa-bed"></i>
                        <span><?php echo count($units); ?><br>Units</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($space_gallery): ?>
        <div class="villa-gallery-block">
            <div class="villa-gallery-heading">
                <div>
                    <p class="section-label">Photo Gallery</p>
                    <h3>Inside <?php echo htmlspecialchars((string)$space['name']); ?></h3>
                </div>
                <div class="villa-gallery-actions">
                    <button type="button" class="villa-gallery-nav prev" data-villa-gallery-prev aria-label="Previous image">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="villa-gallery-nav next" data-villa-gallery-next aria-label="Next image">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="villa-gallery-slider" id="villaGallerySlider">
                <?php foreach ($space_gallery as $index => $img): ?>
                <figure class="villa-gallery-slide" data-villa-gallery-item="<?php echo $index; ?>">
                    <img
                        src="<?php echo htmlspecialchars((string)$img['image_path']); ?>"
                        alt="<?php echo htmlspecialchars((string)($img['caption'] ?: $space['name'])); ?>"
                        loading="lazy"
                    >
                    <button type="button" class="villa-gallery-zoom" aria-label="Open image">
                        <i class="fas fa-expand"></i>
                    </button>
                    <?php if (!empty($img['caption'])): ?>
                    <figcaption><?php echo htmlspecialchars((string)$img['caption']); ?></figcaption>
                    <?php endif; ?>
                </figure>
                <?php endforeach; ?>
            </div>
            <div class="villa-gallery-scrollbar">
                <span id="villaGalleryProgress"></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($space_gallery): ?>
<div class="villa-gallery-lightbox" id="villaGalleryLightbox" aria-hidden="true">
    <div class="villa-gallery-lightbox-backdrop" data-villa-gallery-close></div>
    <button type="button" class="villa-gallery-lightbox-close" data-villa-gallery-close aria-label="Close gallery">
        <i class="fas fa-times"></i>
    </button>
    <button type="button" class="villa-gallery-lightbox-nav prev" id="villaGalleryLbPrev" aria-label="Previous image">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="villa-gallery-lightbox-content">
        <img id="villaGalleryLbImg" src="" alt="">
        <p id="villaGalleryLbCaption"></p>
        <span id="villaGalleryLbCount"></span>
    </div>
    <button type="button" class="villa-gallery-lightbox-nav next" id="villaGalleryLbNext" aria-label="Next image">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>
<?php endif; ?>

<section class="section section-dark" id="space-units">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">Bookable Units</p>
            <h2 class="section-title">Stay Options in <?php echo htmlspecialchars((string)$space['name']); ?></h2>
            <p class="section-desc">Choose the room, suite, or package that fits your stay. Availability requests can be sent directly from each option.</p>
        </div>

        <?php if ($units): ?>
        <div class="unit-card-grid">
            <?php foreach ($units as $unit): ?>
                <?php $unit_prices = $pricing_by_unit[(int)$unit['id']] ?? []; ?>
                <?php $best_price = villa_space_best_pricing($unit_prices); ?>
                <?php
                    $unit_gallery = $unit_gallery_by_unit[(int)$unit['id']] ?? [];
                    $unit_media_items = [];
                    if (!empty($unit['featured_image_path']) && file_exists((string)$unit['featured_image_path'])) {
                        $unit_media_items[] = [
                            'image_path' => (string)$unit['featured_image_path'],
                            'caption' => '',
                        ];
                    }
                    foreach ($unit_gallery as $gallery_item) {
                        $image_path = (string)($gallery_item['image_path'] ?? '');
                        if ($image_path === '' || !file_exists($image_path)) {
                            continue;
                        }
                        $unit_media_items[] = [
                            'image_path' => $image_path,
                            'caption' => (string)($gallery_item['caption'] ?? ''),
                        ];
                    }
                ?>
                <article class="unit-card">
                    <?php if (!empty($unit['is_featured'])): ?>
                    <span class="unit-card-featured-ribbon">Featured</span>
                    <?php endif; ?>
                    <div class="unit-card-main">
                        <div class="unit-card-media">
                            <?php if ($unit_media_items): ?>
                            <div class="unit-media-gallery" data-unit-gallery>
                                <div class="unit-media-slider" data-unit-gallery-slider>
                                    <?php foreach ($unit_media_items as $media_index => $media_item): ?>
                                    <figure class="unit-media-slide" data-unit-gallery-item="<?php echo $media_index; ?>">
                                        <img
                                            src="<?php echo htmlspecialchars((string)$media_item['image_path']); ?>"
                                            alt="<?php echo htmlspecialchars((string)($media_item['caption'] !== '' ? $media_item['caption'] : $unit['name'])); ?>"
                                            loading="lazy"
                                        >
                                        <?php if (!empty($media_item['caption'])): ?>
                                        <figcaption><?php echo htmlspecialchars((string)$media_item['caption']); ?></figcaption>
                                        <?php endif; ?>
                                    </figure>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($unit_media_items) > 1): ?>
                                <button type="button" class="unit-media-nav prev" data-unit-gallery-prev aria-label="Previous image">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" class="unit-media-nav next" data-unit-gallery-next aria-label="Next image">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="unit-card-media-placeholder">
                                <i class="fas fa-bed"></i>
                                <span><?php echo htmlspecialchars(stay_unit_type_labels()[$unit['unit_type']] ?? ucfirst((string)$unit['unit_type'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="unit-card-copy">
                            <div class="unit-card-head">
                                <div>
                                    <h4><?php echo htmlspecialchars((string)$unit['name']); ?></h4>
                                    <?php if (!empty($unit['subtitle'])): ?><p class="unit-card-subtitle"><?php echo htmlspecialchars((string)$unit['subtitle']); ?></p><?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($unit['summary'])): ?><p class="unit-card-summary"><?php echo htmlspecialchars((string)$unit['summary']); ?></p><?php endif; ?>
                            <div class="unit-card-meta">
                                <?php if (!empty($unit['max_guests'])): ?><span><i class="fas fa-users"></i> <?php echo htmlspecialchars((string)$unit['max_guests']); ?></span><?php endif; ?>
                                <?php if (!empty($unit['bed_info'])): ?><span><i class="fas fa-bed"></i> <?php echo htmlspecialchars((string)$unit['bed_info']); ?></span><?php endif; ?>
                                <?php if (!empty($unit['size_label'])): ?><span><i class="fas fa-expand"></i> <?php echo htmlspecialchars((string)$unit['size_label']); ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($unit['description'])): ?>
                            <?php $__unit_detail_lines = preg_split('/\r\n|\r|\n/', trim((string)$unit['description'])); ?>
                            <?php $__unit_detail = trim((string)($__unit_detail_lines[0] ?? '')); ?>
                            <?php if ($__unit_detail !== ''): ?>
                            <p class="unit-card-detail"><?php echo htmlspecialchars($__unit_detail); ?></p>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="unit-card-aside">
                            <div class="unit-card-price-panel">
                                <?php if (count($unit_prices) > 1): ?>
                                <div class="unit-pricing-preview compact">
                                    <div class="unit-pricing-title">More Price Options</div>
                                    <?php foreach ($unit_prices as $price_row): ?>
                                    <div class="unit-pricing-row<?php echo !empty($price_row['is_featured']) ? ' is-active' : ''; ?>">
                                        <div>
                                            <strong><?php echo htmlspecialchars((string)$price_row['label']); ?></strong>
                                            <span><?php echo htmlspecialchars((string)$price_row['days']); ?></span>
                                        </div>
                                        <b>LKR <?php echo number_format((float)$price_row['price_lkr']); ?></b>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php elseif ($best_price): ?>
                                <div class="unit-pricing-preview compact">
                                    <div class="unit-pricing-title">Price Option</div>
                                    <div class="unit-pricing-row is-active">
                                        <div>
                                            <strong><?php echo htmlspecialchars((string)$best_price['label']); ?></strong>
                                            <span><?php echo htmlspecialchars((string)$best_price['days']); ?></span>
                                        </div>
                                        <b>LKR <?php echo number_format((float)$best_price['price_lkr']); ?></b>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($unit_prices): ?>
                                <div class="unit-pricing-popup-template" hidden aria-hidden="true">
                                    <div class="booking-modal-pricing-card">
                                        <div class="booking-modal-pricing-head">
                                            <span class="booking-modal-pricing-kicker">Unit Pricing Details</span>
                                            <div class="booking-modal-pricing-heading-row">
                                                <h4>Choose your package</h4>
                                                <div class="booking-modal-pricing-mini-nav">
                                                    <button type="button" class="booking-modal-pricing-nav prev" data-booking-pricing-prev aria-label="Previous pricing option">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </button>
                                                    <button type="button" class="booking-modal-pricing-nav next" data-booking-pricing-next aria-label="Next pricing option">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="booking-modal-pricing-slider" data-booking-pricing-slider>
                                            <?php foreach ($unit_prices as $price_index => $price_row): ?>
                                            <?php $price_features = villa_space_pricing_features($price_row); ?>
                                            <?php
                                                $price_label = trim((string)$price_row['label']);
                                                $price_days = trim((string)$price_row['days']);
                                                $price_lkr = 'LKR ' . number_format((float)$price_row['price_lkr']);
                                                $price_usd = (float)$price_row['price_usd'] > 0 ? 'USD ' . number_format((float)$price_row['price_usd'], 2) : '';
                                                $price_subject = $price_label !== '' ? $price_label : 'Package';
                                                if ($price_days !== '') {
                                                    $price_subject .= ' - ' . $price_days;
                                                }
                                            ?>
                                            <article
                                                class="booking-modal-pricing-slide"
                                                data-booking-pricing-item
                                                data-pricing-label="<?php echo htmlspecialchars($price_label, ENT_QUOTES); ?>"
                                                data-pricing-days="<?php echo htmlspecialchars($price_days, ENT_QUOTES); ?>"
                                                data-pricing-lkr="<?php echo htmlspecialchars($price_lkr, ENT_QUOTES); ?>"
                                                data-pricing-usd="<?php echo htmlspecialchars($price_usd, ENT_QUOTES); ?>"
                                                data-pricing-subject="<?php echo htmlspecialchars($price_subject, ENT_QUOTES); ?>"
                                            >
                                                <button
                                                    type="button"
                                                    class="booking-modal-pricing-item<?php echo !empty($price_row['is_featured']) ? ' is-featured' : ''; ?><?php echo $price_index === 0 ? ' is-selected' : ''; ?>"
                                                    data-booking-pricing-select
                                                    aria-pressed="<?php echo $price_index === 0 ? 'true' : 'false'; ?>"
                                                >
                                                    <div class="booking-modal-pricing-badge-row">
                                                        <?php if (!empty($price_row['is_featured'])): ?>
                                                        <span class="booking-modal-pricing-badge">Recommended</span>
                                                        <?php endif; ?>
                                                        <span class="booking-modal-pricing-badge subtle"><?php echo htmlspecialchars($price_days !== '' ? $price_days : 'Custom package'); ?></span>
                                                    </div>
                                                    <div class="booking-modal-pricing-top">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($price_label); ?></strong>
                                                            <span><?php echo htmlspecialchars($price_days); ?></span>
                                                        </div>
                                                        <div class="booking-modal-pricing-amounts">
                                                            <b><?php echo htmlspecialchars($price_lkr); ?></b>
                                                            <?php if ($price_usd !== ''): ?>
                                                            <small><?php echo htmlspecialchars($price_usd); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($price_features): ?>
                                                    <ul class="booking-modal-pricing-features">
                                                        <?php foreach ($price_features as $feature): ?>
                                                        <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($feature); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                    <?php endif; ?>
                                                </button>
                                            </article>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($unit_prices) > 1): ?>
                                        <div class="booking-modal-pricing-footer">
                                            <div class="booking-modal-pricing-progress"><span data-booking-pricing-progress></span></div>
                                            <div class="booking-modal-pricing-dots">
                                                <?php foreach ($unit_prices as $price_index => $price_row): ?>
                                                <button
                                                    type="button"
                                                    class="booking-modal-pricing-dot<?php echo $price_index === 0 ? ' is-active' : ''; ?>"
                                                    data-booking-pricing-dot="<?php echo $price_index; ?>"
                                                    aria-label="Go to pricing option <?php echo $price_index + 1; ?>"
                                                ></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="btn btn-gold unit-card-cta unit-inquire-trigger"
                                    data-villa-id="<?php echo (int)$villa['id']; ?>"
                                    data-space-id="<?php echo (int)$space['id']; ?>"
                                    data-unit-id="<?php echo (int)$unit['id']; ?>"
                                    data-subject="<?php echo htmlspecialchars((string)$unit['name'], ENT_QUOTES); ?>"
                                    data-guest="<?php echo htmlspecialchars((string)$unit['max_guests'], ENT_QUOTES); ?>"
                                    data-unit-type="<?php echo htmlspecialchars(stay_unit_type_labels()[$unit['unit_type']] ?? ucfirst((string)$unit['unit_type']), ENT_QUOTES); ?>"
                                    data-unit-subtitle="<?php echo htmlspecialchars((string)($unit['subtitle'] ?? ''), ENT_QUOTES); ?>"
                                    data-space-name="<?php echo htmlspecialchars((string)$space['name'], ENT_QUOTES); ?>"
                                    data-unit-scope="<?php echo (int)($unit['villa_space_id'] ?? 0) > 0 ? 'specific' : 'shared'; ?>"
                                >
                                    Book Now
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="villa-list-empty">
            <i class="fas fa-bed"></i>
            <h3>No bookable units published yet</h3>
            <p>This villa space exists, but its stay options have not been published yet.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($units): ?>
<div class="villa-gallery-lightbox" id="unitGalleryLightbox" aria-hidden="true">
    <div class="villa-gallery-lightbox-backdrop" data-unit-gallery-close></div>
    <button type="button" class="villa-gallery-lightbox-close" data-unit-gallery-close aria-label="Close gallery">
        <i class="fas fa-times"></i>
    </button>
    <button type="button" class="villa-gallery-lightbox-nav prev" id="unitGalleryLbPrev" aria-label="Previous image">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="villa-gallery-lightbox-content">
        <img id="unitGalleryLbImg" src="" alt="">
        <p id="unitGalleryLbCaption"></p>
        <span id="unitGalleryLbCount"></span>
    </div>
    <button type="button" class="villa-gallery-lightbox-nav next" id="unitGalleryLbNext" aria-label="Next image">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($units): ?>
<div class="booking-modal" id="bookingModal" aria-hidden="true">
    <div class="booking-modal-backdrop" data-booking-modal-close></div>
    <div class="booking-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bookingModalTitle">
        <button type="button" class="booking-modal-close" data-booking-modal-close aria-label="Close booking form">
            <i class="fas fa-times"></i>
        </button>
        <div class="booking-modal-layout">
            <div class="booking-modal-media">
                <div class="booking-modal-media-head">
                    <p class="section-label">Selected Stay</p>
                    <h3 id="bookingModalTitle">Book Your Stay</h3>
                    <p id="bookingModalSubtitle">Choose your dates and send your booking request.</p>
                </div>
                <div class="booking-modal-pricing-shell" id="bookingModalPricingShell"></div>
                <div class="booking-modal-slider-wrap">
                    <div class="booking-modal-slider" id="bookingModalSlider"></div>
                    <button type="button" class="booking-modal-nav prev" id="bookingModalPrev" aria-label="Previous image">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="booking-modal-nav next" id="bookingModalNext" aria-label="Next image">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="booking-modal-progress"><span id="bookingModalProgress"></span></div>
                </div>
                <div class="booking-modal-meta">
                    <span><i class="fas fa-bed"></i> <strong id="bookingModalType">Selected bookable unit</strong></span>
                    <span><i class="fas fa-users"></i> <strong id="bookingModalGuests">Flexible</strong></span>
                </div>
            </div>
            <div class="booking-modal-form-shell">
                <div class="stay-form-card booking-modal-form-card">
                    <div class="stay-form-card-head">
                        <div>
                            <p class="section-label">Booking Request</p>
                            <h3>Request Availability</h3>
                        </div>
                        <span class="stay-form-badge">Response within 24 hours</span>
                    </div>
                    <form class="contact-form" id="bookingInquiryForm">
                        <input type="hidden" name="inquiry_type" value="stay">
                        <input type="hidden" name="villa_id" value="<?php echo (int)$villa['id']; ?>">
                        <input type="hidden" name="villa_space_id" id="bookingVillaSpaceIdField" value="<?php echo (int)$space['id']; ?>">
                        <input type="hidden" name="bookable_unit_id" id="bookingBookableUnitIdField" value="">
                        <input type="hidden" name="subject_label" id="bookingSubjectLabelField" value="">
                        <input type="hidden" name="pricing_label" id="bookingPricingLabelField" value="">
                        <input type="hidden" name="source_page" value="villa-space.php?villa=<?php echo htmlspecialchars((string)$villa['slug']); ?>&space=<?php echo htmlspecialchars((string)$space['slug']); ?>">
                        <div class="form-row">
                            <div class="form-group"><label for="bookingFname">First Name *</label><input type="text" id="bookingFname" name="first_name" required></div>
                            <div class="form-group"><label for="bookingLname">Last Name *</label><input type="text" id="bookingLname" name="last_name" required></div>
                        </div>
                        <div class="form-group"><label for="bookingEmail">Email Address *</label><input type="email" id="bookingEmail" name="email" required></div>
                        <div class="form-group"><label for="bookingPhone">Phone Number</label><input type="tel" id="bookingPhone" name="phone"></div>
                        <div class="form-row">
                            <div class="form-group"><label for="bookingCheckin">Check-in Date</label><input type="date" id="bookingCheckin" name="checkin"></div>
                            <div class="form-group"><label for="bookingCheckout">Check-out Date</label><input type="date" id="bookingCheckout" name="checkout"></div>
                        </div>
                        <input type="hidden" id="bookingGuestCount" name="guest_count" value="">
                        <div class="form-row">
                            <div class="form-group"><label for="bookingAdults">Adults</label><input type="number" id="bookingAdults" min="0" step="1" placeholder="e.g. 2"></div>
                            <div class="form-group"><label for="bookingChildren">Childs</label><input type="number" id="bookingChildren" min="0" step="1" placeholder="e.g. 1"></div>
                        </div>
                        <div class="form-group">
                            <label for="bookingSelectedUnitView">Selected Stay Option</label>
                            <input type="text" id="bookingSelectedUnitView" value="" readonly>
                        </div>
                        <div class="form-group" id="bookingPricingSelectGroup" hidden>
                            <label for="bookingPricingSelect">Select Pricing Package *</label>
                            <select id="bookingPricingSelect">
                                <option value="">Choose a pricing package</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="bookingMessage">Message *</label><textarea id="bookingMessage" name="message" rows="5" required></textarea></div>
                        <?php if ($turnstile_active && $turnstile_site_key !== ''): ?>
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>"></div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-gold full-width">Send Booking Request <i class="fas fa-paper-plane"></i></button>
                        <p class="form-note">Your request will be emailed to our team and a confirmation copy will also be sent to your email address.</p>
                    </form>
                    <div class="form-success" id="bookingFormSuccess">
                        <i class="fas fa-check-circle"></i>
                        <h3>Booking request sent!</h3>
                        <p>We’ve emailed your request to our team and sent a confirmation to your inbox.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="section section-light" id="stay-inquiry">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">Plan Your Stay</p>
            <h2 class="section-title">Stay Inquiry Form</h2>
            <p class="section-desc">Ask about a specific unit inside this villa space and we will confirm availability.</p>
        </div>
        <div class="stay-inquiry-layout">
            <div class="stay-inquiry-summary">
                <div class="stay-summary-card">
                    <div class="stay-summary-top">
                        <p class="section-label">Stay Snapshot</p>
                        <h3><?php echo htmlspecialchars((string)$space['name']); ?></h3>
                        <p>Use this form to ask about a room, suite, or package inside this villa space.</p>
                    </div>
                    <div class="stay-summary-list">
                        <div class="stay-summary-item"><span>Villa</span><strong><?php echo htmlspecialchars((string)$villa['name']); ?></strong></div>
                        <div class="stay-summary-item"><span>Space</span><strong><?php echo htmlspecialchars((string)$space['name']); ?></strong></div>
                        <div class="stay-summary-item"><span>Location</span><strong><?php echo htmlspecialchars((string)($villa['location_label'] ?: 'Sri Lanka')); ?></strong></div>
                        <div class="stay-summary-item"><span>Stay Options</span><strong><?php echo count($units); ?></strong></div>
                    </div>
                    <div class="selected-stay-card">
                        <div class="selected-stay-head">
                            <span class="selected-stay-kicker">Current Selection</span>
                            <strong id="selectedUnitHeading"><?php echo htmlspecialchars((string)$space['name']); ?></strong>
                        </div>
                        <div class="selected-stay-meta">
                            <div><span>Stay Type</span><strong id="selectedUnitType">Villa space inquiry</strong></div>
                            <div><span>Guests</span><strong id="selectedUnitGuests">Flexible</strong></div>
                        </div>
                    </div>
                    <div class="stay-summary-note">
                        <i class="fas fa-bolt"></i>
                        <span>Tip: click any “Check Availability” button above to prefill the selected stay option here.</span>
                    </div>
                </div>
            </div>
            <div class="contact-form-wrap stay-form-shell">
                <div class="stay-form-card">
                    <div class="stay-form-card-head">
                        <div>
                            <p class="section-label">Inquiry Details</p>
                            <h3>Request Availability</h3>
                        </div>
                        <span class="stay-form-badge">Response within 24 hours</span>
                    </div>
                    <form class="contact-form" id="inquiryForm">
                        <input type="hidden" name="inquiry_type" value="stay">
                        <input type="hidden" name="villa_id" value="<?php echo (int)$villa['id']; ?>">
                        <input type="hidden" name="villa_space_id" id="villaSpaceIdField" value="<?php echo (int)$space['id']; ?>">
                        <input type="hidden" name="bookable_unit_id" id="bookableUnitIdField" value="">
                        <input type="hidden" name="subject_label" id="subjectLabelField" value="<?php echo htmlspecialchars((string)$space['name']); ?>">
                        <input type="hidden" name="source_page" value="villa-space.php?villa=<?php echo htmlspecialchars((string)$villa['slug']); ?>&space=<?php echo htmlspecialchars((string)$space['slug']); ?>">
                        <div class="form-row">
                            <div class="form-group"><label for="fname">First Name *</label><input type="text" id="fname" name="first_name" required></div>
                            <div class="form-group"><label for="lname">Last Name *</label><input type="text" id="lname" name="last_name" required></div>
                        </div>
                        <div class="form-group"><label for="email">Email Address *</label><input type="email" id="email" name="email" required></div>
                        <div class="form-group"><label for="phone">Phone Number</label><input type="tel" id="phone" name="phone"></div>
                        <div class="form-row">
                            <div class="form-group"><label for="checkin">Check-in Date</label><input type="date" id="checkin" name="checkin"></div>
                            <div class="form-group"><label for="checkout">Check-out Date</label><input type="date" id="checkout" name="checkout"></div>
                        </div>
                        <div class="form-group"><label for="guest_count">Guests</label><input type="text" id="guest_count" name="guest_count" placeholder="e.g. 2 Adults"></div>
                        <div class="form-group">
                            <label for="selectedUnitView">Selected Stay Option</label>
                            <input type="text" id="selectedUnitView" value="<?php echo htmlspecialchars((string)$space['name']); ?>" readonly>
                        </div>
                        <div class="form-group"><label for="message">Message *</label><textarea id="message" name="message" rows="5" required>I'm interested in staying at <?php echo htmlspecialchars((string)$space['name']); ?> in <?php echo htmlspecialchars((string)$villa['name']); ?>. Please share availability and pricing details.</textarea></div>
                        <button type="submit" class="btn btn-gold full-width">Send Inquiry <i class="fas fa-paper-plane"></i></button>
                        <p class="form-note">Share your dates, guest count, and preferred unit. We'll match you with the most suitable rate.</p>
                    </form>
                </div>
                <div class="form-success" id="formSuccess">
                    <i class="fas fa-check-circle"></i>
                    <h3>Thank you for your inquiry!</h3>
                    <p>We'll get back to you with stay availability soon.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($space_showcase_gallery): ?>
<section class="space-showcase-section" id="space-showcase">
    <div class="space-showcase-shell">
        <button type="button" class="space-showcase-nav prev" data-space-showcase-prev aria-label="Previous image">
            <i class="fas fa-chevron-left"></i>
        </button>
        <div class="space-showcase-slider" id="spaceShowcaseSlider">
            <?php foreach ($space_showcase_gallery as $index => $img): ?>
            <figure class="space-showcase-slide" data-space-showcase-item="<?php echo $index; ?>">
                <img
                    src="<?php echo htmlspecialchars((string)$img['image_path']); ?>"
                    alt="<?php echo htmlspecialchars((string)($img['caption'] ?: $space['name'])); ?>"
                    loading="lazy"
                >
                <figcaption>
                    <span><?php echo htmlspecialchars((string)$space['name']); ?></span>
                    <?php if (!empty($img['caption'])): ?><strong><?php echo htmlspecialchars((string)$img['caption']); ?></strong><?php endif; ?>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php if (count($space_showcase_gallery) > 1): ?>
        <div class="space-showcase-pagination" id="spaceShowcasePagination">
            <?php foreach ($space_showcase_gallery as $index => $img): ?>
            <button
                type="button"
                class="space-showcase-pill<?php echo $index === 0 ? ' is-active' : ''; ?>"
                data-space-showcase-dot="<?php echo $index; ?>"
                aria-label="Go to slide <?php echo $index + 1; ?>"
            ></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button type="button" class="space-showcase-nav next" data-space-showcase-next aria-label="Next image">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</section>
<?php endif; ?>

<?php if ($turnstile_active && $turnstile_site_key !== ''): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
