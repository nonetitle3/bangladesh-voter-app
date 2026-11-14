<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'phase9-user-management-test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['voter_auth.jwt_secret' => $this->secret]);
        config(['voter_auth.admin_username' => 'legacy-admin']);
        config(['voter_auth.admin_password' => 'legacy-password']);
    }

    private function admin(string $username = 'db-admin'): array
    {
        $user = User::query()->create([
            'username' => $username,
            'email' => null,
            'password' => Hash::make('admin-password-123'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now()->toIso8601String(),
        ]);
        $token = JWT::encode([
            'iat' => time(), 'exp' => time() + 28800,
            'sub' => (string) $user->id, 'username' => $user->username, 'role' => 'admin',
        ], $this->secret, 'HS256');
        return [$user, $token];
    }

    public function test_admin_can_list_create_update_and_delete_database_users_without_exposing_hashes(): void
    {
        [$admin, $token] = $this->admin();
        $this->withToken($token)->getJson('/api/admin/users')
            ->assertOk()->assertJsonPath('data.0.username', 'db-admin')
            ->assertJsonMissingPath('data.0.password');

        $created = $this->withToken($token)->postJson('/api/admin/users', [
            'username' => 'field-viewer',
            'email' => 'viewer@example.test',
            'password' => 'a-strong-password-123',
            'role' => 'viewer',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('user.role', 'viewer')
          ->assertJsonMissingPath('user.password');

        $id = $created->json('user.id');
        $this->assertTrue(Hash::check('a-strong-password-123', User::findOrFail($id)->password));

        $this->withToken($token)->putJson('/api/admin/users/'.$id, [
            'role' => 'admin', 'is_active' => false,
        ])->assertOk()->assertJsonPath('user.role', 'admin')
          ->assertJsonPath('user.is_active', false);

        $this->withToken($token)->deleteJson('/api/admin/users/'.$id)
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_last_active_admin_cannot_be_demoted_deactivated_or_deleted(): void
    {
        [$admin, $token] = $this->admin();
        $this->withToken($token)->putJson('/api/admin/users/'.$admin->id, ['role' => 'viewer'])
            ->assertUnprocessable();
        $this->withToken($token)->putJson('/api/admin/users/'.$admin->id, ['is_active' => false])
            ->assertUnprocessable();
        $this->withToken($token)->deleteJson('/api/admin/users/'.$admin->id)
            ->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => 1]);
    }

    public function test_admin_cannot_delete_own_account_even_when_another_admin_exists(): void
    {
        [$admin, $token] = $this->admin();
        User::query()->create([
            'username' => 'second-admin', 'email' => null,
            'password' => Hash::make('another-admin-password'),
            'role' => 'admin', 'is_active' => true, 'created_at' => now()->toIso8601String(),
        ]);
        $this->withToken($token)->deleteJson('/api/admin/users/'.$admin->id)
            ->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_inactive_database_user_token_is_rejected_after_deactivation(): void
    {
        [$admin, $token] = $this->admin();
        $admin->forceFill(['is_active' => false])->save();
        $this->withToken($token)->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_legacy_environment_admin_credentials_remain_usable_and_keep_no_database_subject(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'username' => 'legacy-admin', 'password' => 'legacy-password',
        ])->assertOk()->assertJsonPath('success', true);
        $claims = (array) JWT::decode($response->json('token'), new \Firebase\JWT\Key($this->secret, 'HS256'));
        $this->assertSame('legacy-admin', $claims['username']);
        $this->assertSame('admin', $claims['role']);
        $this->assertArrayNotHasKey('sub', $claims);
    }
}
