<?php

function panelEnv(string $key, $default = null)
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function panelConfig(): array
{
    return [
        'username' => panelEnv('PANEL_ADMIN_LOGIN', 'admin'),
        'password' => panelEnv('PANEL_ADMIN_PASSWORD', ''),
        'title'    => panelEnv('PANEL_TITLE', 'vpnbot_panel'),
    ];
}

function panelIsConfigured(): bool
{
    $config = panelConfig();
    return !empty($config['password']);
}

function panelStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('vpnbot_panel');
        session_start();
    }
}

function panelIsAuthorized(): bool
{
    panelStartSession();
    return !empty($_SESSION['panel_auth']);
}

function panelRequireAuth(): void
{
    if (!panelIsAuthorized()) {
        header('Location: /panel/login.php');
        exit;
    }
}

function panelAttemptLogin(string $username, string $password): bool
{
    panelStartSession();
    $config = panelConfig();
    $ok = hash_equals((string) $config['username'], $username) && hash_equals((string) $config['password'], $password);
    if ($ok) {
        $_SESSION['panel_auth'] = 1;
    }
    return $ok;
}

function panelLogout(): void
{
    panelStartSession();
    $_SESSION = [];
    session_destroy();
}

function panelJson($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
