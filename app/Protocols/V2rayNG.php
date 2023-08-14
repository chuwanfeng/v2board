<?php

namespace App\Protocols;

use App\Utils\Helper;

class V2rayNG
{
    public $flag = 'v2rayng';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';

        foreach ($servers as $item) {
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($user['uuid'], $item);
            }
            if ($item['type'] === 'vless') {
                $uri .= self::buildVless($user['uuid'], $item);
            }
            if ($item['type'] === 'shadowsocks') {
                $uri .= self::buildShadowsocks($user['uuid'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($user['uuid'], $item);
            }
        }
        return base64_encode($uri);
    }

    public static function buildShadowsocks($password, $server)
    {
        $name = rawurlencode($server['name']);
        $str = str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode("{$server['cipher']}:{$password}")
        );
        return "ss://{$str}@{$server['host']}:{$server['port']}#{$name}\r\n";
    }

    public static function buildVmess($uuid, $server)
    {
        $config = [
            "v" => "2",
            "ps" => $server['name'],
            "add" => $server['host'],
            "port" => (string)$server['port'],
            "id" => $uuid,
            "aid" => '0',
            "net" => $server['network'],
            "type" => "none",
            "host" => "",
            "path" => "",
            "tls" => $server['tls'] ? "tls" : "",
        ];
        if ($server['tls']) {
            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    $config['sni'] = $tlsSettings['serverName'];
            }
        }
        if ((string)$server['network'] === 'tcp') {
            $tcpSettings = $server['networkSettings'];
            if (isset($tcpSettings['header']['type'])) $config['type'] = $tcpSettings['header']['type'];
            if (isset($tcpSettings['header']['request']['path'][0])) $config['path'] = $tcpSettings['header']['request']['path'][0];
        }
        if ((string)$server['network'] === 'ws') {
            $wsSettings = $server['networkSettings'];
            if (isset($wsSettings['path'])) $config['path'] = $wsSettings['path'];
            if (isset($wsSettings['headers']['Host'])) $config['host'] = $wsSettings['headers']['Host'];
        }
        if ((string)$server['network'] === 'grpc') {
            $grpcSettings = $server['networkSettings'];
            if (isset($grpcSettings['serviceName'])) $config['path'] = $grpcSettings['serviceName'];
        }
        return "vmess://" . base64_encode(json_encode($config)) . "\r\n";
    }

    public static function buildVless($uuid, $server)
    {
        $config = [
            "v" => "2",
            "ps" => Helper::encodeURIComponent($server['name']),
            "add" => $server['host'],
            "port" => (string)$server['port'],
            "id" => $uuid,
            "net" => $server['network'],
            "type" => "none",
            "host" => "",
            "path" => "",
            "tls" => $server['tls'] !=0 ? ($server['tls'] == 2 ? "reality":"tls") : "",
            "flow" => $server['flow'],
            "sni" => "",
            "pbk" => "",
            "sid" =>"",
        ];
        if ($server['tls']) {
            if ($server['tls_settings']) {
                $tlsSettings = $server['tls_settings'];
                if (isset($tlsSettings['server_name']) && !empty($tlsSettings['server_name']))
                    $config['sni'] = $tlsSettings['server_name'];
                if ($server['tls'] == 2) {
                   $config['pbk'] = $tlsSettings['public_key'];
                   $config['sid'] = $tlsSettings['shortId'];
                }
            }
        }
        if ((string)$server['network'] === 'tcp') {
            $tcpSettings = $server['network_settings'];
            if (isset($tcpSettings['header']['type'])) $config['type'] = $tcpSettings['header']['type'];
            if (isset($tcpSettings['header']['request']['path'][0])) $config['path'] = Helper::encodeURIComponent($tcpSettings['header']['request']['path'][0]);
        }
        if ((string)$server['network'] === 'ws') {
            $wsSettings = $server['network_settings'];
            if (isset($wsSettings['path'])) $config['path'] = Helper::encodeURIComponent($wsSettings['path']);
            if (isset($wsSettings['headers']['Host'])) $config['host'] = Helper::encodeURIComponent($wsSettings['headers']['Host']);
        }
        if ((string)$server['network'] === 'grpc') {
            $grpcSettings = $server['network_settings'];
            if (isset($grpcSettings['serviceName'])) $config['path'] = $grpcSettings['serviceName'];
        }
//grpc需要继续修
        if ($server['tls'] == 2) {
            return "vless://{$uuid}@{$config['add']}:{$server['port']}?type={$config['net']}&encryption=none&security={$config['tls']}&path={$config['path']}&host={$config['host']}&headerType={$config['type']}&flow={$server['flow']}&fp=chrome&sni={$config['sni']}&pbk={$config['pbk']}&sid={$config['sid']}#${config['ps']}"  . "\r\n";
        }
        return "vless://{$uuid}@{$config['add']}:{$server['port']}?type={$config['net']}&encryption=none&security={$config['tls']}&path={$config['path']}&host={$config['host']}&headerType={$config['type']}&flow={$server['flow']}&fp=chrome&sni={$config['sni']}#${config['ps']}"  . "\r\n";
    }

    public static function buildTrojan($password, $server)
    {
        $name = rawurlencode($server['name']);
        $query = http_build_query([
            'allowInsecure' => $server['allow_insecure'],
            'peer' => $server['server_name'],
            'sni' => $server['server_name']
        ]);
        $uri = "trojan://{$password}@{$server['host']}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }


}
