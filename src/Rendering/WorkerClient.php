<?php

namespace App\Rendering;

use Styleguide\Contracts\CatalogueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class WorkerClient
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(RENDER_WORKER_URL)%')] private string $renderUrl,
        #[Autowire('%env(BUILD_WORKER_URL)%')] private string $buildUrl,
        #[Autowire('%env(RENDER_WORKER_TOKEN)%')] private string $renderToken,
        #[Autowire('%env(BUILD_WORKER_TOKEN)%')] private string $buildToken,
    ) {
    }

    public function render(array $input): array
    {
        return $this->call($this->renderUrl.'/render', $this->renderToken, $input, 5);
    }

    public function build(array $input): array
    {
        return $this->call($this->buildUrl.'/build', $this->buildToken, $input, 12);
    }

    public function ready(): bool
    {
        try {
            foreach ([$this->renderUrl, $this->buildUrl] as $url) {
                if (200 !== $this->http->request('GET', $url.'/health', ['timeout' => 2, 'max_duration' => 2])->getStatusCode()) {
                    return false;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function call(string $url, string $token, array $input, int $timeout): array
    {
        try {
            $deadline = microtime(true) + $timeout;
            do {
                $remaining = max(0.1, $deadline - microtime(true));
                $response = $this->http->request('POST', $url, ['auth_bearer' => $token, 'json' => $input, 'timeout' => $remaining, 'max_duration' => $remaining, 'max_redirects' => 0]);
                $result = $response->toArray(false);
                if (429 !== $response->getStatusCode() || ($result['error']['code'] ?? '') !== 'busy') break;
                if (microtime(true) + 0.15 >= $deadline) break;
                usleep(100000);
            } while (true);
        } catch (\Throwable) {
            throw new CatalogueException(503, 'The isolated worker is unavailable. Retry later.', [], 'worker_unavailable');
        }
        if (!isset($result['ok'])) {
            throw new CatalogueException(503, 'The isolated worker returned an invalid result.');
        }

        return $result;
    }
}
