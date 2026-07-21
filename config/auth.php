<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . admin_url('login.php'));
        exit;
    }
}

function login_admin(array $user): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']       = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_name']     = $user['full_name'];
}

function logout_admin(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function admin_name(): string {
    return htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
}

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Resolve admin base URL — works regardless of subfolder or port
function admin_url(string $path = ''): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Find the /admin/ segment in the current URL and use everything up to it
    $pos = strpos($script, '/admin/');
    $base = ($pos !== false) ? substr($script, 0, $pos) . '/admin/' : '/admin/';
    return $base . ltrim($path, '/');
}
