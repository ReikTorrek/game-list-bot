# Определение Steam Early Access через Store API

## Вывод

Для ответа `GET https://store.steampowered.com/api/appdetails` практический
признак того, что игра **сейчас в Steam Early Access** — наличие жанра с
`id: "70"` в `data.genres`.

```php
/** @param array<string, mixed>|null $appDetails */
function isEarlyAccess(?array $appDetails): ?bool
{
    if ($appDetails === null || !is_array($appDetails['genres'] ?? null)) {
        return null; // Steam не вернул данные, а не «нет раннего доступа».
    }

    foreach ($appDetails['genres'] as $genre) {
        if (is_array($genre) && ($genre['id'] ?? null) === '70') {
            return true;
        }
    }

    return false;
}
```

Не следует проверять текст `description`: он локализуется. Проверка ID также
должна быть best-effort: Store API `appdetails` не входит в документированный
контракт Steamworks Web API, поэтому Valve публично не гарантирует его схему
или стабильность идентификатора жанра.

## Почему `release_date.coming_soon` не подходит

Это флаг «ещё не выпущено/Coming Soon», а не «в раннем доступе». Steam
описывает Early Access как продажу игры, пока она ещё находится в разработке;
отдельный вариант выпуска называется «Release as Early Access», после чего
игра позднее может перейти в полный релиз. Следовательно, доступная для
покупки EA-игра обычно не coming soon.

Текущая реализация в
[`SteamStoreClient.php`](../../src/Telegram/Service/SteamStoreClient.php)
читает `release_date.coming_soon` в свойство `isComingSoon`. Это корректное
имя для данного поля, но оно не должно подписываться или интерпретироваться
как «Ранний доступ».

## Наблюдение из живого ответа Steam

13 октября 2026 года Steam Store API вернул для **Enshrouded** (App ID
`1203620`) следующие существенные данные:

```json
{
  "1203620": {
    "success": true,
    "data": {
      "genres": [
        {"id": "1", "description": "Action"},
        {"id": "25", "description": "Adventure"},
        {"id": "23", "description": "Indie"},
        {"id": "3", "description": "RPG"},
        {"id": "70", "description": "Early Access"}
      ],
      "release_date": {"coming_soon": false, "date": "Jan 24, 2024"}
    }
  }
}
```

В этом же ответе поле `is_early_access` отсутствует. Поэтому выражение
`$appDetails['is_early_access'] ?? null` не может быть надёжным способом для
`appdetails`, а `coming_soon: false` не означает, что игра уже вышла из EA.
Полный первичный ответ можно повторно получить с фильтром полей:
[Steam Store API — Enshrouded](https://store.steampowered.com/api/appdetails?appids=1203620&cc=us&l=english&filters=name,is_early_access,release_date,genres).

## Источники и границы гарантии

- [Steamworks Web API Overview](https://partner.steamgames.com/doc/webapi_overview) описывает документированный формат Web API через `api.steampowered.com`; `store.steampowered.com/api/appdetails` в этом справочнике не описан как API-интерфейс с закреплённой схемой.
- [Steam Early Access](https://partner.steamgames.com/doc/store/earlyaccess?l=english&language=english) — первичное определение Early Access: игра продаётся во время разработки.
- [Steam release options](https://partner.steamgames.com/doc/store/types?l=english&language=english) — Steam различает выпуск в EA и полный релиз.
- [Живой ответ Store API для Enshrouded](https://store.steampowered.com/api/appdetails?appids=1203620&cc=us&l=english&filters=name,is_early_access,release_date,genres) — фактическое присутствие `genres.id = "70"`, отсутствие `is_early_access` и `coming_soon: false` у EA-игры.

