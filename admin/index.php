<?php
require_once '../config/auth.php';

if (is_logged_in()) {
    header('Location: ' . admin_url('dashboard.php'));
} else {
    header('Location: ' . admin_url('login.php'));
}
exit;
