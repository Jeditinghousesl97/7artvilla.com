<?php
http_response_code(403);
exit('Forbidden');

//  ONE-TIME SETUP — Create default admin user
//  DELETE THIS FILE after running it!


require_once '../config/db.php';

$username  = 'admin';
$password  = 'Admin@1234';          // Change immediately after first login
$full_name = 'Resort Administrator';

try {
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = db()->prepare('
        INSERT INTO admin_users (username, password, full_name)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE password = VALUES(password), full_name = VALUES(full_name)
    ');
    $stmt->execute([$username, $hash, $full_name]);

    echo '<div style="font-family:monospace;padding:40px;background:#111;color:#5daa6a;min-height:100vh;">';
    echo '<h2 style="color:#C8961E">&#10003; Admin user created successfully</h2><br>';
    echo '<strong>Username:</strong> ' . htmlspecialchars($username) . '<br>';
    echo '<strong>Password:</strong> ' . htmlspecialchars($password) . '<br><br>';
    echo '<span style="color:#e74c3c"><strong>&#9888; Delete this file immediately after use!</strong></span><br><br>';
    echo '<a href="login.php" style="color:#C8961E">&#8594; Go to Admin Login</a>';
    echo '</div>';

} catch (Exception $e) {
    http_response_code(500);
    echo '<pre style="color:red;padding:20px">' . htmlspecialchars($e->getMessage()) . '</pre>';
}
