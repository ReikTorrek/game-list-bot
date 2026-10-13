<?php

namespace App\Telegram\Dto;

final readonly class ParsedGameMessage
{
    /**
     * @param list<array<string, mixed>> $descriptionEntities
     */
    public function __construct(
        public string $url,
        public ?string $descriptionText,
        public array $descriptionEntities,
    ) {
    }
}
