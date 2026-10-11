<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

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
        Paginator::useBootstrapFive();
        \Livewire\Volt\Volt::mount([
            resource_path('views/livewire'),
            resource_path('views/pages'),
        ]);

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $dbSettings = \Illuminate\Support\Facades\Cache::rememberForever('custom_settings', function () {
                    return \App\Models\Setting::whereNotNull('payload')->pluck('payload', 'key')->toArray();
                });

                $localConfig = config('local', []);

                foreach ($dbSettings as $key => $value) {
                    if (isset($localConfig[$key]) && is_array($localConfig[$key]) && is_array($value)) {
                        $localConfig[$key] = array_replace_recursive($localConfig[$key], $value);
                    } else {
                        $localConfig[$key] = $value;
                    }
                }

                config(['local' => $localConfig]);
                config(['custom' => $localConfig]);
            }
        } catch (\Throwable $e) {
            // Ignore during early setup/migrations
        }
    }
}
