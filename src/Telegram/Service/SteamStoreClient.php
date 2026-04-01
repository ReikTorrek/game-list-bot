<?php

namespace App\Telegram\Service;

use App\Telegram\Dto\SteamGameMetadata;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SteamStoreClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $storeCountry = 'us',
        private string $storeLanguage = 'russian',
    ) {
    }

    public function fetchMetadataForUrl(string $url): ?SteamGameMetadata
    {
        $appId = $this->extractAppId($url);

        if ($appId === null) {
            return null;
        }

        $appDetails = $this->fetchAppDetails($appId);
        $recentSummary = $this->fetchReviewSummary($appId, true);
        $overallSummary = $this->fetchReviewSummary($appId, false);

        return new SteamGameMetadata(
            appId: $appId,
            name: $this->extractName($appDetails),
            price: $this->extractPrice($appDetails),
            recentReviewSummary: $recentSummary,
            overallReviewSummary: $overallSummary,
        );
    }

    public function extractAppId(string $url): ?int
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host === '' || !str_ends_with($host, 'steampowered.com')) {
            return null;
        }

        if (preg_match('#/app/(?P<appId>\d+)(?:/|$)#', $path, $matches) !== 1) {
            return null;
        }

        return (int) $matches['appId'];
    }

    /**
     * @return array<string, mixed>|null
     * @throws ExceptionInterface
     */
    private function fetchAppDetails(int $appId): ?array
    {
        $response = $this->httpClient->request('GET', 'https://store.steampowered.com/api/appdetails', [
            'query' => [
                'appids' => $appId,
                'cc' => $this->storeCountry,
                'l' => $this->storeLanguage,
            ],
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        $appPayload = $payload[(string) $appId] ?? null;

        if (!is_array($appPayload) || ($appPayload['success'] ?? false) !== true) {
            return null;
        }

        $data = $appPayload['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    /**
     * @throws ExceptionInterface
     */
    private function fetchReviewSummary(int $appId, bool $recent): ?string
    {
        $query = [
            'json' => 1,
            'language' => $this->storeLanguage,
            'purchase_type' => 'all',
            'num_per_page' => 0,
            'filter' => 'summary',
        ];

        if ($recent) {
            $query['day_range'] = 30;
        }

        $response = $this->httpClient->request('GET', sprintf('https://store.steampowered.com/appreviews/%d', $appId), [
            'query' => $query,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $response->toArray(false);
        $querySummary = $payload['query_summary'] ?? null;

        if (!is_array($querySummary)) {
            return null;
        }

        $description = $querySummary['review_score_desc'] ?? null;
        $positive = $querySummary['total_positive'] ?? null;
        $total = $querySummary['total_reviews'] ?? null;

        if (!is_string($description) || !is_int($positive) || !is_int($total) || $total <= 0) {
            return null;
        }

        $percent = (int) round(($positive / $total) * 100);

        return sprintf(
            '%s (%d%%, %s отзывов)',
            $description,
            $percent,
            number_format($total, 0, '.', ' ')
        );
    }

    /**
     * @param array<string, mixed>|null $appDetails
     */
    private function extractPrice(?array $appDetails): ?string
    {
        if ($appDetails === null) {
            return null;
        }

        if (($appDetails['is_free'] ?? false) === true) {
            return 'Бесплатно';
        }

        $priceOverview = $appDetails['price_overview'] ?? null;

        if (!is_array($priceOverview)) {
            return null;
        }

        $formatted = $priceOverview['final_formatted'] ?? null;

        return is_string($formatted) && $formatted !== '' ? $formatted : null;
    }

    /**
     * @param array<string, mixed>|null $appDetails
     */
    private function extractName(?array $appDetails): ?string
    {
        if ($appDetails === null) {
            return null;
        }

        $name = $appDetails['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
