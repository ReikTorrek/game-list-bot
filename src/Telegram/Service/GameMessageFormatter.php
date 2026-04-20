<?php

namespace App\Telegram\Service;

use App\Entity\Game;
use SergiX44\Nutgram\Telegram\Properties\MessageEntityType;

final readonly class GameMessageFormatter
{
    private const TELEGRAM_TEXT_LIMIT = 4096;

    public function __construct(private Utf16String $utf16String)
    {
    }

    /**
     * @return array{text: string, entities: list<array<string, mixed>>}
     */
    public function format(Game $game): array
    {
        $id = $game->getId();

        if ($id === null) {
            throw new \LogicException('Game ID must be available before formatting.');
        }

        $prefixLines = [
            sprintf('Игра #%d', $id),
            sprintf('Статус: %s', $game->getStatus()->label()),
            sprintf('Ссылка: %s', $game->getUrl()),
        ];

        if ($game->getSteamName() !== null) {
            $prefixLines[] = sprintf('Название в Steam: %s', $game->getSteamName());
        }

        if ($game->getSteamAppId() !== null) {
            $prefixLines[] = sprintf('Steam App ID: %d', $game->getSteamAppId());
        }

        if ($game->getSteamPrice() !== null && $game->getSteamPriceRub() !== null) {
            $prefixLines[] = sprintf('Цена в Steam: %s (%s)', $game->getSteamPrice(), $game->getSteamPriceRub());
        } elseif ($game->getSteamPrice() !== null) {
            $prefixLines[] = sprintf('Цена в Steam: %s', $game->getSteamPrice());
        } elseif ($game->getSteamPriceRub() !== null) {
            $prefixLines[] = sprintf('Цена в Steam (RU): %s', $game->getSteamPriceRub());
        }

        if ($game->getSteamRecentReviewSummary() !== null) {
            $prefixLines[] = sprintf('Недавние отзывы в Steam: %s', $game->getSteamRecentReviewSummary());
        }

        if ($game->getSteamOverallReviewSummary() !== null) {
            $prefixLines[] = sprintf('Все отзывы в Steam: %s', $game->getSteamOverallReviewSummary());
        }

        $prefix = implode("\n", $prefixLines) . "\n\nОписание:\n";

        $shift = $this->utf16String->length($prefix);
        $entities = [];

        foreach ($game->getDescriptionEntities() ?? [] as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            $offset = $entity['offset'] ?? null;
            $length = $entity['length'] ?? null;

            if (!is_int($offset) || !is_int($length)) {
                continue;
            }

            $entity['offset'] = $offset + $shift;
            $entities[] = $entity;
        }

        $text = $prefix . $game->getDescriptionText();
        $entities[] = [
            'type' => MessageEntityType::BLOCKQUOTE->value,
            'offset' => 0,
            'length' => $this->utf16String->length($text),
        ];

        return [
            'text' => $text,
            'entities' => $entities,
        ];
    }

    /**
     * @param list<Game> $games
     * @return list<array{text: string, entities: list<array<string, mixed>>}>
     */
    public function formatList(array $games, string $header): array
    {
        $formattedGames = array_map($this->format(...), $games);
        $reservedHeader = $this->buildReservedPageHeader($header, $formattedGames);
        $reservedHeaderLength = $this->utf16String->length($reservedHeader);

        if ($reservedHeader !== '') {
            $reservedHeaderLength += $this->utf16String->length("\n\n");
        }

        $maxContentLength = self::TELEGRAM_TEXT_LIMIT - $reservedHeaderLength;

        if ($maxContentLength <= 0) {
            throw new \LogicException('Header is too long to fit into Telegram message limit.');
        }

        $pages = [];
        $currentPageItems = [];
        $currentPageContentLength = 0;

        foreach ($formattedGames as $formattedGame) {
            $gameParts = $this->utf16String->length($formattedGame['text']) <= $maxContentLength
                ? [$formattedGame]
                : $this->splitFormattedChunk($formattedGame, $maxContentLength);

            foreach ($gameParts as $gamePart) {
                $separatorLength = $currentPageItems === []
                    ? 0
                    : $this->utf16String->length("\n\n");
                $gamePartLength = $this->utf16String->length($gamePart['text']);

                if (
                    $currentPageItems !== []
                    && $currentPageContentLength + $separatorLength + $gamePartLength > $maxContentLength
                ) {
                    $pages[] = $currentPageItems;
                    $currentPageItems = [];
                    $currentPageContentLength = 0;
                    $separatorLength = 0;
                }

                $currentPageItems[] = $gamePart;
                $currentPageContentLength += $separatorLength + $gamePartLength;
            }
        }

        if ($currentPageItems !== []) {
            $pages[] = $currentPageItems;
        }

        return $this->buildPages($pages, $header);
    }

    /**
     * @param list<array{text: string, entities: list<array<string, mixed>>}> $formattedGames
     */
    private function buildReservedPageHeader(string $header, array $formattedGames): string
    {
        $totalContentLength = 0;

        foreach ($formattedGames as $formattedGame) {
            $totalContentLength += $this->utf16String->length($formattedGame['text']);
        }

        $maxPageDigits = strlen((string) max(1, $totalContentLength));
        $pagePlaceholder = str_repeat('9', $maxPageDigits);

        return sprintf('%s%s', $header, sprintf("\nСтраница %s/%s", $pagePlaceholder, $pagePlaceholder));
    }

    /**
     * @param array{text: string, entities: list<array<string, mixed>>} $chunk
     * @return list<array{text: string, entities: list<array<string, mixed>>}>
     */
    private function splitFormattedChunk(array $chunk, int $maxLength = self::TELEGRAM_TEXT_LIMIT): array
    {
        $parts = [];
        $remainingText = $chunk['text'];
        $remainingEntities = $chunk['entities'];

        while ($remainingText !== '') {
            $remainingLength = $this->utf16String->length($remainingText);

            if ($remainingLength <= $maxLength) {
                $parts[] = [
                    'text' => $remainingText,
                    'entities' => $remainingEntities,
                ];
                break;
            }

            $prefix = $this->sliceByUtf16Length($remainingText, $maxLength);
            $splitPosition = $this->findPreferredSplitPosition($prefix) ?? mb_strlen($prefix);

            if ($splitPosition <= 0) {
                $splitPosition = mb_strlen($prefix);
            }

            $partText = mb_substr($remainingText, 0, $splitPosition);
            $partLength = $this->utf16String->length($partText);

            $parts[] = [
                'text' => $partText,
                'entities' => $this->sliceEntities($remainingEntities, 0, $partLength),
            ];

            $remainingText = mb_substr($remainingText, $splitPosition);
            $remainingEntities = $this->shiftEntities($remainingEntities, $partLength);
        }

        return $parts;
    }

    /**
     * @param list<list<array{text: string, entities: list<array<string, mixed>>}>> $pages
     * @return list<array{text: string, entities: list<array<string, mixed>>}>
     */
    private function buildPages(array $pages, string $header): array
    {
        $result = [];
        $pageCount = count($pages);

        foreach ($pages as $pageIndex => $pageItems) {
            $pageHeader = $this->buildPageHeader($header, $pageIndex + 1, $pageCount);
            $pageText = $pageHeader;
            $pageEntities = [];
            $pageLength = $this->utf16String->length($pageText);

            foreach ($pageItems as $pageItem) {
                $separator = $pageText !== '' ? "\n\n" : '';
                $separatorLength = $this->utf16String->length($separator);
                $shift = $pageLength + $separatorLength;

                foreach ($pageItem['entities'] as $entity) {
                    $entity['offset'] += $shift;
                    $pageEntities[] = $entity;
                }

                $pageText .= $separator . $pageItem['text'];
                $pageLength += $separatorLength + $this->utf16String->length($pageItem['text']);
            }

            $result[] = [
                'text' => $pageText,
                'entities' => $pageEntities,
            ];
        }

        return $result;
    }

    private function buildPageHeader(string $header, int $pageNumber, int $pageCount): string
    {
        if ($pageCount <= 1) {
            return $header;
        }

        return sprintf("%s\nСтраница %d/%d", $header, $pageNumber, $pageCount);
    }

    private function sliceByUtf16Length(string $text, int $maxLength): string
    {
        $textLength = mb_strlen($text);
        $low = 0;
        $high = $textLength;
        $best = '';

        while ($low <= $high) {
            $mid = (int) floor(($low + $high) / 2);
            $candidate = mb_substr($text, 0, $mid);
            $candidateLength = $this->utf16String->length($candidate);

            if ($candidateLength <= $maxLength) {
                $best = $candidate;
                $low = $mid + 1;
                continue;
            }

            $high = $mid - 1;
        }

        return $best;
    }

    private function findPreferredSplitPosition(string $text): ?int
    {
        $minimumPosition = max(1, (int) floor(mb_strlen($text) / 2));

        foreach (["\n\n", "\n", ' '] as $separator) {
            $position = mb_strrpos($text, $separator);

            if ($position !== false && $position >= $minimumPosition) {
                return $position + mb_strlen($separator);
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $entities
     * @return list<array<string, mixed>>
     */
    private function sliceEntities(array $entities, int $start, int $length): array
    {
        $end = $start + $length;
        $result = [];

        foreach ($entities as $entity) {
            $offset = $entity['offset'] ?? null;
            $entityLength = $entity['length'] ?? null;

            if (!is_int($offset) || !is_int($entityLength)) {
                continue;
            }

            $entityEnd = $offset + $entityLength;
            $sliceStart = max($offset, $start);
            $sliceEnd = min($entityEnd, $end);

            if ($sliceStart >= $sliceEnd) {
                continue;
            }

            $entity['offset'] = $sliceStart - $start;
            $entity['length'] = $sliceEnd - $sliceStart;
            $result[] = $entity;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $entities
     * @return list<array<string, mixed>>
     */
    private function shiftEntities(array $entities, int $shift): array
    {
        $result = [];

        foreach ($entities as $entity) {
            $offset = $entity['offset'] ?? null;
            $length = $entity['length'] ?? null;

            if (!is_int($offset) || !is_int($length)) {
                continue;
            }

            $entityEnd = $offset + $length;

            if ($entityEnd <= $shift) {
                continue;
            }

            $entity['offset'] = max(0, $offset - $shift);
            $entity['length'] = $entityEnd - max($offset, $shift);
            $result[] = $entity;
        }

        return $result;
    }
}
