<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/turnstile.php';

// Already logged in â†’ go to dashboard
if (is_logged_in()) {
    header('Location: ' . admin_url('dashboard.php'));
    exit;
}

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECS = 15 * 60; // 15 minutes

$error        = '';
$is_locked    = false;
$locked_until = 0;

// Check existing lockout 
$now          = time();
$locked_until = $_SESSION['login_locked_until'] ?? 0;

if ($locked_until > $now) {
    $is_locked = true;
    $remaining = $locked_until - $now;
    $mins      = ceil($remaining / 60);
    $error     = "Too many failed attempts. Try again in {$mins} minute" . ($mins !== 1 ? 's' : '') . '.';
}

if (!$is_locked && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $tsToken = trim($_POST['cf-turnstile-response'] ?? '');
        $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!turnstile_verify_token($tsToken, $ipAddr)) {
            $error = 'Security verification failed. Please try again.';
        } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $stmt = db()->prepare('SELECT id, username, full_name, password FROM admin_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Success  -  clear lockout state, log in
                unset($_SESSION['login_fails'], $_SESSION['login_locked_until']);
                db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
                login_admin($user);
                header('Location: ' . admin_url('dashboard.php'));
                exit;
            } else {
                sleep(1); // base delay
                $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;

                if ($_SESSION['login_fails'] >= LOGIN_MAX_ATTEMPTS) {
                    // Trigger lockout
                    $_SESSION['login_locked_until'] = $now + LOGIN_LOCKOUT_SECS;
                    $_SESSION['login_fails']        = 0;
                    $is_locked    = true;
                    $locked_until = $_SESSION['login_locked_until'];
                    $error        = 'Too many failed attempts. Access locked for 15 minutes.';
                } else {
                    $left  = LOGIN_MAX_ATTEMPTS - $_SESSION['login_fails'];
                    $error = 'Incorrect username or password. ' . $left . ' attempt' . ($left !== 1 ? 's' : '') . ' remaining.';
                }
            }
        }
        }
    }
}

$__ts_login = turnstile_get_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | 7 Art Villa</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta name="theme-color" content="#1a1a1a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <?php if (!empty($__ts_login['enabled']) && !empty($__ts_login['site_key'])): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="login-body">

    <div class="login-bg">
        <div class="login-bg-img"></div>
        <div class="login-bg-overlay"></div>
    </div>

    <div class="login-wrap">

        <!-- Card -->
        <div class="login-card">

            <!-- Logo -->
            <div class="login-logo">
                <img src="../assets/images/logo.png" alt="7 Art Villa">
                <div class="login-logo-text">
                    <span class="login-resort-name">7 Art Villa</span>
                    <span class="login-portal-label">Admin Portal</span>
                </div>
            </div>

            <div class="login-divider"></div>

            <h1 class="login-title">Welcome Back</h1>
            <p class="login-sub">Sign in to manage the resort</p>

            <!-- Error / Lockout -->
            <?php if ($error): ?>
            <div class="login-alert<?php echo $is_locked ? ' login-alert-locked' : ''; ?>">
                <i class="fas <?php echo $is_locked ? 'fa-lock' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
                <?php if ($is_locked): ?>
                <span class="lockout-timer"> Unlocks in <strong id="lockCountdown"></strong>.</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form class="login-form" method="POST" action="" autocomplete="on" novalidate<?php echo $is_locked ? ' style="opacity:0.45;pointer-events:none"' : ''; ?>>
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div class="login-field">
                    <label for="username">Username</label>
                    <div class="login-input-wrap">
                        <i class="fas fa-user"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="login-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Show/hide password">
                            <i class="fas fa-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>

                <?php if (!empty($__ts_login['enabled']) && !empty($__ts_login['site_key'])): ?>
                <div class="login-field">
                    <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($__ts_login['site_key']); ?>"></div>
                </div>
                <?php endif; ?>

                <button type="submit" class="login-btn">
                    <span class="login-btn-text">Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-card-footer">
                <a href="../index.php" class="login-back-link">
                    <i class="fas fa-arrow-left"></i> Back to Website
                </a>
            </div>

        </div>

        <!-- Bottom credit -->
        <p class="login-credit">
            &copy; <?php echo date('Y'); ?> 7 Art Villa
        </p>

    </div>

    <script>
        // Toggle password visibility
        const toggleBtn  = document.getElementById('togglePw');
        const pwInput    = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePwIcon');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const isText = pwInput.type === 'text';
                pwInput.type = isText ? 'password' : 'text';
                toggleIcon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        }

        // Shake card on error
        <?php if ($error): ?>
        document.querySelector('.login-card')?.classList.add('shake');
        setTimeout(() => document.querySelector('.login-card')?.classList.remove('shake'), 600);
        <?php endif; ?>

        <?php if ($is_locked && $locked_until > 0): ?>
        // Lockout countdown
        (function () {
            const unlockAt = <?php echo (int)$locked_until; ?> * 1000;
            const el = document.getElementById('lockCountdown');
            if (!el) return;
            function tick() {
                const diff = Math.max(0, Math.ceil((unlockAt - Date.now()) / 1000));
                const m = Math.floor(diff / 60);
                const s = diff % 60;
                el.textContent = m + ':' + String(s).padStart(2, '0');
                if (diff > 0) setTimeout(tick, 1000);
                else location.reload();
            }
            tick();
        })();
        <?php endif; ?>
    </script>

</body>
</html>
