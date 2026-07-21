<?php
$page_title       = 'Tour Packages | We Trail (Pvt) Ltd';
$page_description = 'Explore Panama and the surrounding region with guided tour packages from We Trail (Pvt) Ltd - waterfalls, tea estates, sunrises, and more.';
$og_title         = 'Tour Packages | We Trail (Pvt) Ltd';
$og_description   = 'Explore Panama and the surrounding region with guided tour packages - waterfalls, tea estates, sunrises, and more.';
$og_url           = 'https://wetrail.lk/tours.php';
$og_image         = 'https://wetrail.lk/assets/images/tours/hero-bg.jpg';
$page_css         = 'tours.css';
$page_js          = 'tours.js';
$nav_base         = 'index.php';

require_once 'config/db.php';
try {
    $__pdo   = db();
    $__tours = $__pdo->query("SELECT * FROM tours WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    $__tour_total = count($__tours);
    $__tour_counts = [];
    foreach ($__tours as $__t) {
        $__tour_counts[$__t['category']] = ($__tour_counts[$__t['category']] ?? 0) + 1;
    }
    $__ts_stmt = $__pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('turnstile_enabled','turnstile_site_key')");
    $__ts_stmt->execute();
    $__ts_rows = $__ts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $__ts_enabled = ($__ts_rows['turnstile_enabled'] ?? '0') === '1';
    $__ts_site_key = trim((string)($__ts_rows['turnstile_site_key'] ?? ''));
    $__ts_active = $__ts_enabled && $__ts_site_key !== '';
} catch (Exception $e) {
    $__tours = [];
    $__tour_total = 0;
    $__tour_counts = [];
    $__ts_enabled = false;
    $__ts_site_key = '';
    $__ts_active = false;
}

$__tour_cat_label = ['half-day' => 'Half Day', 'full-day' => 'Full Day', 'sunrise' => 'Sunrise'];
$__tour_cat_badge = ['half-day' => 'badge-green', 'full-day' => 'badge-gold', 'sunrise' => 'badge-sunrise'];

if (!function_exists('tour_excerpt_words')) {
    function tour_excerpt_words($text, $max_words = 10, $trim = '...') {
        $text = trim((string)$text);
        if ($text === '') return '';

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return '';
        if (count($words) <= $max_words) return $text;

        return implode(' ', array_slice($words, 0, $max_words)) . $trim;
    }
}

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

include 'includes/header.php';
?>

    <!-- PAGE HERO -->
    <section class="tours-hero" id="toursHero">
        <div class="tours-hero-bg">
            <img src="assets/images/tours/hero-bg.jpg" alt="We Trail (Pvt) Ltd Tours" fetchpriority="high">
        </div>
        <div class="tours-hero-overlay"></div>
        <div class="container">
            <div class="tours-hero-content">
                <a href="index.php" class="hero-back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
                <p class="section-label">Explore the Region</p>
                <h1>Tour <span>Packages</span></h1>
                <p class="tours-hero-sub">Step beyond the villa and discover the breathtaking landscapes of Panama - cascading waterfalls, misty tea estates, ancient villages, and unforgettable sunrises await just outside your door.</p>
                <div class="tours-hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-num"><?php echo $__tour_total > 0 ? $__tour_total . '+' : '6+'; ?></span>
                        <span class="hero-stat-label">Packages</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num">Private</span>
                        <span class="hero-stat-label">Guided</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num">5+</span>
                        <span class="hero-stat-label">Rated</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="hero-scroll-hint">
            <span>Scroll to Explore</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- INTRO -->
    <section class="section section-light" id="toursIntro">
        <div class="container">
            <div class="tours-intro-grid">
                <div class="tours-intro-text reveal-el from-left">
                    <p class="section-label">Why Tour With Us</p>
                    <h2 class="section-title">Private Adventures, Crafted for You</h2>
                    <p>Nestled at the heart of Sri Lanka's most stunning highland region, We Trail is your perfect base for exploration. Every tour we offer is <strong>fully private</strong> - designed exclusively for our guests, led by knowledgeable local guides, and tailored around your pace and interests.</p>
                    <p>Whether you crave the adrenaline of a challenging waterfall hike or the calm of sipping highland tea at sunrise, we have an experience that speaks to you.</p>
                    <a href="#toursGrid" class="btn btn-green mt-20 smooth-scroll">
                        <i class="fas fa-compass"></i> View All Packages
                    </a>
                </div>
                <div class="tours-intro-pillars reveal-el from-right">
                    <div class="pillar-item">
                        <div class="pillar-icon"><i class="fas fa-user-shield"></i></div>
                        <div class="pillar-body">
                            <h4>Fully Private</h4>
                            <p>Every tour is just for you - no strangers, no schedules to match, no compromise.</p>
                        </div>
                    </div>
                    <div class="pillar-item">
                        <div class="pillar-icon"><i class="fas fa-map-marked-alt"></i></div>
                        <div class="pillar-body">
                            <h4>Expert Local Guides</h4>
                            <p>Our guides know every trail, viewpoint, and hidden gem in the Panama highlands.</p>
                        </div>
                    </div>
                    <div class="pillar-item">
                        <div class="pillar-icon"><i class="fas fa-car"></i></div>
                        <div class="pillar-body">
                            <h4>Transport Included</h4>
                            <p>Comfortable private vehicle for all transfers - from resort to destination and back.</p>
                        </div>
                    </div>
                    <div class="pillar-item">
                        <div class="pillar-icon"><i class="fas fa-leaf"></i></div>
                        <div class="pillar-body">
                            <h4>Eco-Responsible</h4>
                            <p>We follow low-impact practices and support the local community on every tour.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FILTER + TOUR CARDS -->
    <section class="section section-dark" id="toursGrid">
        <div class="container">
            <div class="section-header center reveal-el from-bottom">
                <p class="section-label">Our Packages</p>
                <h2 class="section-title">Choose Your Adventure</h2>
                <p class="section-desc">From misty sunrise treks to full-day highland explorations - there is a perfect package for every traveller.</p>
            </div>

            <!-- Filter Bar -->
            <div class="tour-filter-bar reveal-el from-bottom">
                <button class="tour-filter-btn active" data-filter="all">
                    <i class="fas fa-th-large"></i> All
                </button>
                <?php if (!empty($__tour_counts['half-day'])): ?>
                <button class="tour-filter-btn" data-filter="half-day">
                    <i class="fas fa-sun"></i> Half Day
                </button>
                <?php endif; ?>
                <?php if (!empty($__tour_counts['full-day'])): ?>
                <button class="tour-filter-btn" data-filter="full-day">
                    <i class="fas fa-mountain"></i> Full Day
                </button>
                <?php endif; ?>
                <?php if (!empty($__tour_counts['sunrise'])): ?>
                <button class="tour-filter-btn" data-filter="sunrise">
                    <i class="fas fa-cloud-sun"></i> Sunrise
                </button>
                <?php endif; ?>
            </div>

            <!-- Tour Cards Grid -->
            <div class="tours-cards-grid" id="toursCardsGrid">

                <?php if (!empty($__tours)): ?>
                <?php foreach ($__tours as $__tour):
                    $__t_cat      = $__tour['category'];
                    $__t_label    = $__tour_cat_label[$__t_cat]  ?? ucwords(str_replace('-',' ',$__t_cat));
                    $__t_badge    = $__tour_cat_badge[$__t_cat]  ?? 'badge-green';
                    $__t_has_img  = !empty($__tour['image_path']) && file_exists($__tour['image_path']);
                    $__t_url      = 'tour-details.php?id=' . (int)$__tour['id'];
                    $__t_price_lines = tour_price_lines($__tour);
                ?>
                <div class="tour-card-full" data-category="<?php echo htmlspecialchars($__t_cat); ?>">
                    <div class="tour-card-image<?php echo $__t_has_img ? '' : ' img-placeholder dark'; ?>">
                        <?php if ($__t_has_img): ?>
                        <img src="<?php echo htmlspecialchars($__tour['image_path']); ?>" alt="<?php echo htmlspecialchars($__tour['title']); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                        <div class="tour-card-badges">
                            <span class="tc-badge <?php echo $__t_badge; ?>"><?php echo htmlspecialchars($__t_label); ?></span>
                            <?php if ($__tour['is_popular']): ?><span class="tc-badge badge-popular">Most Popular</span><?php endif; ?>
                            <?php if ($__tour['is_must_do']): ?><span class="tc-badge badge-popular">Must Do</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon">
                                <?php
                                $__t_icon = $__t_cat === 'sunrise' ? 'fa-sun' : ($__t_cat === 'full-day' ? 'fa-mountain' : 'fa-leaf');
                                ?><i class="fas <?php echo $__t_icon; ?>"></i>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($__tour['title']); ?></h3>
                                <?php if ($__tour['tagline']): ?>
                                <p class="tour-card-tagline"><?php echo htmlspecialchars($__tour['tagline']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="tour-card-desc"><?php echo htmlspecialchars(tour_excerpt_words($__tour['description'], 10, '...')); ?></p>
                        <div class="tour-card-meta">
                            <?php if ($__tour['duration']): ?>
                            <div class="tour-meta-item"><i class="fas fa-clock"></i><span><?php echo htmlspecialchars($__tour['duration']); ?></span></div>
                            <?php endif; ?>
                            <?php if ($__tour['difficulty']): ?>
                            <div class="tour-meta-item"><i class="fas fa-walking"></i><span><?php echo htmlspecialchars($__tour['difficulty']); ?></span></div>
                            <?php endif; ?>
                            <?php if ($__tour['max_guests']): ?>
                            <div class="tour-meta-item"><i class="fas fa-users"></i><span><?php echo htmlspecialchars($__tour['max_guests']); ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="tour-card-footer">
                            <?php if (!empty($__t_price_lines)): ?>
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount"><?php echo htmlspecialchars(implode(' / ', $__t_price_lines)); ?></span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="tour-card-actions">
                                <a href="<?php echo htmlspecialchars($__t_url); ?>" class="btn-inquire btn-details"><i class="fas fa-eye"></i> View Details</a>
                                <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <!-- TOUR 1: Diyaluma Falls Trek (fallback) -->
                <div class="tour-card-full" data-category="half-day">
                    <div class="tour-card-image img-placeholder dark">
                        <!-- Replace with: <img src="assets/images/tours/diyaluma-falls.jpg" alt="Diyaluma Falls Trek"> -->
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <div class="tour-card-badges">
                            <span class="tc-badge badge-green">Half Day</span>
                            <span class="tc-badge badge-popular">Most Popular</span>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon"><i class="fas fa-water"></i></div>
                            <div>
                                <h3>Diyaluma Falls Trek</h3>
                                <p class="tour-card-tagline">Sri Lanka's second-highest waterfall - up close and personal.</p>
                            </div>
                        </div>
                        <p class="tour-card-desc">Hike to the base and natural infinity pools of the magnificent 220-metre Diyaluma Falls, located just minutes from the resort. Swim in crystal-clear rock pools, feel the mist on your skin, and witness nature's raw power at Sri Lanka's iconic cascade.</p>
                        <ul class="tour-highlights">
                            <li><i class="fas fa-check-circle"></i> Trek to upper natural pools</li>
                            <li><i class="fas fa-check-circle"></i> Guided swimming in rock pools</li>
                            <li><i class="fas fa-check-circle"></i> Panoramic valley viewpoints</li>
                            <li><i class="fas fa-check-circle"></i> Refreshments on the trail</li>
                        </ul>
                        <div class="tour-card-meta">
                            <div class="tour-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>3-4 Hours</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-walking"></i>
                                <span>Moderate</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-users"></i>
                                <span>2-4 Guests</span>
                            </div>
                        </div>
                        <div class="tour-card-footer">
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount">LKR 5,500</span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                        </div>
                    </div>
                </div>

                <!-- TOUR 2: Waterfall Discovery Tour -->
                <div class="tour-card-full" data-category="full-day">
                    <div class="tour-card-image img-placeholder dark">
                        <!-- Replace with: <img src="assets/images/tours/waterfall-tour.jpg" alt="Waterfall Discovery Tour"> -->
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <div class="tour-card-badges">
                            <span class="tc-badge badge-gold">Full Day</span>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon"><i class="fas fa-tint"></i></div>
                            <div>
                                <h3>Waterfall Discovery Tour</h3>
                                <p class="tour-card-tagline">Chase hidden cascades through the Panama and Haputale highlands.</p>
                            </div>
                        </div>
                        <p class="tour-card-desc">A full-day adventure visiting multiple hidden waterfalls tucked deep within the highland forests of Panama and Haputale. Your private guide will take you off the beaten path to discover cascades rarely seen by visitors - each one more magical than the last.</p>
                        <ul class="tour-highlights">
                            <li><i class="fas fa-check-circle"></i> Visit 3-5 hidden waterfalls</li>
                            <li><i class="fas fa-check-circle"></i> Forest trail hiking</li>
                            <li><i class="fas fa-check-circle"></i> Packed picnic lunch included</li>
                            <li><i class="fas fa-check-circle"></i> Photography stops at each site</li>
                        </ul>
                        <div class="tour-card-meta">
                            <div class="tour-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>7-8 Hours</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-walking"></i>
                                <span>Moderate</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-users"></i>
                                <span>2-4 Guests</span>
                            </div>
                        </div>
                        <div class="tour-card-footer">
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount">LKR 9,500</span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                        </div>
                    </div>
                </div>

                <!-- TOUR 3: Tea Estate Experience -->
                <div class="tour-card-full" data-category="half-day">
                    <div class="tour-card-image img-placeholder dark">
                        <!-- Replace with: <img src="assets/images/tours/tea-estate.jpg" alt="Tea Estate Experience"> -->
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <div class="tour-card-badges">
                            <span class="tc-badge badge-green">Half Day</span>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon"><i class="fas fa-leaf"></i></div>
                            <div>
                                <h3>Tea Estate Experience</h3>
                                <p class="tour-card-tagline">From leaf to cup - an intimate journey through Sri Lanka's tea culture.</p>
                            </div>
                        </div>
                        <p class="tour-card-desc">Visit a scenic highland tea estate nestled in the rolling hills above Panama. Watch tea pluckers at work, learn the art of tea production from bush to cup, and sit down to a private tasting of freshly brewed single-estate Ceylon teas with local snacks.</p>
                        <ul class="tour-highlights">
                            <li><i class="fas fa-check-circle"></i> Guided estate walkthrough</li>
                            <li><i class="fas fa-check-circle"></i> Tea factory tour</li>
                            <li><i class="fas fa-check-circle"></i> Private tea tasting session</li>
                            <li><i class="fas fa-check-circle"></i> Take-home tea packet included</li>
                        </ul>
                        <div class="tour-card-meta">
                            <div class="tour-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>3-4 Hours</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-walking"></i>
                                <span>Easy</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-users"></i>
                                <span>2-6 Guests</span>
                            </div>
                        </div>
                        <div class="tour-card-footer">
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount">LKR 4,500</span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                        </div>
                    </div>
                </div>

                <!-- TOUR 4: Sunrise Viewpoint Trek -->
                <div class="tour-card-full" data-category="sunrise">
                    <div class="tour-card-image img-placeholder dark">
                        <!-- Replace with: <img src="assets/images/tours/sunrise-trek.jpg" alt="Sunrise Viewpoint Trek"> -->
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <div class="tour-card-badges">
                            <span class="tc-badge badge-sunrise">Sunrise</span>
                            <span class="tc-badge badge-popular">Must Do</span>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon"><i class="fas fa-sun"></i></div>
                            <div>
                                <h3>Sunrise Viewpoint Trek</h3>
                                <p class="tour-card-tagline">Watch the mist lift over the highland valleys as day breaks.</p>
                            </div>
                        </div>
                        <p class="tour-card-desc">Rise before dawn and trek to one of Panama's most spectacular viewpoints in time for sunrise. As the first golden light spills across the valley, you'll witness a panoramic view of misty mountains, waterfalls, and endless green that will stay with you forever. Hot tea served at the summit.</p>
                        <ul class="tour-highlights">
                            <li><i class="fas fa-check-circle"></i> Pre-dawn departure (5:00 AM)</li>
                            <li><i class="fas fa-check-circle"></i> 360 degrees highland viewpoint</li>
                            <li><i class="fas fa-check-circle"></i> Hot tea &amp; breakfast at summit</li>
                            <li><i class="fas fa-check-circle"></i> Return before 9:00 AM</li>
                        </ul>
                        <div class="tour-card-meta">
                            <div class="tour-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>3-4 Hours</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-walking"></i>
                                <span>Easy-Moderate</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-users"></i>
                                <span>2-4 Guests</span>
                            </div>
                        </div>
                        <div class="tour-card-footer">
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount">LKR 4,000</span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                        </div>
                    </div>
                </div>

                <!-- TOUR 5: Haputale Highlands Day Trip -->
                <div class="tour-card-full" data-category="full-day">
                    <div class="tour-card-image img-placeholder dark">
                        <!-- Replace with: <img src="assets/images/tours/haputale-highlands.jpg" alt="Haputale Highlands Day Trip"> -->
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <div class="tour-card-badges">
                            <span class="tc-badge badge-gold">Full Day</span>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon"><i class="fas fa-mountain"></i></div>
                            <div>
                                <h3>Haputale Highlands Day Trip</h3>
                                <p class="tour-card-tagline">The best of the hill country in one unforgettable day.</p>
                            </div>
                        </div>
                        <p class="tour-card-desc">A full-day exploration of the Haputale highlands - one of Sri Lanka's most scenic mountain regions. Visit Lipton's Seat, the iconic Adisham Monastery, rolling tea estates, and the dramatic cliff edge viewpoints that look out over the entire southern highlands.</p>
                        <ul class="tour-highlights">
                            <li><i class="fas fa-check-circle"></i> Lipton's Seat viewpoint</li>
                            <li><i class="fas fa-check-circle"></i> Adisham Benedictine Monastery</li>
                            <li><i class="fas fa-check-circle"></i> Haputale town exploration</li>
                            <li><i class="fas fa-check-circle"></i> Lunch at highland restaurant</li>
                        </ul>
                        <div class="tour-card-meta">
                            <div class="tour-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>8-9 Hours</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-walking"></i>
                                <span>Easy</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-users"></i>
                                <span>2-6 Guests</span>
                            </div>
                        </div>
                        <div class="tour-card-footer">
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount">LKR 11,000</span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                        </div>
                    </div>
                </div>

                <!-- TOUR 6: Village & Culture Walk -->
                <div class="tour-card-full" data-category="half-day">
                    <div class="tour-card-image img-placeholder dark">
                        <!-- Replace with: <img src="assets/images/tours/village-walk.jpg" alt="Village & Culture Walk"> -->
                        <div class="placeholder-label"><i class="fas fa-image"></i></div>
                        <div class="tour-card-badges">
                            <span class="tc-badge badge-green">Half Day</span>
                        </div>
                    </div>
                    <div class="tour-card-body">
                        <div class="tour-card-header">
                            <div class="tour-card-icon"><i class="fas fa-home"></i></div>
                            <div>
                                <h3>Village &amp; Culture Walk</h3>
                                <p class="tour-card-tagline">A slow, soulful walk through the heart of Panama village life.</p>
                            </div>
                        </div>
                        <p class="tour-card-desc">Discover the warmth and simplicity of rural Sri Lankan life on a gentle walk through the villages surrounding We Trail. Meet local families, visit a temple, watch traditional crafts, and enjoy a homemade Sri Lankan breakfast prepared by a local family.</p>
                        <ul class="tour-highlights">
                            <li><i class="fas fa-check-circle"></i> Village temple visit</li>
                            <li><i class="fas fa-check-circle"></i> Traditional craft workshop</li>
                            <li><i class="fas fa-check-circle"></i> Authentic home-cooked breakfast</li>
                            <li><i class="fas fa-check-circle"></i> Local market stop</li>
                        </ul>
                        <div class="tour-card-meta">
                            <div class="tour-meta-item">
                                <i class="fas fa-clock"></i>
                                <span>3-4 Hours</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-walking"></i>
                                <span>Easy</span>
                            </div>
                            <div class="tour-meta-item">
                                <i class="fas fa-users"></i>
                                <span>2-6 Guests</span>
                            </div>
                        </div>
                        <div class="tour-card-footer">
                            <div class="tour-price">
                                <span class="price-from">From</span>
                                <div class="price-row">
                                    <span class="price-amount">LKR 3,500</span>
                                    <span class="price-per">/ person</span>
                                </div>
                            </div>
                            <a href="#tour-inquiry" class="btn-inquire"><i class="fas fa-paper-plane"></i> Inquire</a>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div><!-- /tours-cards-grid -->

        </div>
    </section>

    <!-- TOUR INQUIRY -->
    <section class="section section-light" id="tour-inquiry">
        <div class="container">
            <div class="tour-inquiry-layout">
                <div class="tour-inquiry-copy reveal-el from-left">
                    <p class="section-label">Plan Your Adventure</p>
                    <h2 class="section-title">Tour Inquiry Form</h2>
                    <p class="section-desc">Tell us which tour interests you and your preferred date. We will confirm availability and arrange everything for you.</p>
                    <ul class="tour-inquiry-points">
                        <li><i class="fas fa-check-circle"></i> Quick response from our team</li>
                        <li><i class="fas fa-check-circle"></i> Private, guest-only experiences</li>
                        <li><i class="fas fa-check-circle"></i> Flexible scheduling support</li>
                    </ul>
                </div>
                <div class="tour-inquiry-form-wrap reveal-el from-right">
                    <form class="tour-inquiry-form" data-tour-inquiry-form>
                        <input type="hidden" name="page_source" value="tours.php">
                        <div class="tif-grid">
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
                            <div class="tif-field tif-full">
                                <label>Tour Package *</label>
                                <select name="tour_id" required>
                                    <option value="">Select a package</option>
                                    <?php foreach ($__tours as $__t): ?>
                                    <option value="<?php echo (int)$__t['id']; ?>"><?php echo htmlspecialchars($__t['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tif-field">
                                <label>Preferred Date</label>
                                <input type="date" name="preferred_date">
                            </div>
                            <div class="tif-field">
                                <label>No. of Guests</label>
                                <input type="text" name="guests" placeholder="e.g. 2">
                            </div>
                            <div class="tif-field tif-full">
                                <label>Message (Optional)</label>
                                <textarea name="message" rows="4" placeholder="Any special requirements or questions..."></textarea>
                            </div>
                            <?php if (!empty($__ts_active)): ?>
                            <div class="tif-field tif-full">
                                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($__ts_site_key); ?>"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-gold tif-submit">
                            <i class="fas fa-paper-plane"></i> Send Tour Inquiry
                        </button>
                        <p class="tif-feedback" data-tour-inquiry-feedback aria-live="polite"></p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- WHAT'S INCLUDED -->
    <section class="section section-light" id="toursIncludes">
        <div class="container">
            <div class="section-header center reveal-el from-bottom">
                <p class="section-label">Every Package Includes</p>
                <h2 class="section-title">What's Always Included</h2>
                <p class="section-desc">All our tours come with the same high-quality service and attention to detail - no hidden extras.</p>
            </div>
            <div class="includes-grid">
                <div class="include-item reveal-el from-bottom">
                    <div class="include-icon"><i class="fas fa-user-tie"></i></div>
                    <h4>Private Expert Guide</h4>
                    <p>A dedicated, English-speaking local guide with deep knowledge of every destination.</p>
                </div>
                <div class="include-item reveal-el from-bottom">
                    <div class="include-icon"><i class="fas fa-car-side"></i></div>
                    <h4>Private Transport</h4>
                    <p>Air-conditioned private vehicle for all transfers, both ways. No sharing with other guests.</p>
                </div>
                <div class="include-item reveal-el from-bottom">
                    <div class="include-icon"><i class="fas fa-tint"></i></div>
                    <h4>Water &amp; Snacks</h4>
                    <p>Bottled water, light refreshments, and trail snacks to keep you energised throughout.</p>
                </div>
                <div class="include-item reveal-el from-bottom">
                    <div class="include-icon"><i class="fas fa-shield-alt"></i></div>
                    <h4>Safety Briefing</h4>
                    <p>Full safety briefing and first-aid kit carried on all treks and outdoor activities.</p>
                </div>
                <div class="include-item reveal-el from-bottom">
                    <div class="include-icon"><i class="fas fa-camera"></i></div>
                    <h4>Photography Tips</h4>
                    <p>Guide will ensure you get the best shots at every stop - a photo journal you'll treasure.</p>
                </div>
                <div class="include-item reveal-el from-bottom">
                    <div class="include-icon"><i class="fas fa-ticket-alt"></i></div>
                    <h4>Entry Fees</h4>
                    <p>All applicable entrance and conservation fees are covered - no surprise costs at the gate.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PRACTICAL NOTES -->
    <section class="section section-cream" id="toursNotes">
        <div class="container">
            <div class="tours-notes-grid">
                <div class="tours-notes-text reveal-el from-left">
                    <p class="section-label">Good to Know</p>
                    <h2 class="section-title">Important Notes</h2>
                    <p>To make the most of your experience, please keep these points in mind when planning your tour.</p>
                </div>
                <div class="tours-notes-list reveal-el from-right">
                    <div class="note-item">
                        <div class="note-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="note-body">
                            <h4>Book in Advance</h4>
                            <p>Tours should be arranged at least 24 hours before your preferred date to allow guide and transport scheduling.</p>
                        </div>
                    </div>
                    <div class="note-item">
                        <div class="note-icon"><i class="fas fa-tshirt"></i></div>
                        <div class="note-body">
                            <h4>What to Wear</h4>
                            <p>Comfortable walking shoes, lightweight breathable clothing, a light rain jacket for highland weather, and sun protection.</p>
                        </div>
                    </div>
                    <div class="note-item">
                        <div class="note-icon"><i class="fas fa-cloud-rain"></i></div>
                        <div class="note-body">
                            <h4>Weather Conditions</h4>
                            <p>Highland weather changes quickly. We monitor conditions daily and will advise on rescheduling if safety is a concern.</p>
                        </div>
                    </div>
                    <div class="note-item">
                        <div class="note-icon"><i class="fas fa-child"></i></div>
                        <div class="note-body">
                            <h4>Age &amp; Fitness</h4>
                            <p>Easy tours suit all ages and fitness levels. Moderate trails are fine for active adults. We'll match the right package to you.</p>
                        </div>
                    </div>
                    <div class="note-item">
                        <div class="note-icon"><i class="fas fa-sliders-h"></i></div>
                        <div class="note-body">
                            <h4>Custom Itineraries</h4>
                            <p>Don't see exactly what you're looking for? Just ask - we can design a bespoke tour combining elements from multiple packages.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="tours-cta-section">
        <div class="tours-cta-overlay"></div>
        <div class="container">
            <div class="tours-cta-content reveal-el from-bottom">
                <p class="section-label">Ready to Explore?</p>
                <h2>Let's Plan Your Adventure</h2>
                <p>Tell us which packages interest you and the dates you're staying - your butler will arrange everything before you even arrive.</p>
                <div class="tours-cta-actions">
                    <a href="index.php#contact" class="btn btn-gold">
                        <i class="fas fa-paper-plane"></i> Send an Inquiry
                    </a>
                    <a href="tel:+94777388810" class="btn btn-outline">
                        <i class="fas fa-phone"></i> +94 777 388810
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php if (!empty($__ts_active)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>

