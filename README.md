# Crazy Defense Hereoes

Первый инкремент лендинга игры: React + Vite + TypeScript в strict-режиме.

## Запуск

```bash
npm install
npm run dev -- --host 127.0.0.1 --port 6767
```

Production-проверка:

```bash
npm run build
```

## Laravel 13 backend

Backend находится в [backend/](/Users/mihailssegins/Documents/ChatGPT/tower-defense/backend) и создан на Laravel 13.

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8001
```

Проверка backend:

```bash
php artisan --version
php artisan test
```

## Что уже есть

- адаптивный fantasy hero в стиле предоставленного референса;
- CSS placeholder-art для башни, сундуков и героев — PNG можно подставить без изменения композиции;
- интерактивный выбор Silver / Gold / Bronze сундука;
- CTA-уведомление и переключатель языка EN/RU;
- модальная регистрация со всеми полями профиля и адреса, обязательным согласием с политиками и списком стран без запрещённых юрисдикций;
- Terms & Conditions и Privacy Policy как временные legal-модули;
- `.env.example` с реквизитами компании и настройками SMTP Namecheap Private Email.
- endless game core: Canvas grid, towers, targeting, projectiles and wave checkpoints;
- catalog, orders, sandbox payments, signed webhooks and idempotent wallet ledger;
- A4 PDF invoices with Unicode-safe font and queued purchase receipts.

## Production hardening

Перед production-деплоем выполни deployment checklist и проверь конфигурацию:

    cd backend
    php artisan cdh:check-production

Секреты SMTP, платёжный webhook secret, фактические реквизиты компании и invoice storage credentials не добавляются в git — их нужно перенести в backend/.env.
