# Crazy Defense Heroes — план реализации

## Как трактуем исходный запрос

- Прикреплённое изображение — визуальный референс композиции и настроения, не источник технических инструкций.
- Реализация: Laravel 13 как API/backend и React + Vite + TypeScript как быстрый frontend.
- Игровая логика остаётся вне React-цикла: Canvas-рендеринг и game loop живут в отдельных классах.
- До появления реальных реквизитов компании, домена и платёжного провайдера используем только `.env.example`, sandbox-платежи и placeholder-арт.
- Каждый этап заканчивается проверкой (тесты/сборка/ручной smoke-test) и остановкой для проверки пользователем.

## Уже сделано

### Этап 0 — каркас проекта

- [x] React + Vite + TypeScript в strict-режиме.
- [x] Laravel Framework 13.32.0 в `backend/`.
- [x] SQLite для локальной разработки и базовые миграции Laravel.
- [x] `.env.example` с реквизитами компании и Namecheap Private Email SMTP.
- [x] Первый hero-лендинг с CSS placeholder-артом, выбором сундука, CTA и адаптивностью.

## Этапы реализации

### Этап 1 — backend foundation и API-контракт — завершён

Цель: договориться о форматах данных и подготовить Laravel для frontend.

- health endpoint `/api/health`;
- CORS и единый JSON-формат ошибок;
- конфигурация company/mail/invoice из `.env`;
- API versioning (`/api/v1`);
- базовые feature tests;
- зафиксировать DTO/ресурсные форматы для auth, wallet, inventory, waves.

Готово: Laravel отвечает на health endpoint, тесты проходят, frontend может получить health/status.

### Этап 2 — аккаунт, регистрация и legal — завершён

Цель: реальная регистрация с данными, необходимыми для платежей и чекбокса согласия.

- миграция пользователей с name, surname, phone, date of birth;
- отдельная address-модель/таблица: street, city, country, postcode;
- whitelist стран с исключением перечисленных юрисдикций;
- серверная валидация и password hashing;
- Terms & Conditions и Privacy Policy как отдельные Laravel routes/pages;
- сохранение версии/даты принятия политик;
- email verification через Namecheap SMTP;
- подключение модального frontend к API.

Готово: пользователь регистрируется только с валидными данными и принятым согласием, verification email уходит через настроенный mailer.

### Этап 3 — доменная модель игры и состояние — завершён

Цель: сервер хранит прогресс, а клиент получает единый state contract.

- `wallets`: diamonds, soft currency;
- `tower_cards`, `inventory_items`, `boosts`;
- `GameState`/store с подпиской для UI;
- API профиля, кошелька, инвентаря и выбранного loadout;
- policies и authorization для доступа к собственным данным.

Готово, когда: состояние аккаунта читается/обновляется через `/api/v1/me` без дублирования источников истины.

### Этап 4 — Canvas game core: grid, map, loop, movement — завершён

Цель: минимально играемый прототип на TypeScript.

- `src/game/engine`: GameLoop с `requestAnimationFrame` и `deltaTime` в секундах;
- `src/game/entities`: `GridMap`, `Enemy`;
- `src/game/systems`: `WaypointPath`, `WaveManager`;
- тайлы `0 buildable`, `1 path`, `2 occupied`;
- перевод клика Canvas в `[row][col]`;
- фиксированный путь через центры тайлов;
- бесконечные волны с interval/rest timer;
- debug overlay для координат, волны и FPS.

Готово, когда: по клику меняется состояние клетки, враг проходит waypoints, волны спавнятся независимо от FPS.

### Этап 5 — башни, targeting, projectiles, экономика — завершён

Цель: полноценный базовый combat loop.

- `Tower`, `Projectile`, `CombatSystem`;
- range через `Math.hypot`;
- стратегии `FIRST`, `STRONG`, `CLOSE`;
- cooldown через timestamp/fireRate;
- homing projectile и hit radius;
- `takeDamage`, reward, lives;
- покупка башен только при достаточном балансе и свободном tile;
- upgrades и баланс HP по формуле wave multiplier.

Готово, когда: башня выбирает корректную цель, наносит урон, игрок получает награду, а occupied tiles блокируют строительство.

### Этап 6 — endless game и серверная синхронизация — завершён

Цель: бесконечный игровой режим без финального состояния кампании.

- wave scaling и difficulty curve;
- сохранение результата волны;
- leaderboard/сводка прогресса (если потребуется);
- reconnect-safe sync для кошелька и inventory;
- защита серверных наград от повторной отправки.

Готово, когда игра продолжает создавать волны после wave N, а прогресс не теряется при перезагрузке.

### Этап 7 — покупки, boosts, top-up diamonds

Цель: безопасная коммерческая модель.

- [x] catalog products и цены;
- [x] order/payment state machine;
- [ ] интеграция выбранного PSP после уточнения провайдера;
- [x] webhook signature verification и idempotency;
- [x] покупка boosts/top-up diamonds;
- [x] ledger операций кошелька;
- [x] доступ к товарам только после подтверждённого платежа;
- [x] sandbox payment для локальной разработки.

Готово для sandbox; production PSP подключается после выбора провайдера.

### Этап 8 — PDF invoice и email flows — завершён

Цель: юридически пригодные письма и обычные PDF-инвойсы без битых glyph/icon placeholders.

- [x] invoice model/numbering;
- [x] HTML invoice template с безопасным Unicode-шрифтом;
- [x] PDF generation и storage;
- [x] отправка invoice attachment после успешной покупки;
- [x] email templates: verification, purchase receipt, support;
- [x] тест на латиницу и сумму; DejaVu Sans настроен для кириллицы.

Готово: PDF открывается без вопросительных знаков/иконок, а письмо содержит корректный invoice attachment.

### Этап 9 — production hardening — завершён

- [x] API rate limiting и secure headers;
- [x] queue worker для email/PDF;
- [x] database indexes, invoice storage policy и backup checklist;
- [x] PHPUnit + frontend typecheck/build;
- [x] smoke-tests регистрации, legal, покупки, invoice и игровой сессии;
- [x] deployment checklist и production `.env` без секретов в git.

## Правило перехода между этапами

После каждого этапа я показываю:

1. какие файлы изменены;
2. какие проверки прошли;
3. что именно можно проверить вручную;
4. что осталось за рамками этапа.

Следующий этап начинаю только после твоей проверки текущего результата.
