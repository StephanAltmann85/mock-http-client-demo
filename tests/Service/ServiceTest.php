<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Service;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(Service::class)]
class ServiceTest extends KernelTestCase
{
    private MockHttpClient $mockHttpClient;
    private Service $service;

    /**
     * symfony composer test -- --group manual.
     */
    public function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @phpstan-var MockHttpClient $mockHttpClient */
        $mockHttpClient = $container->get(HttpClientInterface::class);
        /** @phpstan-var Service $collector */
        $service = $container->get(Service::class);

        $this->mockHttpClient = $mockHttpClient;
        $this->service = $service;

        parent::setUp();
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    #[Group('manual')]
    public function testWithManualInstantiation(): void
    {
        $mockHttpClient = new MockHttpClient([
            new MockResponse('body'),
            new MockResponse('body2'),
            new MockResponse('body3', ['response_headers' => ['foo' => 'bar']]),
        ]);
        $service = new Service($mockHttpClient);

        $result = $service->foobar();

        $this->assertSame('body3', $result->getContent());
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['foo' => ['bar']], $result->getHeaders());
    }

    #[Group('count')]
    public function testCount(): void
    {
        $this->mockHttpClient->setResponseFactory([
            new MockResponse('body'),
            new MockResponse('body'),
            new MockResponse('body'),
            new MockResponse('body'),
        ]);

        $this->service->foobar();

        // number of requests received
        $this->assertSame(3, $this->mockHttpClient->getRequestsCount());
    }

    #[Group('request_details')]
    public function testRequestOptions(): void
    {
        $this->mockHttpClient->setResponseFactory([
            $response1 = new MockResponse('body'),
            $response2 = new MockResponse('body2'),
            $response3 = new MockResponse('body3', ['http_code' => 204]),
        ]);

        $this->service->foobar();

        $this->assertSame(['Accept: application/txt', 'Content-Length: 13'], $response2->getRequestOptions()['headers']);
        $this->assertSame('{"foo":"bar"}', $response2->getRequestOptions()['body']);
        $this->assertSame('POST', $response2->getRequestMethod());
        $this->assertSame('https://example.com/url2', $response2->getRequestUrl());
    }

    #[Group('callback')]
    public function testAssignResponseViaCallback(): void
    {
        $expectedRequests = [
            function ($method, $url, $options): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://example.com/url1', $url);

                return new MockResponse('body');
            },
            function ($method, $url, $options): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('https://example.com/url2', $url);
                $this->assertSame(['Accept: application/txt', 'Content-Length: 13'], $options['headers']);

                return new MockResponse('body2');
            },
            function ($method, $url, $options): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('https://example.com/url3', $url);

                return new MockResponse('body3');
            },
        ];

        $this->mockHttpClient->setResponseFactory($expectedRequests);

        $this->service->foobar();
    }

    #[Group('client_exception')]
    public function testClientException(): void
    {
        $this->mockHttpClient->setResponseFactory([
            new MockResponse('body'),
            new MockResponse('body2'),
            new MockResponse('body3', ['http_code' => 404]),
        ]);

        $this->expectException(ClientExceptionInterface::class);

        $this->service->foobar();
    }

    #[Group('server_exception')]
    public function testServerException(): void
    {
        $this->mockHttpClient->setResponseFactory([
            new MockResponse('body'),
            new MockResponse('body2'),
            new MockResponse('body3', ['http_code' => 504]),
        ]);

        $this->expectException(ServerExceptionInterface::class);

        $this->service->foobar();
    }

    #[Group('transport_exception')]
    public function testTransportException(): void
    {
        $this->mockHttpClient->setResponseFactory([
            new MockResponse('body', info: ['error' => 'host unreachable']),
        ]);

        $this->expectException(TransportExceptionInterface::class);

        $this->service->foobar();
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    #[Group('body_exception')]
    public function testBodyException(): void
    {
        $this->mockHttpClient->setResponseFactory([
            new MockResponse('body'),
            new MockResponse('body2'),
            new MockResponse([new \RuntimeException('Error at transport level')]),
        ]);

        $result = $this->service->foobar();

        // exception is thrown when accessing content
        $this->expectException(TransportExceptionInterface::class);

        $result->getContent();
    }
}
