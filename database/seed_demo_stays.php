<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/stay-module.php';

$pdo = db();
stay_ensure_schema($pdo);

function upsertAmenity(PDO $pdo, array $item): void
{
    $stmt = $pdo->prepare('SELECT id FROM amenities WHERE name = ? LIMIT 1');
    $stmt->execute([$item['name']]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $pdo->prepare('UPDATE amenities SET icon=?, category=?, description=?, is_active=?, sort_order=? WHERE id=?')
            ->execute([$item['icon'], $item['category'], $item['description'], $item['is_active'], $item['sort_order'], $id]);
        return;
    }

    $pdo->prepare('INSERT INTO amenities (name, icon, category, description, is_active, sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$item['name'], $item['icon'], $item['category'], $item['description'], $item['is_active'], $item['sort_order']]);
}

function upsertVilla(PDO $pdo, array $item): int
{
    $stmt = $pdo->prepare('SELECT id FROM villas WHERE slug = ? LIMIT 1');
    $stmt->execute([$item['slug']]);
    $id = (int)($stmt->fetchColumn() ?: 0);

    $values = [
        $item['name'], $item['location_label'], $item['tagline'], $item['short_description'], $item['description'],
        $item['hero_image_path'], $item['featured_image_path'], $item['checkin_time'], $item['checkout_time'],
        $item['min_stay'], $item['extra_guest_charge'], $item['pricing_note'], $item['max_guests'], $item['bedrooms'],
        $item['pool_label'], $item['is_featured'], $item['is_homepage'], $item['is_active'], $item['sort_order'],
    ];

    if ($id > 0) {
        $pdo->prepare('
            UPDATE villas
            SET name=?, location_label=?, tagline=?, short_description=?, description=?, hero_image_path=?, featured_image_path=?,
                checkin_time=?, checkout_time=?, min_stay=?, extra_guest_charge=?, pricing_note=?, max_guests=?, bedrooms=?,
                pool_label=?, is_featured=?, is_homepage=?, is_active=?, sort_order=?
            WHERE id=?
        ')->execute(array_merge($values, [$id]));
        return $id;
    }

    $pdo->prepare('
        INSERT INTO villas
            (name, slug, location_label, tagline, short_description, description, hero_image_path, featured_image_path,
             checkin_time, checkout_time, min_stay, extra_guest_charge, pricing_note, max_guests, bedrooms, pool_label,
             is_featured, is_homepage, is_active, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ')->execute(array_merge([$item['name'], $item['slug']], array_slice($values, 1)));

    return (int)$pdo->lastInsertId();
}

function upsertSpace(PDO $pdo, int $villaId, array $item): int
{
    $stmt = $pdo->prepare('SELECT id FROM villa_spaces WHERE villa_id = ? AND slug = ? LIMIT 1');
    $stmt->execute([$villaId, $item['slug']]);
    $id = (int)($stmt->fetchColumn() ?: 0);

    $values = [
        $villaId, $item['name'], $item['slug'], $item['subtitle'], $item['space_type'], $item['short_description'],
        $item['description'], $item['featured_image_path'], $item['is_active'], $item['sort_order'],
    ];

    if ($id > 0) {
        $pdo->prepare('
            UPDATE villa_spaces
            SET villa_id=?, name=?, slug=?, subtitle=?, space_type=?, short_description=?, description=?, featured_image_path=?, is_active=?, sort_order=?
            WHERE id=?
        ')->execute(array_merge($values, [$id]));
        return $id;
    }

    $pdo->prepare('
        INSERT INTO villa_spaces
            (villa_id, name, slug, subtitle, space_type, short_description, description, featured_image_path, is_active, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ')->execute($values);

    return (int)$pdo->lastInsertId();
}

function upsertUnit(PDO $pdo, int $villaId, ?int $spaceId, array $item): int
{
    $stmt = $pdo->prepare('SELECT id FROM bookable_units WHERE slug = ? LIMIT 1');
    $stmt->execute([$item['slug']]);
    $id = (int)($stmt->fetchColumn() ?: 0);

    $values = [
        $villaId, $spaceId, $item['name'], $item['slug'], $item['subtitle'], $item['unit_type'], $item['summary'],
        $item['description'], $item['max_guests'], $item['bed_info'], $item['size_label'], $item['featured_image_path'],
        $item['pricing_note'], $item['is_featured'], $item['is_active'], $item['sort_order'],
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

function replacePricing(PDO $pdo, int $unitId, array $rows): void
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

$amenities = [
    ['name' => 'Ocean View', 'icon' => 'fa-water', 'category' => 'Views', 'description' => 'Wide sea-facing outlook.', 'is_active' => 1, 'sort_order' => 10],
    ['name' => 'Private Deck', 'icon' => 'fa-chair', 'category' => 'Outdoor', 'description' => 'Private outdoor seating deck.', 'is_active' => 1, 'sort_order' => 11],
    ['name' => 'Family Friendly', 'icon' => 'fa-children', 'category' => 'Guest Type', 'description' => 'Well-suited for families and children.', 'is_active' => 1, 'sort_order' => 12],
    ['name' => 'Butler Support', 'icon' => 'fa-concierge-bell', 'category' => 'Service', 'description' => 'Dedicated support from the property team.', 'is_active' => 1, 'sort_order' => 13],
];

foreach ($amenities as $amenity) {
    upsertAmenity($pdo, $amenity);
}

$imageVilla = 'assets/images/villa/resort.jpg';
$imageHero = 'assets/images/villa/hero-bg.jpg';
$imagePool = 'assets/images/pool/pool.jpg';
$imageVilla2 = 'assets/images/villa/resort2.jpg';
$imageVilla3 = 'assets/images/villa/resort3.jpg';

$data = [
    [
        'villa' => [
            'name' => 'Arugam Bay Villa',
            'slug' => 'arugam-bay-villa',
            'location_label' => 'Arugam Bay, Sri Lanka',
            'tagline' => 'Signature beachside stay with flexible kabana booking options.',
            'short_description' => 'A coastal villa offering separate kabanas and multiple room categories for couples, families, and full-villa stays.',
            'description' => "Arugam Bay Villa is the flagship stay in the collection.\nIt combines private villa comfort with modular kabana-based booking options ideal for couples, families, and small groups.",
            'hero_image_path' => $imageHero,
            'featured_image_path' => $imageVilla,
            'checkin_time' => '2:00 PM',
            'checkout_time' => '11:00 AM',
            'min_stay' => '1 Night',
            'extra_guest_charge' => 'LKR 7,500 per extra guest',
            'pricing_note' => 'Rates vary by unit type, season, and meal inclusion.',
            'max_guests' => '2 - 10 Guests',
            'bedrooms' => '5 Bedrooms',
            'pool_label' => 'Shared & Private Options',
            'is_featured' => 1,
            'is_homepage' => 1,
            'is_active' => 1,
            'sort_order' => 1,
        ],
        'spaces' => [
            [
                'name' => 'Kabana 01',
                'slug' => 'kabana-01',
                'subtitle' => 'Sea View Beach Kabana',
                'space_type' => 'kabana',
                'short_description' => 'Closest kabana to the beachline with sunset-facing seating.',
                'description' => 'Kabana 01 is designed for guests who want strong beach proximity and a premium private vibe.',
                'featured_image_path' => $imageVilla2,
                'is_active' => 1,
                'sort_order' => 1,
                'units' => [
                    [
                        'name' => 'Couple Room',
                        'slug' => 'kabana-01-couple-room',
                        'subtitle' => 'Best for couples and honeymoon stays',
                        'unit_type' => 'room',
                        'summary' => 'Compact, stylish room with beach-facing deck access.',
                        'description' => 'A romantic stay option with one queen bed, private sit-out, and quick beach access.',
                        'max_guests' => '2 Adults',
                        'bed_info' => '1 Queen Bed',
                        'size_label' => '320 sq ft',
                        'featured_image_path' => $imageVilla2,
                        'pricing_note' => 'Breakfast included. Dinner optional.',
                        'is_featured' => 1,
                        'is_active' => 1,
                        'sort_order' => 1,
                        'pricing' => [
                            ['label' => 'Bed & Breakfast', 'days' => 'Daily Rate', 'price_lkr' => 28000, 'price_usd' => 93, 'is_featured' => 1, 'features' => ['Breakfast for 2', 'A/C room', 'Wi-Fi', 'Private sit-out'], 'sort_order' => 1],
                            ['label' => 'Half Board', 'days' => 'Daily Rate', 'price_lkr' => 34000, 'price_usd' => 113, 'is_featured' => 0, 'features' => ['Breakfast + dinner', 'A/C room', 'Wi-Fi'], 'sort_order' => 2],
                            ['label' => 'Weekend Escape', 'days' => 'Fri - Sun', 'price_lkr' => 36000, 'price_usd' => 120, 'is_featured' => 0, 'features' => ['Breakfast for 2', 'Welcome drink', 'Late checkout subject to availability'], 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Family Room',
                        'slug' => 'kabana-01-family-room',
                        'subtitle' => 'Expanded room layout for families',
                        'unit_type' => 'family_room',
                        'summary' => 'A larger room with extra bedding and family seating space.',
                        'description' => 'Perfect for small families who want one shared room with comfort and easy beach access.',
                        'max_guests' => '2 Adults + 2 Kids',
                        'bed_info' => '1 Queen + 2 Single Beds',
                        'size_label' => '460 sq ft',
                        'featured_image_path' => $imagePool,
                        'pricing_note' => 'Child meals available at extra charge.',
                        'is_featured' => 0,
                        'is_active' => 1,
                        'sort_order' => 2,
                        'pricing' => [
                            ['label' => 'Family Stay', 'days' => 'Daily Rate', 'price_lkr' => 42000, 'price_usd' => 140, 'is_featured' => 1, 'features' => ['Breakfast for 4', 'Family room setup', 'Wi-Fi'], 'sort_order' => 1],
                            ['label' => 'Family Half Board', 'days' => 'Daily Rate', 'price_lkr' => 50000, 'price_usd' => 167, 'is_featured' => 0, 'features' => ['Breakfast + dinner', 'Kids bedding', 'Wi-Fi'], 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Kabana 02',
                'slug' => 'kabana-02',
                'subtitle' => 'Beach View Sand Kabana',
                'space_type' => 'kabana',
                'short_description' => 'A bright kabana with direct garden-to-sand walking path.',
                'description' => 'Kabana 02 is ideal for flexible room category bookings and small-family stays.',
                'featured_image_path' => $imageVilla3,
                'is_active' => 1,
                'sort_order' => 2,
                'units' => [
                    [
                        'name' => 'Beach Suite',
                        'slug' => 'kabana-02-beach-suite',
                        'subtitle' => 'Spacious suite with premium beach mood',
                        'unit_type' => 'suite',
                        'summary' => 'Suite layout with lounge corner and larger bathroom.',
                        'description' => 'A premium suite inside Kabana 02, best for guests wanting more in-room space.',
                        'max_guests' => '2 Adults',
                        'bed_info' => '1 King Bed',
                        'size_label' => '500 sq ft',
                        'featured_image_path' => $imageVilla3,
                        'pricing_note' => 'Upgrade packages available on request.',
                        'is_featured' => 1,
                        'is_active' => 1,
                        'sort_order' => 1,
                        'pricing' => [
                            ['label' => 'Suite Rate', 'days' => 'Daily Rate', 'price_lkr' => 39000, 'price_usd' => 130, 'is_featured' => 1, 'features' => ['Breakfast for 2', 'King bed', 'Lounge corner'], 'sort_order' => 1],
                            ['label' => 'Suite Plus', 'days' => 'Daily Rate', 'price_lkr' => 47000, 'price_usd' => 157, 'is_featured' => 0, 'features' => ['Breakfast + dinner', 'Welcome platter', 'Extended checkout'], 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Kabana 03',
                'slug' => 'kabana-03',
                'subtitle' => 'Forest View Beach Kabana',
                'space_type' => 'kabana',
                'short_description' => 'A more secluded kabana facing the greenery and quieter side of the property.',
                'description' => 'Kabana 03 works well for guests who want privacy, calm, and nature-facing accommodation.',
                'featured_image_path' => $imageVilla,
                'is_active' => 1,
                'sort_order' => 3,
                'units' => [
                    [
                        'name' => 'Nature Room',
                        'slug' => 'kabana-03-nature-room',
                        'subtitle' => 'Quiet room near the garden edge',
                        'unit_type' => 'room',
                        'summary' => 'A private room with garden-facing windows and calm atmosphere.',
                        'description' => 'Good for solo or couple guests who prefer a peaceful stay close to nature.',
                        'max_guests' => '2 Adults',
                        'bed_info' => '1 Double Bed',
                        'size_label' => '300 sq ft',
                        'featured_image_path' => $imageVilla,
                        'pricing_note' => 'Popular for longer, quieter stays.',
                        'is_featured' => 0,
                        'is_active' => 1,
                        'sort_order' => 1,
                        'pricing' => [
                            ['label' => 'Nature Stay', 'days' => 'Daily Rate', 'price_lkr' => 24000, 'price_usd' => 80, 'is_featured' => 1, 'features' => ['Breakfast for 2', 'Garden-facing room', 'Wi-Fi'], 'sort_order' => 1],
                            ['label' => 'Long Stay Offer', 'days' => '3+ Nights', 'price_lkr' => 22000, 'price_usd' => 73, 'is_featured' => 0, 'features' => ['Breakfast for 2', 'Discounted long-stay rate'], 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
        ],
        'general_units' => [
            [
                'name' => 'Entire Villa',
                'slug' => 'arugam-bay-entire-villa',
                'subtitle' => 'Full property booking for groups',
                'unit_type' => 'entire_villa',
                'summary' => 'Reserve the whole villa with all active kabanas and common areas.',
                'description' => 'Ideal for group gatherings, family events, and private escapes.',
                'max_guests' => '8 - 10 Guests',
                'bed_info' => 'Multiple Room Layout',
                'size_label' => 'Whole Property',
                'featured_image_path' => $imageHero,
                'pricing_note' => 'Custom group rates available.',
                'is_featured' => 1,
                'is_active' => 1,
                'sort_order' => 1,
                'pricing' => [
                    ['label' => 'Whole Villa', 'days' => 'Daily Rate', 'price_lkr' => 95000, 'price_usd' => 317, 'is_featured' => 1, 'features' => ['All rooms', 'Breakfast', 'Shared spaces', 'Butler support'], 'sort_order' => 1],
                    ['label' => 'Celebration Package', 'days' => 'Daily Rate', 'price_lkr' => 120000, 'price_usd' => 400, 'is_featured' => 0, 'features' => ['All rooms', '3 meals', 'Setup support', 'Transport coordination'], 'sort_order' => 2],
                ],
            ],
        ],
    ],
    [
        'villa' => [
            'name' => 'Panama Lagoon Retreat',
            'slug' => 'panama-lagoon-retreat',
            'location_label' => 'Panama, Sri Lanka',
            'tagline' => 'Lagoon-edge stay concept for couples and wellness travelers.',
            'short_description' => 'A peaceful retreat with wellness-focused rooms and soft nature views.',
            'description' => "Panama Lagoon Retreat is a quiet concept property built around slow living.\nIt suits couples, remote workers, and wellness-minded travelers.",
            'hero_image_path' => $imageVilla2,
            'featured_image_path' => $imagePool,
            'checkin_time' => '1:00 PM',
            'checkout_time' => '11:00 AM',
            'min_stay' => '2 Nights',
            'extra_guest_charge' => 'Not available for standard rooms',
            'pricing_note' => 'Lagoon rooms are limited and seasonal offers may apply.',
            'max_guests' => '2 - 6 Guests',
            'bedrooms' => '3 Rooms',
            'pool_label' => 'Garden Dip Pool',
            'is_featured' => 1,
            'is_homepage' => 1,
            'is_active' => 1,
            'sort_order' => 2,
        ],
        'spaces' => [
            [
                'name' => 'Lagoon Wing',
                'slug' => 'lagoon-wing',
                'subtitle' => 'Quiet rooms with lagoon-facing balconies',
                'space_type' => 'wing',
                'short_description' => 'A dedicated wing for premium rooms and wellness stays.',
                'description' => 'The Lagoon Wing includes the most peaceful rooms in the retreat.',
                'featured_image_path' => $imagePool,
                'is_active' => 1,
                'sort_order' => 1,
                'units' => [
                    [
                        'name' => 'Lagoon Couple Suite',
                        'slug' => 'lagoon-couple-suite',
                        'subtitle' => 'Wellness-inspired couple suite',
                        'unit_type' => 'suite',
                        'summary' => 'Calm suite with balcony and morning lagoon light.',
                        'description' => 'A premium room option for couples wanting a quiet atmosphere.',
                        'max_guests' => '2 Adults',
                        'bed_info' => '1 King Bed',
                        'size_label' => '420 sq ft',
                        'featured_image_path' => $imagePool,
                        'pricing_note' => 'Optional yoga and breakfast package available.',
                        'is_featured' => 1,
                        'is_active' => 1,
                        'sort_order' => 1,
                        'pricing' => [
                            ['label' => 'Suite Stay', 'days' => 'Daily Rate', 'price_lkr' => 32000, 'price_usd' => 107, 'is_featured' => 1, 'features' => ['Breakfast', 'Balcony', 'Tea setup'], 'sort_order' => 1],
                        ],
                    ],
                ],
            ],
        ],
        'general_units' => [],
    ],
    [
        'villa' => [
            'name' => 'Kudumbigala Forest Villa',
            'slug' => 'kudumbigala-forest-villa',
            'location_label' => 'Kudumbigala Region, Sri Lanka',
            'tagline' => 'Adventure-led stay product with group-friendly room combinations.',
            'short_description' => 'A forest-edge villa concept for hikers, explorers, and nature groups.',
            'description' => "Kudumbigala Forest Villa is designed around exploration.\nIt supports both room-by-room stays and larger group bookings.",
            'hero_image_path' => $imageVilla3,
            'featured_image_path' => $imageVilla3,
            'checkin_time' => '2:00 PM',
            'checkout_time' => '10:30 AM',
            'min_stay' => '1 Night',
            'extra_guest_charge' => 'Available for group packages',
            'pricing_note' => 'Jeep transfers and guided hikes can be bundled.',
            'max_guests' => '2 - 12 Guests',
            'bedrooms' => '6 Rooms',
            'pool_label' => 'No Pool',
            'is_featured' => 0,
            'is_homepage' => 1,
            'is_active' => 1,
            'sort_order' => 3,
        ],
        'spaces' => [
            [
                'name' => 'Trail Block',
                'slug' => 'trail-block',
                'subtitle' => 'Rooms focused on early-morning activity travelers',
                'space_type' => 'block',
                'short_description' => 'Functional but stylish rooms for activity-led stays.',
                'description' => 'This block is closest to departure point coordination and guide meetups.',
                'featured_image_path' => $imageVilla3,
                'is_active' => 1,
                'sort_order' => 1,
                'units' => [
                    [
                        'name' => 'Explorer Twin Room',
                        'slug' => 'explorer-twin-room',
                        'subtitle' => 'Good for friends or guides',
                        'unit_type' => 'room',
                        'summary' => 'Twin room setup with practical comfort and storage.',
                        'description' => 'Great for trekking pairs or photography groups.',
                        'max_guests' => '2 Adults',
                        'bed_info' => '2 Single Beds',
                        'size_label' => '280 sq ft',
                        'featured_image_path' => $imageVilla3,
                        'pricing_note' => 'Adventure breakfast available.',
                        'is_featured' => 0,
                        'is_active' => 1,
                        'sort_order' => 1,
                        'pricing' => [
                            ['label' => 'Twin Room', 'days' => 'Daily Rate', 'price_lkr' => 21000, 'price_usd' => 70, 'is_featured' => 1, 'features' => ['Twin beds', 'Breakfast', 'Hot water'], 'sort_order' => 1],
                        ],
                    ],
                ],
            ],
        ],
        'general_units' => [
            [
                'name' => 'Explorer Group Stay',
                'slug' => 'explorer-group-stay',
                'subtitle' => 'Multi-room booking for adventure groups',
                'unit_type' => 'custom',
                'summary' => 'Custom bundled stay for small teams and families.',
                'description' => 'A group-oriented stay package mixing rooms, meals, and transport support.',
                'max_guests' => '6 - 12 Guests',
                'bed_info' => 'Mixed Room Types',
                'size_label' => 'Custom Package',
                'featured_image_path' => $imageHero,
                'pricing_note' => 'Rates depend on room count and transport choice.',
                'is_featured' => 1,
                'is_active' => 1,
                'sort_order' => 1,
                'pricing' => [
                    ['label' => 'Group Package', 'days' => 'Per Night', 'price_lkr' => 78000, 'price_usd' => 260, 'is_featured' => 1, 'features' => ['Multi-room use', 'Breakfast', 'Guide coordination'], 'sort_order' => 1],
                ],
            ],
        ],
    ],
    [
        'villa' => [
            'name' => 'Dune View House',
            'slug' => 'dune-view-house',
            'location_label' => 'East Coast, Sri Lanka',
            'tagline' => 'Compact premium stay with simple room and whole-house booking options.',
            'short_description' => 'A smaller premium property for private stays and weekend escapes.',
            'description' => "Dune View House is a smaller inventory concept.\nIt helps demonstrate that the system can support both compact and larger properties.",
            'hero_image_path' => $imageVilla,
            'featured_image_path' => $imageVilla2,
            'checkin_time' => '2:30 PM',
            'checkout_time' => '11:30 AM',
            'min_stay' => '1 Night',
            'extra_guest_charge' => 'Only on entire house booking',
            'pricing_note' => 'Short seasonal offers are common for this property.',
            'max_guests' => '2 - 5 Guests',
            'bedrooms' => '2 Rooms',
            'pool_label' => 'Plunge Pool',
            'is_featured' => 0,
            'is_homepage' => 0,
            'is_active' => 1,
            'sort_order' => 4,
        ],
        'spaces' => [
            [
                'name' => 'Main House',
                'slug' => 'main-house',
                'subtitle' => 'Primary indoor accommodation zone',
                'space_type' => 'sub_villa',
                'short_description' => 'Contains the main sleeping and lounge areas.',
                'description' => 'The main house is the only physical section in this smaller property.',
                'featured_image_path' => $imageVilla2,
                'is_active' => 1,
                'sort_order' => 1,
                'units' => [
                    [
                        'name' => 'Weekend Couple Room',
                        'slug' => 'weekend-couple-room',
                        'subtitle' => 'Ideal for short-stay couples',
                        'unit_type' => 'room',
                        'summary' => 'An efficient premium room for quick escapes.',
                        'description' => 'Perfect for one or two-night couple stays.',
                        'max_guests' => '2 Adults',
                        'bed_info' => '1 Double Bed',
                        'size_label' => '260 sq ft',
                        'featured_image_path' => $imageVilla2,
                        'pricing_note' => 'Most popular on weekends.',
                        'is_featured' => 0,
                        'is_active' => 1,
                        'sort_order' => 1,
                        'pricing' => [
                            ['label' => 'Weekend Rate', 'days' => 'Fri - Sun', 'price_lkr' => 26000, 'price_usd' => 87, 'is_featured' => 1, 'features' => ['Breakfast', 'Wi-Fi', 'Parking'], 'sort_order' => 1],
                        ],
                    ],
                ],
            ],
        ],
        'general_units' => [
            [
                'name' => 'Entire House',
                'slug' => 'dune-view-entire-house',
                'subtitle' => 'Private whole-property use',
                'unit_type' => 'entire_villa',
                'summary' => 'Best for small families or two couples traveling together.',
                'description' => 'Reserve the full house, plunge pool, and shared outdoor areas.',
                'max_guests' => '4 - 5 Guests',
                'bed_info' => '2 Bedrooms',
                'size_label' => 'Whole House',
                'featured_image_path' => $imageVilla,
                'pricing_note' => 'Holiday pricing can differ.',
                'is_featured' => 1,
                'is_active' => 1,
                'sort_order' => 1,
                'pricing' => [
                    ['label' => 'Private House', 'days' => 'Daily Rate', 'price_lkr' => 68000, 'price_usd' => 227, 'is_featured' => 1, 'features' => ['Entire house', 'Breakfast', 'Pool access'], 'sort_order' => 1],
                ],
            ],
        ],
    ],
];

$summary = ['villas' => 0, 'spaces' => 0, 'units' => 0, 'pricing' => 0, 'amenities' => count($amenities)];

foreach ($data as $villaData) {
    $villaId = upsertVilla($pdo, $villaData['villa']);
    $summary['villas']++;

    foreach ($villaData['spaces'] as $spaceData) {
        $units = $spaceData['units'];
        unset($spaceData['units']);
        $spaceId = upsertSpace($pdo, $villaId, $spaceData);
        $summary['spaces']++;

        foreach ($units as $unitData) {
            $pricing = $unitData['pricing'];
            unset($unitData['pricing']);
            $unitId = upsertUnit($pdo, $villaId, $spaceId, $unitData);
            replacePricing($pdo, $unitId, $pricing);
            $summary['units']++;
            $summary['pricing'] += count($pricing);
        }
    }

    foreach ($villaData['general_units'] as $unitData) {
        $pricing = $unitData['pricing'];
        unset($unitData['pricing']);
        $unitId = upsertUnit($pdo, $villaId, null, $unitData);
        replacePricing($pdo, $unitId, $pricing);
        $summary['units']++;
        $summary['pricing'] += count($pricing);
    }
}

echo "Seed complete\n";
foreach ($summary as $key => $value) {
    echo $key . ':' . $value . "\n";
}
