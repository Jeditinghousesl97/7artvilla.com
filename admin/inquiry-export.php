<?php
//  Inquiry CSV Export
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../includes/stay-module.php';
require_login();

$pdo = db();
stay_ensure_schema($pdo);

// Filters (same logic as inquiries.php) 
$status  = $_GET['status'] ?? 'all';
$type    = $_GET['type'] ?? 'all';
$search  = trim($_GET['search'] ?? '');

$allowed = ['all', 'unread', 'read', 'replied'];
if (!in_array($status, $allowed)) $status = 'all';
$allowed_types = ['all', 'general', 'stay', 'tour'];
if (!in_array($type, $allowed_types)) $type = 'all';

$where  = [];
$params = [];

if ($status !== 'all') {
    $where[]  = 'status = ?';
    $params[] = $status;
}
if ($type !== 'all') {
    $where[] = "(CASE WHEN inquiry_type = 'general' AND message LIKE 'Tour Inquiry%' THEN 'tour' ELSE inquiry_type END) = ?";
    $params[] = $type;
}
if ($search !== '') {
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch ALL matching rows (no pagination limit)
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, phone,
           checkin, checkout, guest_count, message, status, ip_address, created_at, source_page, subject_label,
           CASE WHEN inquiry_type = 'general' AND message LIKE 'Tour Inquiry%' THEN 'tour' ELSE inquiry_type END AS inquiry_type_view
    FROM inquiries
    $where_sql
    ORDER BY created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Build filename 
$label    = $status !== 'all' ? '-' . $status : '';
$label   .= $type !== 'all' ? '-' . $type : '';
$filename = 'inquiries' . $label . '-' . date('Y-m-d') . '.csv';

// Output CSV 
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'ID', 'First Name', 'Last Name', 'Email', 'Phone',
    'Type', 'Subject', 'Guests', 'Check-In', 'Check-Out', 'Message', 'Source Page', 'Status', 'IP Address', 'Received At'
]);

// Data rows
foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone'] ?? '',
        ucfirst($row['inquiry_type_view'] ?: 'general'),
        $row['subject_label'] ?? '',
        $row['guest_count'] ?? '',
        $row['checkin']  ? date('Y-m-d', strtotime($row['checkin']))  : '',
        $row['checkout'] ? date('Y-m-d', strtotime($row['checkout'])) : '',
        $row['message'],
        $row['source_page'] ?? '',
        ucfirst($row['status']),
        $row['ip_address'] ?? '',
        date('Y-m-d H:i', strtotime($row['created_at'])),
    ]);
}

fclose($out);
exit;
