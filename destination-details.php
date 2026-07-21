<?php
$slug = trim((string)($_GET['slug'] ?? ''));

require_once 'config/db.php';
$destination = null;
$gallery = [];
$related = [];
$ts_active = false;
$ts_site_key = '';

function destination_safe_embed(string $html): string {
    $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? '';
    return trim($clean);
}

function destination_meta_excerpt(string $text, int $width = 155, string $trim = '...'): string {
    $text = trim(strip_tags($text));
    if ($text === '') return '';

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $width, $trim);
    }

    if (strlen($text) <= $width) {
        return $text;
    }

    return substr($text, 0, max(0, $width - strlen($trim))) . $trim;
}

try {
    $pdo = db();
    if ($slug !== '') {
        $stmt = $pdo->prepare("
            SELECT d.*,
                   GROUP_CONCAT(c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names
            FROM destinations d
            LEFT JOIN destination_category_map m ON m.destination_id = d.id
            LEFT JOIN destination_categories c ON c.id = m.category_id
            WHERE d.slug = ? AND d.is_active = 1
            GROUP BY d.id
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $destination = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($destination) {
        $gstmt = $pdo->prepare('SELECT image_path, caption FROM destination_gallery_images WHERE destination_id = ? ORDER BY sort_order ASC, id ASC');
        $gstmt->execute([(int)$destination['id']]);
        $gallery = $gstmt->fetchAll(PDO::FETCH_ASSOC);

        $rstmt = $pdo->prepare("SELECT id, title, slug, short_summary, featured_image_path FROM destinations WHERE is_active = 1 AND id <> ? ORDER BY is_featured DESC, sort_order ASC, id ASC LIMIT 3");
        $rstmt->execute([(int)$destination['id']]);
        $related = $rstmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
} catch (Exception $e) {
    $destination = null;
}

$not_found = !$destination;
if ($not_found) {
    http_response_code(404);
    $page_robots = 'noindex, nofollow';
}
$page_title = $not_found ? 'Destination Not Found | 7 Art Villa' : ($destination['title'] . ' | Destination | 7 Art Villa');
$page_description = $not_found ? 'The requested destination could not be found.' : trim((string)($destination['short_summary'] ?: destination_meta_excerpt((string)$destination['description'], 155, '...')));
$og_title = $page_title;
$og_description = $page_description;
$og_url = 'https://7artvilla.com/destination-details.php' . ($slug !== '' ? ('?slug=' . urlencode($slug)) : '');
$og_image = (!$not_found && !empty($destination['featured_image_path'])) ? ('https://7artvilla.com/' . ltrim($destination['featured_image_path'], '/')) : 'https://7artvilla.com/assets/images/logo.png';
$page_css = 'tour-details.css';
$page_js = 'tour-details.js';
$nav_base = 'index.php';

include 'includes/header.php';
?>
<?php $hero_image = (!$not_found && !empty($destination['featured_image_path']) && file_exists($destination['featured_image_path'])) ? $destination['featured_image_path'] : 'assets/images/villa/destiheader.jpg'; ?>
<section class="tour-details-hero destination-details-hero">
    <div class="destination-hero-bg">
        <img src="<?php echo htmlspecialchars($hero_image); ?>" alt="<?php echo htmlspecialchars(!$not_found ? $destination['title'] : 'Nearby destinations'); ?>" fetchpriority="high">
    </div>
    <div class="tour-details-overlay destination-details-overlay"></div>
    <div class="container">
        <?php if ($not_found): ?>
        <div class="tour-details-empty">
            <p class="section-label">Destinations</p>
            <h1>Destination Not Found</h1>
            <p>This destination is unavailable or no longer active.</p>
            <div class="tour-details-actions">
                <a href="destinations.php" class="btn btn-gold"><i class="fas fa-arrow-left"></i> Back to Destinations</a>
            </div>
        </div>
        <?php else: ?>
        <div class="tour-details-head">
            <a href="destinations.php" class="tour-back-link"><i class="fas fa-arrow-left"></i> Back to Destinations</a>
            <p class="section-label"><?php echo htmlspecialchars($destination['category_names'] ?: 'Destination'); ?></p>
            <h1><?php echo htmlspecialchars($destination['title']); ?></h1>
            <?php if (!empty($destination['short_summary'])): ?>
            <p class="tour-detail-tagline"><?php echo htmlspecialchars($destination['short_summary']); ?></p>
            <?php endif; ?>
            <div class="destination-hero-chips">
                <span class="destination-hero-chip"><i class="fas fa-route"></i> <?php echo htmlspecialchars($destination['distance_from_villa'] ?: 'Distance available on request'); ?></span>
                <span class="destination-hero-chip"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($destination['travel_time_from_villa'] ?: 'Travel time available on request'); ?></span>
                <?php if (!empty($destination['best_time_to_visit'])): ?>
                <span class="destination-hero-chip"><i class="fas fa-sun"></i> <?php echo htmlspecialchars($destination['best_time_to_visit']); ?></span>
                <?php endif; ?>
            </div>
            <div class="tour-details-actions">
                <a href="#destinationContactForm" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Send Inquiry</a>
                <?php if (!empty($destination['map_embed_html'])): ?>
                <a href="#destinationMap" class="btn btn-outline-gold"><i class="fas fa-map-marked-alt"></i> View Map</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$not_found): ?>
<section class="section section-light">
    <div class="container">
        <div class="tour-details-grid">
            <article class="tour-details-main">
                <?php $has_image = !empty($destination['featured_image_path']) && file_exists($destination['featured_image_path']); ?>
                <div class="tour-main-image<?php echo $has_image ? '' : ' image-placeholder'; ?>">
                    <?php if ($has_image): ?>
                    <img src="<?php echo htmlspecialchars($destination['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($destination['title']); ?>">
                    <?php else: ?>
                    <div class="placeholder-icon"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </div>

                <div class="tour-detail-card">
                    <h2>About This Destination</h2>
                    <p><?php echo nl2br(htmlspecialchars((string)$destination['description'])); ?></p>
                </div>

                <?php
                $things = json_decode((string)($destination['things_to_do'] ?? '[]'), true);
                $things = is_array($things) ? array_filter(array_map('trim', $things)) : [];
                ?>
                <?php if (!empty($things)): ?>
                <div class="tour-detail-card">
                    <h2>Things To Do</h2>
                    <ul class="tour-detail-list">
                        <?php foreach ($things as $todo): ?>
                        <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($todo); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($destination['map_embed_html'])): ?>
                <div class="tour-detail-card" id="destinationMap">
                    <h2>Map</h2>
                    <div class="destination-map-embed">
                        <?php echo destination_safe_embed((string)$destination['map_embed_html']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($gallery)): ?>
                <div class="tour-detail-card">
                    <h2>Photo Gallery</h2>
                    <div class="tour-album-grid">
                        <?php $album_index = 0; ?>
                        <?php foreach ($gallery as $img): ?>
                        <?php if (empty($img['image_path']) || !file_exists($img['image_path'])) continue; ?>
                        <figure class="tour-album-figure" data-album-item="<?php echo $album_index; ?>">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($img['caption'] ?: $destination['title']); ?>" loading="lazy">
                            <?php if (!empty($img['caption'])): ?><figcaption><?php echo htmlspecialchars($img['caption']); ?></figcaption><?php endif; ?>
                        </figure>
                        <?php $album_index++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>

            <aside class="tour-details-side">
                <div class="tour-detail-card">
                    <h3>Travel Info</h3>
                    <ul class="tour-meta-list">
                        <li><span>Distance</span><strong><?php echo htmlspecialchars($destination['distance_from_villa'] ?: 'Not specified'); ?></strong></li>
                        <li><span>Travel Time</span><strong><?php echo htmlspecialchars($destination['travel_time_from_villa'] ?: 'Not specified'); ?></strong></li>
                        <?php if (!empty($destination['best_time_to_visit'])): ?><li><span>Best Time</span><strong><?php echo htmlspecialchars($destination['best_time_to_visit']); ?></strong></li><?php endif; ?>
                    </ul>
                    <a href="#destinationContactForm" class="btn btn-green btn-block"><i class="fas fa-paper-plane"></i> Plan This Visit</a>
                </div>

                <div class="tour-detail-card destination-contact-card" id="destinationContactForm">
                    <h3>Contact & Inquiries</h3>
                    <p class="destination-contact-intro">Planning a stay or day visit? Send us your details and we'll help you arrange the best way to experience this destination.</p>
                    <form class="contact-form contact-form-light" id="inquiryForm">
                        <input type="hidden" name="inquiry_type" value="general">
                        <input type="hidden" name="source_page" value="destination-details.php?slug=<?php echo htmlspecialchars($destination['slug']); ?>">
                        <input type="hidden" name="subject_label" value="<?php echo htmlspecialchars($destination['title']); ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fname">First Name *</label>
                                <input type="text" id="fname" name="first_name" placeholder="Your first name" required>
                            </div>
                            <div class="form-group">
                                <label for="lname">Last Name *</label>
                                <input type="text" id="lname" name="last_name" placeholder="Your last name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" placeholder="your@email.com" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" placeholder="+94 xx xxx xxxx">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="checkin">Check-in Date</label>
                                <input type="date" id="checkin" name="checkin">
                            </div>
                            <div class="form-group">
                                <label for="checkout">Check-out Date</label>
                                <input type="date" id="checkout" name="checkout">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="5" required><?php echo htmlspecialchars("I'm interested in visiting " . $destination['title'] . ". Please share more details."); ?></textarea>
                        </div>
                        <?php if (!empty($ts_active)): ?>
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($ts_site_key); ?>"></div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-gold full-width">Send Inquiry <i class="fas fa-paper-plane"></i></button>
                        <p class="form-note">We typically respond within 24 hours.</p>
                    </form>
                    <div class="form-success form-success-light" id="formSuccess">
                        <i class="fas fa-check-circle"></i>
                        <h3>Thank you for your inquiry!</h3>
                        <p>We'll get back to you within 24 hours.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if (!empty($related)): ?>
<section class="section section-dark">
    <div class="container">
        <div class="section-header center">
            <p class="section-label">More Places</p>
            <h2 class="section-title">You Might Also Like</h2>
        </div>
        <div class="related-tours-grid">
            <?php foreach ($related as $r): ?>
            <?php $img_ok = !empty($r['featured_image_path']) && file_exists($r['featured_image_path']); ?>
            <a class="related-tour-card" href="destination-details.php?slug=<?php echo urlencode($r['slug']); ?>">
                <div class="related-image<?php echo $img_ok ? '' : ' image-placeholder'; ?>">
                    <?php if ($img_ok): ?><img src="<?php echo htmlspecialchars($r['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>" loading="lazy"><?php else: ?><div class="placeholder-icon"><i class="fas fa-image"></i></div><?php endif; ?>
                </div>
                <div class="related-body">
                    <h3><?php echo htmlspecialchars($r['title']); ?></h3>
                    <?php if (!empty($r['short_summary'])): ?><p><?php echo htmlspecialchars($r['short_summary']); ?></p><?php endif; ?>
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
    <button type="button" class="tour-album-lightbox-close" data-album-close aria-label="Close"><i class="fas fa-times"></i></button>
    <button type="button" class="tour-album-lightbox-nav prev" id="tourAlbumPrev" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>
    <div class="tour-album-lightbox-content">
        <img id="tourAlbumLbImg" src="" alt="">
        <p id="tourAlbumLbCaption"></p>
        <span id="tourAlbumLbCount"></span>
    </div>
    <button type="button" class="tour-album-lightbox-nav next" id="tourAlbumNext" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>
</div>

<?php if (!empty($ts_active)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
