<?php

declare(strict_types=1);

namespace App\Service\Hitobito;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClientProvider {
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $pbsmidataBaseUrl,
        private readonly bool $useMockClient = false,
    ) {}

    public function getClient(HitobitoProvider $provider, string $accessToken): ClientInterface {
        if ($this->useMockClient) {
            return new MockClient();
        }

        // Configure client based on the given provider
        $baseUrl = match ($provider) {
            HitobitoProvider::PBSMIDATA => $this->pbsmidataBaseUrl,
            HitobitoProvider::CEVIDB, HitobitoProvider::JUBLADB => throw new \InvalidArgumentException("Unsupported Hitobito provider \"{$provider->value}\""),
        };

        return new Client($this->client, $baseUrl, $accessToken);
    }
}
