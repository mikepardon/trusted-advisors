<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAuthService(string $authId = 'auth-123', string $username = 'NewPlayer', string $email = 'new@example.com'): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'access-tok', 'refresh_token' => 'refresh-tok']),
            '*/api/user' => Http::response([
                'id' => $authId,
                'username' => $username,
                'email' => $email,
                'avatar_url' => null,
            ]),
            // OneSignal registerEmail and any other outbound call
            '*' => Http::response([], 200),
        ]);
    }

    public function test_new_user_is_provisioned_logged_in_and_sent_to_choose_username(): void
    {
        $this->fakeAuthService();

        $response = $this
            ->withSession(['oauth_state' => 'state-xyz', 'oauth_code_verifier' => 'verifier-xyz'])
            ->get('/auth/callback?code=auth-code&state=state-xyz');

        $response->assertRedirect('/choose-username');

        $user = User::where('auth_id', 'auth-123')->first();
        $this->assertNotNull($user);
        $this->assertSame('newplayer', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertFalse((bool) $user->username_chosen);
        $this->assertSame('refresh-tok', $user->refresh_token);

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, LoginLog::where('user_id', $user->id)->count());
    }

    public function test_returning_user_without_advisors_is_sent_to_choose_advisors(): void
    {
        $this->fakeAuthService(authId: 'auth-999', username: 'Veteran', email: 'vet@example.com');

        $existing = User::factory()->create([
            'auth_id' => 'auth-999',
            'name' => 'veteran',
            'email' => 'vet@example.com',
            'username_chosen' => true,
        ]);

        $response = $this
            ->withSession(['oauth_state' => 'state-1', 'oauth_code_verifier' => 'verifier-1'])
            ->get('/auth/callback?code=code-1&state=state-1');

        // Existing username, but no characters yet -> advisor selection
        $response->assertRedirect('/choose-advisors');
        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::where('auth_id', 'auth-999')->count());
    }

    public function test_state_mismatch_redirects_to_login_and_creates_no_user(): void
    {
        $this->fakeAuthService();

        $response = $this
            ->withSession(['oauth_state' => 'the-real-state', 'oauth_code_verifier' => 'verifier'])
            ->get('/auth/callback?code=auth-code&state=a-forged-state');

        $response->assertRedirect('/?auth_error=state');
        $this->assertGuest();
        $this->assertSame(0, User::count());
        Http::assertNothingSent();
    }

    public function test_provider_error_redirects_to_login(): void
    {
        $this->fakeAuthService();

        $response = $this
            ->withSession(['oauth_state' => 'state', 'oauth_code_verifier' => 'verifier'])
            ->get('/auth/callback?error=access_denied&state=state');

        $response->assertRedirect('/?auth_error=denied');
        $this->assertGuest();
    }
}
