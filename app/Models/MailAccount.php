<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Graph\TokenManager;
use PromoSeven\AzureMailer\Transport\AzureTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailAccount extends Model
{
    protected $fillable = ['name', 'label', 'type', 'from_address', 'from_name', 'config', 'enabled'];

    protected $casts = [
        'config'  => 'encrypted:array',
        'enabled' => 'boolean',
    ];

    public function buildTransport(): TransportInterface
    {
        if ($this->type === 'azure') {
            $config = array_merge($this->config, [
                'from_address'       => $this->from_address,
                'graph_api_version'  => 'v1.0',
                'timeout'            => 30,
                'save_to_sent_items' => false,
            ]);
            return new AzureTransport(
                new GraphClient(new TokenManager($config), $config),
                $config
            );
        }

        $cfg       = $this->config;
        $tls       = ($cfg['encryption'] ?? 'tls') === 'ssl';
        $transport = new EsmtpTransport($cfg['host'], (int) ($cfg['port'] ?? 587), $tls);
        if (!empty($cfg['username'])) {
            $transport->setUsername($cfg['username']);
            $transport->setPassword($cfg['password'] ?? '');
        }
        return $transport;
    }
}
