<?php

namespace Ktr\LightSearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class LightSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No config is needed for this package, so this remains intentionally empty
    }

    public function boot(): void
    {
        // Publish migration if not already published
        if (! class_exists('CreateLightsearchIndexTable')) {
            $timestamp = date('Y_m_d_His');
            $this->publishes([
                __DIR__ . '/../database/migrations/create_lightsearch_index_table.php' =>
                    database_path("migrations/{$timestamp}_create_lightsearch_index_table.php"),
            ], 'migrations');
        }

        // Register the custom Scout engine
        $this->app->make(EngineManager::class)->extend('lightsearch', function () {
            return new LightSearchEngine(config('scout.lightsearch'));
        });
    }
}
