<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // @pkr($amount) → "PKR 1,250,000"
        Blade::directive('pkr', fn ($expr) => "<?php echo 'PKR ' . number_format((float) ($expr)); ?>");
    }
}
