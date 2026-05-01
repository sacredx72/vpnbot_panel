<?php

require __DIR__ . '/timezone.php';
require __DIR__ . '/config.php';
require __DIR__ . '/calc.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/panel_lib.php';
require __DIR__ . '/panel_backend.php';

panelRequireAuth();

$bot = new Bot($c['key'], $i);
$backend = new PanelBackend($bot);
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $raw['action'] ?? '';

function panel_ok(array $extra = []): void
{
    panelJson(array_merge(['ok' => true], $extra));
}

function panel_error(string $message, int $code = 400): void
{
    panelJson(['ok' => false, 'error' => $message], $code);
}

switch ($action) {
    case 'set_transport':
        $backend->setTransport((string) ($raw['transport'] ?? ''));
        panel_ok();
        break;

    case 'set_hy_port':
        $backend->setHyPort((int) ($raw['port'] ?? 0));
        panel_ok();
        break;

    case 'toggle_port':
        $backend->togglePort((string) ($raw['service'] ?? ''));
        panel_ok();
        break;

    case 'restart_service':
        $backend->restartService((string) ($raw['service'] ?? ''));
        panel_ok();
        break;

    case 'save_domain':
        $backend->saveDomain((string) ($raw['domain'] ?? ''));
        panel_ok();
        break;

    case 'save_naive':
        $backend->saveNaive((string) ($raw['user'] ?? ''), (string) ($raw['pass'] ?? ''), (string) ($raw['subdomain'] ?? ''));
        panel_ok();
        break;

    case 'save_openconnect':
        $backend->saveOpenConnect((string) ($raw['pass'] ?? ''), (string) ($raw['dns'] ?? ''), (string) ($raw['subdomain'] ?? ''));
        panel_ok();
        break;

    case 'save_hysteria':
        $backend->saveHysteria((string) ($raw['pass'] ?? ''));
        panel_ok();
        break;

    case 'save_adguard':
        $backend->saveAdguard((string) ($raw['pass'] ?? ''), (string) ($raw['clientId'] ?? ''));
        panel_ok();
        break;

    case 'restart_all':
        $backend->requestRestart();
        panel_ok();
        break;

    case 'snapshot':
        panel_ok(['data' => $backend->serviceSnapshot()]);
        break;

    default:
        panel_error('Unknown action');
}
