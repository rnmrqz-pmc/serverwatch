<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Spatie\Health\Facades\Health::checks([
            \Spatie\Health\Checks\Checks\DatabaseCheck::new(),
            \Spatie\Health\Checks\Checks\DebugModeCheck::new(),
            \Spatie\Health\Checks\Checks\UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
        ]);

        // Load custom settings
        try {
            if (app()->bound('db')) {
                if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                    $settings = \Illuminate\Support\Facades\Cache::rememberForever('system_settings', function () {
                        try {
                            return \App\Models\Setting::all()->pluck('value', 'key')->toArray();
                        } catch (\Exception $e) {
                            return [];
                        }
                    });

                    if (!empty($settings['mail_host'])) {
                        config([
                            'mail.default'                 => 'smtp',
                            'mail.mailers.smtp.host'       => $settings['mail_host'],
                            'mail.mailers.smtp.port'       => (int) ($settings['mail_port'] ?? 587),
                            'mail.mailers.smtp.encryption' => ($settings['mail_encryption'] ?? 'tls') === 'none' ? null : ($settings['mail_encryption'] ?? 'tls'),
                            'mail.mailers.smtp.username'   => $settings['mail_username'] ?? null,
                            'mail.from.address'            => $settings['mail_from_address'] ?? config('mail.from.address'),
                            'mail.from.name'               => $settings['mail_from_name'] ?? config('mail.from.name'),
                        ]);

                        if (!empty($settings['mail_password'])) {
                            try {
                                config([
                                    'mail.mailers.smtp.password' => decrypt($settings['mail_password']),
                                ]);
                            } catch (\Exception $e) {
                                // Decryption error handler
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silence DB or bootstrap exceptions
        }
    }
}
