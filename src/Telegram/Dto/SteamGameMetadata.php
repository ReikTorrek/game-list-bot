<?php

namespace App\Telegram\Dto;

final readonly class SteamGameMetadata
{
    public function __construct(
        public int $appId,
        public ?string $name,
        public ?string $price,
        public ?string $priceRub,
        public ?string $recentReviewSummary,
        public ?string $overallReviewSummary,
    ) {
    }
}
