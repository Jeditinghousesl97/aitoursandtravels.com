<?php
header('Content-Type: application/json');
require_once __DIR__ . '/admin/config/db.php';
require_once __DIR__ . '/assets/php/mailer.php';
require_once __DIR__ . '/assets/php/turnstile.php';

$pdo  = getPDO();
$settings = nt_load_settings_map($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$name    = trim($data['name']    ?? '');
$email   = trim($data['email']   ?? '');
$phone   = trim($data['phone']   ?? '');
$subject = trim($data['subject'] ?? 'General Inquiry');
$message = trim($data['message'] ?? '');

if ($name === '' || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Name and message are required.']);
    exit;
}

$turnstile = nt_turnstile_verify($settings, nt_turnstile_extract_token($data), $_SERVER['REMOTE_ADDR'] ?? null);
if (!$turnstile['success']) {
    echo json_encode(['success' => false, 'error' => $turnstile['error']]);
    exit;
}

try {
    $pdo->prepare('
        INSERT INTO inquiries (full_name, email, phone, subject, message, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ')->execute([$name, $email ?: '', $phone ?: null, $subject, $message]);

    $html = '<h2>New Contact Inquiry</h2>'
        . '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#d9e2ec;">'
        . '<tr><th align="left" style="background:#f8fafc;">Name</th><td>' . htmlspecialchars($name) . '</td></tr>'
        . '<tr><th align="left" style="background:#f8fafc;">Email</th><td>' . htmlspecialchars($email !== '' ? $email : 'Not provided') . '</td></tr>'
        . '<tr><th align="left" style="background:#f8fafc;">Phone</th><td>' . htmlspecialchars($phone !== '' ? $phone : 'Not provided') . '</td></tr>'
        . '<tr><th align="left" style="background:#f8fafc;">Subject</th><td>' . htmlspecialchars($subject) . '</td></tr>'
        . '<tr><th align="left" style="background:#f8fafc;">Message</th><td>' . nl2br(htmlspecialchars($message)) . '</td></tr>'
        . '</table>';

    $text = "New Contact Inquiry\n\n"
        . 'Name: ' . $name . "\n"
        . 'Email: ' . ($email !== '' ? $email : 'Not provided') . "\n"
        . 'Phone: ' . ($phone !== '' ? $phone : 'Not provided') . "\n"
        . 'Subject: ' . $subject . "\n"
        . "Message:\n" . $message . "\n";

    $replyTo = $email !== '' ? ['email' => $email, 'name' => $name] : null;
    $mailResult = nt_send_notification_email($pdo, [
        'subject' => 'New Contact Inquiry - ' . $subject,
        'html' => $html,
        'text' => $text,
        'reply_to' => $replyTo,
    ]);

    if (!$mailResult['success']) {
        error_log('Inquiry notification email failed: ' . ($mailResult['error'] ?? 'Unknown error'));
    }

    echo json_encode([
        'success' => true,
        'email_sent' => (bool)$mailResult['success'],
        'email_error' => $mailResult['success'] ? null : ($mailResult['error'] ?? 'Email failed'),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Could not save. Please try again.']);
}
