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
            'Payment webhook secret is configured' => $this->isRealValue(config('payments.webhook_secret')),
            'Queue is not synchronous' => config('queue.default') !== 'sync',
            'CORS does not contain localhost' => ! collect(config('cors.allowed_origins', []))
                ->contains(fn (string $origin): bool => str_contains($origin, 'localhost')),
        ];
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
