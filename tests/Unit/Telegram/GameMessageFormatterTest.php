<?php

namespace App\Tests\Unit\Telegram;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Telegram\Service\GameMessageFormatter;
use App\Telegram\Service\Utf16String;
use PHPUnit\Framework\TestCase;

final class GameMessageFormatterTest extends TestCase
{
    public function testFormatShiftsEntitiesAfterPrefix(): void
    {
        $game = (new Game())
            ->setUrl('https://example.com/game')
            ->setDescriptionText("Описание\nсо второй строкой")
            ->setDescriptionEntities([
                [
                    'type' => 'italic',
                    'offset' => 0,
                    'length' => 8,
                ],
            ])
            ->setStatus(GameStatus::NEW);

        $reflection = new \ReflectionProperty($game, 'id');
        $reflection->setValue($game, 7);

        $formatter = new GameMessageFormatter(new Utf16String());
        $formatted = $formatter->format($game);

        self::assertStringContainsString('Игра #7', $formatted['text']);
        self::assertStringContainsString("Описание:\nОписание\nсо второй строкой", $formatted['text']);
        self::assertCount(2, $formatted['entities']);
        self::assertGreaterThan(0, $formatted['entities'][0]['offset']);
        self::assertSame('italic', $formatted['entities'][0]['type']);
        self::assertSame('blockquote', $formatted['entities'][1]['type']);
        self::assertSame(0, $formatted['entities'][1]['offset']);
    }

    public function testFormatIncludesSteamMetadataWhenAvailable(): void
    {
        $game = (new Game())
            ->setUrl('https://store.steampowered.com/app/620/Portal_2/')
            ->setDescriptionText('Описание')
            ->setDescriptionEntities(null)
            ->setSteamAppId(620)
            ->setSteamName('Portal 2')
            ->setSteamPrice('$9.99')
            ->setSteamPriceRub('385 pуб.')
            ->setSteamRecentReviewSummary('Крайне положительные (98%, 1 234 отзывов)')
            ->setSteamOverallReviewSummary('Очень положительные (97%, 12 345 отзывов)')
            ->setStatus(GameStatus::NEW);

        (new \ReflectionProperty($game, 'id'))->setValue($game, 11);

        $formatter = new GameMessageFormatter(new Utf16String());
        $formatted = $formatter->format($game);

        self::assertStringContainsString('Название в Steam: Portal 2', $formatted['text']);
        self::assertStringContainsString('Steam App ID: 620', $formatted['text']);
        self::assertStringContainsString('Цена в Steam: $9.99 (385 pуб.)', $formatted['text']);
        self::assertStringNotContainsString('Цена в Steam (RU): 385 pуб.', $formatted['text']);
        self::assertStringContainsString('Недавние отзывы в Steam: Крайне положительные (98%, 1 234 отзывов)', $formatted['text']);
        self::assertStringContainsString('Все отзывы в Steam: Очень положительные (97%, 12 345 отзывов)', $formatted['text']);
    }

    public function testFormatShowsRubPriceSeparatelyWhenOnlyRubPriceAvailable(): void
    {
        $game = (new Game())
            ->setUrl('https://store.steampowered.com/app/620/Portal_2/')
            ->setDescriptionText('Описание')
            ->setDescriptionEntities(null)
            ->setSteamPriceRub('385 pуб.')
            ->setStatus(GameStatus::NEW);

        (new \ReflectionProperty($game, 'id'))->setValue($game, 12);

        $formatter = new GameMessageFormatter(new Utf16String());
        $formatted = $formatter->format($game);

        self::assertStringContainsString('Цена в Steam (RU): 385 pуб.', $formatted['text']);
        self::assertStringNotContainsString('Цена в Steam: 385 pуб.', $formatted['text']);
    }

    public function testFormatListSplitsOnlyBetweenGames(): void
    {
        $formatter = new GameMessageFormatter(new Utf16String());

        $firstGame = (new Game())
            ->setUrl('https://example.com/game-1')
            ->setDescriptionText(str_repeat('A', 3200))
            ->setDescriptionEntities(null)
            ->setStatus(GameStatus::NEW);

        $secondGame = (new Game())
            ->setUrl('https://example.com/game-2')
            ->setDescriptionText(str_repeat('B', 1200))
            ->setDescriptionEntities(null)
            ->setStatus(GameStatus::NEW);

        (new \ReflectionProperty($firstGame, 'id'))->setValue($firstGame, 1);
        (new \ReflectionProperty($secondGame, 'id'))->setValue($secondGame, 2);

        $chunks = $formatter->formatList(
            [$firstGame, $secondGame],
            'Найдено игр со статусом "новая": 2'
        );

        self::assertCount(2, $chunks);
        self::assertStringContainsString('Игра #1', $chunks[0]['text']);
        self::assertStringNotContainsString('Игра #2', $chunks[0]['text']);
        self::assertStringContainsString('Игра #2', $chunks[1]['text']);
        self::assertSame('blockquote', $chunks[0]['entities'][0]['type']);
        self::assertSame('blockquote', $chunks[1]['entities'][0]['type']);
    }

    public function testFormatListSplitsOversizedSingleGameIntoMultipleChunks(): void
    {
        $formatter = new GameMessageFormatter(new Utf16String());
        $utf16String = new Utf16String();

        $game = (new Game())
            ->setUrl('https://example.com/game-1')
            ->setDescriptionText(str_repeat("Очень длинное описание игры\n", 300))
            ->setDescriptionEntities(null)
            ->setStatus(GameStatus::NEW);

        (new \ReflectionProperty($game, 'id'))->setValue($game, 1);

        $chunks = $formatter->formatList(
            [$game],
            'Найдено игр со статусом "новая": 1'
        );

        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(4096, $utf16String->length($chunk['text']));
            self::assertNotEmpty($chunk['entities']);
            self::assertSame('blockquote', $chunk['entities'][0]['type']);
        }

        self::assertStringContainsString('Найдено игр со статусом "новая": 1', $chunks[0]['text']);
        self::assertStringContainsString('Игра #1', implode("\n", array_column($chunks, 'text')));
    }
}
