<?php

function nt_load_settings_map(PDO $pdo): array
{
    return $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
}

function nt_turnstile_site_key(array $settings): string
{
    return trim((string)($settings['turnstile_site_key'] ?? ''));
}

function nt_turnstile_secret_key(array $settings): string
{
    return trim((string)($settings['turnstile_secret_key'] ?? ''));
}

function nt_turnstile_enabled(array $settings): bool
{
    return nt_turnstile_site_key($settings) !== '' && nt_turnstile_secret_key($settings) !== '';
}

function nt_turnstile_extract_token(array $data): string
{
    return trim((string)($data['cf-turnstile-response'] ?? $data['turnstile_token'] ?? ''));
}

function nt_turnstile_verify(array $settings, string $token, ?string $remoteIp = null): array
{
    if (!nt_turnstile_enabled($settings)) {
        return ['success' => true, 'skipped' => true];
    }

    if ($token === '') {
        return ['success' => false, 'error' => 'Please complete the verification challenge.'];
    }

    $payload = http_build_query(array_filter([
        'secret' => nt_turnstile_secret_key($settings),
        'response' => $token,
        'remoteip' => $remoteIp ?: null,
    ], static fn($value) => $value !== null && $value !== ''));

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);

    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($raw === false) {
        return ['success' => false, 'error' => 'Verification service is unavailable right now. Please try again.'];
    }

    $result = json_decode($raw, true);
    if (!is_array($result) || empty($result['success'])) {
        return ['success' => false, 'error' => 'Verification failed. Please try again.', 'details' => $result];
    }

    return ['success' => true, 'details' => $result];
}

