<?php

namespace Wesleydeveloper\DataProcessor;

use Illuminate\Support\ServiceProvider;


class DataProcessorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/data-processor.php', 'data-processor'
        );

        $this->app->singleton(FileManager::class, function ($app) {
            return new FileManager(config('data-processor.storage_disk', 'local'));
        });

        $this->app->singleton(ChunkProcessor::class, function ($app) {
            return new ChunkProcessor($app->make(FileManager::class));
        });

        $this->app->singleton(DataProcessor::class, function ($app) {
            return new DataProcessor(
                $app->make(FileManager::class),
                $app->make(ChunkProcessor::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/data-processor.php' => config_path('data-processor.php'),
            ], 'data-processor-config');
        }
    }
}
