<?php

//  Dynamic Sitemap â€” wetrail.lk

header('Content-Type: application/xml; charset=utf-8');

define('SITE_ROOT', 'https://wetrail.lk');

// Pull latest updated_at from dynamic tables
$tours_updated   = null;
$gallery_updated = null;
$services_updated = null;
$destinations_updated = null;
$villas_updated = null;
$villa_spaces_updated = null;
$tour_urls = [];
$destination_urls = [];
$villa_urls = [];
$villa_space_urls = [];

try {
    require_once __DIR__ . '/config/db.php';
    $pdo = db();

    $r = $pdo->query("SELECT MAX(updated_at) FROM tours WHERE is_active = 1");
    $tours_updated = $r->fetchColumn() ?: null;

    $r = $pdo->query("SELECT MAX(created_at) FROM gallery_images WHERE is_active = 1");
    $gallery_updated = $r->fetchColumn() ?: null;

    $r = $pdo->query("SELECT MAX(updated_at) FROM services WHERE is_active = 1");
    $services_updated = $r->fetchColumn() ?: null;

    $r = $pdo->query("SELECT MAX(updated_at) FROM destinations WHERE is_active = 1");
    $destinations_updated = $r->fetchColumn() ?: null;

    $r = $pdo->query("SELECT MAX(updated_at) FROM villas WHERE is_active = 1");
    $villas_updated = $r->fetchColumn() ?: null;

    $r = $pdo->query("
        SELECT MAX(s.updated_at)
        FROM villa_spaces s
        INNER JOIN villas v ON v.id = s.villa_id
        WHERE s.is_active = 1 AND v.is_active = 1
    ");
    $villa_spaces_updated = $r->fetchColumn() ?: null;

    $r = $pdo->query("SELECT id, updated_at FROM tours WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $tour_urls = $r->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $r = $pdo->query("SELECT slug, updated_at FROM destinations WHERE is_active = 1 AND slug <> '' ORDER BY is_featured DESC, sort_order ASC, id ASC");
    $destination_urls = $r->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $r = $pdo->query("SELECT slug, updated_at FROM villas WHERE is_active = 1 AND slug <> '' ORDER BY is_featured DESC, sort_order ASC, id ASC");
    $villa_urls = $r->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $r = $pdo->query("
        SELECT s.slug, s.updated_at, v.slug AS villa_slug
        FROM villa_spaces s
        INNER JOIN villas v ON v.id = s.villa_id
        WHERE s.is_active = 1 AND v.is_active = 1 AND s.slug <> '' AND v.slug <> ''
        ORDER BY v.is_featured DESC, v.sort_order ASC, v.id ASC, s.sort_order ASC, s.id ASC
    ");
    $villa_space_urls = $r->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

// Helper â€” format a date or fall back to today
function xmlDate($date) {
    if ($date) return date('Y-m-d', strtotime($date));
    return date('Y-m-d');
}

// File-based lastmod for static pages
function fileDate($file) {
    $path = __DIR__ . '/' . $file;
    return file_exists($path) ? date('Y-m-d', filemtime($path)) : date('Y-m-d');
}

$today = date('Y-m-d');

// Pages: [url, lastmod, changefreq, priority]
$pages = [
    ['',                 fileDate('index.php'),          'weekly',  '1.0'],
    ['villa.php',        xmlDate($villas_updated),        'weekly',  '0.9'],
    ['services.php',     xmlDate($services_updated),      'monthly', '0.8'],
    ['tours.php',        xmlDate($tours_updated),         'weekly',  '0.8'],
    ['destinations.php', xmlDate($destinations_updated),  'weekly',  '0.8'],
    ['gallery.php',      xmlDate($gallery_updated),       'weekly',  '0.7'],
    ['privacy-policy.php', fileDate('privacy-policy.php'), 'yearly', '0.3'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<?php foreach ($pages as [$path, $lastmod, $freq, $priority]): ?>
    <url>
        <loc><?php echo htmlspecialchars(SITE_ROOT . '/' . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo $freq; ?></changefreq>
        <priority><?php echo $priority; ?></priority>
    </url>
<?php endforeach; ?>

<?php foreach ($tour_urls as $tour): ?>
    <url>
        <loc><?php echo htmlspecialchars(SITE_ROOT . '/tour-details.php?id=' . (int)$tour['id'], ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
        <lastmod><?php echo xmlDate($tour['updated_at'] ?? null); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>

<?php foreach ($destination_urls as $destination): ?>
    <url>
        <loc><?php echo htmlspecialchars(SITE_ROOT . '/destination-details.php?slug=' . urlencode((string)$destination['slug']), ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
        <lastmod><?php echo xmlDate($destination['updated_at'] ?? null); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>

<?php foreach ($villa_urls as $villa): ?>
    <url>
        <loc><?php echo htmlspecialchars(SITE_ROOT . '/villa.php?slug=' . urlencode((string)$villa['slug']), ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
        <lastmod><?php echo xmlDate($villa['updated_at'] ?? null); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>

<?php foreach ($villa_space_urls as $space): ?>
    <url>
        <loc><?php echo htmlspecialchars(SITE_ROOT . '/villa-space.php?villa=' . urlencode((string)$space['villa_slug']) . '&space=' . urlencode((string)$space['slug']), ENT_XML1 | ENT_QUOTES, 'UTF-8'); ?></loc>
        <lastmod><?php echo xmlDate($space['updated_at'] ?? null); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>

</urlset>
