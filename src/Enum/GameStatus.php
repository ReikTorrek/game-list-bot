<?php

namespace App\Enum;

enum GameStatus: string
{
    case NEW = 'new';
    case DONE = 'done';
    case DROPPED = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'новая',
            self::DONE => 'пройдена',
            self::DROPPED => 'брошена',
        };
    }
}
