<?php
// Footer: load contact / social settings from DB 
try {
    if (!function_exists('db')) require_once __DIR__ . '/../config/db.php';
    $__fpdo  = db();
    $__fkeys = ['phone','whatsapp','email','facebook','instagram','youtube','tiktok','tripadvisor','twitter','maps_url'];
    $__fph   = implode(',', array_fill(0, count($__fkeys), '?'));
    $__fst   = $__fpdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($__fph)");
    $__fst->execute($__fkeys);
    $__fs    = $__fst->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) { $__fs = []; }

$__f_phone       = $__fs['phone']        ?? '077 387 0850';
$__f_whatsapp    = $__fs['whatsapp']     ?? '94773870850';
$__f_email       = $__fs['email']        ?? 'info@7artvilla.com';
$__f_facebook    = $__fs['facebook']     ?? '';
$__f_instagram   = $__fs['instagram']   ?? '';
$__f_youtube     = $__fs['youtube']     ?? '';
$__f_tiktok      = $__fs['tiktok']      ?? '';
$__f_tripadvisor = $__fs['tripadvisor'] ?? '';
$__f_twitter     = $__fs['twitter']     ?? '';
$__f_maps_url    = $__fs['maps_url']    ?? 'https://www.google.com/maps/search/?api=1&query=Ella%2C%20Sri%20Lanka';
$__f_phone_href  = 'tel:' . preg_replace('/\s+/', '', $__f_phone);
$__f_wa_url      = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $__f_whatsapp)
                 . '?text=Hello%2C%20I%20am%20interested%20in%20booking%207%20Art%20Villa.';
?>
    <!-- FOOTER CTA STRIP -->
    <div class="footer-cta-strip">
        <div class="container">
            <div class="footer-cta-inner">
                <div class="footer-cta-text">
                    <h3>Ready to Escape?</h3>
                    <p>Plan your peaceful Ella escape at 7 Art Villa.</p>
                </div>
                <div class="footer-cta-actions">
                    <a href="index.php#contact" class="btn btn-gold">Make an Inquiry</a>
                    <a href="<?php echo $__f_phone_href; ?>" class="btn btn-outline"><i class="fas fa-phone"></i> Call Now</a>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">

                <!-- Brand -->
                <div class="footer-brand">
                    <a href="index.php">
                        <img src="assets/images/logo.png" alt="7 Art Villa" class="footer-logo" loading="lazy">
                    </a>
                    <p>7 Art Villa is an eco villa in Ella, Sri Lanka, created for peaceful stays surrounded by nature.</p>
                    <div class="footer-social">
                        <?php if ($__f_facebook):   ?><a href="<?php echo htmlspecialchars($__f_facebook); ?>"   target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                        <?php if ($__f_instagram):  ?><a href="<?php echo htmlspecialchars($__f_instagram); ?>"  target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a><?php endif; ?>
                        <?php if ($__f_youtube):    ?><a href="<?php echo htmlspecialchars($__f_youtube); ?>"    target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a><?php endif; ?>
                        <?php if ($__f_tiktok):     ?><a href="<?php echo htmlspecialchars($__f_tiktok); ?>"     target="_blank" rel="noopener" aria-label="TikTok"><i class="fab fa-tiktok"></i></a><?php endif; ?>
                        <?php if ($__f_tripadvisor):?><a href="<?php echo htmlspecialchars($__f_tripadvisor); ?>" target="_blank" rel="noopener" aria-label="TripAdvisor"><i class="fab fa-tripadvisor"></i></a><?php endif; ?>
                        <?php if ($__f_twitter):    ?><a href="<?php echo htmlspecialchars($__f_twitter); ?>"    target="_blank" rel="noopener" aria-label="X / Twitter"><i class="fab fa-x-twitter"></i></a><?php endif; ?>
                        <?php if ($__f_email):      ?><a href="mailto:<?php echo htmlspecialchars($__f_email); ?>" aria-label="Email"><i class="fas fa-envelope"></i></a><?php endif; ?>
                    </div>
                    <div class="footer-badge"><i class="fas fa-leaf"></i><span>Eco-Certified Retreat</span></div>
                </div>

                <!-- Quick Links -->
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="villa.php">Villas</a></li>
                        <li><a href="services.php">Our Services</a></li>
                        <li><a href="tours.php">Tour Packages</a></li>
                        <li><a href="destinations.php">Destinations</a></li>
                        <li><a href="gallery.php">Gallery</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                    </ul>
                </div>

                <!-- Explore -->
                <div class="footer-links">
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="villa.php">Villa Details</a></li>
                        <li><a href="services.php">All Services</a></li>
                        <li><a href="tours.php">Tour Packages</a></li>
                        <li><a href="destinations.php">Destinations</a></li>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    </ul>
                    <div class="footer-award">
                        <i class="fas fa-mountain"></i>
                        <div>
                            <span class="award-title">Ella, Sri Lanka</span>
                            <span class="award-sub">Breath of Serenity</span>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="footer-contact">
                    <h4>Get In Touch</h4>
                    <ul>
                        <li><i class="fas fa-phone-alt"></i><div><span class="contact-label">Phone</span><a href="<?php echo $__f_phone_href; ?>"><?php echo htmlspecialchars($__f_phone); ?></a></div></li>
                        <li><i class="fas fa-envelope"></i><div><span class="contact-label">Email</span><a href="mailto:<?php echo htmlspecialchars($__f_email); ?>"><?php echo htmlspecialchars($__f_email); ?></a></div></li>
                        <li><i class="fas fa-map-marker-alt"></i><div><span class="contact-label">Location</span><a href="<?php echo htmlspecialchars($__f_maps_url); ?>" target="_blank" rel="noopener">Ella,<br>Sri Lanka</a></div></li>
                    </ul>
                </div>

            </div>
            <div class="footer-divider"><span class="footer-divider-icon"><i class="fas fa-leaf"></i></span></div>
            <div class="footer-bottom">
                <div class="footer-bottom-copy">
                    <p>&copy; <?php echo date('Y'); ?> 7 Art Villa. All Rights Reserved.</p>
                </div>
                <div class="footer-bottom-legal">
                    <a href="privacy-policy.php">Privacy Policy</a>
                    <span class="footer-sep">|</span>
                    <a href="privacy-policy.php#terms">Terms of Use</a>
                    <span class="footer-sep">|</span>
                    <a href="index.php#contact">Inquiries</a>
                </div>


<!--
                <div class="footer-bottom-credit">
                    <p>Designed &amp; Developed by <a href="https://www.asseminate.com/" target="_blank" rel="noopener">Asseminate <i class="fas fa-external-link-alt"></i></a></p>
                </div>
-->


            </div>
        </div>
    </footer>

    <!-- WhatsApp -->
    <a href="<?php echo htmlspecialchars($__f_wa_url); ?>"
       class="whatsapp-float" id="waFloat" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Back to Top -->
    <a href="#" class="back-to-top" id="backToTop" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>
    <?php if (!empty($page_js)): ?>
    <script src="assets/js/<?php echo htmlspecialchars($page_js); ?>"></script>
    <?php endif; ?>

</body>
</html>
