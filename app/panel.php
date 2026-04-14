<?php

require __DIR__ . '/timezone.php';
require __DIR__ . '/config.php';
require __DIR__ . '/calc.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/panel_lib.php';

if ($c['debug']) {
    require __DIR__ . '/debug.php';
}

panelRequireAuth();

$bot = new Bot($c['key'], $i);
$conf = $bot->getPacConf();
$compose = yaml_parse_file('/docker/compose')['services'] ?? [];
$hash = $bot->getHashBot();
$domain = $conf['domain'] ?: $bot->ip;
$sslType = $bot->nginxGetTypeCert();
$scheme = $sslType ? 'https' : 'http';
$transport = $conf['transport'] ?: 'Websocket';
$hyPort = !empty($compose['hy']['ports'][0]) ? explode(':', $compose['hy']['ports'][0])[0] : '';
$adguardConfig = yaml_parse_file('/config/AdGuardHome.yaml');
$wgTitle = !empty($conf['amnezia']) ? 'Amnezia' : 'WireGuard';
$wg1Title = !empty($conf['wg1_amnezia']) ? 'Amnezia' : 'WireGuard';
$ocSub = $bot->getHashSubdomain('oc');
$npSub = $bot->getHashSubdomain('np');

function panel_service_state(Bot $bot, array $conf, array $compose, string $service): array
{
    return match ($service) {
        'wireguard' => [
            'running' => (bool) $bot->ssh(!empty($conf['amnezia']) ? 'awg' : 'wg', 'wg'),
            'port'    => getenv('WGPORT'),
            'label'   => !empty($conf['amnezia']) ? 'Amnezia' : 'WireGuard',
        ],
        'wireguard_secondary' => [
            'running' => (bool) $bot->ssh(!empty($conf['wg1_amnezia']) ? 'awg' : 'wg', 'wg1'),
            'port'    => getenv('WG1PORT'),
            'label'   => !empty($conf['wg1_amnezia']) ? 'Amnezia #2' : 'WireGuard #2',
        ],
        'xray' => [
            'running' => (bool) $bot->ssh('pgrep xray', 'xr'),
            'port'    => '443',
            'label'   => 'VLESS',
        ],
        'naive' => [
            'running' => (bool) $bot->ssh('pgrep caddy', 'np'),
            'port'    => '443',
            'label'   => 'NaiveProxy',
        ],
        'openconnect' => [
            'running' => (bool) $bot->ssh('pgrep ocserv', 'oc'),
            'port'    => '443',
            'label'   => 'OpenConnect',
        ],
        'hysteria' => [
            'running' => (bool) $bot->ssh('pgrep hysteria', 'hy'),
            'port'    => !empty($compose['hy']['ports'][0]) ? explode(':', $compose['hy']['ports'][0])[0] : '',
            'label'   => 'Hysteria',
        ],
        'mtproto' => [
            'running' => (bool) $bot->ssh('pgrep mtproto-proxy', 'tg'),
            'port'    => getenv('TGPORT'),
            'label'   => 'MTProto',
        ],
        'adguard' => [
            'running' => (bool) exec('JSON=1 timeout 2 dnslookup google.com ad'),
            'port'    => '853',
            'label'   => 'AdGuardHome',
        ],
        'dnstt' => [
            'running' => (bool) $bot->ssh('pgrep dnstt', 'dnstt'),
            'port'    => '53',
            'label'   => 'DNSTT',
        ],
        'shadowsocks' => [
            'running' => (bool) $bot->ssh('pgrep ssserver', 'ss'),
            'port'    => getenv('SSPORT'),
            'label'   => 'Shadowsocks',
        ],
        default => [
            'running' => false,
            'port'    => '',
            'label'   => $service,
        ],
    };
}

$services = [
    'wireguard',
    'wireguard_secondary',
    'xray',
    'naive',
    'openconnect',
    'hysteria',
    'mtproto',
    'adguard',
    'dnstt',
    'shadowsocks',
];

$serviceCards = array_map(fn($key) => ['key' => $key] + panel_service_state($bot, $conf, $compose, $key), $services);
$xrayStats = $bot->getXrayStats();
$down = $bot->getBytes(($xrayStats['global']['download'] ?? 0) + ($xrayStats['session']['download'] ?? 0));
$up = $bot->getBytes(($xrayStats['global']['upload'] ?? 0) + ($xrayStats['session']['upload'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(panelConfig()['title']) ?></title>
    <style>
        :root { color-scheme: dark; }
        body { font-family: Arial, sans-serif; background:#111827; color:#e5e7eb; margin:0; }
        .wrap { max-width:1200px; margin:0 auto; padding:24px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
        .title { font-size:28px; font-weight:700; }
        .muted { color:#9ca3af; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; margin-top:20px; }
        .card { background:#1f2937; border:1px solid #374151; border-radius:14px; padding:16px; }
        .status { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .on { background:#14532d; color:#bbf7d0; }
        .off { background:#7f1d1d; color:#fecaca; }
        .row { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
        button, select, input { background:#111827; color:#e5e7eb; border:1px solid #4b5563; border-radius:10px; padding:10px 12px; }
        button { cursor:pointer; }
        button.primary { background:#2563eb; border-color:#2563eb; }
        .section { margin-top:28px; }
        .section h2 { margin:0 0 12px; }
        .config { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; }
        .kv { line-height:1.8; }
        .toolbar { display:flex; gap:12px; flex-wrap:wrap; }
        .notice { margin-top:14px; padding:12px 14px; border-radius:10px; background:#1e3a8a; color:#dbeafe; display:none; }
        a { color:#93c5fd; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <div class="title">vpnbot_panel</div>
            <div class="muted">Web panel for built-in services management</div>
        </div>
        <div class="toolbar">
            <a href="/panel/logout.php"><button>Logout</button></a>
            <button class="primary" data-action="restart_all">Apply pending restart</button>
        </div>
    </div>

    <div class="section config">
        <div class="card kv">
            <h2>Core</h2>
            <div>Domain: <b><?= htmlspecialchars($domain) ?></b></div>
            <div>Transport: <b id="transportValue"><?= htmlspecialchars($transport) ?></b></div>
            <div>Xray traffic: <b><?= htmlspecialchars($down ?: '0 B') ?></b> down, <b><?= htmlspecialchars($up ?: '0 B') ?></b> up</div>
            <div>SSL: <b><?= $sslType ? 'enabled' : 'self-signed / disabled' ?></b></div>
        </div>
        <div class="card kv">
            <h2>Quick links</h2>
            <div>AdGuard: <a href="<?= htmlspecialchars("$scheme://$domain/adguard$hash") ?>" target="_blank"><?= htmlspecialchars("$scheme://$domain/adguard$hash") ?></a></div>
            <div>NaiveProxy: <b><?= htmlspecialchars("https://{$conf['naive']['user']}:{$conf['naive']['pass']}@$npSub.$domain") ?></b></div>
            <div>OpenConnect: <b><?= htmlspecialchars($ocSub ? "https://$ocSub.$domain/" : 'not configured') ?></b></div>
            <div>Hysteria port: <b><?= htmlspecialchars($hyPort ?: 'not configured') ?></b></div>
        </div>
    </div>

    <div class="section">
        <h2>Services</h2>
        <div class="grid">
            <?php foreach ($serviceCards as $card): ?>
                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <strong><?= htmlspecialchars($card['label']) ?></strong>
                        <span class="status <?= $card['running'] ? 'on' : 'off' ?>"><?= $card['running'] ? 'RUNNING' : 'STOPPED' ?></span>
                    </div>
                    <div class="muted" style="margin-top:8px;">Port: <?= htmlspecialchars((string) $card['port']) ?></div>
                    <div class="row">
                        <button data-service="<?= htmlspecialchars($card['key']) ?>" data-action="restart_service">Restart</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section config">
        <div class="card">
            <h2>VLESS</h2>
            <div class="row">
                <select id="transportSelect">
                    <?php foreach (['Reality', 'Websocket', 'xhttp'] as $item): ?>
                        <option value="<?= htmlspecialchars($item) ?>" <?= $transport === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="primary" data-action="set_transport">Save transport</button>
            </div>
        </div>
        <div class="card">
            <h2>Ports and network</h2>
            <div class="row">
                <label>Hysteria port <input id="hyPort" type="number" min="1" max="65535" value="<?= htmlspecialchars($hyPort ?: '') ?>"></label>
                <button data-action="set_hy_port">Save port</button>
            </div>
            <div class="row">
                <button data-action="toggle_port" data-port-service="wg">Toggle WG port</button>
                <button data-action="toggle_port" data-port-service="wg1">Toggle WG2 port</button>
                <button data-action="toggle_port" data-port-service="tg">Toggle MTProto port</button>
                <button data-action="toggle_port" data-port-service="ad">Toggle AdGuard DoT</button>
                <button data-action="toggle_port" data-port-service="ss">Toggle Shadowsocks</button>
                <button data-action="toggle_port" data-port-service="dnstt">Toggle DNSTT</button>
            </div>
        </div>
        <div class="card kv">
            <h2>Credentials</h2>
            <div>Naive user: <b><?= htmlspecialchars($conf['naive']['user'] ?? '') ?></b></div>
            <div>Naive pass: <b><?= htmlspecialchars($conf['naive']['pass'] ?? '') ?></b></div>
            <div>OpenConnect pass: <b><?= htmlspecialchars($conf['ocserv'] ?? '') ?></b></div>
            <div>AdGuard pass: <b><?= htmlspecialchars($conf['adpswd'] ?? '') ?></b></div>
            <div>Hysteria pass: <b><?= htmlspecialchars($conf['hysteria_pass'] ?? '') ?></b></div>
        </div>
    </div>

    <div class="notice" id="notice"></div>
</div>
<script>
async function api(action, payload = {}) {
    const response = await fetch('/panel/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...payload })
    });
    const data = await response.json();
    if (!response.ok || !data.ok) {
        throw new Error(data.error || 'Request failed');
    }
    return data;
}
function showNotice(text) {
    const n = document.getElementById('notice');
    n.textContent = text;
    n.style.display = 'block';
}
document.querySelectorAll('[data-action]').forEach((button) => {
    button.addEventListener('click', async () => {
        const action = button.dataset.action;
        try {
            if (action === 'set_transport') {
                const transport = document.getElementById('transportSelect').value;
                await api(action, { transport });
                document.getElementById('transportValue').textContent = transport;
                showNotice('Transport updated');
                return;
            }
            if (action === 'set_hy_port') {
                const port = document.getElementById('hyPort').value;
                await api(action, { port });
                showNotice('Hysteria port updated');
                return;
            }
            if (action === 'toggle_port') {
                await api(action, { service: button.dataset.portService });
                showNotice('Port visibility toggled. Restart if needed.');
                return;
            }
            if (action === 'restart_service') {
                await api(action, { service: button.dataset.service });
                showNotice('Service restart command sent');
                return;
            }
            if (action === 'restart_all') {
                await api(action);
                showNotice('Restart requested');
                return;
            }
        } catch (error) {
            showNotice(error.message);
        }
    });
});
</script>
</body>
</html>
