<?php

namespace Tests\Feature;

use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['is_admin' => true])->save());
    }

    public function test_guests_and_players_cannot_open_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $player = User::factory()->create(['name' => 'Ada']);
        $player->scores()->create(['layout' => 'turtle', 'score' => 5000, 'duration' => 400, 'hints' => 0, 'shuffles' => 0, 'undos' => 0]);

        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk()->assertSee('Admin overview')->assertSee('Ada');
        $this->get('/admin/users?q=Ada')->assertOk()->assertSee('Ada');
        $this->get('/admin/scores?layout=turtle&sort=score')->assertOk()->assertSee('5,000');
        $this->get('/')->assertSee(route('admin.dashboard'));
    }

    public function test_admin_emails_env_grants_access(): void
    {
        config(['mahjong.admin_emails' => ['boss@example.com']]);
        $this->actingAs(User::factory()->create(['email' => 'Boss@example.com']))->get('/admin')->assertOk();
    }

    public function test_admin_can_remove_scores_and_users_and_toggle_admin(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();
        $score = $player->scores()->create(['layout' => 'garden', 'score' => 100, 'duration' => 100, 'hints' => 0, 'shuffles' => 0, 'undos' => 0]);

        $this->actingAs($admin)->delete("/admin/scores/{$score->id}")->assertRedirect();
        $this->assertModelMissing($score);

        $this->post("/admin/users/{$player->id}/admin")->assertRedirect();
        $this->assertTrue($player->fresh()->is_admin);

        $this->post("/admin/users/{$admin->id}/admin");
        $this->assertTrue($admin->fresh()->is_admin, 'cannot demote yourself');

        $this->delete("/admin/users/{$player->id}")->assertRedirect();
        $this->assertModelMissing($player);
        $this->assertSame(0, Score::count());
    }
}
