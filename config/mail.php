<?php
// Process environment takes precedence over the private project .env file.
$values = [];
$path = dirname(__DIR__) . '/.env';
if (is_file($path)) {
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pair = explode('=', $line, 2);
        if (count($pair) !== 2) continue;
        [$key, $value] = $pair;
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"'))
            || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
        $values[trim($key)] = $value;
    }
}
$env = static function (string $key, string $default = '') use ($values): string {
    $value = getenv($key);
    return $value === false ? ($values[$key] ?? $default) : $value;
};
return [
    'contact_email' => $env('CONTACT_EMAIL', 'pavansay.16@gmail.com'),
    'mail_bcc' => $env('MAIL_BCC'),
    'mail_from' => $env('MAIL_FROM_ADDRESS', $env('SMTP_USERNAME')),
    'mail_name' => $env('MAIL_FROM_NAME', 'Pavan Sai Engineering Services'),
    'smtp_host' => $env('SMTP_HOST'),
    'smtp_port' => (int)$env('SMTP_PORT', '465'),
    'smtp_username' => $env('SMTP_USERNAME'),
    'smtp_password' => $env('SMTP_PASSWORD'),
    'minimum_seconds' => 3,
];
