<?php
$page_title       = '7 Art Villa | Eco Villa in Ella, Sri Lanka';
$page_description = 'Discover 7 Art Villa, a peaceful eco villa in Ella, Sri Lanka. Breath of Serenity.';
$og_title         = '7 Art Villa | Breath of Serenity';
$og_url           = 'https://7artvilla.com/';
$og_image         = 'https://7artvilla.com/assets/images/logo.png';
$nav_base         = ''; // homepage - use hash links in navbar

// Load settings from DB
require_once 'config/db.php';
require_once 'includes/stay-module.php';

if (!function_exists('home_word_excerpt')) {
    function home_word_excerpt($text, $max_words = 10, $trim = '...') {
        $text = trim(strip_tags((string)$text));
        if ($text === '') return '';

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) return '';
        if (count($words) <= $max_words) return $text;

        return implode(' ', array_slice($words, 0, $max_words)) . $trim;
    }
}
try {
    $__pdo  = db();
    stay_ensure_schema($__pdo);
    $__keys = ['hero_image',
               'about_label','about_heading','about_paragraph1','about_paragraph2',
               'about_stat1_number','about_stat1_label',
               'about_stat2_number','about_stat2_label',
               'about_stat3_number','about_stat3_label',
               'about_image_main','about_image_accent',
               'phone','whatsapp','email','facebook','instagram','youtube',
               'tiktok','tripadvisor','twitter','maps_url','maps_embed_url',
               'turnstile_enabled','turnstile_site_key'];
    $__ph   = implode(',', array_fill(0, count($__keys), '?'));
    $__st   = $__pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($__ph)");
    $__st->execute($__keys);
    $__s    = $__st->fetchAll(PDO::FETCH_KEY_PAIR);

    $hero_image       = $__s['hero_image']         ?? '';
    $about_label      = $__s['about_label']        ?? 'About 7 Art Villa';
    $about_heading    = $__s['about_heading']       ?? 'Breath of Serenity';
    $about_p1         = $__s['about_paragraph1']    ?? '7 Art Villa is an eco villa in Ella, Sri Lanka, offering a peaceful retreat shaped by thoughtful hospitality and a close connection to nature.';
    $about_p2         = $__s['about_paragraph2']    ?? 'Surrounded by Ella\'s fresh mountain air and lush scenery, every stay is designed to feel private, unhurried, and restorative.';
    $about_stats      = [
        ['number' => $__s['about_stat1_number'] ?? '100%', 'label' => $__s['about_stat1_label'] ?? 'Private'],
        ['number' => $__s['about_stat2_number'] ?? '1',    'label' => $__s['about_stat2_label'] ?? 'Exclusive Villa'],
        ['number' => $__s['about_stat3_number'] ?? '24/7', 'label' => $__s['about_stat3_label'] ?? 'Butler Service'],
    ];
    $about_img_main   = $__s['about_image_main']   ?? '';
    $about_img_accent = $__s['about_image_accent'] ?? '';

    // Contact / social
    $idx_phone    = $__s['phone']        ?? '077 387 0850';
    $idx_email    = $__s['email']        ?? 'info@7artvilla.com';
    $idx_wa       = $__s['whatsapp']     ?? '94773870850';
    $idx_facebook = $__s['facebook']     ?? '';
    $idx_instagram= $__s['instagram']   ?? '';
    $idx_youtube  = $__s['youtube']     ?? '';
    $idx_tiktok   = $__s['tiktok']      ?? '';
    $idx_tripadvisor=$__s['tripadvisor'] ?? '';
    $idx_twitter  = $__s['twitter']     ?? '';
    $idx_maps       = $__s['maps_url']       ?? 'https://www.google.com/maps/search/?api=1&query=Ella%2C%20Sri%20Lanka';
    $idx_maps_embed = $__s['maps_embed_url'] ?? '';
    $idx_turnstile_enabled = ($__s['turnstile_enabled'] ?? '0') === '1';
    $idx_turnstile_site_key = trim((string)($__s['turnstile_site_key'] ?? ''));
    $idx_turnstile_active = $idx_turnstile_enabled && $idx_turnstile_site_key !== '';
    $idx_wa_url   = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $idx_wa)
                  . '?text=Hello%2C%20I%20am%20interested%20in%20booking%207%20Art%20Villa.';

    // Services preview (up to 6)
    $__home_services = $__pdo->query("SELECT * FROM services WHERE is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 6")->fetchAll();

    // Tours preview (up to 3)
    $__home_tours = $__pdo->query("SELECT * FROM tours WHERE is_active=1 ORDER BY sort_order ASC, id ASC LIMIT 3")->fetchAll();

    $__home_destinations = [];
    try {
        $__home_destinations = $__pdo->query("
            SELECT d.*,
                   GROUP_CONCAT(c.name ORDER BY c.sort_order ASC, c.name ASC SEPARATOR ', ') AS category_names
            FROM destinations d
            LEFT JOIN destination_category_map m ON m.destination_id = d.id
            LEFT JOIN destination_categories c ON c.id = m.category_id
            WHERE d.is_active = 1 AND d.is_homepage = 1
            GROUP BY d.id
            ORDER BY d.sort_order ASC, d.id ASC
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $__home_destinations = [];
    }

    // Gallery preview - images explicitly marked for the homepage
    $__home_gallery = $__pdo->query("SELECT * FROM gallery_images WHERE is_active=1 AND show_on_home=1 ORDER BY sort_order ASC, id DESC LIMIT 6")->fetchAll();
    $__home_villa_spaces = $__pdo->query("
        SELECT s.*,
               v.name AS villa_name,
               v.slug AS villa_slug,
               v.location_label,
               v.is_featured AS villa_is_featured,
               v.is_homepage AS villa_is_homepage,
               (SELECT COUNT(*) FROM bookable_units u WHERE u.villa_space_id = s.id AND u.is_active = 1) AS units_count
        FROM villa_spaces s
        JOIN villas v ON v.id = s.villa_id
        WHERE s.is_active = 1 AND v.is_active = 1
        ORDER BY v.is_homepage DESC, v.is_featured DESC, v.sort_order ASC, s.sort_order ASC, s.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $hero_image = $about_label = $about_heading = $about_p1 = $about_p2 = '';
    $about_stats = [
        ['number'=>'100%','label'=>'Private'],
        ['number'=>'1',   'label'=>'Exclusive Villa'],
        ['number'=>'24/7','label'=>'Butler Service'],
    ];
    $about_img_main = $about_img_accent = '';
    $idx_phone = '077 387 0850'; $idx_email = 'info@7artvilla.com';
    $idx_wa = '94773870850'; $idx_facebook = $idx_instagram = $idx_youtube = '';
    $idx_tiktok = $idx_tripadvisor = $idx_twitter = '';
    $idx_maps = 'https://www.google.com/maps/search/?api=1&query=Ella%2C%20Sri%20Lanka';
    $idx_wa_url = 'https://wa.me/94773870850?text=Hello%2C%20I%20am%20interested%20in%20booking%207%20Art%20Villa.';
    $idx_turnstile_enabled = false;
    $idx_turnstile_site_key = '';
    $idx_turnstile_active = false;
    $__home_services = $__home_tours = $__home_gallery = $__home_destinations = $__home_villa_spaces = [];
}

include 'includes/header.php';
?>

    <!-- HERO -->
    <section class="hero" id="home">
        <div class="hero-bg">
            <?php if ($hero_image && file_exists($hero_image)): ?>
            <img src="<?php echo htmlspecialchars($hero_image); ?>" alt="7 Art Villa" fetchpriority="high">
            <?php endif; ?>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <p class="hero-tagline">Ella, Sri Lanka</p>
            <h1 class="hero-title">Breath of <span>Serenity</span></h1>
            <p class="hero-subtitle">A peaceful eco villa where fresh mountain air, thoughtful comfort, and the natural beauty of Ella come together.</p>
            <div class="hero-actions">
                <!-- <a href="villa.php" class="btn btn-gold">Explore Our Villas</a> -->
                <a href="villa.php" class="btn btn-gold">Explore Our Villa</a>
                <a href="#contact" class="btn btn-outline">Make an Inquiry</a>
            </div>
        </div>
        <div class="hero-scroll-indicator">
            <a href="#about"><i class="fas fa-chevron-down"></i></a>
        </div>
    </section>

    <!-- HIGHLIGHTS BAR -->
    <section class="highlights-bar">
        <div class="container">
            <div class="highlight-item"><i class="fas fa-swimming-pool"></i><span>Private Pool</span></div>
            <div class="highlight-item"><i class="fas fa-user-tie"></i><span>Butler Service</span></div>
            <div class="highlight-item"><i class="fas fa-heart"></i><span>Family Retreat</span></div>
            <div class="highlight-item"><i class="fas fa-leaf"></i><span>Eco Friendly</span></div>
            <div class="highlight-item"><i class="fas fa-mountain"></i><span>In Scenic Ella</span></div>
        </div>
    </section>

    <!-- ABOUT -->
    <section class="section section-light" id="about">
        <div class="container">
            <div class="about-grid">
                <div class="about-image-block">
                    <div class="about-img-main <?php echo ($about_img_main && file_exists($about_img_main)) ? '' : 'img-placeholder'; ?>">
                        <?php if ($about_img_main && file_exists($about_img_main)): ?>
                        <img src="<?php echo htmlspecialchars($about_img_main); ?>" alt="<?php echo htmlspecialchars($about_heading); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="placeholder-label"><i class="fas fa-image"></i> Resort Exterior</div>
                        <?php endif; ?>
                    </div>
                    <div class="about-img-accent <?php echo ($about_img_accent && file_exists($about_img_accent)) ? '' : 'img-placeholder'; ?>">
                        <?php if ($about_img_accent && file_exists($about_img_accent)): ?>
                        <img src="<?php echo htmlspecialchars($about_img_accent); ?>" alt="Private Pool" loading="lazy">
                        <?php else: ?>
                        <div class="placeholder-label"><i class="fas fa-image"></i> Private Pool</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="about-content">
                    <?php if ($about_label): ?><p class="section-label"><?php echo htmlspecialchars($about_label); ?></p><?php endif; ?>
                    <h2 class="section-title"><?php echo htmlspecialchars($about_heading); ?></h2>
                    <?php if ($about_p1): ?><p class="section-text"><?php echo htmlspecialchars($about_p1); ?></p><?php endif; ?>
                    <?php if ($about_p2): ?><p class="section-text"><?php echo htmlspecialchars($about_p2); ?></p><?php endif; ?>
                    <div class="about-stats">
                        <?php foreach ($about_stats as $stat): ?>
                        <div class="stat">
                            <span class="stat-number"><?php echo htmlspecialchars($stat['number']); ?></span>
                            <span class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- <a href="villa.php" class="btn btn-gold">Discover Our Villas</a> -->
                    <a href="villa.php" class="btn btn-gold">Discover Our Villa</a>
                </div>
            </div>
        </div>
    </section>

    <!-- VILLAS -->
    <section class="section section-dark villa-home-section" id="villa">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">Our Stays</p>
                <h2 class="section-title">Featured Villa Spaces</h2>
                <p class="section-desc">Explore the villa spaces we manage and the bookable stay options available inside each one.</p>
            </div>
            <div class="section-slider-shell">
                <div class="section-slider-controls" aria-label="Villa spaces navigation">
                    <button type="button" class="section-slider-btn" data-stays-prev aria-label="Previous villa space">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="section-slider-btn" data-stays-next aria-label="Next villa space">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="stays-slider" data-stays-slider>
                <?php if (!empty($__home_villa_spaces)): ?>
                    <?php foreach ($__home_villa_spaces as $__space): ?>
                    <div class="tour-card home-destination-card stays-slide" data-stays-slide>
                        <a href="villa-space.php?villa=<?php echo urlencode($__space['villa_slug']); ?>&space=<?php echo urlencode($__space['slug']); ?>" class="card-stretch-link" aria-label="Open <?php echo htmlspecialchars($__space['name']); ?>"></a>
                        <?php if (!empty($__space['featured_image_path']) && file_exists($__space['featured_image_path'])): ?>
                        <div class="tour-image"><img src="<?php echo htmlspecialchars($__space['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($__space['name']); ?>" loading="lazy"></div>
                        <?php else: ?>
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-door-open"></i></div></div>
                        <?php endif; ?>
                        <div class="tour-content">
                            <span class="tour-badge"><?php echo htmlspecialchars(stay_space_type_labels()[$__space['space_type']] ?? 'Villa Space'); ?></span>
                            <h3><?php echo htmlspecialchars($__space['name']); ?></h3>
                            <p><?php echo htmlspecialchars(home_word_excerpt($__space['short_description'] ?: $__space['description'], 30, '...')); ?></p>
                            <div class="home-destination-meta">
                                <span><i class="fas fa-house"></i> <?php echo htmlspecialchars($__space['villa_name']); ?></span>
                                <span><i class="fas fa-bed"></i> <?php echo (int)$__space['units_count']; ?> units</span>
                            </div>
                            <span class="tour-link">Explore <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="tour-card home-destination-card stays-slide" data-stays-slide>
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-door-open"></i></div></div>
                        <div class="tour-content"><span class="tour-badge">Stay</span><h3>Villa Spaces Coming Soon</h3><p>Your villa spaces and bookable stay options will appear here once added from the admin panel.</p><a href="villa.php" class="tour-link">Explore <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="center mt-40">
                <!-- <a href="villa.php" class="btn btn-gold">View All Stays</a> -->
                <a href="villa.php" class="btn btn-gold">View All Stays</a>
            </div>
        </div>
    </section>

    <!-- SERVICES -->
    <section class="section section-light" id="services">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">What We Offer</p>
                <h2 class="section-title">Our Services</h2>
                <p class="section-desc">Every experience here is personal, curated, and delivered with warmth.</p>
            </div>
            <div class="services-grid">
                <?php if (!empty($__home_services)): ?>
                    <?php foreach ($__home_services as $__svc): ?>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas <?php echo htmlspecialchars($__svc['icon'] ?: 'fa-concierge-bell'); ?>"></i></div>
                        <h3><?php echo htmlspecialchars($__svc['title']); ?></h3>
                        <p><?php echo htmlspecialchars($__svc['description']); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="service-card"><div class="service-icon"><i class="fas fa-concierge-bell"></i></div><h3>Butler Service</h3><p>A dedicated host helps with every detail, from room setup to tailored arrangements for your stay.</p></div>
                    <div class="service-card"><div class="service-icon"><i class="fas fa-utensils"></i></div><h3>Private Dining</h3><p>Enjoy authentic Sri Lankan and international cuisine, prepared fresh and served privately in your villa.</p></div>
                    <div class="service-card"><div class="service-icon"><i class="fas fa-map-marked-alt"></i></div><h3>Tour Assistance</h3><p>Curated local excursions and transport arrangements to help you explore Ella and beyond with ease.</p></div>
                    <div class="service-card"><div class="service-icon"><i class="fas fa-car"></i></div><h3>Airport Transfer</h3><p>Comfortable and reliable private transfers arranged from and to the airport at your convenience.</p></div>
                    <div class="service-card"><div class="service-icon"><i class="fas fa-fire"></i></div><h3>Campfire Experience</h3><p>A cozy bonfire setup under the stars, perfect for slow evenings and memorable conversations.</p></div>
                    <div class="service-card"><div class="service-icon"><i class="fas fa-camera"></i></div><h3>Photography Session</h3><p>Capture your memories with a professional photography session amidst the stunning natural scenery.</p></div>
                <?php endif; ?>
            </div>
            <div class="center mt-40">
                <a href="services.php" class="btn btn-brown">View All Services</a>
            </div>
        </div>
    </section>

    <!-- TOURS -->
    <section class="section section-dark" id="tours">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">Explore the Region</p>
                <h2 class="section-title">Tour Packages</h2>
                <p class="section-desc">Discover handpicked Sri Lankan adventures arranged by the 7 Art Villa team.</p>
            </div>
            <?php
            $__cat_label = ['half-day'=>'Half Day','full-day'=>'Full Day','sunrise'=>'Sunrise'];
            ?>
            <div class="tours-grid">
                <?php if (!empty($__home_tours)): ?>
                    <?php foreach ($__home_tours as $__t): ?>
                    <div class="tour-card">
                        <?php if (!empty($__t['image_path']) && file_exists($__t['image_path'])): ?>
                        <div class="tour-image"><img src="<?php echo htmlspecialchars($__t['image_path']); ?>" alt="<?php echo htmlspecialchars($__t['title']); ?>" loading="lazy"></div>
                        <?php else: ?>
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-image"></i></div></div>
                        <?php endif; ?>
                        <div class="tour-content">
                            <span class="tour-badge"><?php echo htmlspecialchars($__cat_label[$__t['category']] ?? ucfirst($__t['category'])); ?></span>
                            <h3><?php echo htmlspecialchars($__t['title']); ?></h3>
                            <p><?php echo htmlspecialchars($__t['tagline'] ?: mb_strimwidth($__t['description'], 0, 100, '...')); ?></p>
                            <a href="tours.php" class="tour-link">Explore <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="tour-card">
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-image"></i></div></div>
                        <div class="tour-content"><span class="tour-badge">Half Day</span><h3>Diyaluma Falls Trek</h3><p>Hike to the base and upper pools of Sri Lanka's second-highest waterfall - a breathtaking experience.</p><a href="tours.php" class="tour-link">Explore <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                    <div class="tour-card">
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-image"></i></div></div>
                        <div class="tour-content"><span class="tour-badge">Full Day</span><h3>Waterfall Discovery Tour</h3><p>Explore hidden waterfalls around Ella and the Haputale region with a private guide.</p><a href="tours.php" class="tour-link">Explore <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                    <div class="tour-card">
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-image"></i></div></div>
                        <div class="tour-content"><span class="tour-badge">Half Day</span><h3>Tea Estate Experience</h3><p>Visit a scenic hill country tea estate, learn about tea production, and enjoy fresh highland tea.</p><a href="tours.php" class="tour-link">Explore <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="center mt-40">
                <a href="tours.php" class="btn btn-gold">View All Packages</a>
            </div>
        </div>
    </section>

    <!-- DESTINATIONS -->
    <section class="section section-light" id="destinations">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">Explore Nearby</p>
                <h2 class="section-title">Destinations</h2>
                <p class="section-desc">Discover beautiful places across Sri Lanka with curated travel notes, map guidance, and things to do.</p>
            </div>
            <div class="tours-grid home-destinations-grid">
                <?php if (!empty($__home_destinations)): ?>
                    <?php foreach ($__home_destinations as $__d): ?>
                    <div class="tour-card home-destination-card">
                        <a href="destination-details.php?slug=<?php echo urlencode($__d['slug']); ?>" class="card-stretch-link" aria-label="Open <?php echo htmlspecialchars($__d['title']); ?>"></a>
                        <?php if (!empty($__d['featured_image_path']) && file_exists($__d['featured_image_path'])): ?>
                        <div class="tour-image"><img src="<?php echo htmlspecialchars($__d['featured_image_path']); ?>" alt="<?php echo htmlspecialchars($__d['title']); ?>" loading="lazy"></div>
                        <?php else: ?>
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-image"></i></div></div>
                        <?php endif; ?>
                        <div class="tour-content">
                            <span class="tour-badge"><?php echo htmlspecialchars($__d['category_names'] ?: 'Destination'); ?></span>
                            <h3><?php echo htmlspecialchars($__d['title']); ?></h3>
                            <p><?php echo htmlspecialchars(home_word_excerpt($__d['short_summary'] ?: $__d['description'], 30, '...')); ?></p>
                            <div class="home-destination-meta">
                                <span><i class="fas fa-route"></i> <?php echo htmlspecialchars($__d['distance_from_villa'] ?: 'Distance unavailable'); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($__d['travel_time_from_villa'] ?: 'Time unavailable'); ?></span>
                            </div>
                            <span class="tour-link">Explore <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="tour-card home-destination-card">
                        <div class="tour-image img-placeholder dark small"><div class="placeholder-label"><i class="fas fa-map-marked-alt"></i></div></div>
                        <div class="tour-content"><span class="tour-badge">Destination</span><h3>Destinations Coming Soon</h3><p>We are preparing curated destination guides around Ella for your next adventure.</p><a href="destinations.php" class="tour-link">Explore <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="center mt-40">
                <a href="destinations.php" class="btn btn-brown">View All Destinations</a>
            </div>
        </div>
    </section>

    <!-- GALLERY -->
    <section class="section section-light" id="gallery">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">Visual Journey</p>
                <h2 class="section-title">Gallery</h2>
                <p class="section-desc">A glimpse into the 7 Art Villa experience.</p>
            </div>
            <?php if (!empty($__home_gallery)): ?>
            <div class="gallery-home-grid">
                <?php foreach ($__home_gallery as $__gi):
                    $__gi_src = ($__gi['image_path'] && file_exists($__gi['image_path'])) ? htmlspecialchars($__gi['image_path']) : null;
                    $__gi_alt = htmlspecialchars($__gi['caption'] ?: '7 Art Villa');
                ?>
                <div class="gallery-item"<?php echo $__gi_src ? ' data-src="'.$__gi_src.'"' : ''; ?>>
                    <?php if ($__gi_src): ?>
                    <img src="<?php echo $__gi_src; ?>" alt="<?php echo $__gi_alt; ?>" loading="lazy">
                    <?php else: ?>
                    <div class="gallery-home-placeholder"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                    <div class="gallery-overlay"><i class="fas fa-expand-alt"></i></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="gallery-home-empty">
                <i class="fas fa-images"></i>
                <p>No gallery images yet - check back soon.</p>
            </div>
            <?php endif; ?>
            <div class="center mt-40">
                <a href="gallery.php" class="btn btn-gold"><i class="fas fa-images"></i> View Full Gallery</a>
            </div>
        </div>
    </section>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
        <button class="lightbox-close" id="lightboxClose"><i class="fas fa-times"></i></button>
        <button class="lightbox-prev"  id="lightboxPrev"><i class="fas fa-chevron-left"></i></button>
        <button class="lightbox-next"  id="lightboxNext"><i class="fas fa-chevron-right"></i></button>
        <div class="lightbox-content">
            <img id="lightboxImg" src="" alt="Gallery Image">
        </div>
    </div>

    <!-- CONTACT -->
    <section class="section section-dark" id="contact">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">Get in Touch</p>
                <h2 class="section-title">Contact &amp; Inquiries</h2>
                <p class="section-desc">Have a question or ready to plan your escape? We'd love to hear from you.</p>
            </div>
            <div class="contact-grid">
                <div class="contact-info">
                    <div class="contact-info-item"><div class="contact-icon"><i class="fas fa-phone"></i></div><div><h4>Phone</h4><a href="tel:<?php echo preg_replace('/\s+/','',$idx_phone); ?>"><?php echo htmlspecialchars($idx_phone); ?></a></div></div>
                    <div class="contact-info-item"><div class="contact-icon"><i class="fas fa-envelope"></i></div><div><h4>Email</h4><a href="mailto:<?php echo htmlspecialchars($idx_email); ?>"><?php echo htmlspecialchars($idx_email); ?></a></div></div>
                    <div class="contact-info-item"><div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div><div><h4>Location</h4><a href="<?php echo htmlspecialchars($idx_maps); ?>" target="_blank" rel="noopener">Ella, Sri Lanka</a></div></div>
                    <div class="contact-social">
                        <?php if ($idx_facebook):   ?><a href="<?php echo htmlspecialchars($idx_facebook); ?>"    target="_blank" rel="noopener" class="social-btn facebook"    aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                        <?php if ($idx_instagram):  ?><a href="<?php echo htmlspecialchars($idx_instagram); ?>"   target="_blank" rel="noopener" class="social-btn instagram"  aria-label="Instagram"><i class="fab fa-instagram"></i></a><?php endif; ?>
                        <?php if ($idx_youtube):    ?><a href="<?php echo htmlspecialchars($idx_youtube); ?>"     target="_blank" rel="noopener" class="social-btn youtube"     aria-label="YouTube"><i class="fab fa-youtube"></i></a><?php endif; ?>
                        <?php if ($idx_tiktok):     ?><a href="<?php echo htmlspecialchars($idx_tiktok); ?>"      target="_blank" rel="noopener" class="social-btn tiktok"      aria-label="TikTok"><i class="fab fa-tiktok"></i></a><?php endif; ?>
                        <?php if ($idx_tripadvisor):?><a href="<?php echo htmlspecialchars($idx_tripadvisor); ?>" target="_blank" rel="noopener" class="social-btn tripadvisor" aria-label="TripAdvisor"><i class="fab fa-tripadvisor"></i></a><?php endif; ?>
                        <?php if ($idx_twitter):    ?><a href="<?php echo htmlspecialchars($idx_twitter); ?>"     target="_blank" rel="noopener" class="social-btn twitter"     aria-label="X / Twitter"><i class="fab fa-x-twitter"></i></a><?php endif; ?>
                        <?php if ($idx_email):      ?><a href="mailto:<?php echo htmlspecialchars($idx_email); ?>" class="social-btn gmail" aria-label="Email"><i class="fas fa-envelope"></i></a><?php endif; ?>
                    </div>
                    <?php if ($idx_maps_embed): ?>
                    <div class="contact-map-embed">
                        <iframe src="<?php echo htmlspecialchars($idx_maps_embed); ?>"
                                width="100%" style="border:0;height:220px;" allowfullscreen=""
                                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                title="7 Art Villa Location"></iframe>
                        <a href="<?php echo htmlspecialchars($idx_maps); ?>" target="_blank" rel="noopener" class="contact-map-link">
                            <i class="fas fa-directions"></i> Get Directions
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="contact-map">
                        <a href="<?php echo htmlspecialchars($idx_maps); ?>" target="_blank" rel="noopener" class="btn btn-outline-gold"><i class="fas fa-map-marked-alt"></i> View on Google Maps</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="contact-form-wrap">
                    <form class="contact-form" id="inquiryForm">
                        <input type="hidden" name="inquiry_type" value="general">
                        <input type="hidden" name="source_page" value="index.php">
                        <div class="form-row">
                            <div class="form-group"><label for="fname">First Name *</label><input type="text" id="fname" name="first_name" placeholder="Your first name" required></div>
                            <div class="form-group"><label for="lname">Last Name *</label><input type="text" id="lname" name="last_name" placeholder="Your last name" required></div>
                        </div>
                        <div class="form-group"><label for="email">Email Address *</label><input type="email" id="email" name="email" placeholder="your@email.com" required></div>
                        <div class="form-group"><label for="phone">Phone Number</label><input type="tel" id="phone" name="phone" placeholder="+94 xx xxx xxxx"></div>
                        <div class="form-row">
                            <div class="form-group"><label for="checkin">Check-in Date</label><input type="date" id="checkin" name="checkin"></div>
                            <div class="form-group"><label for="checkout">Check-out Date</label><input type="date" id="checkout" name="checkout"></div>
                        </div>
                        <div class="form-group"><label for="message">Message *</label><textarea id="message" name="message" rows="5" placeholder="Tell us about your plans or ask any questions..." required></textarea></div>
                        <?php if ($idx_turnstile_active): ?>
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($idx_turnstile_site_key); ?>"></div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-gold full-width">Send Inquiry <i class="fas fa-paper-plane"></i></button>
                        <p class="form-note">We typically respond within 24 hours.</p>
                    </form>
                    <div class="form-success" id="formSuccess">
                        <i class="fas fa-check-circle"></i>
                        <h3>Thank you for your inquiry!</h3>
                        <p>We'll get back to you within 24 hours.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php if (!empty($idx_turnstile_active)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
