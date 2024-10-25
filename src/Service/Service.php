<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

readonly class Service
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function foobar(): ResponseInterface
    {
        $this->client->request('GET', 'url1');
        $this->client->request('POST', 'url2', ['headers' => ['Accept' => 'application/txt'], 'body' => '{"foo":"bar"}']);

        return $this->client->request('GET', 'url3');
    }
}
