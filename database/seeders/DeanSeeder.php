<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DeanSeeder extends Seeder
{
    private const NAME = 'Dean';

    private const EMAIL = 'dean@gmail.com';

    private const PASSWORD = 'dean123';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->where('name', 'dean')->first();

        if ($role === null) {
            $this->command?->error('Dean role not found. Run RoleSeeder first.');

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

        $this->command?->info('Dean seeded: '.self::EMAIL);
    }
}
