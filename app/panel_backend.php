<?php

require_once __DIR__ . '/bot.php';

class PanelBackend
{
    public function __construct(private Bot $bot)
    {
    }

    public function setTransport(string $transport): void
    {
        if (!in_array($transport, ['Reality', 'Websocket', 'xhttp'], true)) {
            throw new RuntimeException('Unsupported transport');
        }
        $this->bot->changeTransport($transport);
    }

    public function setHyPort(int $port): void
    {
        $this->bot->setPort($port, 'hy');
    }

    public function togglePort(string $service): void
    {
        if (!in_array($service, ['wg', 'wg1', 'tg', 'ad', 'ss', 'dnstt'], true)) {
            throw new RuntimeException('Unsupported port target');
        }
        $this->bot->hidePort($service);
    }

    public function restartService(string $service): void
    {
        $map = [
            'wireguard' => fn() => $this->bot->ssh('pkill -f wg-quick; /bin/sh /start_wg.sh', 'wg', false, '/logs/wg_restart'),
            'wireguard_secondary' => fn() => $this->bot->ssh('pkill -f wg-quick; /bin/sh /start_wg.sh', 'wg1', false, '/logs/wg1_restart'),
            'xray' => fn() => $this->bot->restartXray($this->bot->getXray()),
            'naive' => fn() => $this->bot->restartNaive(),
            'openconnect' => fn() => $this->bot->restartOcserv(file_get_contents('/config/ocserv.conf')),
            'hysteria' => fn() => $this->bot->restartHysteria(),
            'mtproto' => fn() => $this->bot->restartTG(),
            'adguard' => fn() => [$this->bot->stopAd(), $this->bot->startAd()],
            'dnstt' => fn() => $this->bot->dnsttStart(),
            'shadowsocks' => fn() => $this->bot->ssh('pkill ssserver; /bin/sh /start_ss.sh', 'ss', false, '/logs/ss_restart'),
        ];
        if (empty($map[$service])) {
            throw new RuntimeException('Unsupported service');
        }
        $map[$service]();
    }

    public function saveDomain(string $domain): void
    {
        $this->bot->addDomain($domain, true);
    }

    public function saveNaive(string $user, string $pass, string $subdomain): void
    {
        $pac = $this->bot->getPacConf();
        $pac['naive']['user'] = trim($user);
        $pac['naive']['pass'] = trim($pass);
        $pac['np_domain'] = trim($subdomain);
        $this->bot->setPacConf($pac);
        $this->bot->restartNaive();
        $this->bot->setUpstreamDomainNaive($pac['domain']);
    }

    public function saveOpenConnect(string $pass, string $dns, string $subdomain): void
    {
        $pac = $this->bot->getPacConf();
        $pac['ocserv'] = trim($pass);
        $pac['oc_domain'] = trim($subdomain);
        $this->bot->setPacConf($pac);
        $this->bot->chocdns(trim($dns));
        $this->bot->chOcSubdomain(trim($subdomain));
        if ($pass !== '') {
            $this->bot->chocpass(trim($pass));
        }
    }

    public function saveHysteria(string $pass): void
    {
        $this->bot->chhypass(trim($pass));
    }

    public function saveAdguard(string $password, string $clientId): void
    {
        $password = trim($password);
        $clientId = trim($clientId);
        if ($password !== '') {
            $this->bot->chpsswd($password);
        }
        $this->bot->setAdKey($clientId);
    }

    public function requestRestart(): void
    {
        file_put_contents('/update/pipe', '2');
    }

    public function serviceSnapshot(): array
    {
        $conf = $this->bot->getPacConf();
        $compose = yaml_parse_file('/docker/compose')['services'] ?? [];
        return [
            'domain' => $conf['domain'] ?: $this->bot->ip,
            'transport' => $conf['transport'] ?: 'Websocket',
            'hyPort' => !empty($compose['hy']['ports'][0]) ? explode(':', $compose['hy']['ports'][0])[0] : '',
            'naive' => [
                'user' => $conf['naive']['user'] ?? '',
                'pass' => $conf['naive']['pass'] ?? '',
                'subdomain' => $this->bot->getHashSubdomain('np'),
            ],
            'openconnect' => [
                'pass' => $conf['ocserv'] ?? '',
                'subdomain' => $this->bot->getHashSubdomain('oc'),
                'dns' => $this->extractOcDns(),
            ],
            'adguard' => [
                'pass' => $conf['adpswd'] ?? '',
                'clientId' => $conf['adguardkey'] ?? '',
            ],
            'hysteria' => [
                'pass' => $conf['hysteria_pass'] ?? '',
            ],
        ];
    }

    private function extractOcDns(): string
    {
        $ocserv = @file_get_contents('/config/ocserv.conf') ?: '';
        preg_match('~^dns = ([^\n]+)~sm', $ocserv, $m);
        return trim($m[1] ?? '');
    }
}
