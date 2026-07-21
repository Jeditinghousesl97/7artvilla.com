<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/stay-module.php';

$pdo = db();
stay_ensure_schema($pdo);

function seed_upsert_unit(PDO $pdo, int $villaId, ?int $spaceId, array $item): int
{
    $stmt = $pdo->prepare('SELECT id FROM bookable_units WHERE slug = ? LIMIT 1');
    $stmt->execute([$item['slug']]);
    $id = (int)($stmt->fetchColumn() ?: 0);

    $values = [
        $villaId,
        $spaceId,
        $item['name'],
        $item['slug'],
        $item['subtitle'],
        $item['unit_type'],
        $item['summary'],
        $item['description'],
        $item['max_guests'],
        $item['bed_info'],
        $item['size_label'],
        $item['featured_image_path'],
        $item['pricing_note'],
        $item['is_featured'],
        $item['is_active'],
        $item['sort_order'],
    ];

    if ($id > 0) {
        $pdo->prepare('
            UPDATE bookable_units
            SET villa_id=?, villa_space_id=?, name=?, slug=?, subtitle=?, unit_type=?, summary=?, description=?, max_guests=?, bed_info=?,
                size_label=?, featured_image_path=?, pricing_note=?, is_featured=?, is_active=?, sort_order=?
            WHERE id=?
        ')->execute(array_merge($values, [$id]));
        return $id;
    }

    $pdo->prepare('
        INSERT INTO bookable_units
            (villa_id, villa_space_id, name, slug, subtitle, unit_type, summary, description, max_guests, bed_info, size_label, featured_image_path, pricing_note, is_featured, is_active, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ')->execute($values);

    return (int)$pdo->lastInsertId();
}

function seed_replace_pricing(PDO $pdo, int $unitId, array $rows): void
{
    $pdo->prepare('DELETE FROM unit_pricing WHERE bookable_unit_id = ?')->execute([$unitId]);
    $stmt = $pdo->prepare('
        INSERT INTO unit_pricing
            (bookable_unit_id, label, days, price_lkr, price_usd, is_featured, features, sort_order)
        VALUES (?,?,?,?,?,?,?,?)
    ');

    foreach ($rows as $row) {
        $stmt->execute([
            $unitId,
            $row['label'],
            $row['days'],
            $row['price_lkr'],
            $row['price_usd'],
            $row['is_featured'],
            json_encode(array_values($row['features'])),
            $row['sort_order'],
        ]);
    }
}

$villa = $pdo->query("SELECT * FROM villas WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$villa) {
    exit("No active villa found.\n");
}

$space = $pdo->prepare("SELECT * FROM villa_spaces WHERE villa_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
$space->execute([(int)$villa['id']]);
$space = $space->fetch(PDO::FETCH_ASSOC);

$imageVilla = 'assets/images/villa/resort.jpg';
$imageVilla2 = 'assets/images/villa/resort2.jpg';
$imageVilla3 = 'assets/images/villa/resort3.jpg';
$imagePool = 'assets/images/pool/pool.jpg';
$imageHero = 'assets/images/villa/hero-bg.jpg';

$units = [
    [
        'space_id' => $space ? (int)$space['id'] : null,
        'name' => 'Couple Room',
        'slug' => 'essential-couple-room',
        'subtitle' => 'Romantic stay for couples',
        'unit_type' => 'room',
        'summary' => 'A calm, private room ideal for couple stays near the beach.',
        'description' => 'This room is designed for two guests who want a simple but polished stay with privacy, comfort, and quick access to the villa surroundings.',
        'max_guests' => '2 Adults',
        'bed_info' => '1 Queen Bed',
        'size_label' => '320 sq ft',
        'featured_image_path' => $imageVilla2,
        'pricing_note' => 'Breakfast included in the standard rate.',
        'is_featured' => 1,
        'is_active' => 1,
        'sort_order' => 1,
        'pricing' => [
            ['label' => 'Standard Stay', 'days' => 'Daily Rate', 'price_lkr' => 26000, 'price_usd' => 87, 'is_featured' => 1, 'features' => ['Breakfast for 2', 'A/C room', 'Wi-Fi', 'Private bathroom'], 'sort_order' => 1],
            ['label' => 'Weekend Stay', 'days' => 'Fri - Sun', 'price_lkr' => 30000, 'price_usd' => 100, 'is_featured' => 0, 'features' => ['Breakfast for 2', 'A/C room', 'Wi-Fi', 'Late checkout subject to availability'], 'sort_order' => 2],
        ],
    ],
    [
        'space_id' => $space ? (int)$space['id'] : null,
        'name' => 'Family Room',
        'slug' => 'essential-family-room',
        'subtitle' => 'Comfortable room for small families',
        'unit_type' => 'family_room',
        'summary' => 'A larger room layout that suits parents with children.',
        'description' => 'The family room gives extra bedding space and a more flexible layout for longer family stays.',
        'max_guests' => '2 Adults + 2 Kids',
        'bed_info' => '1 Queen + 2 Single Beds',
        'size_label' => '460 sq ft',
        'featured_image_path' => $imagePool,
        'pricing_note' => 'Family meal upgrades available on request.',
        'is_featured' => 0,
        'is_active' => 1,
        'sort_order' => 2,
        'pricing' => [
            ['label' => 'Family Stay', 'days' => 'Daily Rate', 'price_lkr' => 42000, 'price_usd' => 140, 'is_featured' => 1, 'features' => ['Breakfast for 4', 'Family bedding setup', 'Wi-Fi', 'Hot water'], 'sort_order' => 1],
            ['label' => 'Family Half Board', 'days' => 'Daily Rate', 'price_lkr' => 50000, 'price_usd' => 167, 'is_featured' => 0, 'features' => ['Breakfast + dinner', 'Family bedding setup', 'Wi-Fi'], 'sort_order' => 2],
        ],
    ],
    [
        'space_id' => $space ? (int)$space['id'] : null,
        'name' => 'Beach Suite',
        'slug' => 'essential-beach-suite',
        'subtitle' => 'Premium suite option',
        'unit_type' => 'suite',
        'summary' => 'A more spacious stay with a premium suite-style layout.',
        'description' => 'The beach suite is positioned as the premium room category for guests who want more space and a more elevated in-room experience.',
        'max_guests' => '2 Adults',
        'bed_info' => '1 King Bed',
        'size_label' => '500 sq ft',
        'featured_image_path' => $imageVilla3,
        'pricing_note' => 'Best suited for premium short stays and special occasions.',
        'is_featured' => 1,
        'is_active' => 1,
        'sort_order' => 3,
        'pricing' => [
            ['label' => 'Suite Rate', 'days' => 'Daily Rate', 'price_lkr' => 39000, 'price_usd' => 130, 'is_featured' => 1, 'features' => ['Breakfast for 2', 'King bed', 'Premium room setup', 'Wi-Fi'], 'sort_order' => 1],
            ['label' => 'Suite Plus', 'days' => 'Daily Rate', 'price_lkr' => 47000, 'price_usd' => 157, 'is_featured' => 0, 'features' => ['Breakfast + dinner', 'Welcome platter', 'Extended checkout subject to availability'], 'sort_order' => 2],
        ],
    ],
    [
        'space_id' => null,
        'name' => 'Entire Villa',
        'slug' => 'essential-entire-villa',
        'subtitle' => 'Full property booking',
        'unit_type' => 'entire_villa',
        'summary' => 'Reserve the full villa for private family or group use.',
        'description' => 'This option is meant for guests who want the whole property experience with room privacy, shared gathering space, and a more exclusive stay.',
        'max_guests' => '6 - 8 Guests',
        'bed_info' => 'Multiple Room Layout',
        'size_label' => 'Whole Property',
        'featured_image_path' => $imageHero,
        'pricing_note' => 'Group packages and meal bundles can be customized.',
        'is_featured' => 1,
        'is_active' => 1,
        'sort_order' => 10,
        'pricing' => [
            ['label' => 'Private Villa', 'days' => 'Daily Rate', 'price_lkr' => 85000, 'price_usd' => 283, 'is_featured' => 1, 'features' => ['Entire villa use', 'Breakfast', 'Wi-Fi', 'Private common areas'], 'sort_order' => 1],
            ['label' => 'Gold Package', 'days' => 'Daily Rate', 'price_lkr' => 110000, 'price_usd' => 367, 'is_featured' => 0, 'features' => ['Entire villa use', '3 meals', 'Transport support', 'Butler support'], 'sort_order' => 2],
            ['label' => 'Celebration Package', 'days' => 'Daily Rate', 'price_lkr' => 125000, 'price_usd' => 417, 'is_featured' => 0, 'features' => ['Entire villa use', 'Decor support', 'Meals', 'Special event setup'], 'sort_order' => 3],
        ],
    ],
];

$added = 0;
$pricingRows = 0;
foreach ($units as $item) {
    $pricing = $item['pricing'];
    unset($item['pricing']);
    $unitId = seed_upsert_unit($pdo, (int)$villa['id'], $item['space_id'], $item);
    seed_replace_pricing($pdo, $unitId, $pricing);
    $added++;
    $pricingRows += count($pricing);
}

echo "Essential units seeded for villa: {$villa['name']}\n";
echo "units:{$added}\n";
echo "pricing_rows:{$pricingRows}\n";
