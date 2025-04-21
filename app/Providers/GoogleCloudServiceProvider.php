<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class GoogleCloudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Set GOOGLE_APPLICATION_CREDENTIALS to absolute path
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage_path('credentials/laravel.json'));
    }
    public function register()
    {
        //
    }
}
