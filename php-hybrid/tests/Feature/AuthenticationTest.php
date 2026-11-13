<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voter_auth.jwt_secret' => 'phase3-test-secret']);
        config(['voter_auth.admin_username' => 'legacy-admin']);
        config(['voter_auth.admin_password' => 'legacy-password']);
        config(['voter_auth.admin_password_hash' => '']);
    }

    public function test_legacy_environment_admin_login_keeps_eight_hour_jwt_contract(): void
    {
        $response = $this->postJson('/api/admin/login', ['username'=>'legacy-admin','password'=>'legacy-password']);
        $response->assertOk()->assertJson(['success'=>true]);
        $claims = (array) JWT::decode($response->json('token'), new \Firebase\JWT\Key('phase3-test-secret','HS256'));
        $this->assertSame('admin', $claims['role']);
        $this->assertSame('legacy-admin', $claims['username']);
        $this->assertSame(28800, $claims['exp'] - $claims['iat']);
        $this->assertArrayNotHasKey('sub', $claims);
    }

    public function test_database_viewer_can_login_but_cannot_access_admin_management(): void
    {
        User::query()->create(['username'=>'reader','email'=>null,'password'=>Hash::make('viewer-password-123'),'role'=>'viewer','is_active'=>true,'created_at'=>now()->toIso8601String()]);
        $login = $this->postJson('/api/admin/login', ['username'=>'reader','password'=>'viewer-password-123']);
        $login->assertOk();
        $this->withHeader('Authorization','Bearer '.$login->json('token'))->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_inactive_database_user_cannot_login(): void
    {
        User::query()->create(['username'=>'disabled','email'=>null,'password'=>Hash::make('viewer-password-123'),'role'=>'viewer','is_active'=>false,'created_at'=>now()->toIso8601String()]);
        $this->postJson('/api/admin/login', ['username'=>'disabled','password'=>'viewer-password-123'])->assertUnauthorized();
    }

    public function test_database_admin_can_create_viewer_without_exposing_password(): void
    {
        $admin = User::query()->create(['username'=>'dbadmin','email'=>null,'password'=>Hash::make('administrator-password-123'),'role'=>'admin','is_active'=>true,'created_at'=>now()->toIso8601String()]);
        $token = JWT::encode(['iat'=>time(),'exp'=>time()+28800,'sub'=>(string)$admin->id,'username'=>$admin->username,'role'=>'admin'],'phase3-test-secret','HS256');
        $response = $this->withHeader('Authorization','Bearer '.$token)->postJson('/api/admin/users', ['username'=>'new-viewer','password'=>'a-very-long-viewer-password','role'=>'viewer']);
        $response->assertCreated()->assertJsonPath('user.role','viewer')->assertJsonMissingPath('user.password');
        $this->assertSame(2, User::query()->count());
    }

    public function test_database_user_token_is_revoked_when_account_is_deactivated(): void
    {
        $admin = User::query()->create(['username'=>'dbadmin','email'=>null,'password'=>Hash::make('administrator-password-123'),'role'=>'admin','is_active'=>true,'created_at'=>now()->toIso8601String()]);
        $token = JWT::encode(['iat'=>time(),'exp'=>time()+28800,'sub'=>(string)$admin->id,'username'=>$admin->username,'role'=>'admin'],'phase3-test-secret','HS256');
        $admin->is_active = false; $admin->save();
        $this->withHeader('Authorization','Bearer '.$token)->getJson('/api/admin/users')->assertUnauthorized();
    }
}
