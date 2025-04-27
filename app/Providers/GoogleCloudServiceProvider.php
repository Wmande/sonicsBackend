<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class GoogleCloudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($base64Credentials = env('GOOGLE_CREDENTIALS_BASE64')) {
            try {
                $credentials = base64_decode($base64Credentials);
                if ($credentials === false) {
                    throw new \Exception('Invalid Google Cloud credentials');
                }
                $tempPath = sys_get_temp_dir() . '/google-credentials.json';
                file_put_contents($tempPath, $credentials);
                putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempPath");
            } catch (\Exception $e) {
                Log::error('Error setting Google Cloud credentials: ' . $e->getMessage());
            }
        } else {
            Log::warning('GOOGLE_CREDENTIALS_BASE64 environment variable not set');
        }
    }

    public function register()
    {
        //
    }
}
