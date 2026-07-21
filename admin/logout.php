<?php
require_once '../config/auth.php';
logout_admin();
header('Location: ' . admin_url('login.php') . '?logged_out=1');
exit;
