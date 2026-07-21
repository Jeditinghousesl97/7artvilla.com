<?php

function site_theme_defaults(): array
{
    return [
        'theme_green' => '#3A7D44',
        'theme_green_dark' => '#2D6235',
        'theme_gold' => '#C8961E',
        'theme_gold_dark' => '#A87A14',
        'theme_brown' => '#6B4226',
        'theme_brown_dark' => '#4E2F1A',
        'theme_dark' => '#1A1A1A',
        'theme_dark_alt' => '#111111',
        'theme_dark_soft' => '#222222',
        'theme_cream' => '#F5F0E8',
        'theme_cream_alt' => '#EDE7DB',
        'theme_text' => '#333333',
        'theme_text_light' => '#666666',
        'theme_text_muted' => '#8A847A',
    ];
}

function site_theme_setting_keys(): array
{
    return array_keys(site_theme_defaults());
}

function site_theme_normalize_hex(?string $value, string $default): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return strtoupper($default);
    }

    if ($value[0] !== '#') {
        $value = '#' . $value;
    }

    if (!preg_match('/^#([0-9a-fA-F]{6})$/', $value)) {
        return strtoupper($default);
    }

    return strtoupper($value);
}

function site_theme_hex_to_rgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    return implode(',', [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ]);
}

function site_theme_resolve(array $values): array
{
    $theme = [];
    foreach (site_theme_defaults() as $key => $default) {
        $theme[$key] = site_theme_normalize_hex($values[$key] ?? '', $default);
    }
    return $theme;
}

function site_theme_load(?PDO $pdo = null): array
{
    $theme = site_theme_defaults();

    try {
        if ($pdo === null) {
            require_once __DIR__ . '/db.php';
            $pdo = db();
        }

        $keys = site_theme_setting_keys();
        $ph = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($ph)");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $theme = site_theme_resolve($rows);
    } catch (Exception $e) {
        $theme = site_theme_defaults();
    }

    return $theme;
}

function site_theme_css_vars(array $theme): string
{
    $green = $theme['theme_green'];
    $greenDark = $theme['theme_green_dark'];
    $gold = $theme['theme_gold'];
    $goldDark = $theme['theme_gold_dark'];
    $brown = $theme['theme_brown'];
    $brownDark = $theme['theme_brown_dark'];
    $dark = $theme['theme_dark'];
    $darkAlt = $theme['theme_dark_alt'];
    $darkSoft = $theme['theme_dark_soft'];
    $cream = $theme['theme_cream'];
    $creamAlt = $theme['theme_cream_alt'];
    $text = $theme['theme_text'];
    $textLight = $theme['theme_text_light'];
    $textMuted = $theme['theme_text_muted'];

    $greenRgb = site_theme_hex_to_rgb($green);
    $goldRgb = site_theme_hex_to_rgb($gold);
    $creamRgb = site_theme_hex_to_rgb($cream);
    $whiteRgb = '255,255,255';

    return ':root{' .
        '--green:' . $green . ';' .
        '--green-d:' . $greenDark . ';' .
        '--green-rgb:' . $greenRgb . ';' .
        '--gold:' . $gold . ';' .
        '--gold-d:' . $goldDark . ';' .
        '--gold-rgb:' . $goldRgb . ';' .
        '--gold-l:rgba(var(--gold-rgb),0.15);' .
        '--brown:' . $brown . ';' .
        '--brown-d:' . $brownDark . ';' .
        '--dark:' . $dark . ';' .
        '--dark2:' . $darkAlt . ';' .
        '--dark3:' . $darkSoft . ';' .
        '--cream:' . $cream . ';' .
        '--cream2:' . $creamAlt . ';' .
        '--cream-rgb:' . $creamRgb . ';' .
        '--white:#FFFFFF;' .
        '--white-rgb:' . $whiteRgb . ';' .
        '--text:' . $text . ';' .
        '--text-light:' . $textLight . ';' .
        '--text-muted:' . $textMuted . ';' .
        '--border:rgba(var(--gold-rgb),0.25);' .
    '}';
}
