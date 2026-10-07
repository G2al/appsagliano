<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_payload_exposes_must_change_password_flag(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'must_change_password' => true,
        ]));

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('must_change_password', true);
    }

    public function test_user_can_change_password_and_flag_is_cleared(): void
    {
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'password' => 'oldpassword',
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword',
            'new_password_confirmation' => 'newpassword',
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword', $user->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'password' => 'oldpassword',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword',
            'new_password_confirmation' => 'newpassword',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }
}
