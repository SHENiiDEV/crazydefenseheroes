# Crazy Defense Heroes — полный гайд по деплою

Актуализировано: 25 ноября 2026 года. React + Vite находятся в корне, Laravel 13 — в `backend/`. Sterling Pay / Corefy поддерживает EUR и отдельно включаемый GBP с другим merchant account; PDF-инвойсы приватные, письма с ними отправляет очередь.

Это инструкция, а не уже выполненный деплой. Примеры рассчитаны на отдельный VPS с Ubuntu 24.04 LTS, Nginx и PHP 8.4 (CLI + FPM). На работающем сервере начните с раздела 12: не клонируйте проект поверх существующего, не заменяйте `.env`, базу или `APP_KEY`.

| Назначение | Значение в примерах |
| --- | --- |
| Домен | `crazydefensehereoes.co.uk` — именно такое написание |
| Репозиторий | `/var/www/crazydefensehereoes` |
| Публичный фронтенд | `/var/www/crazydefensehereoes/dist` |
| Laravel | `/var/www/crazydefensehereoes/backend` |
| PHP-точка входа | `backend/public/index.php` |
| Пользователь деплоя | `deploy` |
| PHP-FPM и очередь | `www-data` |

Если у сервера другие пути, пользователи или PHP-сокет, замените их во всех примерах. Выполняйте команды по шагам; после ошибки остановитесь, не продолжайте релиз автоматически.

## Содержание

1. [Схема production](#1-схема-production)
2. [Сервер и зависимости](#2-сервер-и-зависимости)
3. [Получение проекта](#3-получение-проекта)
4. [База данных](#4-база-данных)
5. [Два отдельных env-файла](#5-два-отдельных-env-файла)
6. [Установка Laravel и права](#6-установка-laravel-и-права)
7. [Сборка фронтенда](#7-сборка-фронтенда)
8. [Nginx и HTTPS](#8-nginx-и-https)
9. [Очередь писем](#9-очередь-писем)
10. [Sterling Pay и инвойсы](#10-sterling-pay-и-инвойсы)
11. [Проверка запуска](#11-проверка-запуска)
12. [Обновление работающего сайта](#12-обновление-работающего-сайта)
13. [Бэкапы и восстановление](#13-бэкапы-и-восстановление)
14. [Типовые ошибки](#14-типовые-ошибки)

## 1. Схема production

Nginx отдаёт `/` и статику из `dist/`. Запросы `/api/*`, `/legal/*` и `/up` передаёт в Laravel через PHP-FPM. Laravel работает с БД, создаёт PDF и ставит отправку писем в очередь; постоянный worker под Supervisor обрабатывает её.

На production не нужны `npm run dev`, `vite preview`, `php artisan serve`, PM2 и открытые наружу порты 5173, 6767 или 8001. Node нужен для сборки. Сайт и API используют один HTTPS-домен.

Не устанавливайте Nginx root на весь репозиторий или `backend/`: это может открыть секреты. Здесь root — только `dist/`, а PHP выполняет явно заданный `backend/public/index.php`.

## 2. Сервер и зависимости

### 2.1. DNS и доступ

- A-запись домена должна указывать на IPv4 сервера. AAAA добавляйте только при рабочем IPv6.
- `www` требует отдельной DNS-записи и сертификата; основной пример работает без `www`.
- Нужны входящие 80/443 и SSH. Перед включением firewall разрешите фактический SSH-порт и проверьте второй сеанс, чтобы не потерять доступ.
- Не открывайте MariaDB, PHP-FPM и Supervisor в интернет. Разрешите исходящие HTTPS к Sterling Pay и SMTP к Private Email.
- Настройте пользователю `deploy` вход по SSH-ключу и `sudo`. Сборки и Composer выполняются не от root.

Если пользователя ещё нет, администратор создаёт его:

```bash
sudo adduser deploy
sudo usermod -aG sudo deploy
```

### 2.2. PHP и системные пакеты

Используем только PHP 8.4: CLI, PHP-FPM и worker одной ветки с актуальными исправлениями, **не ниже 8.4.1**. Текущие Symfony-зависимости требуют `>=8.4.1`, поэтому 8.4.0 не подходит. PHP-ограничения lock-файла проверены для 8.4.1; это не заменяет проверку расширений и runtime на сервере. Минимального значения в `composer.json` недостаточно — после установки выполните `composer check-platform-reqs --no-dev` именно под PHP 8.4.

```bash
sudo apt update
sudo apt install -y nginx mariadb-server supervisor git curl unzip acl nano ripgrep software-properties-common
```

Если доверенный репозиторий PHP 8.4 уже подключён, не добавляйте второй. На чистой Ubuntu 24.04 один из вариантов — сторонний [PPA Ondřej Surý](https://launchpad.net/~ondrej/+archive/ubuntu/php). Подключая его, вы доверяете этому поставщику пакетов:

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
apt-cache policy php8.4-fpm
sudo apt install -y php8.4-cli php8.4-fpm php8.4-common php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-mysql php8.4-sqlite3 php8.4-gd php8.4-bcmath
sudo apt install -y composer
sudo systemctl enable --now nginx mariadb php8.4-fpm supervisor
```

Проверьте:

```bash
php -v
php8.4 -v
php8.4 -m
composer --version
ls -l /run/php/
sudo systemctl status php8.4-fpm --no-pager
```

Далее `php` должен обозначать PHP 8.4, и Composer должен запускаться под ним. Иначе администратор выбирает CLI через `sudo update-alternatives --config php`, учитывая другие сайты, и повторно проверяет `php -v`. У worker ниже явный путь `/usr/bin/php8.4`, у Nginx — сокет PHP 8.4. В CLI нужен `pcntl` для таймаутов очереди. Наличие другого FPM на сервере не означает, что наш сайт должен его использовать.

Не обходите отсутствующие расширения через `--ignore-platform-reqs`. Базовые требования и правила публикации: [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment).

### 2.3. Node.js

Используйте Node.js 24 с актуальными исправлениями и npm. Установленный Vite 8.3.0 требует Node `^20.19.0 || >=22.12.0`; Node 24 подходит. Статусы веток: [Node.js Releases](https://nodejs.org/en/about/previous-releases).

Установите Node для `deploy`. Один из вариантов без исполнения скачанного shell-установщика — git-установка закреплённого релиза [nvm](https://github.com/nvm-sh/nvm#git-install). Только если `/home/deploy/.nvm` ещё не существует, в сеансе `deploy`:

```bash
git clone --depth 1 --branch v0.40.8 https://github.com/nvm-sh/nvm.git /home/deploy/.nvm
. /home/deploy/.nvm/nvm.sh
nvm install 24
nvm alias default 24
nvm use 24
node --version
npm --version
```

В новых сеансах повторите `. /home/deploy/.nvm/nvm.sh` или добавьте эту строку редактором в профиль shell пользователя `deploy`. При другом домашнем каталоге измените путь. Не выполняйте `sudo npm`. В автоматическом деплое nvm надо явно загрузить либо использовать подготовленный build runner.

## 3. Получение проекта

На новом сервере администратор создаёт пустой каталог:

```bash
sudo install -d -o deploy -g www-data -m 0755 /var/www/crazydefensehereoes
```

Далее — сеанс `deploy`:

```bash
git clone https://github.com/SHENiiDEV/crazydefenseheroes.git /var/www/crazydefensehereoes
cd /var/www/crazydefensehereoes
git status --short
git log -1 --oneline
```

Для закрытого репозитория используйте read-only deploy key или защищённую авторизацию. Не вставляйте GitHub-токен в URL и историю команд. Если каталог уже существует, используйте текущую копию и раздел 12. Не затирайте серверные изменения через `git reset --hard` или `git clean`.

## 4. База данных

### 4.1. Новая установка: MariaDB

Администратор открывает SQL-консоль:

```bash
sudo mariadb
```

Выполните один раз, заменив пароль уникальным. Не сохраняйте его в git/переписке; учитывайте историю SQL-клиента.

```sql
CREATE DATABASE cdh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cdh_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD';
GRANT ALL PRIVILEGES ON cdh.* TO 'cdh_app'@'127.0.0.1';
EXIT;
```

Параметры должны совпадать с `backend/.env`. Приложению не нужен пользователь БД root.

### 4.2. Если сайт уже работает на SQLite

Не меняйте `DB_CONNECTION` только ради гайда: без отдельного переноса пользователи, балансы и заказы останутся в старой базе. Сначала установите, какая БД реально используется, и сделайте проверенный бэкап.

Для SQLite задайте абсолютный `DB_DATABASE`, обеспечьте доступ PHP-FPM и worker к файлу и каталогу для journal/WAL, учитывайте ограничения конкурентной записи. Не создавайте пустую БД вместо действующей. Бэкап описан в разделе 13.

## 5. Два отдельных env-файла

| Файл | Назначение | После изменения |
| --- | --- | --- |
| `/var/www/crazydefensehereoes/.env` | Vite: адрес API | Пересобрать frontend для `VITE_*` |
| `/var/www/crazydefensehereoes/backend/.env` | Laravel: компания, БД, SMTP, платежи | Обновить PHP-кэши и worker |

Секреты никогда не помещаются в `VITE_*`: они попадают в публичный JavaScript. Старый корневой `.env.example` содержит SMTP/company-примеры, но Laravel читает их из **`backend/.env`**, не из корня.

### 5.1. Корневой .env

Создайте редактором `/var/www/crazydefensehereoes/.env`:

```dotenv
VITE_API_BASE_URL=/api/v1
```

Проверьте переопределения в `.env.production`, `.env.production.local` и среде build runner. В production API не должен ссылаться на localhost: для посетителя это его компьютер.

### 5.2. Laravel .env

Только при новой установке, если файла ещё нет:

```bash
cd /var/www/crazydefensehereoes/backend
cp -n .env.example .env
nano .env
```

Приведите параметры к следующему виду, заменив пароли и платёжные данные. Реквизиты компании предоставлены владельцем проекта; перед запуском подтвердите актуальность.

```dotenv
APP_NAME="Crazy Defense Heroes"
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://crazydefensehereoes.co.uk
FRONTEND_URL=https://crazydefensehereoes.co.uk
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cdh
DB_USERNAME=cdh_app
DB_PASSWORD="REPLACE_WITH_A_UNIQUE_DATABASE_PASSWORD"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_AFTER_COMMIT=true
DB_QUEUE_RETRY_AFTER=120
FILESYSTEM_DISK=local

CORS_ALLOWED_ORIGINS=https://crazydefensehereoes.co.uk
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Accept,Authorization,Content-Type,X-Request-Id,Idempotency-Key,X-Payment-Signature

COMPANY_NAME="PROTECT YOUR ASSETS TODAY LTD"
COMPANY_NUMBER="15969857"
COMPANY_ADDRESS="Academy House, 11 Dunraven Place, Bridgend, Mid Glamorgan, CF31 1JF"
COMPANY_EMAIL="info@crazydefensehereoes.co.uk"
COMPANY_DOMAIN="crazydefensehereoes.co.uk"

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=mail.privateemail.com
MAIL_PORT=587
MAIL_USERNAME=info@crazydefensehereoes.co.uk
MAIL_PASSWORD="REPLACE_WITH_THE_MAILBOX_PASSWORD"
MAIL_FROM_ADDRESS=info@crazydefensehereoes.co.uk
MAIL_FROM_NAME="${APP_NAME}"

INVOICE_NUMBER_PREFIX=CDH-
INVOICE_CURRENCY=EUR
INVOICE_LOCALE=en_GB
INVOICE_STORAGE_DISK=local

PAYMENTS_PROVIDER=corefy
TOPUP_MANUAL_ENABLED=false
TOPUP_DIAMONDS_PER_EURO=100
TOPUP_GOLD_PER_EURO=2000

COREFY_API_URL=https://api.sterling-pay.com
COREFY_ACCOUNT_ID=
COREFY_API_KEY=
COREFY_WEBHOOK_SECRET=
COREFY_TEST_MODE=true
COREFY_SERVICE_EUR=payment_card_eur_hpp
COREFY_HPP_HOSTS=api.sterling-pay.com
COREFY_CALLBACK_URL=https://crazydefensehereoes.co.uk/api/v1/payments/corefy/webhook

TERMS_VERSION=2026-11-25
PRIVACY_VERSION=2026-11-25
```

`APP_KEY` генерируется один раз после установки зависимостей; на существующем сайте сохраните прежний. Правильные имена — `COMPANY_NAME` / `COMPANY_NUMBER`, без опечаток `COMAPANY_NAME` / `COMAPNY_NUMBER` из старого шаблона.

`DB_URL` и `MAIL_URL`, если заданы, могут переопределять параметры подключения. Не оставляйте старые значения и дубли переменных. Пустые Corefy-ключи в примере намеренные: заполните их до проверки checkout, не открывая незавершённую установку для покупок.

### 5.3. Private Email

Создайте настоящий ящик `info@crazydefensehereoes.co.uk`. Настройте MX, SPF, DKIM и DMARC по данным почтовой панели; не придумывайте DKIM-ключи и не создавайте конфликтующие SPF-записи.

Private Email использует `mail.privateemail.com`, полное имя ящика как логин, 587 с STARTTLS или 465 с implicit TLS. Источник: [Namecheap Private Email](https://www.namecheap.com/support/knowledgebase/article.aspx/1179/2175/general-private-email-configuration-for-mail-clients-and-mobile-devices/).

В текущем `config/mail.php` читается `MAIL_SCHEME`, а `MAIL_ENCRYPTION` из старого шаблона не используется. Для 587 укажите `MAIL_SCHEME=smtp`, не `tls`; TLS согласует SMTP-транспорт. Для 465 — `MAIL_SCHEME=smtps`. Не отключайте проверку сертификата.

## 6. Установка Laravel и права

### 6.1. Runtime-каталоги

Исходники принадлежат `deploy`; веб-процесс не должен переписывать весь репозиторий. В `storage/` и `bootstrap/cache/` нужны права на запись обоим пользователям. ACL ниже также наследуется новыми файлами:

```bash
sudo setfacl -R -m u:deploy:rwX,u:www-data:rwX /var/www/crazydefensehereoes/backend/storage /var/www/crazydefensehereoes/backend/bootstrap/cache
sudo setfacl -R -d -m u:deploy:rwx,u:www-data:rwx /var/www/crazydefensehereoes/backend/storage /var/www/crazydefensehereoes/backend/bootstrap/cache
sudo chown deploy:www-data /var/www/crazydefensehereoes/backend/.env
sudo chmod 640 /var/www/crazydefensehereoes/backend/.env
sudo -u www-data test -r /var/www/crazydefensehereoes/backend/.env
sudo -u www-data test -w /var/www/crazydefensehereoes/backend/storage/app/private
```

Проверьте чтение исходников и проход по родительским каталогам от `www-data`. Не используйте `chmod -R 777`.

### 6.2. Зависимости и APP_KEY

От имени `deploy`, из `backend/`:

```bash
cd /var/www/crazydefensehereoes/backend
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Только для **новой установки с пустым APP_KEY**:

```bash
php artisan key:generate --force
```

На обновлениях ключ не генерируется. `composer install` использует закоммиченный lock-файл; `composer update` на production не нужен. Подробнее: [Composer install](https://getcomposer.org/doc/03-cli.md#install-i).

### 6.3. Миграции и каталог

```bash
php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=ProductSeeder --force
php artisan migrate:status
php artisan optimize
```

Миграции создают также таблицы очереди, cache и сессий. Для Sterling Pay обязательна `2026_10_26_080045_add_corefy_checkout_fields_to_orders_table`. На существующей базе сначала сделайте бэкап.

Запускайте только `ProductSeeder`, не общий `DatabaseSeeder`: общий создаёт тестового пользователя и использует dev-фабрики. `ProductSeeder` обновляет предустановленные товары; не запускайте его при каждом релизе, если цены каталога менялись вручную.

Инвойсы на диске `local` находятся в `backend/storage/app/private/invoices/`. Для них **не нужен** `storage:link`, публиковать каталог нельзя. PDF выдаётся владельцу через авторизованный `/api/v1/me/invoices/{invoice}`. S3 не обязателен; его подключение потребует отдельной настройки приватного диска и совместимого адаптера.

## 7. Сборка фронтенда

Команды выполняются **из корня, не из backend/**:

```bash
cd /var/www/crazydefensehereoes
npm ci
npm run build
test -s dist/index.html
ls -lh dist/assets/
```

Сборка проверяет TypeScript и создаёт `dist/`. Не используйте `--omit=dev` перед сборкой: Vite и TypeScript находятся в devDependencies. Не выполняйте `npm update` при деплое. Уязвимость из аудита разбирайте отдельно и проверяйте исправление до релиза, без слепого `npm audit fix --force`.

`backend/package.json` относится к Laravel-шаблону, а текущий UI находится в корне. Ошибка `Cannot find package 'laravel-vite-plugin'` при сборке из `backend/` означает неверно выбранную сборку. Подробнее: [Vite production build](https://vite.dev/guide/build).

## 8. Nginx и HTTPS

### 8.1. Конфиг сайта

Администратор создаёт `/etc/nginx/sites-available/crazydefensehereoes`. Не заменяйте работающий конфиг без копии. Проверьте сокет в `/run/php/`.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name crazydefensehereoes.co.uk;

    root /var/www/crazydefensehereoes/dist;
    index index.html;
    charset utf-8;
    client_max_body_size 2m;

    access_log /var/log/nginx/cdh-access.log;
    error_log /var/log/nginx/cdh-error.log;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location ~ /\. {
        deny all;
    }

    # Preserve the original URI and query string; execute Laravel's entry point.
    location ~ ^/(?:api(?:/|$)|legal(?:/|$)|up$) {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/crazydefensehereoes/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/crazydefensehereoes/backend/public;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 120s;
    }

    # Never execute arbitrary PHP files from a browser URL.
    location ~ \.php(?:/|$) {
        return 404;
    }

    location /assets/ {
        try_files $uri =404;
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options nosniff always;
    }

    location = /index.html {
        add_header Cache-Control "no-cache";
        add_header X-Content-Type-Options nosniff always;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

Это конфиг двух частей нашего приложения, не стандартного Blade-сайта. Системный `fastcgi_params` должен передавать исходные `REQUEST_URI`, `QUERY_STRING` и `HTTPS`; проверьте include на вашем сервере. Описание: [Nginx FastCGI](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html).

Включите сайт один раз; если ссылка есть, повторять `ln` не нужно:

```bash
sudo ln -s /etc/nginx/sites-available/crazydefensehereoes /etc/nginx/sites-enabled/crazydefensehereoes
sudo nginx -t
sudo systemctl reload nginx
```

При конфликте `server_name` найдите старый конфиг через `sudo nginx -T`, не удаляйте другие сайты. Пока доступен только HTTP, не принимайте регистрации/реальные платежи.

### 8.2. Сертификат

Если Certbot установлен, используйте существующую установку. На чистой Ubuntu с настроенным snapd один из вариантов:

```bash
sudo snap install --classic certbot
sudo /snap/bin/certbot --nginx --redirect -d crazydefensehereoes.co.uk
sudo /snap/bin/certbot renew --dry-run
sudo nginx -t
```

Если `snap` отсутствует, сначала установите/настройте `snapd` для вашей Ubuntu; не продолжайте при ошибке установки. Certbot добавит HTTPS и HTTP-редирект. Не добавляйте `www` без его DNS. Проверьте автоматическое продление: `systemctl list-timers --all`. Не смешивайте apt- и snap-установки Certbot. Источник: [Certbot для Nginx](https://certbot.eff.org/instructions?ws=nginx&os=snap).

При CDN/reverse proxy используйте HTTPS до origin и проверьте определение HTTPS Laravel. Не отключайте TLS и не доверяйте любым proxy-адресам без отдельной настройки: подписи ссылок зависят от внешнего URL.

Не кэшируйте `/api/*`, `/legal/*` и HTML политик на CDN. На callback не должно быть CAPTCHA/browser challenge или пользовательской авторизации; подлинность проверяет подпись.

## 9. Очередь писем

PDF формируется после подтверждения заказа, письмо отправляет `SendPurchaseReceipt` через database queue. Без worker письма копятся, даже когда сайт работает.

Создайте `/etc/supervisor/conf.d/cdh-worker.conf`:

```ini
[program:cdh-worker]
directory=/var/www/crazydefensehereoes/backend
command=/usr/bin/php8.4 artisan queue:work database --queue=default --sleep=3 --tries=3 --backoff=60 --timeout=60 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=180
redirect_stderr=true
stdout_logfile=/var/log/supervisor/cdh-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

Проверьте наличие `/var/log/supervisor/`, затем:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status cdh-worker
```

Ожидается `RUNNING`. Здесь timeout 60 меньше `DB_QUEUE_RETRY_AFTER=120`, а `stopwaitsecs=180` даёт заданию завершиться. Принципы: [Laravel queues](https://laravel.com/framework/docs/13.x/queues), [Supervisor configuration](https://supervisord.org/configuration.html#program-x-section-settings).

После изменения только `.env` или небольшого изменения кода:

```bash
cd /var/www/crazydefensehereoes/backend
php artisan config:clear
php artisan optimize
php artisan queue:restart
sudo supervisorctl status cdh-worker
```

`queue:restart` просит worker завершить текущее задание; Supervisor запускает его заново. Cache должен быть общим для CLI/worker — здесь database. Если worker уже остановлен, его запускают через `sudo supervisorctl start cdh-worker`.

В текущем `routes/console.php` нет расписания. Cron `schedule:run` не требуется для чеков и не обрабатывает очередь. Бэкапы планируются отдельно.

## 10. Sterling Pay и инвойсы

### 10.1. Режимы

| Настройки | Поведение |
| --- | --- |
| `PAYMENTS_PROVIDER=corefy` | Hosted checkout EUR и отдельно включённого GBP, выдача после серверной проверки |
| Corefy + `COREFY_TEST_MODE=true` | Тестовые платежи с соответствующими test-данными |
| Corefy + `COREFY_TEST_MODE=false` | Реальные платежи с соответствующими live-данными |
| Не Corefy + `TOPUP_MANUAL_ENABLED=true` | Бесплатная ручная выдача, деньги не списываются |
| Не Corefy + `TOPUP_MANUAL_ENABLED=false` | Ручные покупки выключены |

В Corefy `TOPUP_MANUAL_ENABLED` не включает бесплатную выдачу. GBP требует отдельного профиля ниже; USD по-прежнему не подключён к эквайрингу. Не переводите live-сайт в ручной режим для обхода ошибки оплаты.

Тестовые успешные Corefy-платежи тоже выдают виртуальные товары: проводите испытания в изолированном staging с отдельной БД, не в пользовательских live-кошельках.

### 10.2. Подключение

1. Внесите в `backend/.env` выданные account ID, API key, callback secret и EUR service. Разные ключи не обязательно взаимозаменяемы.
2. Проведите provider test на staging с `COREFY_TEST_MODE=true`. Фейковые локальные тесты не проверяют merchant account.
3. Подтвердите production callback: `https://crazydefensehereoes.co.uk/api/v1/payments/corefy/webhook`. Staging использует свой домен и callback.
4. Return URL приложение строит само: `https://crazydefensehereoes.co.uk/?payment_return=<public-order-uuid>`. Это только подсказка интерфейсу, не подтверждение оплаты.
5. После согласованного теста внесите live-данные и `COREFY_TEST_MODE=false`, обновите config cache и worker. Заказы сохраняют test/live-признак; смена режима не переносит старый заказ в новый режим.

Используется Commerce Payment Invoice API `/payment-invoices`. Callback ожидает `X-Signature`: `base64(sha1(secret + raw body + secret, binary))`. Это не старый generic endpoint `/api/v1/payments/webhooks/{provider}` с `X-Payment-Signature`. Не отключайте проверку подписи.

Перед выдачей backend сам читает invoice и сверяет reference, ID, валюту заказа, исходную/обработанную суммы, test/live-режим, `processed` и `resolution=ok`. Pending, ошибки и частичная оплата не дают начислений; повтор callback не должен повторять выдачу.

Для GBP добавьте в `backend/.env` отдельные данные, полученные именно для GBP merchant account. Прежние `COREFY_ACCOUNT_ID`, `COREFY_API_KEY`, `COREFY_WEBHOOK_SECRET` и `COREFY_SERVICE_EUR` остаются EUR-профилем:

```dotenv
COREFY_GBP_ENABLED=true
COREFY_GBP_API_URL=https://api.sterling-pay.com
COREFY_GBP_ACCOUNT_ID=
COREFY_GBP_API_KEY=
COREFY_GBP_WEBHOOK_SECRET=
COREFY_GBP_TEST_MODE=true
COREFY_SERVICE_GBP=payment_card_gbp_hpp
COREFY_GBP_CALLBACK_URL=https://crazydefensehereoes.co.uk/api/v1/payments/corefy/gbp/webhook
COREFY_GBP_HPP_HOSTS=api.sterling-pay.com
TOPUP_DIAMONDS_PER_GBP=120
TOPUP_GOLD_PER_GBP=2400
```

Пример использует test mode; после проверки с соответствующими test-данными для боевого GBP задайте `COREFY_GBP_TEST_MODE=false` с live-ключами GBP. Подтверждённый service-код — `payment_card_gbp_hpp`. В GBP merchant account укажите **отдельный callback** `/api/v1/payments/corefy/gbp/webhook`; прежний `/api/v1/payments/corefy/webhook` остаётся только EUR. Return URL для обеих валют одинакового вида, с уникальным UUID заказа.

GBP не использует EUR account/key/secret при отсутствии своих значений. Выбор ключей для создания invoice, повторной проверки, восстановления ссылки и CLI-сверки определяется валютой сохранённого заказа. Подпись GBP callback проверяется только GBP-секретом, затем валютой заказа. Секреты не передаются frontend. Test/live-режим также независим для каждой валюты; не меняйте merchant account существующих pending-заказов.

После изменения конфигурации выполните `config:clear`, `optimize`, `queue:restart`, а после обновления кода — пересоберите frontend из корня. Проверьте `cdh:check-production`, выбор GBP в Treasury, итог в GBP и подтверждение через нужный аккаунт провайдера. Новая миграция для GBP не требуется: валюта и test/live-признак уже сохраняются в заказе. Обновите `TERMS_VERSION` и `PRIVACY_VERSION` до `2026-11-25`.

`COREFY_GBP_ENABLED=false` скрывает GBP и блокирует новые GBP-покупки, но не отменяет созданные provider invoices. Сохраните GBP-ключи, callback и прежний режим: подтверждение ранее созданного GBP-заказа продолжает обрабатываться.

### 10.3. Ссылка и восстановление

Сначала выбирается разрешённый HTTPS `hpp_url`, затем разрешённый GET `flow_data.action`. Если HPP на `api.sterling-pay.com`, а action на `checkout.s-pay.finance`, достаточно `COREFY_HPP_HOSTS=api.sterling-pay.com`. Не добавляйте wildcard или непроверенные хосты.

Если invoice создан, но ссылки нет, в Treasury нажмите **Check payment**, затем **Continue payment**. Backend восстановит допустимую ссылку pending-заказа. Повтор с тем же idempotency key использует уже связанный invoice, не создавая новый.

Если POST оборвался до получения provider ID, не создавайте новый заказ вслепую: invoice мог появиться у провайдера. Найдите его в merchant dashboard и сверьте точный заказ:

```bash
cd /var/www/crazydefensehereoes/backend
php artisan cdh:reconcile-payment PUBLIC_ORDER_UUID --invoice=PROVIDER_INVOICE_ID
```

Когда provider ID уже привязан:

```bash
php artisan cdh:reconcile-payment PUBLIC_ORDER_UUID
```

Замените заглушки точными значениями. Команда не инициирует списание/возврат, но при подтверждённом `processed` **начисляет товары и запускает выдачу PDF/письма** — это не только диагностическое чтение. Не выставляйте `paid` вручную в БД.

Возвраты фиксируются для проверки поддержкой; использованные товары автоматически не списываются. Денежный возврат выполняется по процессу merchant/provider, не этой командой.

### 10.4. PDF и почта

- Номер: `CDH-<год>-<16 случайных букв/цифр>`, не последовательный счётчик.
- PDF хранится приватно; деплой не должен удалять `storage/app/private/`.
- После подтверждения проверьте PDF в Treasury: реквизиты, сумму, валюту и читаемость. Тестовые инвойсы помечены `TEST PAYMENT`.
- Проверьте письмо с тем же PDF в реальном ящике и Spam. `sent_at` означает передачу mail transport, не гарантию попадания во входящие.
- При SMTP-ошибке выполните `php artisan queue:failed`, устраните причину и повторите конкретное задание: `php artisan queue:retry JOB_UUID`. Не повторяйте покупку ради письма.

Сейчас `tax_minor=0`. Это не налоговый расчёт и не подтверждение правомерности нулевого VAT. Владелец должен до live-запуска определить налоговый режим и корректность цены/инвойса; при необходимости понадобится отдельная доработка.

## 11. Проверка запуска

### 11.1. Публичные проверки

```bash
curl -fsS https://crazydefensehereoes.co.uk/api/v1/health
curl -fsS https://crazydefensehereoes.co.uk/api/v1/company
curl -fsS https://crazydefensehereoes.co.uk/api/v1/registration/countries
curl -I https://crazydefensehereoes.co.uk/api/v1/legal/terms/page
curl -I https://crazydefensehereoes.co.uk/legal/privacy
curl -I https://crazydefensehereoes.co.uk/up
```

У health проверьте `environment=production`, `services.database=ok` и компанию. HTTP 200 возможен даже с `database=unavailable`; `mail=configured` не проверяет авторизацию SMTP/доставку.

Проверка конфигурации без печати ключей:

```bash
cd /var/www/crazydefensehereoes/backend
php artisan cdh:check-production
php artisan migrate:status
php artisan queue:failed
sudo supervisorctl status cdh-worker
```

На staging с Corefy test mode production-check намеренно сообщает, что live mode выключен. Не меняйте режим ради зелёного результата. На боевом запуске устраните каждый FAIL; PASS не доказывает доступность callback, SMTP или правильность налогов.

### 11.2. Проверка посетителем

- Откройте сайт на компьютере/телефоне, проверьте favicon, графику и Console.
- В Network API должен идти на `/api/v1/*`, не localhost, и возвращать JSON, не SPA HTML.
- Зарегистрируйте согласованный тестовый аккаунт с доступным ящиком: обязательные поля, страны, согласия и welcome-письмо. Сейчас код сразу помечает email подтверждённым; обязательной email-верификации регистрация не обеспечивает.
- Откройте четыре политики и **Open full policy page**. HTML: `/api/v1/legal/{document}/page`; старые `/legal/*` также обслуживает Nginx выше.
- Проверьте компанию в футере/legal/members area, сохранение баланса после повторного входа и награды завершённой волны.
- На staging проверьте успешную, отклонённую, pending-оплату, return URL, повтор/задержку callback. Успех должен давать одно начисление, один PDF и одно штатное письмо.
- Другой аккаунт не должен скачивать чужой PDF. Pending-заказ не должен выдавать товары или PDF оплаченного заказа.

Версия legal в коде — `2026-11-25`; `TERMS_VERSION` и `PRIVACY_VERSION` должны совпадать с публикуемыми документами. Список валют в Corefy-политиках учитывает включение GBP. `ACQUIRER_RESPONSE.md` описывает прежний ручной режим, не пересылайте его как описание Corefy. Локальные тесты не доказывают работу production SMTP/provider account.

### 11.3. Автотесты в CI / изолированном checkout

Не запускайте тесты на production-базе или под закэшированной production-конфигурацией. После `composer install --no-dev` тестовые зависимости не устанавливаются. Для тестов нужен отдельный checkout, dev-зависимости и testing env: SQLite in-memory, mail `array`, queue `sync`, без live-ключей.

```bash
cd /path/to/isolated-checkout
npm ci
npm run build
cd backend
composer install --prefer-dist --no-interaction
php artisan config:clear
php artisan test --compact
```

`backend/phpunit.xml` содержит testing-настройки, но всё равно убедитесь в отсутствии production-переменных и доступа к live-сервисам. PHPUnit не обязателен на runtime-сервере.

## 12. Обновление работающего сайта

### 12.1. Подготовка

1. Проверьте релиз и миграции на staging/CI. Запишите текущий commit и сохраните предыдущий frontend artifact.
2. Сделайте бэкап БД/PDF/конфига по разделу 13. Учтите платежи и callback в полёте.
3. Проверьте локальные изменения; не затирайте их, даже если это только права. Не делайте автоматический stash с секретами.
4. Сохраните действующие `.env` и `APP_KEY`, не заменяйте их шаблонами.

```bash
cd /var/www/crazydefensehereoes
git status --short
git log -1 --oneline
git fetch origin
git log --oneline HEAD..origin/main
```

### 12.2. Последовательность релиза

Это простой single-server деплой с перерывом, не zero-downtime. При ошибке не переходите к `up`. После окна обслуживания может потребоваться сверка уже созданных платежей; политику повторов callback уточните у провайдера.

```bash
cd /var/www/crazydefensehereoes/backend
php artisan down --retry=60
sudo supervisorctl stop cdh-worker

cd /var/www/crazydefensehereoes
git pull --ff-only
npm ci
npm run build

cd /var/www/crazydefensehereoes/backend
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan migrate --force
php artisan optimize
php artisan cdh:check-production
sudo systemctl reload php8.4-fpm
sudo nginx -t
sudo systemctl reload nginx
php artisan up
sudo supervisorctl start cdh-worker
sudo supervisorctl status cdh-worker
```

`down` закрывает Laravel, но Nginx может показывать статический лендинг; покупки/API недоступны. Для общей страницы обслуживания настройте Nginx отдельно. Остановка worker исключает работу старого процесса во время смены зависимостей.

Для полного релиза здесь используется stop/start worker. Для изменения только `.env` достаточно `config:clear`, `optimize`, `queue:restart` и проверки Supervisor. `optimize:clear` дополнительно очищает default cache: не используйте его вслепую при активных заданиях/unique locks. Для обновления конфига достаточно `config:clear`.

После релиза повторите раздел 11. Pending-платёж проверяйте через **Check payment**, не новой покупкой. Не запускайте `ProductSeeder` на каждом релизе без намерения обновить каталог.

### 12.3. Что обновлять

| Изменение | Действие |
| --- | --- |
| `src/`, `public/`, корневой `VITE_*` | `npm run build` из корня; `npm ci` при изменении lock-файла |
| `backend/.env` / config | `config:clear`, `optimize`, worker restart |
| PHP / Composer lock | `composer install`, кэши, PHP-FPM reload и worker restart |
| Миграции | Проверенный бэкап, `migrate --force` |
| Nginx | `nginx -t`, после успеха reload |

## 13. Бэкапы и восстановление

### 13.1. Что сохранять

- БД: аккаунты, балансы, ledger, progress, заказы, invoice records и очередь.
- `backend/storage/app/private/`, особенно `invoices/`; используемые uploads, если появятся.
- `backend/.env`, неизменный `APP_KEY` и необходимые секреты — только в шифрованной копии с ограниченным доступом.
- Nginx/Supervisor, commit и предыдущий `dist/`.

Нужны ежедневный и предрелизный бэкап, отдельное хранилище, шифрование, срок хранения, уведомления об ошибках и проверка восстановления на изолированном сервере. Единственная копия на том же диске недостаточна. Бэкапы содержат персональные данные.

### 13.2. Пример ручного бэкапа MariaDB

В согласованном окне сначала закройте Laravel и остановите worker по разделу 12, чтобы БД и PDF копировались без новых записей. Далее — администратор; время в имени каталога UTC:

```bash
cd /var/www/crazydefensehereoes
CDH_BACKUP_DIR="/var/backups/cdh/$(date -u +%Y%m%dT%H%M%SZ)"
sudo install -d -m 0700 "$CDH_BACKUP_DIR"
sudo bash -o pipefail -c 'mariadb-dump --single-transaction --quick --skip-lock-tables cdh | gzip > "$1/database.sql.gz"' bash "$CDH_BACKUP_DIR"
sudo tar -czf "$CDH_BACKUP_DIR/private-storage.tar.gz" -C /var/www/crazydefensehereoes/backend/storage/app private
sudo cp /var/www/crazydefensehereoes/backend/.env "$CDH_BACKUP_DIR/backend.env"
sudo cp /etc/nginx/sites-available/crazydefensehereoes "$CDH_BACKUP_DIR/nginx.conf"
sudo cp /etc/supervisor/conf.d/cdh-worker.conf "$CDH_BACKUP_DIR/supervisor.conf"
sudo tar -czf "$CDH_BACKUP_DIR/frontend-dist.tar.gz" -C /var/www/crazydefensehereoes dist
git rev-parse HEAD | sudo tee "$CDH_BACKUP_DIR/commit.txt" > /dev/null
sudo find "$CDH_BACKUP_DIR" -maxdepth 1 -type f -exec chmod 600 {} +
sudo gzip -t "$CDH_BACKUP_DIR/database.sql.gz"
sudo tar -tzf "$CDH_BACKUP_DIR/private-storage.tar.gz" > /dev/null
```

Dump предполагает локальную MariaDB с административной socket-авторизацией root. При иной схеме используйте защищённый credentials file, не пароль в аргументах. `gzip -t` проверяет архив, не полноту восстановления БД. До внешней передачи зашифруйте копии: 0700 само по себе не шифрование. Если бэкап не сопровождается релизом, после проверки верните приложение и worker в работу по разделу 12.

Для SQLite используйте штатную `.backup` на точном пути действующей базы либо проверенную offline-копию после полной остановки записей. Не копируйте только `.sqlite`, игнорируя активные WAL-файлы. Автоматические бэкапы сначала протестируйте.

### 13.3. Восстановление / откат

1. Закройте запросы/очередь, сохраните отдельную копию текущего состояния, даже повреждённого.
2. Для отката кода подготовьте прежний проверенный commit в отдельном release-каталоге с его lock-файлами и frontend artifact. Не делайте разрушительный reset текущего checkout.
3. Проверьте совместимость старого кода с новой схемой. Не выполняйте автоматический `migrate:rollback`: он может удалить данные.
4. Сначала восстановите dump в **новую изолированную БД** вместе с приватным storage и тем же `APP_KEY`. Production заменяется только по согласованному плану.
5. Перед запуском очереди проверьте старые jobs и `sent_at`: восстановление может привести к повторным письмам. Изолированная копия не подключается к live SMTP/callback.
6. Сопоставьте provider-транзакции после времени бэкапа: восстановление БД не отменяет списания. Затем сверяйте конкретные заказы штатной командой, запускайте worker и открывайте сайт.

## 14. Типовые ошибки

### Сборка требует laravel-vite-plugin

Вы собираете backend-шаблон вместо текущего UI:

```bash
cd /var/www/crazydefensehereoes
npm ci
npm run build
```

Не устанавливайте backend Vite-плагины для обхода этой ошибки.

### Company API правильный, футер старый

```bash
cd /var/www/crazydefensehereoes/backend
php artisan config:show company
curl -fsS https://crazydefensehereoes.co.uk/api/v1/company
cd /var/www/crazydefensehereoes
rg -n 'getCompanyInfo|/company|DEFAULT_COMPANY_INFO' src
```

Если кода нет — на сервере старые исходники, пересборка не добавит отсутствующий код. Обновите релиз. Иначе проверьте Network, `VITE_API_BASE_URL`, HTML/CDN cache и соответствие JS в публичном HTML локальному `dist/index.html`. Только `COMPANY_*` не требует npm build, если frontend уже получает `/company`.

### Country list is using the local fallback

Проверьте `/api/v1/registration/countries`, Nginx mapping и адрес API в сборке. В ответе должен быть JSON, не SPA HTML. На production Laravel обслуживает PHP-FPM, отдельный публичный dev-порт не нужен.

### Open full policy page открывает лендинг

`/api/v1/legal/terms/page` должен возвращать HTML политики через Laravel. Старые `/legal/*` тоже требуют mapping. JSON на `/api/v1/legal/terms` — другой endpoint.

### Manual purchases are currently paused

Проверьте актуальность frontend/backend и разрешённые настройки без вывода ключей:

```bash
cd /var/www/crazydefensehereoes/backend
php artisan config:show payments.provider
php artisan config:show corefy.test_mode
php artisan config:show corefy.hpp_hosts
```

Для hosted checkout нужен `PAYMENTS_PROVIDER=corefy`, обновлённые кэши/worker. `TOPUP_MANUAL_ENABLED=true` не заменяет настройку Sterling Pay. Не печатайте весь `config:show corefy`: там секреты.

### POST /me/topup — 503, но provider invoice существует

Не повторяйте покупку новым заказом. Исправление HPP/recovery находится в commit `3e2efb0`: разрешённый `hpp_url` выбирается раньше action другого домена. Используйте раздел 10.3. `process_pending` не означает успешную оплату.

API намеренно скрывает детали 5xx за `An unexpected error occurred.`. Сохраните endpoint, статус, время, `request_id`, сопоставьте с логами. Общий текст сам по себе не устанавливает причину.

### 502, permission denied или SQLSTATE

```bash
sudo systemctl status php8.4-fpm --no-pager
sudo tail -n 80 /var/log/nginx/cdh-error.log
cd /var/www/crazydefensehereoes/backend
php artisan migrate:status
sudo tail -n 80 storage/logs/laravel.log
```

Проверьте PHP-сокет, права, БД и миграции. Не включайте публичный `APP_DEBUG=true`. Перед передачей логов удаляйте секреты, токены и персональные данные.

### PDF есть, письма нет

```bash
sudo supervisorctl status cdh-worker
sudo tail -n 80 /var/log/supervisor/cdh-worker.log
cd /var/www/crazydefensehereoes/backend
php artisan queue:failed
```

Проверьте SMTP-пароль ящика, `MAIL_MAILER=smtp`, `MAIL_SCHEME`, исходящий 587, DNS/Spam. `MAIL_MAILER=log` не отправляет письма. После исправления перезапустите worker и повторите конкретный failed job, не покупку. Успешная регистрация не доказывает SMTP: welcome-mail failure сейчас не блокирует регистрацию.

### Постоянный контроль

Настройте уведомления о сбоях API/БД, worker/failed jobs, заполнении диска, ошибках callback, TLS и бэкапов. Проверяйте содержимое health, не только HTTP-код. Не удаляйте очередь, invoice storage или ledger для «очистки ошибок».
