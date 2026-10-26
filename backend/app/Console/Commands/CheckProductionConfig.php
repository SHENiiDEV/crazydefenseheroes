<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckProductionConfig extends Command
{
    protected $signature = 'cdh:check-production';

    protected $description = 'Check required production settings without printing secrets.';

    public function handle(): int
    {
        $checks = [
            'APP_KEY is configured' => filled(config('app.key')),
            'APP_DEBUG is false' => config('app.debug') === false,
            'Company name is configured' => $this->isRealValue(config('company.name')),
            'Company number is configured' => $this->isRealValue(config('company.number')),
            'Company address is configured' => $this->isRealValue(config('company.address')),
            'Company email is configured' => filter_var(config('company.email'), FILTER_VALIDATE_EMAIL) !== false
                && ! str_ends_with((string) config('company.email'), '@yourdomain.com'),
            'SMTP password is configured' => filled(config('mail.mailers.smtp.password')),
            'Queue is not synchronous' => config('queue.default') !== 'sync',
            'CORS does not contain localhost' => ! collect(config('cors.allowed_origins', []))
                ->contains(fn (string $origin): bool => str_contains($origin, 'localhost')),
        ];
        if (config('payments.provider') === 'corefy') {
            $checks += [
                'Corefy account ID is configured' => $this->isRealValue(config('corefy.account_id')),
                'Corefy API key is configured' => $this->isRealValue(config('corefy.api_key')),
                'Corefy callback secret is configured' => $this->isRealValue(config('corefy.webhook_secret')),
                'Corefy uses live mode' => config('corefy.test_mode') === false,
                'Corefy API uses HTTPS' => parse_url(config('corefy.api_url'), PHP_URL_SCHEME) === 'https',
                'Corefy return URL uses HTTPS' => parse_url(config('corefy.frontend_url'), PHP_URL_SCHEME) === 'https',
                'Corefy callback URL uses HTTPS' => parse_url(config('corefy.callback_url') ?: config('app.url'), PHP_URL_SCHEME) === 'https',
                'Corefy EUR service is configured' => filled(config('corefy.service_eur')),
                'Corefy HPP allowlist is configured' => count(config('corefy.hpp_hosts', [])) > 0,
            ];
        } else {
            $checks['Payment webhook secret is configured'] = $this->isRealValue(config('payments.webhook_secret'));
        }
        $failed = 0;

        foreach ($checks as $label => $passed) {
            if ($passed) {
                $this->line('<info>PASS</info> '.$label);

                continue;
            }

            $this->error('FAIL '.$label);
            $failed++;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function isRealValue(mixed $value): bool
    {
        return filled($value) && ! str_contains((string) $value, 'change-this') && ! str_contains((string) $value, 'Your ');
    }
}
