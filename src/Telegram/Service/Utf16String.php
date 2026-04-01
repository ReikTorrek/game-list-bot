<?php

namespace App\Telegram\Service;

final class Utf16String
{
    public function length(string $text): int
    {
        $encoded = iconv('UTF-8', 'UTF-16LE', $text);

        if ($encoded === false) {
            throw new \RuntimeException('Unable to convert string to UTF-16LE.');
        }

        return (int) (strlen($encoded) / 2);
    }
}
