<?php

namespace App\Tests\Unit\Telegram;

use App\Telegram\Service\SteamStoreClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SteamStoreClientTest extends TestCase
{
    public function testExtractAppIdSupportsSteamStoreUrls(): void
    {
        $client = new SteamStoreClient(new MockHttpClient());

        self::assertSame(2956680, $client->extractAppId('https://store.steampowered.com/app/2956680/LORT/'));
        self::assertSame(312670, $client->extractAppId('https://store.steampowered.com/app/312670/?snr=1_5_9__205'));
        self::assertNull($client->extractAppId('https://example.com/app/620'));
        self::assertNull($client->extractAppId('https://store.steampowered.com/sub/620'));
    }

    public function testFetchMetadataForSteamUrl(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                '620' => [
                    'success' => true,
                    'data' => [
                        'name' => 'Portal 2',
                        'is_free' => false,
                        'price_overview' => [
                            'final_formatted' => '$9.99',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'query_summary' => [
                    'review_score_desc' => 'Крайне положительные',
                    'total_positive' => 980,
                    'total_reviews' => 1000,
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'query_summary' => [
                    'review_score_desc' => 'Очень положительные',
                    'total_positive' => 9700,
                    'total_reviews' => 10000,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new SteamStoreClient($httpClient);
        $metadata = $client->fetchMetadataForUrl('https://store.steampowered.com/app/620/Portal_2/');

        self::assertNotNull($metadata);
        self::assertSame(620, $metadata->appId);
        self::assertSame('Portal 2', $metadata->name);
        self::assertSame('$9.99', $metadata->price);
        self::assertSame('Крайне положительные (98%, 1 000 отзывов)', $metadata->recentReviewSummary);
        self::assertSame('Очень положительные (97%, 10 000 отзывов)', $metadata->overallReviewSummary);
    }
}
