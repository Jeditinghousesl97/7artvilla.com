<?php
$page_title = 'Destinations | We Trail (Pvt) Ltd';
$page_description = 'Explore nearby destinations from our villa with travel time, distance, map references, and activity ideas.';
$og_title = 'Destinations | We Trail (Pvt) Ltd';
$og_description = $page_description;
$og_url = 'https://wetrail.lk/destinations.php';
$og_image = 'https://wetrail.lk/assets/images/villa/destiheader.jpg';
$page_css = 'destinations.css';
$page_js = 'destinations.js';
$nav_base = 'index.php';

require_once 'config/db.php';

if (!function_exists('destination_excerpt')) {
    function destination_excerpt($text, $width = 140, $trim = '...') {
        $text = trim(strip_tags((string)$text));
        if ($text === '') return '';

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $width, $trim);
        }

        if (strlen($text) <= $width) {
            return $text;
        }

        return substr($text, 0, max(0, $width - strlen($trim))) . $trim;
    }
}

if (!function_exists('destination_word_excerpt')) {
    function destination_word_excerpt($text, $max_words = 30, $trim = '...') {
        $text = trim(strip_tags((string)$text));
        if ($text === '') return '';

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return '';
        if (count($words) <= $max_words) return $text;

        return implode(' ', array_slice($words, 0, $max_words)) . $trim;
    }
}

$destinations = [];
$categories = [];
try {
    $pdo = db();
    $destinations = $pdo->query("
        SELECT d.*,
               GROUP_CONCAT(c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names,
               GROUP_CONCAT(c.slug ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ',') AS category_slugs
        FROM destinations d
        LEFT JOIN destination_category_map m ON m.destination_id = d.id
        LEFT JOIN destination_categories c ON c.id = m.category_id
        WHERE d.is_active = 1
        GROUP BY d.id
        ORDER BY d.is_featured DESC, d.sort_order ASC, d.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $categories = $pdo->query("SELECT name, slug FROM destination_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $destinations = [];
    $categories = [];
}

$destination_total = count($destinations);
$featured_total = count(array_filter($destinations, static fn($d) => (int)($d['is_featured'] ?? 0) === 1));
$category_total = count($categories);

include 'includes/header.php';
?>
<section class="dest-hero">
    <div class="dest-hero-bg">
        <img src="assets/images/villa/destiheader.jpg" alt="Nearby destinations from We Trail (Pvt) Ltd" fetchpriority="high">
    </div>
    <div class="dest-hero-overlay"></div>
    <div class="container">
        <div class="dest-hero-content">
            <a href="index.php" class="hero-back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            <p class="section-label">Explore Nearby</p>
            <h1>Nearby <span>Destinations</span></h1>
            <p class="dest-hero-sub">Handpicked places around Panama with route hints, travel details, and guest-friendly recommendations from our villa.</p>
            <div class="dest-hero-stats">
                <div class="dest-hero-stat">
                    <span class="dest-hero-num"><?php echo $destination_total > 0 ? $destination_total . '+' : '0'; ?></span>
                    <span class="dest-hero-label">Places</span>
                </div>
                <div class="dest-hero-divider"></div>
                <div class="dest-hero-stat">
                    <span class="dest-hero-num"><?php echo $featured_total > 0 ? $featured_total : '0'; ?></span>
                    <span class="dest-hero-label">Featured</span>
                </div>
                <div class="dest-hero-divider"></div>
                <div class="dest-hero-stat">
                    <span class="dest-hero-num"><?php echo $category_total > 0 ? $category_total : '0'; ?></span>
                    <span class="dest-hero-label">Categories</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section-light">
    <div class="container">
        <div class="dest-filter-bar">
            <button class="dest-filter-btn active" data-filter="all">All</button>
            <?php foreach ($categories as $cat): ?>
            <button class="dest-filter-btn" data-filter="<?php echo htmlspecialchars($cat['slug']); ?>"><?php echo htmlspecialchars($cat['name']); ?></button>
            <?php endforeach; ?>
        </div>

        <div class="dest-grid" id="destGrid">
            <?php if (empty($destinations)): ?>
            <div class="dest-empty">
                <i class="fas fa-map-marked-alt"></i>
                <h3>No destinations yet</h3>
                <p>We are preparing destination guides. Please check back soon.</p>
            </div>
            <?php else: ?>
            <?php foreach ($destinations as $d): ?>
            <?php
            $img_exists = !empty($d['featured_image_path']) && file_exists($d['featured_image_path']);
            $summary_source = trim((string)($d['short_summary'] ?: $d['description']));
            $summary = destination_word_excerpt($summary_source, 30, '...');
            $cat_slugs = trim((string)($d['category_slugs'] ?? ''));
            ?>
            <article class="dest-card" data-categories="<?php echo htmlspecialchars($cat_slugs); ?>" data-href="destination-details.php?slug=<?php echo urlencode($d['slug']); ?>" tabindex="0" aria-label="Open <?php echo htmlspecialchars($d['title']); ?>">
                <div class="dest-image-wrap">
                    <?php if ($img_exists): ?>
                    <img src="<?php echo htmlspecialchars($d['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($d['title']); ?>" loading="lazy">
                    <?php else: ?>
                    <div class="dest-image-placeholder"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                    <?php if ((int)$d['is_featured'] === 1): ?><span class="dest-badge">Featured</span><?php endif; ?>
                </div>
                <div class="dest-body">
                    <div class="dest-meta-top">
                        <span><?php echo htmlspecialchars($d['category_names'] ?: 'Destination'); ?></span>
                    </div>
                    <h3><?php echo htmlspecialchars($d['title']); ?></h3>
                    <p><?php echo htmlspecialchars($summary); ?></p>
                    <div class="dest-travel">
                        <span><i class="fas fa-route"></i> <?php echo htmlspecialchars($d['distance_from_villa'] ?: 'Distance unavailable'); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($d['travel_time_from_villa'] ?: 'Time unavailable'); ?></span>
                    </div>
                    <div class="dest-actions">
                        <a href="destination-details.php?slug=<?php echo urlencode($d['slug']); ?>" class="dest-details-btn">View Details <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
