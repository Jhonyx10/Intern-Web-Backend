<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class SchoolYearSeeder extends Seeder
{
    /**
     * @var list<array{name: string, start_date: string, end_date: string, is_active: bool}>
     */
    private const SCHOOL_YEARS = [
        [
            'name' => '2024-2025',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31',
            'is_active' => false,
        ],
        [
            'name' => '2025-2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'is_active' => true,
        ],
        [
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'is_active' => false,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::SCHOOL_YEARS as $schoolYear) {
            SchoolYear::query()->updateOrCreate(
                ['name' => $schoolYear['name']],
                $schoolYear,
            );
        }
    }
}
