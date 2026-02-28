<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    private string $appId;
    private string $apiKey;

    public function __construct()
    {
        $this->appId = config('services.onesignal.app_id', '');
        $this->apiKey = config('services.onesignal.rest_api_key', '');
    }

    public function sendToUser(User $user, string $heading, string $message, array $data = []): void
    {
        if (!$this->appId || !$this->apiKey) {
            return;
        }

        if (!$user->onesignal_player_id) {
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => 'Basic ' . $this->apiKey,
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $this->appId,
                'include_player_ids' => [$user->onesignal_player_id],
                'headings' => ['en' => $heading],
                'contents' => ['en' => $message],
                'data' => $data,
                'web_url' => config('app.url'),
            ]);
        } catch (\Exception $e) {
            Log::warning('OneSignal notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendToUsers(array $users, string $heading, string $message, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $heading, $message, $data);
        }
    }
}
