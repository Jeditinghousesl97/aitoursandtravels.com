<?php
header('Content-Type: application/json');
require_once __DIR__ . '/admin/config/db.php';
require_once __DIR__ . '/assets/php/mailer.php';
require_once __DIR__ . '/assets/php/turnstile.php';

$pdo  = getPDO();
$settings = nt_load_settings_map($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$full_name      = trim($data['full_name']       ?? '');
$email          = trim($data['email']           ?? '');
$phone          = trim($data['phone']           ?? '');
$nationality    = trim($data['nationality']     ?? '');
$adults         = max(1, (int)($data['adults']  ?? 1));
$children       = max(0, (int)($data['children']?? 0));
$travel_date    = trim($data['travel_date']     ?? '');
$special_request= trim($data['special_request'] ?? '');
$package_id     = !empty($data['package_id']) ? (int)$data['package_id'] : null;

if ($full_name === '' || $email === '') {
    echo json_encode(['success' => false, 'error' => 'Name and email are required.']);
    exit;
}

$turnstile = nt_turnstile_verify($settings, nt_turnstile_extract_token($data), $_SERVER['REMOTE_ADDR'] ?? null);
if (!$turnstile['success']) {
    echo json_encode(['success' => false, 'error' => $turnstile['error']]);
    exit;
}

// Validate travel date
$travelDate = null;
if ($travel_date !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $travel_date);
    if ($d) $travelDate = $d->format('Y-m-d');
}

try {
    $pdo->prepare('
        INSERT INTO bookings
          (package_id, full_name, email, phone, nationality, adults, children, travel_date, special_request, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'new\', NOW())
    ')->execute([$package_id, $full_name, $email, $phone ?: null, $nationality ?: null,
                 $adults, $children, $travelDate, $special_request ?: null]);

    $packageName = '';
    if ($package_id) {
        $pkgStmt = $pdo->prepare('SELECT title FROM packages WHERE id = ? LIMIT 1');
        $pkgStmt->execute([$package_id]);
        $packageName = (string)($pkgStmt->fetchColumn() ?: '');
    }

    $detailRows = [
        'Package' => $packageName !== '' ? $packageName : ($package_id ? ('Package #' . $package_id) : 'Custom inquiry'),
        'Name' => $full_name,
        'Email' => $email,
        'Phone' => $phone !== '' ? $phone : 'Not provided',
        'Nationality' => $nationality !== '' ? $nationality : 'Not provided',
        'Adults' => (string)$adults,
        'Children' => (string)$children,
        'Travel date' => $travelDate ?: 'Not provided',
        'Special request' => $special_request !== '' ? nl2br(htmlspecialchars($special_request)) : 'None',
    ];

    $html = '<h2>New Booking Request</h2><table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#d9e2ec;">';
    $text = "New Booking Request\n\n";
    foreach ($detailRows as $label => $value) {
        $safeLabel = htmlspecialchars($label);
        $safeValue = $label === 'Special request'
            ? (string)$value
            : htmlspecialchars((string)$value);
        $html .= '<tr><th align="left" style="background:#f8fafc;">' . $safeLabel . '</th><td>' . $safeValue . '</td></tr>';
        $plainValue = is_string($value) ? strip_tags(str_replace('<br />', "\n", $value)) : (string)$value;
        $text .= $label . ': ' . $plainValue . "\n";
    }
    $html .= '</table>';

    $mailResult = nt_send_notification_email($pdo, [
        'subject' => 'New Booking Request - ' . $full_name,
        'html' => $html,
        'text' => $text,
        'reply_to' => ['email' => $email, 'name' => $full_name],
    ]);

    if (!$mailResult['success']) {
        error_log('Booking notification email failed: ' . ($mailResult['error'] ?? 'Unknown error'));
    }

    echo json_encode([
        'success' => true,
        'email_sent' => (bool)$mailResult['success'],
        'email_error' => $mailResult['success'] ? null : ($mailResult['error'] ?? 'Email failed'),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Could not save booking. Please try again.']);
}
