<?php

require_once __DIR__ . '/db.php';

/**
 * Fetch Cloudflare Turnstile settings from site_settings.
 */
function turnstile_get_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'enabled' => false,
        'site_key' => '',
        'secret_key' => '',
    ];

    try {
        $pdo = db();
        $keys = ['turnstile_enabled', 'turnstile_site_key', 'turnstile_secret_key'];
        $ph = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ($ph)");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $enabled = ($rows['turnstile_enabled'] ?? '0') === '1';
        $siteKey = trim((string)($rows['turnstile_site_key'] ?? ''));
        $secret = trim((string)($rows['turnstile_secret_key'] ?? ''));

        $settings = [
            'enabled' => $enabled && $siteKey !== '' && $secret !== '',
            'site_key' => $siteKey,
            'secret_key' => $secret,
        ];
    } catch (Exception $e) {
        // Keep defaults (disabled)
    }

    return $settings;
}

/**
 * Verify Turnstile token against Cloudflare.
 * Returns true when disabled (so existing forms continue to work).
 */
function turnstile_verify_token(string $token, string $remoteIp = ''): bool
{
    $cfg = turnstile_get_settings();
    if (!$cfg['enabled']) {
        return true;
    }

    if ($token === '') {
        return false;
    }

    $postFields = http_build_query([
        'secret' => $cfg['secret_key'],
        'response' => $token,
        'remoteip' => $remoteIp,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                        "Content-Length: " . strlen($postFields) . "\r\n",
            'content' => $postFields,
            'timeout' => 10,
        ],
    ]);

    $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($resp === false) {
        return false;
    }

    $data = json_decode($resp, true);
    return is_array($data) && !empty($data['success']);
}

