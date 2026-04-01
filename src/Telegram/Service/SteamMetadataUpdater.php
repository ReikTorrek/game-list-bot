<?php

namespace App\Telegram\Service;

use App\Entity\Game;
use Psr\Log\LoggerInterface;

final readonly class SteamMetadataUpdater
{
    public function __construct(
        private SteamStoreClient $steamStoreClient,
        private LoggerInterface $logger,
    ) {
    }

    public function refresh(Game $game): bool
    {
        try {
            $steamMetadata = $this->steamStoreClient->fetchMetadataForUrl($game->getUrl());
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to fetch Steam metadata for game URL.', [
                'url' => $game->getUrl(),
                'exception' => $exception,
            ]);

            return false;
        }

        if ($steamMetadata === null) {
            return false;
        }

        $game
            ->setSteamAppId($steamMetadata->appId)
            ->setSteamName($steamMetadata->name)
            ->setSteamPrice($steamMetadata->price)
            ->setSteamPriceRub($steamMetadata->priceRub)
            ->setSteamRecentReviewSummary($steamMetadata->recentReviewSummary)
            ->setSteamOverallReviewSummary($steamMetadata->overallReviewSummary);

        return true;
    }
}
