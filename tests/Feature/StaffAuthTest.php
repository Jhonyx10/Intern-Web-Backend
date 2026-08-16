<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

function ensurePassportPersonalClient(): void
{
    Artisan::call('passport:keys', ['--force' => true]);
    Artisan::call('passport:client', [
        '--personal' => true,
        '--name' => 'Test SPA Client',
        '--provider' => 'users',
        '--no-interaction' => true,
    ]);
}

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
    ensurePassportPersonalClient();

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

    Passport::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('user.email', 'superadmin@gmail.com');
});

it('blocks intern accounts from staff login', function (): void {
    ensurePassportPersonalClient();

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
