<?php

namespace App\Services;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttPublisher
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly ?string $username,
        private readonly ?string $password,
    ) {}

    public function publish(string $topic, string $payload, int $qos = 1): void
    {
        $client = new MqttClient($this->host, $this->port, 'backend-publisher-'.uniqid());

        $settings = (new ConnectionSettings)
            ->setUsername($this->username)
            ->setPassword($this->password)
            ->setConnectTimeout(5);

        $client->connect($settings, true);

        try {
            $client->publish($topic, $payload, $qos);
        } finally {
            $client->disconnect();
        }
    }
}
