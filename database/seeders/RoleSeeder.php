<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const ROLES = [
        'super_admin' => 'Super Admin',
        'dean' => 'Dean',
        'program_head' => 'Program Head',
        'coordinator' => 'Coordinator',
        'supervisor' => 'Supervisor',
        'intern' => 'Intern',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::ROLES as $name => $label) {
            Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => $label],
            );
        }
    }
}
