<?php

namespace Tests\Feature;

use App\Models\Score;
use App\Models\User;
use App\Support\Nikniq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableNikniq(bool $autoSignOn = false): void
    {
        config([
            'services.nikniq.client_id' => 'client-123',
            'services.nikniq.url' => 'https://portal.test',
            'services.nikniq.redirect' => 'http://localhost/auth/callback',
            'services.nikniq.auto_sign_on' => $autoSignOn,
        ]);
    }

    public function test_buttons_are_hidden_until_configured(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('with Google')->assertDontSee('with nikniq.com');
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/nikniq')->assertNotFound();

        config(['services.google.client_id' => 'g', 'services.google.client_secret' => 's']);
        $this->enableNikniq();
        $this->get('/login')->assertSee('Log in with Google')->assertSee('Log in with nikniq.com');
        $this->get('/register')->assertSee('Sign up with Google');
    }

    public function test_google_callback_creates_and_then_links_a_player(): void
    {
        config(['services.google.client_id' => 'g', 'services.google.client_secret' => 's']);
        $existing = User::factory()->create(['email' => 'mei@example.com', 'name' => 'Mei']);

        $google = (new SocialiteUser)->map(['id' => 'g-1', 'name' => 'Mei Ling', 'email' => 'MEI@example.com', 'avatar' => 'https://img/a.png']);
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get('/auth/google/callback')->assertRedirect(route('game'));
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('g-1', $existing->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_nikniq_redirect_uses_pkce(): void
    {
        $this->enableNikniq();

        $location = $this->get('/auth/nikniq')->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://portal.test/sso/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('client-123', $q['client_id']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertSame('http://localhost/auth/callback', $q['redirect_uri']);
        $this->assertArrayNotHasKey('prompt', $q);
    }

    public function test_nikniq_callback_signs_in_a_new_player_and_stores_the_token(): void
    {
        $this->enableNikniq();
        Http::fake(['portal.test/api/sso/token' => Http::response([
            'access_token' => 'tok-abc', 'expires_at' => now()->addDays(30)->toIso8601String(),
            'player' => ['id' => 42, 'name' => 'Kai', 'email' => 'kai@example.com', 'avatar' => null],
        ])]);

        $this->withSession(['nikniq_state' => 'st', 'nikniq_verifier' => 'ver'])
            ->get('/auth/callback?code=c0de&state=st')
            ->assertRedirect(route('game'));

        $user = User::firstWhere('email', 'kai@example.com');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('42', $user->nikniq_id);
        $this->assertSame('tok-abc', $user->nikniq_token);
        Http::assertSent(fn ($r) => $r['code_verifier'] === 'ver' && $r['code'] === 'c0de');
    }

    public function test_nikniq_callback_rejects_a_bad_state(): void
    {
        $this->enableNikniq();
        Http::fake();

        $this->withSession(['nikniq_state' => 'st', 'nikniq_verifier' => 'ver'])
            ->get('/auth/callback?code=c0de&state=forged')
            ->assertRedirect(route('login'));
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_guests_are_silently_checked_once_and_left_alone_after_logout(): void
    {
        $this->enableNikniq(autoSignOn: true);

        $this->get('/')->assertRedirect(route('auth.nikniq', ['silent' => 1]));
        $this->get('/')->assertOk(); // checked recently

        $this->withSession(['nikniq_optout' => true])->get('/play')->assertOk();
    }

    public function test_wins_are_reported_to_the_portal_for_nikniq_players(): void
    {
        $this->enableNikniq();
        Http::fake(['portal.test/*' => Http::response(['ok' => true])]);
        $user = User::factory()->create();
        $user->forceFill(['nikniq_id' => '7', 'nikniq_token' => 'tok', 'nikniq_token_expires_at' => now()->addDay()])->save();

        $this->actingAs($user)->postJson('/scores', [
            'layout' => 'garden', 'score' => 3000, 'duration' => 200, 'hints' => 0, 'shuffles' => 0, 'undos' => 0,
        ])->assertCreated();

        Http::assertSent(fn ($r) => $r->url() === 'https://portal.test/api/games/mahjong/events'
            && $r['event'] === 'game_over' && $r['score'] === 3000 && $r->hasHeader('Authorization', 'Bearer tok'));
    }
}
