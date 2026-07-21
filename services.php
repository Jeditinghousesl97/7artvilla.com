<?php
$page_title       = 'Our Services | 7 Art Villa';
$page_description = 'Discover the thoughtful services at 7 Art Villa in Ella, from dining and celebrations to tours and more.';
$og_title         = 'Our Services | 7 Art Villa';
$og_description   = 'Discover the thoughtful services at 7 Art Villa in Ella, from dining and celebrations to tours and more.';
$og_url           = 'https://7artvilla.com/services.php';
$og_image         = 'https://7artvilla.com/assets/images/services/hero-bg.jpg';
$page_css         = 'services.css';
$page_js          = 'services.js';
$nav_base         = 'index.php';

require_once 'config/db.php';
try {
    $__pdo  = db();
    $__svcs = $__pdo->query("SELECT * FROM services WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    $__svc_total = count($__svcs);
    // Count per category
    $__svc_counts = [];
    foreach ($__svcs as $__s) {
        $__svc_counts[$__s['category']] = ($__svc_counts[$__s['category']] ?? 0) + 1;
    }
} catch (Exception $e) {
    $__svcs = [];
    $__svc_total = 0;
    $__svc_counts = [];
}

$__svc_badge = ['included' => 'Included', 'request' => 'On Request', 'extra' => 'Extra Charge'];

include 'includes/header.php';
?>

    <!-- PAGE HERO -->
    <section class="services-hero">
        <div class="services-hero-bg">
            <img src="assets/images/services/hero-bg.jpg" alt="7 Art Villa Services" fetchpriority="high">
        </div>
        <div class="services-hero-overlay"></div>
        <div class="container">
            <div class="services-hero-content">
                <a href="index.php" class="hero-back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
                <p class="section-label">Curated for You</p>
                <h1>Our <span>Services</span></h1>
                <p class="services-hero-sub">Every experience at 7 Art Villa is personal, unhurried, and delivered with genuine warmth - because true luxury is in the details.</p>
                <div class="services-hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-num">12+</span>
                        <span class="hero-stat-label">Services</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num">24/7</span>
                        <span class="hero-stat-label">Butler</span>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <span class="hero-stat-num">100%</span>
                        <span class="hero-stat-label">Private</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="services-hero-scroll">
            <a href="#services-intro"><i class="fas fa-chevron-down"></i></a>
        </div>
    </section>

    <!-- INTRO -->
    <section class="section section-dark services-intro" id="services-intro">
        <div class="container">
            <div class="services-intro-grid">
                <div class="services-intro-text">
                    <p class="section-label">The 7 Art Villa Promise</p>
                    <h2 class="section-title">Service as an Art Form</h2>
                    <p class="section-text" style="color:rgba(245,240,232,0.7)">
                        At 7 Art Villa, service is an expression of hospitality. From the moment you arrive until you depart, every detail is considered and every moment is shaped around a peaceful stay.
                    </p>
                    <p class="section-text" style="color:rgba(245,240,232,0.7)">
                        Your dedicated butler is your single point of contact for everything - day or night. Nothing is too small, nothing too large. Your comfort is our purpose.
                    </p>
                </div>
                <div class="services-intro-pillars">
                    <div class="pillar">
                        <div class="pillar-icon"><i class="fas fa-lock"></i></div>
                        <h4>Complete Privacy</h4>
                        <p>The entire resort is yours alone. No other guests, no shared spaces - ever.</p>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><i class="fas fa-user-tie"></i></div>
                        <h4>Personal Butler</h4>
                        <p>A dedicated butler at your service around the clock, anticipating your every need.</p>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><i class="fas fa-star"></i></div>
                        <h4>Bespoke Experiences</h4>
                        <p>Every service is tailored to you - your preferences, your pace, your vision.</p>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><i class="fas fa-leaf"></i></div>
                        <h4>Eco Conscious</h4>
                        <p>Luxury that respects the natural environment - sustainable practices at every step.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES GRID -->
    <section class="section section-light" id="all-services">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">What We Offer</p>
                <h2 class="section-title">All Services</h2>
                <p class="section-desc">A full range of thoughtfully designed services to make your stay seamless, special, and unforgettable.</p>
            </div>

            <!-- Filter Tabs -->
            <div class="service-filters">
                <button class="filter-btn active" data-filter="all">All Services <?php if ($__svc_total > 0): ?><span class="filter-count">(<?php echo $__svc_total; ?>)</span><?php endif; ?></button>
                <?php if (!empty($__svc_counts['included'])): ?><button class="filter-btn" data-filter="included">Included in Stay <span class="filter-count">(<?php echo $__svc_counts['included']; ?>)</span></button><?php endif; ?>
                <?php if (!empty($__svc_counts['request'])): ?><button class="filter-btn" data-filter="request">On Request <span class="filter-count">(<?php echo $__svc_counts['request']; ?>)</span></button><?php endif; ?>
                <?php if (!empty($__svc_counts['extra'])): ?><button class="filter-btn" data-filter="extra">Extra Charge <span class="filter-count">(<?php echo $__svc_counts['extra']; ?>)</span></button><?php endif; ?>
            </div>

            <div class="services-full-grid" id="servicesGrid">

                <?php if (!empty($__svcs)): ?>
                <?php foreach ($__svcs as $__sv):
                    $__sv_icon    = $__sv['icon'] ?: 'fa-concierge-bell';
                    $__sv_cat     = $__sv['category'];
                    $__sv_badge   = $__svc_badge[$__sv_cat] ?? ucfirst($__sv_cat);
                    $__sv_feats   = [];
                    if (!empty($__sv['features'])) {
                        $__sv_feats = json_decode($__sv['features'], true) ?: [];
                    }
                    $__sv_has_img = !empty($__sv['image_path']) && file_exists($__sv['image_path']);
                ?>
                <div class="service-full-card" data-category="<?php echo htmlspecialchars($__sv_cat); ?>">
                    <div class="sfc-image">
                        <?php if ($__sv_has_img): ?>
                        <img src="<?php echo htmlspecialchars($__sv['image_path']); ?>" alt="<?php echo htmlspecialchars($__sv['title']); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="sfc-img-placeholder">
                            <i class="fas <?php echo htmlspecialchars($__sv_icon); ?>"></i>
                        </div>
                        <?php endif; ?>
                        <div class="sfc-badge <?php echo htmlspecialchars($__sv_cat); ?>"><?php echo htmlspecialchars($__sv_badge); ?></div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas <?php echo htmlspecialchars($__sv_icon); ?>"></i></div>
                        <h3><?php echo htmlspecialchars($__sv['title']); ?></h3>
                        <p><?php echo htmlspecialchars($__sv['description']); ?></p>
                        <?php if (!empty($__sv_feats)): ?>
                        <ul class="sfc-list">
                            <?php foreach ($__sv_feats as $__feat): ?>
                            <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($__feat); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <!-- No services in database yet -->
                <div class="service-full-card" data-category="included">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <!-- Replace: <img src="assets/images/services/butler.jpg" alt="Butler Service"> -->
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="sfc-badge included">Included</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-user-tie"></i></div>
                        <h3>Personal Butler Service</h3>
                        <p>Your dedicated personal butler is available 24 hours a day, 7 days a week. From organising your meals and activities to arranging special surprises, your butler is your guide, your host, and your confidant throughout your stay.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> 24/7 dedicated butler</li>
                            <li><i class="fas fa-check"></i> Room & villa setup on request</li>
                            <li><i class="fas fa-check"></i> Activity & dining arrangements</li>
                            <li><i class="fas fa-check"></i> Special occasion planning</li>
                        </ul>
                    </div>
                </div>

                <!-- Private Dining -->
                <div class="service-full-card" data-category="included">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="sfc-badge included">Included</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-utensils"></i></div>
                        <h3>In-Villa Private Dining</h3>
                        <p>Enjoy freshly prepared meals served privately in your villa - at the dining table, by the pool, or under the stars. Our chef prepares authentic Sri Lankan cuisine alongside international dishes, tailored to your dietary preferences.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Daily breakfast included</li>
                            <li><i class="fas fa-check"></i> Lunch & dinner on request</li>
                            <li><i class="fas fa-check"></i> Sri Lankan & international menu</li>
                            <li><i class="fas fa-check"></i> Dietary requirements catered for</li>
                        </ul>
                    </div>
                </div>

                <!-- Housekeeping -->
                <div class="service-full-card" data-category="included">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-broom"></i>
                        </div>
                        <div class="sfc-badge included">Included</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-broom"></i></div>
                        <h3>Daily Housekeeping</h3>
                        <p>The villa is serviced twice daily - a full morning clean and an evening turndown service. Fresh linen, replenished toiletries, and a perfectly prepared villa await you each time you return.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Full morning housekeeping</li>
                            <li><i class="fas fa-check"></i> Evening turndown service</li>
                            <li><i class="fas fa-check"></i> Daily fresh linen & towels</li>
                            <li><i class="fas fa-check"></i> Minibar & amenity replenishment</li>
                        </ul>
                    </div>
                </div>

                <!-- Welcome Amenity -->
                <div class="service-full-card" data-category="included">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-gift"></i>
                        </div>
                        <div class="sfc-badge included">Included</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-gift"></i></div>
                        <h3>Welcome Experience</h3>
                        <p>Your arrival sets the tone. A personalised welcome awaits - chilled coconut water, seasonal fruits, a handwritten note, and a specially prepared welcome gift that reflects the natural beauty of the region.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Personalised welcome gift</li>
                            <li><i class="fas fa-check"></i> Fresh fruit basket</li>
                            <li><i class="fas fa-check"></i> Welcome beverages on arrival</li>
                            <li><i class="fas fa-check"></i> Villa walk-through & orientation</li>
                        </ul>
                    </div>
                </div>

                <!-- Romantic Setup -->
                <div class="service-full-card" data-category="request">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="sfc-badge request">On Request</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-heart"></i></div>
                        <h3>Romantic Setups &amp; Celebrations</h3>
                        <p>From rose petal trails and candlelit pool setups to anniversary surprises and birthday cakes - our team creates deeply personal, beautifully executed moments that you will carry with you long after you leave.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Rose petal & candle arrangements</li>
                            <li><i class="fas fa-check"></i> Candlelit private pool dinner</li>
                            <li><i class="fas fa-check"></i> Custom cakes & celebration setups</li>
                            <li><i class="fas fa-check"></i> Proposal & anniversary planning</li>
                        </ul>
                    </div>
                </div>

                <!-- Campfire -->
                <div class="service-full-card" data-category="request">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="sfc-badge request">On Request</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-fire"></i></div>
                        <h3>Starlit Campfire Experience</h3>
                        <p>As night falls over the highlands, gather around a private bonfire under a sky full of stars. Marshmallows, hot chocolate, and the sounds of the forest - a moment of pure, unhurried magic for couples and families.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Private bonfire setup</li>
                            <li><i class="fas fa-check"></i> Marshmallows & hot beverages</li>
                            <li><i class="fas fa-check"></i> Comfortable outdoor seating</li>
                            <li><i class="fas fa-check"></i> Safe, attended fire management</li>
                        </ul>
                    </div>
                </div>

                <!-- Photography -->
                <div class="service-full-card" data-category="extra">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="sfc-badge extra">Extra Charge</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-camera"></i></div>
                        <h3>Professional Photography</h3>
                        <p>Let the stunning surroundings of Diyaluma and the villa itself be the backdrop for your story. Our recommended professional photographers capture natural moments - from couple and family portraits to full resort shoots.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Couple, family & portrait sessions</li>
                            <li><i class="fas fa-check"></i> Golden hour & night shoots</li>
                            <li><i class="fas fa-check"></i> High-resolution edited gallery</li>
                            <li><i class="fas fa-check"></i> Drone photography available</li>
                        </ul>
                    </div>
                </div>

                <!-- Celebration Planning -->
                <div class="service-full-card" data-category="extra">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-glass-cheers"></i>
                        </div>
                        <div class="sfc-badge extra">Extra Charge</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-glass-cheers"></i></div>
                        <h3>Celebration Planning</h3>
                        <p>Mark a birthday, anniversary, proposal, or family milestone with thoughtful decorations, private dining arrangements, custom cakes, and photography support.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Birthday & anniversary setups</li>
                            <li><i class="fas fa-check"></i> Proposal arrangements</li>
                            <li><i class="fas fa-check"></i> Custom cakes & decorations</li>
                            <li><i class="fas fa-check"></i> Photography coordination</li>
                        </ul>
                    </div>
                </div>

                <!-- Airport Transfer -->
                <div class="service-full-card" data-category="extra">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="sfc-badge extra">Extra Charge</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-car"></i></div>
                        <h3>Private Airport Transfer</h3>
                        <p>Begin and end your journey in comfort. We arrange private, air-conditioned vehicles for seamless transfers between Bandaranaike International Airport (CMB) or Ratmalana Airport and the resort - with a driver who knows every turn of the highland roads.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Private air-conditioned vehicle</li>
                            <li><i class="fas fa-check"></i> BIA (CMB) & Ratmalana airport</li>
                            <li><i class="fas fa-check"></i> Flexible pick-up & drop-off times</li>
                            <li><i class="fas fa-check"></i> Meet & greet service available</li>
                        </ul>
                    </div>
                </div>

                <!-- BBQ & Private Dining -->
                <div class="service-full-card" data-category="request">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-drumstick-bite"></i>
                        </div>
                        <div class="sfc-badge request">On Request</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-drumstick-bite"></i></div>
                        <h3>Private BBQ &amp; Outdoor Dining</h3>
                        <p>Take your dining experience outdoors with a private poolside BBQ or a lantern-lit garden dinner. Our chef prepares a selection of grilled meats, fresh seafood, and vegetarian options, served course by course as the forest comes alive at night.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Poolside BBQ dinners</li>
                            <li><i class="fas fa-check"></i> Lantern-lit garden dining</li>
                            <li><i class="fas fa-check"></i> Custom menus on request</li>
                            <li><i class="fas fa-check"></i> Chef-attended table service</li>
                        </ul>
                    </div>
                </div>

                <!-- Laundry -->
                <div class="service-full-card" data-category="extra">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <div class="sfc-badge extra">Extra Charge</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-tshirt"></i></div>
                        <h3>Laundry &amp; Valet Service</h3>
                        <p>Leave the details to us. Simply place your garments in the provided laundry bag, and our team will have them washed, pressed, and returned to your villa - fresh and folded - within a few hours.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Same-day laundry service</li>
                            <li><i class="fas fa-check"></i> Ironing & pressing</li>
                            <li><i class="fas fa-check"></i> Delicate garment care available</li>
                            <li><i class="fas fa-check"></i> Returned neatly folded or hung</li>
                        </ul>
                    </div>
                </div>

                <!-- Local Transport -->
                <div class="service-full-card" data-category="extra">
                    <div class="sfc-image">
                        <div class="sfc-img-placeholder">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="sfc-badge extra">Extra Charge</div>
                    </div>
                    <div class="sfc-content">
                        <div class="sfc-icon"><i class="fas fa-map-marked-alt"></i></div>
                        <h3>Local Excursions &amp; Transport</h3>
                        <p>Explore the spectacular highland region at your own pace with our private vehicle and knowledgeable local driver. From Diyaluma Falls and Haputale viewpoints to Ella Rock and Bandarawela markets - we take you there, safely and comfortably.</p>
                        <ul class="sfc-list">
                            <li><i class="fas fa-check"></i> Private driver & vehicle</li>
                            <li><i class="fas fa-check"></i> Diyaluma Falls day trips</li>
                            <li><i class="fas fa-check"></i> Haputale & Ella excursions</li>
                            <li><i class="fas fa-check"></i> Custom itinerary on request</li>
                        </ul>
                    </div>
                </div>

                <?php endif; ?>
            </div>

            <div class="no-results" id="noResults" style="display:none;">
                <i class="fas fa-search"></i>
                <p>No services found for this filter.</p>
            </div>
        </div>
    </section>

    <!-- SERVICE PROCESS -->
    <section class="section section-dark" id="how-it-works">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">How It Works</p>
                <h2 class="section-title">Requesting a Service</h2>
                <p class="section-desc">Everything is just a conversation away. Here's how our service process works.</p>
            </div>
            <div class="process-grid">
                <div class="process-step">
                    <div class="process-number">01</div>
                    <div class="process-icon"><i class="fas fa-comments"></i></div>
                    <h3>Tell Your Butler</h3>
                    <p>Simply speak to your dedicated butler or send a message - any time of day or night. No forms, no waiting on hold.</p>
                </div>
                <div class="process-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="process-step">
                    <div class="process-number">02</div>
                    <div class="process-icon"><i class="fas fa-clipboard-check"></i></div>
                    <h3>We Arrange Everything</h3>
                    <p>Your butler coordinates all the details behind the scenes - from ingredients to scheduling - so you don't have to think about a thing.</p>
                </div>
                <div class="process-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="process-step">
                    <div class="process-number">03</div>
                    <div class="process-icon"><i class="fas fa-magic"></i></div>
                    <h3>Sit Back &amp; Enjoy</h3>
                    <p>Your experience is delivered seamlessly, exactly as you envisioned - personalised, private, and perfectly executed.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SPECIAL OCCASIONS -->
    <section class="section section-light" id="occasions">
        <div class="container">
            <div class="section-header center">
                <p class="section-label">Celebrate with Us</p>
                <h2 class="section-title">Special Occasions</h2>
                <p class="section-desc">Let us help you create a moment worth remembering forever.</p>
            </div>
            <div class="occasions-grid">
                <div class="occasion-card">
                    <div class="occasion-icon"><i class="fas fa-ring"></i></div>
                    <h3>Proposals</h3>
                    <p>A private poolside proposal setup with flowers, candles, and your choice of champagne or Ceylon tea. We'll even arrange the photographer to capture the moment.</p>
                </div>
                <div class="occasion-card">
                    <div class="occasion-icon"><i class="fas fa-heart"></i></div>
                    <h3>Anniversaries</h3>
                    <p>Celebrate your love story with a bespoke romantic package - floral decorations, private dinner, and a personalised anniversary cake.</p>
                </div>
                <div class="occasion-card">
                    <div class="occasion-icon"><i class="fas fa-birthday-cake"></i></div>
                    <h3>Birthdays</h3>
                    <p>Make their day extraordinary - with a decorated villa, a custom birthday cake, a surprise breakfast by the pool, and an evening campfire celebration.</p>
                </div>
                <div class="occasion-card">
                    <div class="occasion-icon"><i class="fas fa-moon"></i></div>
                    <h3>Honeymoons</h3>
                    <p>Begin your forever in paradise. Our honeymoon package includes romantic turndown, champagne on arrival, floral decorations, and a private candlelit dinner under the stars.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="services-cta-section">
        <div class="services-cta-overlay"></div>
        <div class="container">
            <div class="services-cta-content">
                <p class="section-label">Get in Touch</p>
                <h2>Have a Special Request?</h2>
                <p>Every guest is unique. If there's something you'd like that isn't listed here - just ask. We'll do our absolute best to make it happen.</p>
                <div class="services-cta-actions">
                    <a href="index.php#contact" class="btn btn-gold">
                        <i class="fas fa-paper-plane"></i> Send an Inquiry
                    </a>
                    <a href="tel:+94773870850" class="btn btn-outline">
                        <i class="fas fa-phone"></i> 077 387 0850
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
