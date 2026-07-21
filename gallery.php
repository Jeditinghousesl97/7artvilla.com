<?php
$page_title       = 'Gallery | We Trail (Pvt) Ltd';
$page_description = 'Browse photos of We Trail (Pvt) Ltd - the villa, private pool, highland views, nature, and dining experiences in Panama, Sri Lanka.';
$og_title         = 'Gallery | We Trail (Pvt) Ltd';
$og_description   = 'Browse photos of We Trail (Pvt) Ltd - the villa, private pool, highland views, nature, and dining experiences in Panama, Sri Lanka.';
$og_url           = 'https://wetrail.lk/gallery.php';
$og_image         = 'https://wetrail.lk/assets/images/gallery/hero-bg.jpg';
$page_css         = 'gallery.css';
$page_js          = 'gallery.js';
$nav_base         = 'index.php';

// Load gallery images from DB
require_once 'config/db.php';
try {
    $__pdo    = db();
    $__images = $__pdo->query("
        SELECT * FROM gallery_images
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ")->fetchAll();

    // Count per category for filter buttons
    $__counts = [];
    foreach ($__images as $__img) {
        $__counts[$__img['category']] = ($__counts[$__img['category']] ?? 0) + 1;
    }
    $__total = count($__images);

} catch (Exception $e) {
    $__images = [];
    $__counts = [];
    $__total  = 0;
}

// Category meta - icon + label
$__cat_meta = [
    'villa'  => ['icon' => 'fa-home',          'label' => 'Villa'],
    'pool'   => ['icon' => 'fa-swimming-pool',  'label' => 'Pool'],
    'views'  => ['icon' => 'fa-mountain',       'label' => 'Views'],
    'nature' => ['icon' => 'fa-leaf',           'label' => 'Nature'],
    'dining' => ['icon' => 'fa-utensils',       'label' => 'Dining'],
];

// Active categories (only those with images)
$__active_cats = array_keys($__counts);

include 'includes/header.php';
?>

    <!-- PAGE HERO -->
    <section class="gallery-hero" id="galleryHero">
        <div class="gallery-hero-bg">
            <img src="assets/images/gallery/hero-bg.jpg" alt="We Trail (Pvt) Ltd Gallery" fetchpriority="high">
        </div>
        <div class="gallery-hero-overlay"></div>
        <div class="container">
            <div class="gallery-hero-content">
                <a href="index.php" class="hero-back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
                <p class="section-label">Visual Journey</p>
                <h1>Our <span>Gallery</span></h1>
                <p class="gallery-hero-sub">A collection of moments from We Trail (Pvt) Ltd - the villa, the pool, the highlands, and the experiences that make every stay unforgettable.</p>
                <div class="gallery-hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo count($__active_cats); ?></span>
                        <span class="hero-stat-label">Categories</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo $__total > 0 ? $__total . '+' : ' - '; ?></span>
                        <span class="hero-stat-label">Photos</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num">4K</span>
                        <span class="hero-stat-label">Quality</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- GALLERY -->
    <section class="section section-light" id="galleryGrid">
        <div class="container-wide">

            <?php if ($__total > 0): ?>

            <!-- Filter Bar - only shows categories that have images -->
            <div class="gallery-filter-bar reveal-el from-bottom">
                <button class="gallery-filter-btn active" data-filter="all">
                    <i class="fas fa-th"></i> All
                    <span class="gal-filter-count"><?php echo $__total; ?></span>
                </button>
                <?php foreach ($__cat_meta as $__cat => $__meta):
                    if (!isset($__counts[$__cat])) continue; ?>
                <button class="gallery-filter-btn" data-filter="<?php echo $__cat; ?>">
                    <i class="fas <?php echo $__meta['icon']; ?>"></i>
                    <?php echo $__meta['label']; ?>
                    <span class="gal-filter-count"><?php echo $__counts[$__cat]; ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Masonry Grid -->
            <div class="gallery-masonry" id="galleryMasonry">
                <?php foreach ($__images as $__img):
                    $__cat   = $__img['category'];
                    $__label = $__cat_meta[$__cat]['label'] ?? ucfirst($__cat);
                    $__cls   = 'gal-item';
                    if ($__img['span_col']) $__cls .= ' span-2';
                    if ($__img['span_row']) $__cls .= ' span-r-2';
                    $__src   = htmlspecialchars($__img['image_path']);
                    $__alt   = htmlspecialchars($__img['caption'] ?? $__label);
                    $__has_img = $__img['image_path'] && file_exists($__img['image_path']);
                ?>
                <div class="<?php echo $__cls; ?>" data-category="<?php echo htmlspecialchars($__cat); ?>">
                    <?php if ($__has_img): ?>
                    <img src="<?php echo $__src; ?>" alt="<?php echo $__alt; ?>" loading="lazy">
                    <?php else: ?>
                    <div class="img-placeholder dark small">
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                    </div>
                    <?php endif; ?>
                    <div class="gal-overlay">
                        <div class="gal-caption">
                            <span class="gal-cat-tag"><?php echo htmlspecialchars($__label); ?></span>
                            <?php if ($__img['caption']): ?>
                            <p><?php echo htmlspecialchars($__img['caption']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="gal-zoom"><i class="fas fa-expand-alt"></i></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty filter result (shown by JS when a filter returns 0) -->
            <div class="gallery-empty" id="galleryEmpty" style="display:none">
                <i class="fas fa-images"></i>
                <p>No photos in this category yet - check back soon.</p>
            </div>

            <?php else: ?>

            <!-- No images at all -->
            <div class="gallery-empty" style="display:block">
                <i class="fas fa-images"></i>
                <p>Our gallery is coming soon - check back shortly.</p>
            </div>

            <?php endif; ?>

        </div>
    </section>

    <!-- LIGHTBOX -->
    <div class="gal-lightbox" id="galLightbox">
        <div class="gal-lightbox-backdrop" id="galLightboxBackdrop"></div>
        <button class="gal-lb-close" id="galLbClose" aria-label="Close"><i class="fas fa-times"></i></button>
        <button class="gal-lb-prev" id="galLbPrev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
        <button class="gal-lb-next" id="galLbNext" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
        <div class="gal-lb-content">
            <div class="gal-lb-img-wrap">
                <img id="galLbImg" src="" alt="Gallery Image">
                <div class="gal-lb-placeholder" id="galLbPlaceholder">
                    <i class="fas fa-image"></i>
                    <span>Image coming soon</span>
                </div>
            </div>
            <div class="gal-lb-footer">
                <p class="gal-lb-caption" id="galLbCaption"></p>
                <span class="gal-lb-counter" id="galLbCounter"></span>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
