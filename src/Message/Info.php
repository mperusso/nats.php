<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Info extends Prototype
{
    public $headers;
    public $max_payload;
    public $port;
    public $proto;
    public $go;
    public $host;
    public $server_id;
    public $server_name;
    public $version;

    /** @var string[]|null  */
    public  $connect_urls;
    /** @var string[]|null  */
    public  $ws_connect_urls;
    public $auth_required;
    public $cluster_dynamic;
    public $jetstream;
    public $ldm;
    public $tls_available;
    public $tls_required;
    public $tls_verify;
    public $client_id;
    public $client_ip;
    public $cluster;
    public $domain;
    public $git_commit;
    public $ip;
    public $nonce;
    public $xkey;

    public function render(): string
    {
        return 'INFO ' . json_encode($this);
    }
}
