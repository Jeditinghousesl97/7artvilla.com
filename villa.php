<?php
$page_css = 'villa.css';
$page_js = 'villa.js';
$nav_base = 'index.php';

require_once 'config/db.php';
require_once 'includes/stay-module.php';

$pdo = db();
stay_ensure_schema($pdo);

$slug = trim((string)($_GET['slug'] ?? ''));
$id = (int)($_GET['id'] ?? 0);

$page_mode = 'listing';
$villas = [];
$listing_spaces = [];
$villa = null;
$spaces = [];
$villa_gallery = [];
$requested_detail = ($slug !== '' || $id > 0);

if ($requested_detail) {
    $sql = "
        SELECT *
        FROM villas
        WHERE is_active = 1 AND " . ($id > 0 ? 'id = ?' : 'slug = ?') . "
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id > 0 ? $id : $slug]);
    $villa = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($villa) {
        $page_mode = 'detail';
        $page_title = $villa['name'] . ' | We Trail (Pvt) Ltd';
        $desc_source = trim(strip_tags((string)$villa['description']));
        $page_description = $villa['short_description'] ?: (function_exists('mb_strimwidth') ? mb_strimwidth($desc_source, 0, 160, '...') : substr($desc_source, 0, 157) . (strlen($desc_source) > 157 ? '...' : ''));
        $og_title = $page_title;
        $og_description = $page_description;
        $og_url = 'https://wetrail.lk/villa.php?slug=' . urlencode($villa['slug']);
        $og_image = !empty($villa['featured_image_path']) ? ('https://wetrail.lk/' . ltrim($villa['featured_image_path'], '/')) : 'https://wetrail.lk/assets/images/logo.png';

        $space_stmt = $pdo->prepare('SELECT * FROM villa_spaces WHERE villa_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
        $space_stmt->execute([$villa['id']]);
        $spaces = $space_stmt->fetchAll(PDO::FETCH_ASSOC);

        $gallery_stmt = $pdo->prepare('SELECT image_path, caption FROM villa_gallery_images WHERE villa_id = ? ORDER BY sort_order ASC, id ASC');
        $gallery_stmt->execute([$villa['id']]);
        $villa_gallery = array_values(array_filter(
            $gallery_stmt->fetchAll(PDO::FETCH_ASSOC),
            static fn(array $item): bool => !empty($item['image_path']) && file_exists($item['image_path'])
        ));
    } else {
        $page_mode = 'not_found';
        http_response_code(404);
        $page_robots = 'noindex, nofollow';
        $page_title = 'Villa Not Found | We Trail (Pvt) Ltd';
        $page_description = 'The requested villa could not be found.';
        $og_title = $page_title;
        $og_description = $page_description;
        $og_url = 'https://wetrail.lk/villa.php' . ($slug !== '' ? ('?slug=' . urlencode($slug)) : ($id > 0 ? ('?id=' . $id) : ''));
        $og_image = 'https://wetrail.lk/assets/images/logo.png';
    }
}

if ($page_mode === 'listing') {
    $page_title = 'Villas | We Trail (Pvt) Ltd';
    $page_description = 'Explore villas, kabanas, and flexible stay options managed by We Trail (Pvt) Ltd.';
    $og_title = $page_title;
    $og_description = $page_description;
    $og_url = 'https://wetrail.lk/villa.php';
    $og_image = 'https://wetrail.lk/assets/images/logo.png';

    $listing_spaces = $pdo->query("
        SELECT s.*,
               v.name AS villa_name,
               v.slug AS villa_slug,
               v.location_label AS villa_location,
               (SELECT COUNT(*) FROM bookable_units u WHERE u.villa_space_id = s.id AND u.is_active = 1) AS units_count
        FROM villa_spaces s
        INNER JOIN villas v ON v.id = s.villa_id
        WHERE s.is_active = 1 AND v.is_active = 1
        ORDER BY v.is_featured DESC, v.sort_order ASC, v.id ASC, s.sort_order ASC, s.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';

function villa_word_excerpt(string $text, int $max_words = 28, string $trim = '...'): string
{
    $text = trim(strip_tags($text));
    if ($text === '') return '';
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!$words || count($words) <= $max_words) return $text;
    return implode(' ', array_slice($words, 0, $max_words)) . $trim;
}

?>

<?php if ($page_mode === 'listing'): ?>
<section class="villa-list-hero">
    <div class="villa-list-hero-bg">
        <img src="assets/images/villa/hero-bg.jpg" alt="We Trail Villas" fetchpriority="high">
    </div>
    <div class="villa-hero-overlay"></div>
    <div class="villa-hero-content">
        <a href="index.php" class="hero-back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        <p class="section-label">Our Stays</p>
        <h1>Villas, <span>Kabanas</span> & Stay Options</h1>
        <p class="villa-hero-sub">Browse the villas we manage and explore the spaces and bookable stay units available inside each property.</p>
    </div>
</section>

<section class="section section-light">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">Stay Collection</p>
            <h2 class="section-title">Villa Spaces</h2>
            <p class="section-desc">Browse the published villa spaces directly and jump into the exact stay area you want to explore.</p>
        </div>
        <?php if ($listing_spaces): ?>
        <div class="villa-space-collection">
            <div class="villa-space-collection-actions">
                <button type="button" class="villa-space-collection-nav prev" data-villa-space-collection-prev aria-label="Previous villa space">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="villa-space-collection-nav next" data-villa-space-collection-next aria-label="Next villa space">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="villa-space-collection-slider" data-villa-space-collection-slider>
                <?php foreach ($listing_spaces as $space): ?>
                <article class="villa-space-card villa-space-collection-card" data-villa-space-collection-item>
                    <div class="villa-space-card-image">
                        <?php if (!empty($space['featured_image_path']) && file_exists($space['featured_image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($space['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($space['name']); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="villa-space-card-placeholder"><i class="fas fa-door-open"></i></div>
                        <?php endif; ?>
                        <span class="villa-space-card-badge"><?php echo htmlspecialchars(stay_space_type_labels()[$space['space_type']] ?? ucfirst($space['space_type'])); ?></span>
                    </div>
                    <div class="villa-space-card-body">
                        <div class="villa-space-collection-kicker"><?php echo htmlspecialchars($space['villa_name']); ?></div>
                        <h3><?php echo htmlspecialchars($space['name']); ?></h3>
                        <?php if (!empty($space['subtitle'])): ?><p class="villa-space-card-subtitle"><?php echo htmlspecialchars($space['subtitle']); ?></p><?php endif; ?>
                        <p class="villa-space-card-copy"><?php echo htmlspecialchars(villa_word_excerpt((string)($space['short_description'] ?: $space['description']))); ?></p>
                        <div class="villa-list-meta villa-space-collection-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($space['villa_location'] ?: 'Sri Lanka'); ?></span>
                            <span><i class="fas fa-bed"></i> <?php echo (int)$space['units_count']; ?> units</span>
                        </div>
                        <a class="btn btn-gold full-width" href="villa-space.php?villa=<?php echo urlencode($space['villa_slug']); ?>&space=<?php echo urlencode($space['slug']); ?>">
                            View Space Details
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <div class="villa-space-collection-scrollbar">
                <span data-villa-space-collection-progress></span>
            </div>
        </div>
            <?php else: ?>
            <div class="villa-list-empty">
                <i class="fas fa-door-open"></i>
                <h3>No villa spaces published yet</h3>
                <p>Use the admin panel to create villa spaces and publish them here.</p>
            </div>
            <?php endif; ?>
    </div>
</section>
<?php elseif ($page_mode === 'detail'): ?>
<section class="villa-hero">
    <div class="villa-hero-bg">
        <?php if (!empty($villa['hero_image_path']) && file_exists($villa['hero_image_path'])): ?>
        <img src="<?php echo htmlspecialchars($villa['hero_image_path']); ?>" alt="<?php echo htmlspecialchars($villa['name']); ?>" fetchpriority="high">
        <?php else: ?>
        <img src="assets/images/villa/hero-bg.jpg" alt="<?php echo htmlspecialchars($villa['name']); ?>" fetchpriority="high">
        <?php endif; ?>
    </div>
    <div class="villa-hero-overlay"></div>
    <div class="villa-hero-content">
        <a href="villa.php" class="hero-back-link"><i class="fas fa-arrow-left"></i> Back to Villas</a>
        <p class="section-label"><?php echo htmlspecialchars($villa['location_label'] ?: 'Sri Lanka'); ?></p>
        <h1><?php echo htmlspecialchars($villa['name']); ?></h1>
        <?php if (!empty($villa['tagline'])): ?><p class="villa-hero-sub"><?php echo htmlspecialchars($villa['tagline']); ?></p><?php endif; ?>
        <div class="villa-hero-tags">
            <?php if (!empty($villa['max_guests'])): ?><span><i class="fas fa-users"></i> <?php echo htmlspecialchars($villa['max_guests']); ?></span><?php endif; ?>
            <?php if (!empty($villa['bedrooms'])): ?><span><i class="fas fa-bed"></i> <?php echo htmlspecialchars($villa['bedrooms']); ?></span><?php endif; ?>
            <?php if (!empty($villa['pool_label'])): ?><span><i class="fas fa-swimming-pool"></i> <?php echo htmlspecialchars($villa['pool_label']); ?></span><?php endif; ?>
            <span><i class="fas fa-sitemap"></i> <?php echo count($spaces); ?> spaces</span>
        </div>
    </div>
    <div class="villa-hero-scroll">
        <a href="#overview"><i class="fas fa-chevron-down"></i></a>
    </div>
</section>

<div class="villa-info-bar" id="overview">
    <div class="container">
        <div class="villa-info-grid">
            <div class="villa-info-item"><i class="fas fa-moon"></i><div><span class="info-label">Min. Stay</span><span class="info-value"><?php echo htmlspecialchars($villa['min_stay'] ?: 'On Request'); ?></span></div></div>
            <div class="villa-info-item"><i class="fas fa-users"></i><div><span class="info-label">Capacity</span><span class="info-value"><?php echo htmlspecialchars($villa['max_guests'] ?: 'Flexible'); ?></span></div></div>
            <div class="villa-info-item"><i class="fas fa-sign-in-alt"></i><div><span class="info-label">Check-In</span><span class="info-value"><?php echo htmlspecialchars($villa['checkin_time'] ?: 'On Request'); ?></span></div></div>
            <div class="villa-info-item"><i class="fas fa-sign-out-alt"></i><div><span class="info-label">Check-Out</span><span class="info-value"><?php echo htmlspecialchars($villa['checkout_time'] ?: 'On Request'); ?></span></div></div>
            <div class="villa-info-item"><i class="fas fa-bed"></i><div><span class="info-label">Bedrooms</span><span class="info-value"><?php echo htmlspecialchars($villa['bedrooms'] ?: 'Mixed'); ?></span></div></div>
            <div class="villa-info-item"><i class="fas fa-swimming-pool"></i><div><span class="info-label">Pool</span><span class="info-value"><?php echo htmlspecialchars($villa['pool_label'] ?: 'Available'); ?></span></div></div>
        </div>
    </div>
</div>

<section class="section section-light" id="about-villa">
    <div class="container">
        <div class="villa-overview-grid">
            <div class="villa-overview-content">
                <p class="section-label">Overview</p>
                <h2 class="section-title"><?php echo htmlspecialchars($villa['name']); ?></h2>
                <?php if (!empty($villa['short_description'])): ?><p class="section-text"><?php echo htmlspecialchars($villa['short_description']); ?></p><?php endif; ?>
                <?php if (!empty($villa['description'])): ?>
                    <?php foreach (preg_split('/\r\n|\r|\n/', trim((string)$villa['description'])) as $paragraph): ?>
                        <?php if (trim($paragraph) !== ''): ?><p class="section-text"><?php echo htmlspecialchars($paragraph); ?></p><?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($villa['pricing_note'])): ?>
                <div class="pricing-note">
                    <i class="fas fa-info-circle"></i>
                    <p><?php echo htmlspecialchars($villa['pricing_note']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <div class="villa-overview-image">
                <div class="villa-img-frame">
                    <?php if (!empty($villa['featured_image_path']) && file_exists($villa['featured_image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($villa['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($villa['name']); ?>" loading="lazy">
                    <?php else: ?>
                    <img src="assets/images/villa/resort.jpg" alt="<?php echo htmlspecialchars($villa['name']); ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="villa-img-badge">
                        <i class="fas fa-house"></i>
                        <span><?php echo count($spaces); ?><br>Spaces</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($villa_gallery): ?>
        <div class="villa-gallery-block">
            <div class="villa-gallery-heading">
                <div>
                    <p class="section-label">Photo Gallery</p>
                    <h3>Inside the Experience</h3>
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
                <?php foreach ($villa_gallery as $index => $img): ?>
                <figure class="villa-gallery-slide" data-villa-gallery-item="<?php echo $index; ?>">
                    <img
                        src="<?php echo htmlspecialchars($img['image_path']); ?>"
                        alt="<?php echo htmlspecialchars($img['caption'] ?: $villa['name']); ?>"
                        loading="lazy"
                    >
                    <button type="button" class="villa-gallery-zoom" aria-label="Open image">
                        <i class="fas fa-expand"></i>
                    </button>
                    <?php if (!empty($img['caption'])): ?>
                    <figcaption><?php echo htmlspecialchars($img['caption']); ?></figcaption>
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

<?php if ($villa_gallery): ?>
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

<section class="section section-dark" id="spaces">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">Inside This Villa</p>
            <h2 class="section-title">Villa Spaces</h2>
            <p class="section-desc">Explore each kabana, wing, or villa section first. Bookable units and stay options are shown inside the individual space page.</p>
        </div>

        <?php if ($spaces): ?>
        <div class="villa-space-card-grid">
            <?php foreach ($spaces as $space): ?>
            <article class="villa-space-card">
                <div class="villa-space-card-image">
                    <?php if (!empty($space['featured_image_path']) && file_exists($space['featured_image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($space['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($space['name']); ?>" loading="lazy">
                    <?php else: ?>
                    <div class="villa-space-card-placeholder"><i class="fas fa-door-open"></i></div>
                    <?php endif; ?>
                    <span class="villa-space-card-badge"><?php echo htmlspecialchars(stay_space_type_labels()[$space['space_type']] ?? ucfirst($space['space_type'])); ?></span>
                </div>
                <div class="villa-space-card-body">
                    <h3><?php echo htmlspecialchars($space['name']); ?></h3>
                    <?php if (!empty($space['subtitle'])): ?><p class="villa-space-card-subtitle"><?php echo htmlspecialchars($space['subtitle']); ?></p><?php endif; ?>
                    <p class="villa-space-card-copy"><?php echo htmlspecialchars((string)$space['short_description']); ?></p>
                    <a class="btn btn-gold full-width" href="villa-space.php?villa=<?php echo urlencode($villa['slug']); ?>&space=<?php echo urlencode($space['slug']); ?>">
                        View Space Details
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="villa-list-empty">
            <i class="fas fa-sitemap"></i>
            <h3>No spaces published yet</h3>
            <p>Use the admin panel to add villa spaces and publish them here.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-light" id="stay-inquiry">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">Plan Your Stay</p>
            <h2 class="section-title">Stay Inquiry Form</h2>
            <p class="section-desc">Tell us which villa space or bookable unit you are interested in and we will confirm availability.</p>
        </div>
        <div class="stay-inquiry-layout">
            <div class="stay-inquiry-summary">
                <div class="stay-summary-card">
                    <div class="stay-summary-top">
                        <p class="section-label">Stay Snapshot</p>
                        <h3><?php echo htmlspecialchars($villa['name']); ?></h3>
                        <p>Use this form to ask about a specific room, suite, or full-villa booking. We’ll come back with availability and the best fitting rate option.</p>
                    </div>
                    <div class="stay-summary-list">
                        <div class="stay-summary-item"><span>Villa</span><strong><?php echo htmlspecialchars($villa['name']); ?></strong></div>
                        <div class="stay-summary-item"><span>Location</span><strong><?php echo htmlspecialchars($villa['location_label'] ?: 'Sri Lanka'); ?></strong></div>
                        <div class="stay-summary-item"><span>Min. Stay</span><strong><?php echo htmlspecialchars($villa['min_stay'] ?: 'Flexible'); ?></strong></div>
                        <div class="stay-summary-item"><span>Bedrooms</span><strong><?php echo htmlspecialchars($villa['bedrooms'] ?: 'Mixed layout'); ?></strong></div>
                    </div>
                    <div class="selected-stay-card">
                        <div class="selected-stay-head">
                            <span class="selected-stay-kicker">Current Selection</span>
                            <strong id="selectedUnitHeading"><?php echo htmlspecialchars($villa['name']); ?></strong>
                        </div>
                        <div class="selected-stay-meta">
                            <div><span>Stay Type</span><strong id="selectedUnitType">Villa inquiry</strong></div>
                            <div><span>Guests</span><strong id="selectedUnitGuests">Flexible</strong></div>
                        </div>
                    </div>
                    <div class="stay-summary-note">
                        <i class="fas fa-bolt"></i>
                        <span>Tip: browse a villa space above to view its bookable units, rates, and unit-specific inquiry options.</span>
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
                    <input type="hidden" name="villa_space_id" id="villaSpaceIdField" value="">
                    <input type="hidden" name="bookable_unit_id" id="bookableUnitIdField" value="">
                    <input type="hidden" name="subject_label" id="subjectLabelField" value="<?php echo htmlspecialchars($villa['name']); ?>">
                    <input type="hidden" name="source_page" value="villa.php?slug=<?php echo htmlspecialchars($villa['slug']); ?>">
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
                        <input type="text" id="selectedUnitView" value="<?php echo htmlspecialchars($villa['name']); ?>" readonly>
                    </div>
                    <div class="form-group"><label for="message">Message *</label><textarea id="message" name="message" rows="5" required>I'm interested in staying at <?php echo htmlspecialchars($villa['name']); ?>. Please share availability and pricing details.</textarea></div>
                    <button type="submit" class="btn btn-gold full-width">Send Inquiry <i class="fas fa-paper-plane"></i></button>
                    <p class="form-note">Share your dates, guest count, and preferred unit. We’ll match you with the most suitable rate.</p>
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
<?php else: ?>
<section class="section section-light" style="padding-top:140px">
    <div class="container">
        <div class="villa-list-empty">
            <i class="fas fa-house"></i>
            <h3>Villa not found</h3>
            <p>The villa you requested is unavailable or no longer active.</p>
            <a href="villa.php" class="btn btn-gold">Back to Villas</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
