<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_login();

$pdo    = db();
$errors = [];
$flash  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Load current admin
$admin = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
$admin->execute([$_SESSION['admin_id']]);
$admin = $admin->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if ($current === '')                         $errors[] = 'Current password is required.';
        if ($new === '')                             $errors[] = 'New password is required.';
        if (strlen($new) < 8)                        $errors[] = 'New password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $new))            $errors[] = 'New password must contain at least one uppercase letter.';
        if (!preg_match('/[0-9]/', $new))            $errors[] = 'New password must contain at least one number.';
        if ($new !== $confirm)                       $errors[] = 'New passwords do not match.';

        if (empty($errors)) {
            if (!password_verify($current, $admin['password'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                    ->execute([$hash, $admin['id']]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password changed successfully.'];
                header('Location: change-password.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | We Trail Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<div class="admin-main">

    <header class="admin-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div>
                <div class="topbar-title">Change Password</div>
                <div class="topbar-sub"><?php echo htmlspecialchars($admin['full_name']); ?> &mdash; <?php echo htmlspecialchars($admin['username']); ?></div>
            </div>
        </div>
    </header>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" data-auto-dismiss>
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
        <?php endif; ?>

        <div style="max-width:480px">

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error" style="margin-bottom:20px">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?></div>
            </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-card-title">
                    <i class="fas fa-lock" style="color:var(--gold);margin-right:8px"></i>
                    Update Password
                </div>

                <form method="POST" style="margin-top:20px">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                    <div class="form-group" style="margin-bottom:16px">
                        <label>Current Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="current_password" id="currentPass" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label>New Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-key"></i>
                            <input type="password" name="new_password" id="newPass" autocomplete="new-password" required>
                        </div>
                        <!-- Strength meter -->
                        <div class="pw-strength-bar" id="pwStrengthBar" style="margin-top:8px">
                            <div class="pw-strength-fill" id="pwStrengthFill"></div>
                        </div>
                        <span class="form-hint" id="pwStrengthLabel">Min. 8 characters, one uppercase, one number.</span>
                    </div>

                    <div class="form-group" style="margin-bottom:20px">
                        <label>Confirm New Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-check"></i>
                            <input type="password" name="confirm_password" id="confirmPass" autocomplete="new-password" required>
                        </div>
                        <span class="form-hint" id="matchLabel"></span>
                    </div>

                    <button type="submit" class="btn-admin btn-gold" style="width:100%">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </form>
            </div>

            <div class="form-card" style="margin-top:16px">
                <div class="form-card-title">
                    <i class="fas fa-shield-alt" style="color:var(--gold);margin-right:8px"></i>
                    Password Requirements
                </div>
                <ul style="margin-top:14px;display:flex;flex-direction:column;gap:8px;list-style:none">
                    <li class="pw-req" id="req-length"><i class="fas fa-circle" style="font-size:0.5rem;margin-right:8px"></i>At least 8 characters</li>
                    <li class="pw-req" id="req-upper"><i class="fas fa-circle" style="font-size:0.5rem;margin-right:8px"></i>At least one uppercase letter</li>
                    <li class="pw-req" id="req-number"><i class="fas fa-circle" style="font-size:0.5rem;margin-right:8px"></i>At least one number</li>
                    <li class="pw-req" id="req-match"><i class="fas fa-circle" style="font-size:0.5rem;margin-right:8px"></i>Passwords match</li>
                </ul>
            </div>

        </div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/admin.js"></script>
<script>
const newPass     = document.getElementById('newPass');
const confirmPass = document.getElementById('confirmPass');
const fill        = document.getElementById('pwStrengthFill');
const label       = document.getElementById('pwStrengthLabel');
const matchLabel  = document.getElementById('matchLabel');

const reqs = {
    length: { el: document.getElementById('req-length'),  test: v => v.length >= 8 },
    upper:  { el: document.getElementById('req-upper'),   test: v => /[A-Z]/.test(v) },
    number: { el: document.getElementById('req-number'),  test: v => /[0-9]/.test(v) },
};

function checkStrength(v) {
    let score = 0;
    Object.entries(reqs).forEach(([k, r]) => {
        const ok = r.test(v);
        r.el.style.color = ok ? 'var(--green)' : 'var(--text-muted)';
        r.el.querySelector('i').className = ok ? 'fas fa-check-circle' : 'fas fa-circle';
        r.el.querySelector('i').style.fontSize = ok ? '0.75rem' : '0.5rem';
        if (ok) score++;
    });
    if (v.length >= 12) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    const pct = Math.min(100, score * 20);
    fill.style.width = pct + '%';
    fill.style.background = pct < 40 ? 'var(--red)' : pct < 70 ? '#f39c12' : 'var(--green)';
    label.textContent = pct < 40 ? 'Weak' : pct < 70 ? 'Moderate' : pct < 90 ? 'Strong' : 'Very Strong';
    label.style.color = pct < 40 ? 'var(--red)' : pct < 70 ? '#f39c12' : 'var(--green)';
}

function checkMatch() {
    const reqMatch = document.getElementById('req-match');
    if (!confirmPass.value) { matchLabel.textContent = ''; reqMatch.style.color = 'var(--text-muted)'; return; }
    const ok = newPass.value === confirmPass.value;
    matchLabel.textContent = ok ? 'âœ“ Passwords match' : 'âœ— Passwords do not match';
    matchLabel.style.color = ok ? 'var(--green)' : 'var(--red)';
    reqMatch.style.color   = ok ? 'var(--green)' : 'var(--red)';
    reqMatch.querySelector('i').className = ok ? 'fas fa-check-circle' : 'fas fa-times-circle';
    reqMatch.querySelector('i').style.fontSize = '0.75rem';
}

newPass.addEventListener('input',     () => { checkStrength(newPass.value); checkMatch(); });
confirmPass.addEventListener('input', checkMatch);
</script>
</body>
</html>
