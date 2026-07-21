<?php
$tour_id = (int)($_GET['id'] ?? 0);

require_once 'config/db.php';

$tour = null;
$related_tours = [];
$tour_album = [];
$tour_itinerary = [];
$tour_not_found = false;
$ts_active = false;
$ts_site_key = '';

try {
    $pdo = db();

    if ($tour_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$tour_id]);
        $tour = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($tour) {
        try {
            $ts_stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('turnstile_enabled','turnstile_site_key')");
            $ts_stmt->execute();
            $ts_rows = $ts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $ts_enabled = ($ts_rows['turnstile_enabled'] ?? '0') === '1';
            $ts_site_key = trim((string)($ts_rows['turnstile_site_key'] ?? ''));
            $ts_active = $ts_enabled && $ts_site_key !== '';
        } catch (Exception $e) {
            $ts_active = false;
            $ts_site_key = '';
        }

        try {
            $album_stmt = $pdo->prepare("SELECT image_path, caption FROM tour_gallery_images WHERE tour_id = ? ORDER BY sort_order ASC, id ASC");
            $album_stmt->execute([$tour['id']]);
            $tour_album = $album_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $tour_album = [];
        }

        try {
            $itinerary_stmt = $pdo->prepare("SELECT title, description, image_1_path, image_2_path FROM tour_itinerary_items WHERE tour_id = ? ORDER BY sort_order ASC, id ASC");
            $itinerary_stmt->execute([$tour['id']]);
            $tour_itinerary = $itinerary_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $tour_itinerary = [];
        }

        $related_stmt = $pdo->prepare("
            SELECT id, title, tagline, category, image_path
            FROM tours
            WHERE is_active = 1 AND id <> ?
            ORDER BY sort_order ASC, id ASC
            LIMIT 3
        ");
        $related_stmt->execute([$tour['id']]);
        $related_tours = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $tour_not_found = true;
    }
} catch (Exception $e) {
    $tour_not_found = true;
}

if ($tour_not_found) {
    http_response_code(404);
    $page_robots = 'noindex, nofollow';
}

$tour_cat_label = ['half-day' => 'Half Day', 'full-day' => 'Full Day', 'sunrise' => 'Sunrise'];

if (!function_exists('tour_price_lines')) {
    function tour_price_lines(array $tour) {
        $lkr = isset($tour['price_lkr']) && $tour['price_lkr'] !== null && (float)$tour['price_lkr'] > 0
            ? (float)$tour['price_lkr']
            : ((isset($tour['price']) && (float)$tour['price'] > 0) ? (float)$tour['price'] : null);
        $usd = isset($tour['price_usd']) && $tour['price_usd'] !== null && (float)$tour['price_usd'] > 0
            ? (float)$tour['price_usd']
            : null;

        $lines = [];
        if ($lkr !== null) $lines[] = 'LKR ' . number_format($lkr, 0);
        if ($usd !== null) $lines[] = 'USD ' . number_format($usd, 0);
        return $lines;
    }
}

$tour_price_lines = $tour ? tour_price_lines($tour) : [];
$tour_hero_has_image = $tour && !empty($tour['image_path']) && file_exists($tour['image_path']);

$page_title       = $tour ? ($tour['title'] . ' | Tour Package | 7 Art Villa') : 'Tour Not Found | 7 Art Villa';
$page_description = $tour ? trim((string)($tour['tagline'] ?: $tour['description'])) : 'The requested tour package could not be found.';
$og_title         = $page_title;
$og_description   = $page_description;
$og_url           = 'https://7artvilla.com/tour-details.php' . ($tour_id > 0 ? ('?id=' . $tour_id) : '');
$og_image         = ($tour && !empty($tour['image_path'])) ? ('https://7artvilla.com/' . ltrim($tour['image_path'], '/')) : 'https://7artvilla.com/assets/images/logo.png';
$page_css         = 'tour-details.css';
$page_js          = 'tour-details.js';
$nav_base         = 'index.php';

include 'includes/header.php';
?>

<section class="tour-details-hero">
    <?php if ($tour_hero_has_image): ?>
    <div class="tour-hero-bg">
        <img src="<?php echo htmlspecialchars($tour['image_path']); ?>" alt="<?php echo htmlspecialchars($tour['title']); ?>" fetchpriority="high">
    </div>
    <?php endif; ?>
    <div class="tour-details-overlay"></div>
    <div class="container">
        <?php if ($tour_not_found): ?>
        <div class="tour-details-empty">
            <p class="section-label">Tour Packages</p>
            <h1>Tour Not Found</h1>
            <p>The package you requested is unavailable or no longer active.</p>
            <div class="tour-details-actions">
                <a href="tours.php" class="btn btn-gold"><i class="fas fa-arrow-left"></i> Back to All Packages</a>
            </div>
        </div>
        <?php else: ?>
        <div class="tour-details-head">
            <a href="tours.php#toursGrid" class="tour-back-link"><i class="fas fa-arrow-left"></i> Back to Packages</a>
            <p class="section-label"><?php echo htmlspecialchars($tour_cat_label[$tour['category']] ?? 'Tour'); ?> Package</p>
            <h1><?php echo htmlspecialchars($tour['title']); ?></h1>
            <?php if (!empty($tour['tagline'])): ?>
            <p class="tour-detail-tagline"><?php echo htmlspecialchars($tour['tagline']); ?></p>
            <?php endif; ?>
            <div class="tour-details-actions">
                <a href="#tourInquiryForm" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Inquire Now</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$tour_not_found): ?>
<section class="section section-light">
    <div class="container">
        <div class="tour-details-grid">
            <article class="tour-details-main">
                <?php $has_image = !empty($tour['image_path']) && file_exists($tour['image_path']); ?>
                <div class="tour-main-image<?php echo $has_image ? '' : ' image-placeholder'; ?>">
                    <?php if ($has_image): ?>
                    <img src="<?php echo htmlspecialchars($tour['image_path']); ?>" alt="<?php echo htmlspecialchars($tour['title']); ?>">
                    <?php else: ?>
                    <div class="placeholder-icon"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </div>

                <div class="tour-detail-card">
                    <h2>Experience Overview</h2>
                    <p><?php echo nl2br(htmlspecialchars((string)$tour['description'])); ?></p>
                </div>

                <?php
                $highlights = [];
                if (!empty($tour['highlights'])) {
                    $highlights = json_decode($tour['highlights'], true) ?: [];
                }
                ?>
                <?php if (!empty($highlights)): ?>
                <div class="tour-detail-card">
                    <h2>Highlights</h2>
                    <ul class="tour-detail-list">
                        <?php foreach ($highlights as $hl): ?>
                        <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($hl); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($tour_itinerary)): ?>
                <div class="tour-detail-card tour-itinerary-card">
                    <h2>Tour Itinerary</h2>
                    <div class="tour-itinerary-public">
                        <?php foreach ($tour_itinerary as $index => $item): ?>
                        <article class="tour-itinerary-public-item">
                            <div class="tour-itinerary-public-step"><?php echo str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></div>
                            <div class="tour-itinerary-public-body">
                                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                <?php if (!empty($item['description'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                <?php endif; ?>
                                <?php
                                $itinerary_images = [];
                                foreach (['image_1_path', 'image_2_path'] as $img_field) {
                                    if (!empty($item[$img_field]) && file_exists($item[$img_field])) {
                                        $itinerary_images[] = $item[$img_field];
                                    }
                                }
                                ?>
                                <?php if (!empty($itinerary_images)): ?>
                                <div class="tour-itinerary-public-images">
                                    <?php foreach ($itinerary_images as $img_path): ?>
                                    <img src="<?php echo htmlspecialchars($img_path); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($tour_album)): ?>
                <div class="tour-detail-card">
                    <h2>Photo Album</h2>
                    <div class="tour-album-grid">
                        <?php $album_index = 0; ?>
                        <?php foreach ($tour_album as $img): ?>
                        <?php if (empty($img['image_path']) || !file_exists($img['image_path'])) continue; ?>
                        <figure class="tour-album-figure" data-album-item="<?php echo $album_index; ?>">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($img['caption'] ?: $tour['title']); ?>" loading="lazy">
                            <?php if (!empty($img['caption'])): ?>
                            <figcaption><?php echo htmlspecialchars($img['caption']); ?></figcaption>
                            <?php endif; ?>
                        </figure>
                        <?php $album_index++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>

            <aside class="tour-details-side">
                <div class="tour-detail-card">
                    <h3>Package Details</h3>
                    <ul class="tour-meta-list">
                        <li><span>Category</span><strong><?php echo htmlspecialchars($tour_cat_label[$tour['category']] ?? $tour['category']); ?></strong></li>
                        <?php if (!empty($tour['duration'])): ?><li><span>Duration</span><strong><?php echo htmlspecialchars($tour['duration']); ?></strong></li><?php endif; ?>
                        <?php if (!empty($tour['difficulty'])): ?><li><span>Difficulty</span><strong><?php echo htmlspecialchars($tour['difficulty']); ?></strong></li><?php endif; ?>
                        <?php if (!empty($tour['max_guests'])): ?><li><span>Max Guests</span><strong><?php echo htmlspecialchars($tour['max_guests']); ?></strong></li><?php endif; ?>
                        <?php if (!empty($tour_price_lines)): ?><li><span>Price</span><strong><?php echo htmlspecialchars(implode(' / ', $tour_price_lines)); ?> / person</strong></li><?php endif; ?>
                    </ul>
                    <a href="#tourInquiryForm" class="btn btn-green btn-block"><i class="fas fa-paper-plane"></i> Request This Tour</a>
                </div>

                <div class="tour-detail-card" id="tourInquiryForm">
                    <h3>Tour Inquiry Form</h3>
                    <form class="tour-inquiry-form" data-tour-inquiry-form>
                        <input type="hidden" name="page_source" value="tour-details.php">
                        <input type="hidden" name="tour_id" value="<?php echo (int)$tour['id']; ?>">
                        <input type="hidden" name="tour_title" value="<?php echo htmlspecialchars($tour['title']); ?>">
                        <div class="tif-field">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="tif-field">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="tif-field">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="tif-field">
                            <label>Phone</label>
                            <input type="text" name="phone">
                        </div>
                        <div class="tif-field">
                            <label>Preferred Date</label>
                            <input type="date" name="preferred_date">
                        </div>
                        <div class="tif-field">
                            <label>No. of Guests</label>
                            <input type="text" name="guests" placeholder="e.g. 2">
                        </div>
                        <div class="tif-field">
                            <label>Message (Optional)</label>
                            <textarea name="message" rows="4" placeholder="Any special requirements or questions..."></textarea>
                        </div>
                        <?php if (!empty($ts_active)): ?>
                        <div class="tif-field">
                            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($ts_site_key); ?>"></div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-gold btn-block tif-submit">
                            <i class="fas fa-paper-plane"></i> Send Inquiry
                        </button>
                        <p class="tif-feedback" data-tour-inquiry-feedback aria-live="polite"></p>
                    </form>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if (!empty($related_tours)): ?>
<section class="section section-dark">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">More Packages</p>
            <h2 class="section-title">You Might Also Like</h2>
        </div>
        <div class="related-tours-grid">
            <?php foreach ($related_tours as $rt): ?>
            <?php $rt_has_image = !empty($rt['image_path']) && file_exists($rt['image_path']); ?>
            <a class="related-tour-card" href="tour-details.php?id=<?php echo (int)$rt['id']; ?>">
                <div class="related-image<?php echo $rt_has_image ? '' : ' image-placeholder'; ?>">
                    <?php if ($rt_has_image): ?>
                    <img src="<?php echo htmlspecialchars($rt['image_path']); ?>" alt="<?php echo htmlspecialchars($rt['title']); ?>" loading="lazy">
                    <?php else: ?>
                    <div class="placeholder-icon"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </div>
                <div class="related-body">
                    <span class="related-category"><?php echo htmlspecialchars($tour_cat_label[$rt['category']] ?? $rt['category']); ?></span>
                    <h3><?php echo htmlspecialchars($rt['title']); ?></h3>
                    <?php if (!empty($rt['tagline'])): ?><p><?php echo htmlspecialchars($rt['tagline']); ?></p><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<div class="tour-album-lightbox" id="tourAlbumLightbox" aria-hidden="true">
    <div class="tour-album-lightbox-backdrop" data-album-close></div>
    <button type="button" class="tour-album-lightbox-close" data-album-close aria-label="Close">
        <i class="fas fa-times"></i>
    </button>
    <button type="button" class="tour-album-lightbox-nav prev" id="tourAlbumPrev" aria-label="Previous image">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="tour-album-lightbox-content">
        <img id="tourAlbumLbImg" src="" alt="">
        <p id="tourAlbumLbCaption"></p>
        <span id="tourAlbumLbCount"></span>
    </div>
    <button type="button" class="tour-album-lightbox-nav next" id="tourAlbumNext" aria-label="Next image">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<?php if (!empty($ts_active)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
