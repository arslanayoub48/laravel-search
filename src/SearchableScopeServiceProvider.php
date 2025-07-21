<?php

namespace ArslanAyoub\SearchableScope;

use Illuminate\Support\ServiceProvider;

class SearchableScopeServiceProvider extends ServiceProvider
{
  public function boot(): void
  {
    $this->publishes([
      __DIR__ . '/../config/searchable-scope.php' => config_path('searchable-scope.php'),
    ], 'config');
  }

  public function register(): void
  {
    $this->mergeConfigFrom(
      __DIR__ . '/../config/searchable-scope.php',
      'searchable-scope'
    );
  }
} 