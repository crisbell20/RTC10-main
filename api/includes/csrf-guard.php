<?php

// Reject cross-site state changes and keep the session cookie HttpOnly + SameSite=Lax.

if (defined('RTC_CSRF_GUARD_LOADED')) {
    return;
}
define('RTC_CSRF_GUARD_LOADED', true);

if (PHP_SAPI === 'cli') {
    return;
}

function rtcCsrfNormalizeHost(string $host): string
{
    $host = strtolower(trim($host));
    $host = trim($host, '[]');
    return (string)preg_replace('/:(80|443)$/', '', $host);
}

function rtcCsrfHostFromUrl(string $url): ?string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? (int)$parts['port'] : null;
    if ($port && $port !== 80 && $port !== 443) {
        $host .= ':' . $port;
    }

    return rtcCsrfNormalizeHost($host);
}

function rtcCsrfRefreshSessionCookie(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    $params = session_get_cookie_params();
    $expires = (int)($params['lifetime'] ?? 0);
    setcookie(session_name(), session_id(), [
        'expires' => $expires > 0 ? time() + $expires : 0,
        'path' => ($params['path'] ?? '') !== '' ? $params['path'] : '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function rtcCsrfOriginAllowed(): bool
{
    $expected = rtcCsrfNormalizeHost((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($expected === '') {
        return false;
    }

    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        $host = rtcCsrfHostFromUrl($origin);
        return $host !== null && $host === $expected;
    }

    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        $host = rtcCsrfHostFromUrl($referer);
        return $host !== null && $host === $expected;
    }

    return false;
}

rtcCsrfRefreshSessionCookie();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    return;
}

if (rtcCsrfOriginAllowed()) {
    return;
}

http_response_code(403);
if (!headers_sent()) {
    header('Content-Type: application/json');
}
echo json_encode([
    'success' => false,
    'message' => 'Invalid request origin',
]);
exit;
