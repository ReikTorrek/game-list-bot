<?php

namespace App\Tests\Unit\Telegram;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Repository\GameRepository;
use App\Telegram\Service\GameTitleFinder;
use PHPUnit\Framework\TestCase;

final class GameTitleFinderTest extends TestCase
{
    public function testFindBestMatchUsesSteamNameCaseInsensitively(): void
    {
        $portal = $this->createGame(1, 'https://store.example/portal-2', 'Кооперативная головоломка', 'Portal 2');
        $halfLife = $this->createGame(2, 'https://store.example/half-life', 'Шутер от первого лица', 'Half-Life');

        $finder = new GameTitleFinder($this->createRepository([$portal, $halfLife]));

        self::assertSame($portal, $finder->findBestMatch('portal 2'));
    }

    public function testFindBestMatchAllowsSmallTypos(): void
    {
        $portal = $this->createGame(1, 'https://store.example/portal-2', 'Кооперативная головоломка', 'Portal 2');
        $prey = $this->createGame(2, 'https://store.example/prey', 'Immersive sim', 'Prey');

        $finder = new GameTitleFinder($this->createRepository([$portal, $prey]));

        self::assertSame($portal, $finder->findBestMatch('Portla 2'));
    }

    public function testFindBestMatchCanUseTitleFromDescriptionWhenSteamNameIsMissing(): void
    {
        $game = $this->createGame(
            3,
            'https://example.com/furi',
            "Furi — boss rush action\nОчень быстрые бои",
            null,
        );

        $finder = new GameTitleFinder($this->createRepository([$game]));

        self::assertSame($game, $finder->findBestMatch('furi'));
    }

    public function testFindBestMatchReturnsNullForUnrelatedQuery(): void
    {
        $portal = $this->createGame(1, 'https://store.example/portal-2', 'Кооперативная головоломка', 'Portal 2');
        $finder = new GameTitleFinder($this->createRepository([$portal]));

        self::assertNull($finder->findBestMatch('Civilization'));
    }

    /**
     * @param list<Game> $games
     */
    private function createRepository(array $games): GameRepository
    {
        $repository = $this->getMockBuilder(GameRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy'])
            ->getMock();

        $repository->method('findBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($games);

        return $repository;
    }

    private function createGame(int $id, string $url, string $description, ?string $steamName): Game
    {
        $game = (new Game())
            ->setUrl($url)
            ->setDescriptionText($description)
            ->setStatus(GameStatus::NEW)
            ->setSteamName($steamName);

        \Closure::bind(
            static function (int $value) use ($game): void {
                $game->id = $value;
            },
            null,
            Game::class,
        )($id);

        return $game;
    }
}
