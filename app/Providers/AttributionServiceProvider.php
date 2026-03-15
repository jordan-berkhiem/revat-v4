<?php

namespace App\Providers;

use App\Services\AttributionEngine;
use App\Services\ConnectorKeyProcessor;
use Illuminate\Support\ServiceProvider;

class AttributionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorKeyProcessor::class);
        $this->app->singleton(AttributionEngine::class);
    }
}
