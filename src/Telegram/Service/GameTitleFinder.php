<?php

namespace App\Telegram\Service;

use App\Entity\Game;
use App\Repository\GameRepository;

final readonly class GameTitleFinder
{
    public function __construct(private GameRepository $gameRepository)
    {
    }

    public function findBestMatch(string $query): ?Game
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return null;
        }

        $games = $this->gameRepository->findBy([], ['id' => 'ASC']);
        $bestGame = null;
        $bestScore = 0.0;

        foreach ($games as $game) {
            $score = $this->scoreGame($game, $normalizedQuery);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestGame = $game;
            }
        }

        if ($bestScore < 0.54) {
            return null;
        }

        return $bestGame;
    }

    private function scoreGame(Game $game, string $normalizedQuery): float
    {
        $bestScore = 0.0;

        foreach ($this->buildCandidates($game) as [$candidate, $weight]) {
            $normalizedCandidate = $this->normalize($candidate);

            if ($normalizedCandidate === '') {
                continue;
            }

            $score = $this->scoreStrings($normalizedQuery, $normalizedCandidate) * $weight;
            $bestScore = max($bestScore, $score);
        }

        return $bestScore;
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    private function buildCandidates(Game $game): array
    {
        $candidates = [];

        if ($game->getSteamName() !== null) {
            $candidates[] = [$game->getSteamName(), 1.0];
        }

        $descriptionLines = preg_split('/\R/u', $game->getDescriptionText(), 2) ?: [];
        $firstLine = trim($descriptionLines[0] ?? '');

        if ($firstLine !== '') {
            $candidates[] = [$firstLine, 0.82];

            $titleParts = preg_split('/\s*(?:—|-|:|\|)\s*/u', $firstLine, 2) ?: [];
            $titlePart = trim($titleParts[0] ?? '');

            if ($titlePart !== '' && $titlePart !== $firstLine) {
                $candidates[] = [$titlePart, 0.93];
            }
        }

        return $candidates;
    }

    private function scoreStrings(string $query, string $candidate): float
    {
        if ($query === $candidate) {
            return 1.0;
        }

        $queryLength = $this->unicodeLength($query);
        $candidateLength = $this->unicodeLength($candidate);

        if ($queryLength === 0 || $candidateLength === 0) {
            return 0.0;
        }

        $substringScore = 0.0;

        if (str_contains($candidate, $query) || str_contains($query, $candidate)) {
            $lengthRatio = min($queryLength, $candidateLength) / max($queryLength, $candidateLength);
            $substringScore = 0.84 + (0.12 * $lengthRatio);
        }

        $distance = $this->unicodeLevenshtein($query, $candidate);
        $levenshteinScore = 1 - ($distance / max($queryLength, $candidateLength));
        $tokenScore = $this->tokenSimilarity($query, $candidate);

        return max($substringScore, $levenshteinScore, $tokenScore);
    }

    private function tokenSimilarity(string $query, string $candidate): float
    {
        $queryTokens = $this->tokenize($query);
        $candidateTokens = $this->tokenize($candidate);

        if ($queryTokens === [] || $candidateTokens === []) {
            return 0.0;
        }

        $queryCounts = array_count_values($queryTokens);
        $candidateCounts = array_count_values($candidateTokens);
        $intersection = 0;

        foreach ($queryCounts as $token => $count) {
            $intersection += min($count, $candidateCounts[$token] ?? 0);
        }

        return (2 * $intersection) / (count($queryTokens) + count($candidateTokens));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $value): array
    {
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function unicodeLength(string $value): int
    {
        return mb_strlen($value);
    }

    private function unicodeLevenshtein(string $left, string $right): int
    {
        $leftChars = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rightChars = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $leftLength = count($leftChars);
        $rightLength = count($rightChars);

        if ($leftLength === 0) {
            return $rightLength;
        }

        if ($rightLength === 0) {
            return $leftLength;
        }

        $previousRow = range(0, $rightLength);

        for ($i = 1; $i <= $leftLength; ++$i) {
            $currentRow = [$i];

            for ($j = 1; $j <= $rightLength; ++$j) {
                $cost = $leftChars[$i - 1] === $rightChars[$j - 1] ? 0 : 1;

                $currentRow[$j] = min(
                    $currentRow[$j - 1] + 1,
                    $previousRow[$j] + 1,
                    $previousRow[$j - 1] + $cost,
                );
            }

            $previousRow = $currentRow;
        }

        return $previousRow[$rightLength];
    }
}
