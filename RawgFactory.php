<?php

namespace Omniplay\Rawg;

use Omniplay\Config;
use Omniplay\Http\JsonClient;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** "rawg": options api_key (required), user_agent, api ("https://api.rawg.io/api"). */
final class RawgFactory extends ProviderFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omniplay.factory_name' => 'rawg',
            'omniplay.factory_title' => 'RAWG',
            'omniplay.required_options' => ['api_key'],
            'user_agent' => 'omniplay/1.0 (+https://github.com/glitchr-omnisense/omniplay)',
            'api' => 'https://api.rawg.io/api',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new RawgProvider(new JsonClient($this->http ?? HttpClient::create(['timeout' => 20]), 'RAWG', ['Accept' => 'application/json', 'User-Agent' => (string) $c->string('user_agent')], $c->backoff()), (string) $c->string('api_key'), rtrim((string) $c->string('api'), '/'));
    }
}
