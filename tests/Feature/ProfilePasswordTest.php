<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::factory()->create(['password' => Hash::make('oldpassword')]);
        $u->forceFill(['role' => 'owner', 'is_active' => true])->save();

        return $u;
    }

    public function test_user_can_change_password(): void
    {
        $u = $this->user();

        $this->actingAs($u)->put('/admin/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpassword1', $u->fresh()->password));
    }

    public function test_wrong_current_password_rejected(): void
    {
        $u = $this->user();

        $this->actingAs($u)->put('/admin/profile/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('oldpassword', $u->fresh()->password));
    }
}
