<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestTwitchConnection extends Command
{
    protected $signature = 'twitch:test';
    protected $description = 'Test Twitch API connection';

    public function handle()
    {
        $clientId = env('TWITCH_CLIENT_ID');
        $clientSecret = env('TWITCH_CLIENT_SECRET');
        $tokenUrl = env('TWITCH_TOKEN_URL');
        $apiUrl = env('TWITCH_API_URL');

        // get the token
        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials'
        ]);

        if ($response->failed()) {
            $this->error('Failed to get token');
            return 1;
        }

        $token = $response->json()['access_token'];
        $this->info("Twitch token retrieved successfully: $token");

        // Test
        $userResponse = Http::withHeaders([
            'Client-ID' => $clientId,
            'Authorization' => "Bearer $token"
        ])->get($apiUrl.'/users', ['login' => 'twitch']);

        if ($userResponse->failed()) {
            $this->error('Failed to connect to Twitch API');
            return 1;
        }

        $this->info('Twitch API connection successful!');
        $this->info(json_encode($userResponse->json(), JSON_PRETTY_PRINT));
        return 0;
    }
}
