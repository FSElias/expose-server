<?php

namespace Tests\Feature\Server;

use Expose\Server\Configuration;
use Expose\Server\Connections\ControlConnection;
use Expose\Server\Connections\HttpConnection;
use Expose\Server\Contracts\ConnectionManager;
use Expose\Server\Contracts\DomainRepository;
use Expose\Server\Contracts\SubdomainRepository;
use Expose\Server\Contracts\UserRepository;
use Expose\Server\Http\Controllers\ControlMessageController;
use Mockery;
use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\Message;
use Tests\Feature\TestCase;

class ControlMessageControllerTest extends TestCase
{
    /** @test */
    public function it_handles_reusable_proxy_completion_messages()
    {
        $proxy = Mockery::mock(ConnectionInterface::class);
        $proxy->request_id = 'request-one';
        $proxy->proxy_client_id = 'client-one';

        $httpConnection = Mockery::mock(HttpConnection::class);
        $httpConnection->shouldReceive('close')->once();

        $controlConnection = Mockery::mock(ControlConnection::class);
        $controlConnection->shouldReceive('releaseProxy')->once()->with($proxy);

        $connectionManager = Mockery::mock(ConnectionManager::class);
        $connectionManager->shouldReceive('getHttpConnectionForRequestId')->once()->with('request-one')->andReturn($httpConnection);
        $connectionManager->shouldReceive('removeHttpConnection')->once()->with('request-one');
        $connectionManager->shouldReceive('findControlConnectionForClientId')->once()->with('client-one')->andReturn($controlConnection);

        $handled = $this->handleProxyControlMessage($connectionManager, $proxy, $this->textMessage(json_encode([
            'event' => 'proxyComplete',
            'data' => [
                'request_id' => 'request-one',
                'client_id' => 'client-one',
            ],
        ])));

        $this->assertTrue($handled);
    }

    protected function handleProxyControlMessage(ConnectionManager $connectionManager, ConnectionInterface $connection, Message $message): bool
    {
        $controller = new ControlMessageController(
            $connectionManager,
            Mockery::mock(UserRepository::class),
            Mockery::mock(SubdomainRepository::class),
            Mockery::mock(Configuration::class),
            Mockery::mock(DomainRepository::class)
        );

        $method = new \ReflectionMethod(ControlMessageController::class, 'handleProxyControlMessage');
        $method->setAccessible(true);

        return $method->invoke($controller, $connection, $message);
    }

    protected function textMessage(string $payload): Message
    {
        $message = new Message();
        $message->addFrame(new Frame($payload));

        return $message;
    }
}
