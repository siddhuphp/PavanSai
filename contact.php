<?php
// Core PHP only. No contact submissions are persisted.
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $code, bool $success, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
function field(string $name, int $limit, bool $multiline = false): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || strlen($value) > $limit || preg_match('//u', $value) !== 1) {
        respond(422, false, 'Please check the length and format of your form fields.');
    }
    $value = trim($value);
    // Single-line values must never contain header separators or control bytes.
    $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
    if (preg_match($pattern, $value)) respond(422, false, 'Please remove invalid characters from your form fields.');
    return $value;
}
function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
require_once __DIR__ . '/config/smtp.php';
try {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
    $config = require __DIR__ . '/config/mail.php';
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'token') {
        // Token and server-side time protect against cross-site posts and immediate bots.
        $_SESSION['form_token'] = bin2hex(random_bytes(32));
        $_SESSION['form_started'] = time();
        respond(200, true, '', ['token' => $_SESSION['form_token']]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST'); respond(405, false, 'This request is not supported.');
    }
    $token = field('token', 64);
    if (empty($_SESSION['form_token']) || !hash_equals($_SESSION['form_token'], $token)) {
        respond(403, false, 'Your form session has expired. Refresh this page and try again.');
    }
    if (field('website', 200) !== '') respond(422, false, 'We could not accept this submission.');
    $elapsed = time() - (int)($_SESSION['form_started'] ?? time());
    if ($elapsed < $config['minimum_seconds']) respond(429, false, 'Please wait a few seconds before sending your enquiry.');
    if ($elapsed > 7200) respond(403, false, 'Your form session has expired. Refresh this page and try again.');
    if (isset($_SESSION['last_attempt']) && time() - $_SESSION['last_attempt'] < 30) {
        respond(429, false, 'Please wait 30 seconds before sending another enquiry.');
    }
    $name = field('name', 120); $email = field('email', 254);
    $phone = field('phone', 40); $company = field('company', 160);
    $subject = field('subject', 180); $message = field('message', 10000, true);
    if ($name === '' || $email === '' || $subject === '' || strlen($message) < 10) {
        respond(422, false, 'Please enter your name, email, subject and a message of at least 10 characters.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(422, false, 'Please enter a valid email address.');
    if ($phone !== '' && !preg_match('/^[0-9+().\s-]{5,40}$/', $phone)) respond(422, false, 'Please enter a valid phone number.');
    if (!filter_var($config['contact_email'], FILTER_VALIDATE_EMAIL) || !filter_var($config['mail_from'], FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $config['mail_name'] . $config['mail_from'] . $config['contact_email'])) {
        error_log('Contact form: mail configuration is incomplete.');
        respond(503, false, 'We could not send your message at this time. Please contact us by email or phone.');
    }
    $rows = ['Name' => $name, 'Email' => $email, 'Phone' => $phone ?: 'Not provided', 'Company' => $company ?: 'Not provided',
        'Subject' => $subject, 'Message' => $message, 'Submission time (UTC)' => gmdate('Y-m-d H:i:s')];
    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#173746"><h1 style="font-size:22px">New website enquiry</h1><table cellpadding="12" style="border-collapse:collapse;width:100%">';
    foreach ($rows as $label => $value) $html .= '<tr><th style="text-align:left;vertical-align:top;border-bottom:1px solid #ddd">' . escape($label) . '</th><td style="border-bottom:1px solid #ddd">' . nl2br(escape($value)) . '</td></tr>';
    $html .= '</table></body></html>';
    $_SESSION['last_attempt'] = time();
    if (!sendEnquiry($config, $email, $html)) {
        error_log('Contact form: mail transport failed.');
        respond(503, false, 'We could not send your message at this time. Please try again or contact us by email.');
    }
    unset($_SESSION['form_token'], $_SESSION['form_started']);
    respond(200, true, 'Thank you. Your message has been sent successfully.');
} catch (Throwable $exception) {
    error_log('Contact form: unexpected server error.');
    respond(500, false, 'We could not send your message at this time. Please try again.');
}
