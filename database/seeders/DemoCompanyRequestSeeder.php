<?php

namespace Database\Seeders;

use App\Models\CompanyRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCompanyRequestSeeder extends Seeder
{
    /**
     * Seed sample company requests submitted by intern users.
     */
    public function run(): void
    {
        $internRole = Role::query()->where('name', 'intern')->firstOrFail();

        $intern = User::query()->updateOrCreate(
            ['email' => 'intern@gmail.com'],
            [
                'name' => 'Demo Intern',
                'password' => 'intern123',
                'role_id' => $internRole->id,
                'is_active' => true,
            ],
        );

        $requests = [
            [
                'name' => 'Lagoon Tech Partners',
                'address' => 'Poblacion, El Salvador City, Misamis Oriental',
                'latitude' => 8.5630,
                'longitude' => 124.5247,
            ],
            [
                'name' => 'Molugan Industrial Hub',
                'address' => 'Zone 3, Molugan, El Salvador City, Misamis Oriental',
                'latitude' => 8.5452,
                'longitude' => 124.5398,
            ],
            [
                'name' => 'CDO Riverside Works',
                'address' => 'Cagayan de Oro City, Misamis Oriental',
                'latitude' => 8.4822,
                'longitude' => 124.6472,
            ],
        ];

        foreach ($requests as $request) {
            CompanyRequest::query()->updateOrCreate(
                [
                    'user_id' => $intern->id,
                    'name' => $request['name'],
                ],
                [
                    ...$request,
                    'status' => CompanyRequest::STATUS_PENDING,
                ],
            );
        }
    }
}
