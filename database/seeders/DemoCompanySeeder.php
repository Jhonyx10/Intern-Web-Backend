<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    /**
     * Seed sample companies with Mapbox-ready coordinates (foundation).
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'OCC Partner Tech Hub',
                'address' => 'Poblacion, El Salvador City, Misamis Oriental',
                'latitude' => 8.5630,
                'longitude' => 124.5247,
                'geofence_radius_meters' => 150,
                'geofence_enabled' => true,
                'contact_person' => 'Jane Partner',
                'contact_email' => 'partner@example.com',
                'contact_phone' => '09171234567',
                'is_active' => true,
            ],
            [
                'name' => 'ElSal Industrial Partners',
                'address' => 'Zone 3, Molugan, El Salvador City, Misamis Oriental',
                'latitude' => 8.5452,
                'longitude' => 124.5398,
                'geofence_radius_meters' => 200,
                'geofence_enabled' => true,
                'contact_person' => 'John Host',
                'contact_email' => 'host@example.com',
                'contact_phone' => '09179876543',
                'is_active' => true,
            ],
            [
                'name' => 'CDO Downtown Internships',
                'address' => 'Divisoria, Cagayan de Oro City, Misamis Oriental',
                'latitude' => 8.4822,
                'longitude' => 124.6472,
                'geofence_radius_meters' => 180,
                'geofence_enabled' => true,
                'contact_person' => 'Maria Santos',
                'contact_email' => 'cdo.downtown@example.com',
                'contact_phone' => '09175551234',
                'is_active' => true,
            ],
            [
                'name' => 'Limketkai Business Center',
                'address' => 'Limketkai Center, Cagayan de Oro City',
                'latitude' => 8.4855,
                'longitude' => 124.6510,
                'geofence_radius_meters' => 160,
                'geofence_enabled' => true,
                'contact_person' => 'Rico Lim',
                'contact_email' => 'limketkai@example.com',
                'contact_phone' => '09176667890',
                'is_active' => true,
            ],
            [
                'name' => 'Carmen Tech Solutions',
                'address' => 'Carmen, Cagayan de Oro City, Misamis Oriental',
                'latitude' => 8.4758,
                'longitude' => 124.6395,
                'geofence_radius_meters' => 140,
                'geofence_enabled' => true,
                'contact_person' => 'Ana Reyes',
                'contact_email' => 'carmen.tech@example.com',
                'contact_phone' => '09172223344',
                'is_active' => true,
            ],
        ];

        Company::query()
            ->whereIn('name', ['MisOcc Business Center'])
            ->delete();

        foreach ($companies as $company) {
            Company::query()->updateOrCreate(
                ['name' => $company['name']],
                $company,
            );
        }
    }
}
