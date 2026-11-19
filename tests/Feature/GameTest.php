<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Mahjong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'layout' => 'turtle', 'score' => 9000, 'duration' => 400,
            'hints' => 1, 'shuffles' => 0, 'undos' => 2,
        ];
    }

    public function test_guests_can_open_the_game_and_leaderboard(): void
    {
        $this->get('/')->assertOk()->assertSee('Play now')->assertSee('next-deal');
        $this->get('/play')->assertOk()->assertSee('js/game.js');
        $this->get('/how-to-play')->assertOk();
        $this->get('/leaderboard?layout=daily')->assertOk();
        $this->get('/leaderboard')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_a_player_can_register_log_out_and_log_in(): void
    {
        $this->post('/register', [
            'name' => 'Mei', 'email' => 'mei@example.com',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertRedirect('/play');
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/login', ['email' => 'mei@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'mei@example.com', 'password' => 'secret-password'])
            ->assertRedirect('/play');
        $this->assertAuthenticated();
    }

    public function test_guests_cannot_submit_scores(): void
    {
        $this->postJson('/scores', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('scores', 0);
    }

    public function test_a_signed_in_player_can_record_a_score(): void
    {
        $user = User::factory()->create(['name' => 'Mei']);

        $this->actingAs($user)->postJson('/scores', $this->payload())
            ->assertCreated()
            ->assertJson(['saved' => true, 'personal_best' => true, 'best' => 9000, 'rank' => 1]);

        $this->actingAs($user)->postJson('/scores', $this->payload(['score' => 5000]))
            ->assertCreated()
            ->assertJson(['personal_best' => false, 'best' => 9000]);

        $this->assertDatabaseCount('scores', 2);
        $this->get('/leaderboard?layout=turtle')->assertOk()->assertSee('Mei')->assertSee('9,000');
        $this->get('/leaderboard?layout=garden')->assertOk()->assertDontSee('9,000');
    }

    public function test_impossible_scores_are_rejected(): void
    {
        $user = User::factory()->create();
        $max = Mahjong::maxScore('garden');

        $this->actingAs($user)->postJson('/scores', $this->payload(['layout' => 'garden', 'score' => $max]))
            ->assertCreated();

        foreach ([
            ['layout' => 'garden', 'score' => $max + 1],
            ['layout' => 'nope'],
            ['duration' => 3],
            ['score' => -5],
        ] as $bad) {
            $this->actingAs($user)->postJson('/scores', $this->payload($bad))->assertUnprocessable();
        }

        $this->assertDatabaseCount('scores', 1);
    }

    public function test_daily_deal_scores_have_their_own_leaderboard(): void
    {
        $user = User::factory()->create(['name' => 'Dawn']);
        $today = Mahjong::today();

        $this->actingAs($user)->postJson('/scores', $this->payload(['daily' => $today, 'score' => 7777]))
            ->assertCreated()->assertJson(['personal_best' => true]);
        // A normal turtle game doesn't count towards the daily board, and vice versa.
        $this->actingAs($user)->postJson('/scores', $this->payload(['score' => 1234]))
            ->assertCreated()->assertJson(['personal_best' => true, 'best' => 1234]);

        $this->get('/leaderboard?layout=daily')->assertSee('7,777')->assertDontSee('1,234');
        $this->get('/leaderboard?layout=turtle')->assertSee('1,234')->assertDontSee('7,777');
        $this->get('/')->assertSee('Dawn');

        $this->actingAs($user)->postJson('/scores', $this->payload(['daily' => '2020-01-01']))->assertUnprocessable();
        $this->actingAs($user)->postJson('/scores', $this->payload(['daily' => $today, 'layout' => 'garden']))->assertUnprocessable();
    }
}
