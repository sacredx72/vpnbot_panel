<?php

require __DIR__ . '/timezone.php';
require __DIR__ . '/config.php';
require __DIR__ . '/calc.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/panel_lib.php';

panelRequireAuth();

$bot = new Bot($c['key'], $i);
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
        $transport = $raw['transport'] ?? '';
        if (!in_array($transport, ['Reality', 'Websocket', 'xhttp'], true)) {
            panel_error('Unsupported transport');
        }
        $bot->changeTransport($transport);
        panel_ok();
        break;

    case 'set_hy_port':
        $port = (int) ($raw['port'] ?? 0);
        $bot->setPort($port, 'hy');
        panel_ok();
        break;

    case 'toggle_port':
        $service = $raw['service'] ?? '';
        if (!in_array($service, ['wg', 'wg1', 'tg', 'ad', 'ss', 'dnstt'], true)) {
            panel_error('Unsupported port target');
        }
        $bot->hidePort($service);
        panel_ok();
        break;

    case 'restart_service':
        $service = $raw['service'] ?? '';
        $map = [
            'wireguard' => fn() => $bot->ssh('pkill -f wg-quick; /bin/sh /start_wg.sh', 'wg', false, '/logs/wg_restart'),
            'wireguard_secondary' => fn() => $bot->ssh('pkill -f wg-quick; /bin/sh /start_wg.sh', 'wg1', false, '/logs/wg1_restart'),
            'xray' => fn() => $bot->restartXray($bot->getXray()),
            'naive' => fn() => $bot->ssh('pkill caddy; /bin/sh /start_np.sh', 'np', false, '/logs/naive_restart'),
            'openconnect' => fn() => $bot->ssh('pkill ocserv; /bin/sh /start_oc.sh', 'oc', false, '/logs/oc_restart'),
            'hysteria' => fn() => $bot->restartHysteria(),
            'mtproto' => fn() => $bot->restartTG(),
            'adguard' => fn() => [$bot->stopAd(), $bot->startAd()],
            'dnstt' => fn() => $bot->dnsttStart(),
            'shadowsocks' => fn() => $bot->ssh('pkill ssserver; /bin/sh /start_ss.sh', 'ss', false, '/logs/ss_restart'),
        ];
        if (empty($map[$service])) {
            panel_error('Unsupported service');
        }
        $map[$service]();
        panel_ok();
        break;

    case 'restart_all':
        $bot->restart();
        panel_ok();
        break;

    default:
        panel_error('Unknown action');
}
