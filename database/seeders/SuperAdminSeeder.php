<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    private const NAME = 'Super Admin';

    private const EMAIL = 'superadmin@gmail.com';

    private const PASSWORD = 'sadmin123';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->where('name', 'super_admin')->first();

        if ($role === null) {
            $this->command?->error('Super Admin role not found. Run RoleSeeder first.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => self::NAME,
                'password' => self::PASSWORD,
                'role_id' => $role->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info('Super Admin seeded: '.self::EMAIL);
    }
}
