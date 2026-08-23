<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Role::query()->create([
        'name' => 'super_admin',
        'label' => 'Super Admin',
    ]);

    Role::query()->create([
        'name' => 'intern',
        'label' => 'Intern',
    ]);
});

it('logs in staff with email and password', function (): void {
    $role = Role::query()->where('name', 'super_admin')->firstOrFail();

    User::query()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@gmail.com',
        'password' => 'sadmin123',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'superadmin@gmail.com',
        'password' => 'sadmin123',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'superadmin@gmail.com')
        ->assertJsonPath('user.role.name', 'super_admin');

    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();
});

it('rejects invalid credentials', function (): void {
    $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'wrong',
    ])->assertUnprocessable();
});

it('returns the authenticated user from me', function (): void {
    $role = Role::query()->where('name', 'super_admin')->firstOrFail();

    $user = User::query()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@gmail.com',
        'password' => 'sadmin123',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('user.email', 'superadmin@gmail.com');
});

it('revokes the current token on logout', function (): void {
    $role = Role::query()->where('name', 'super_admin')->firstOrFail();

    User::query()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@gmail.com',
        'password' => 'sadmin123',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'superadmin@gmail.com',
        'password' => 'sadmin123',
    ]);

    $token = $login->json('access_token');
    $tokenId = $login->json('user.id');

    expect($token)->toBeString()->not->toBeEmpty();

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $tokenId,
        'tokenable_type' => User::class,
    ]);

    Auth::forgetGuards();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('blocks intern accounts from staff login', function (): void {
    $role = Role::query()->where('name', 'intern')->firstOrFail();

    User::query()->create([
        'name' => 'Intern User',
        'email' => 'intern@example.com',
        'password' => 'password',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'intern@example.com',
        'password' => 'password',
    ])->assertUnprocessable();
});
