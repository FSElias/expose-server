<?php

namespace Expose\Server\Connections;

use Evenement\EventEmitterTrait;
use Expose\Common\Http\QueryParameters;
use Ratchet\ConnectionInterface;

class ControlConnection
{
    use EventEmitterTrait;

    /** @var ConnectionInterface */
    public $socket;
    public $host;
    public $serverHost;
    public $authToken;
    public $subdomain;
    public $client_id;
    public $client_version;
    public $message;
    public $proxies = [];
    public $idleProxies = [];
    protected $shared_at;

    public function __construct(ConnectionInterface $socket, string $host, string $subdomain, string $clientId, string $serverHost, string $authToken = '')
    {
        $this->socket = $socket;
        $this->host = $host;
        $this->subdomain = $subdomain;
        $this->client_id = $clientId;
        $this->authToken = $authToken;
        $this->serverHost = $serverHost;
        $this->shared_at = now()->toDateTimeString();
        $this->client_version = QueryParameters::create($socket->httpRequest)->get('version');
    }

    public function setMaximumConnectionLength(int $maximumConnectionLength)
    {
        $this->socket->send(json_encode([
            'event' => 'setMaximumConnectionLength',
            'data' => [
                'length' => $maximumConnectionLength,
            ],
        ]));
    }

    public function registerProxy($requestId)
    {
        if ($proxy = $this->getIdleProxy()) {
            $proxy->request_id = $requestId;

            $this->emit('proxy_ready_'.$requestId, [
                $proxy,
            ]);

            return;
        }

        $this->socket->send(json_encode([
            'event' => 'createProxy',
            'data' => [
                'host' => $this->host,
                'subdomain' => $this->subdomain,
                'request_id' => $requestId,
                'client_id' => $this->client_id,
                'reusable' => true,
            ],
        ]));
    }

    public function releaseProxy(ConnectionInterface $proxy): void
    {
        unset($proxy->request_id);

        $this->idleProxies[spl_object_id($proxy)] = $proxy;
    }

    public function removeProxy(ConnectionInterface $proxy): void
    {
        unset($this->idleProxies[spl_object_id($proxy)]);
    }

    protected function getIdleProxy(): ?ConnectionInterface
    {
        while ($proxy = array_pop($this->idleProxies)) {
            if ($proxy instanceof ConnectionInterface) {
                return $proxy;
            }
        }

        return null;
    }

    public function closeWithoutReconnect()
    {
        $this->socket->send(json_encode([
            'event' => 'closeWithoutReconnect',
            'data' => [
                'message' => config('expose-server.messages.maximum_connection_length_reached'),
            ],
        ]));
    }

    public function close()
    {
        $this->socket->close();
    }

    public function toArray()
    {
        return [
            'type' => 'http',
            'host' => $this->host,
            'remote_address' => $this->socket->remoteAddress ?? null,
            'server_host' => $this->serverHost,
            'client_id' => $this->client_id,
            'client_version' => $this->client_version,
            'auth_token' => $this->authToken,
            'subdomain' => $this->subdomain,
            'shared_at' => $this->shared_at,
        ];
    }
}
