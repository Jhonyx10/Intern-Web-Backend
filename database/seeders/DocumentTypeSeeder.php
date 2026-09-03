<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $documentTypes = [
            [
                'code'        => 'COR',
                'name'        => 'Certificate of Registration',
                'is_required' => true,
            ],
            [
                'code'        => 'GRADES',
                'name'        => 'Certified True Copy of Grades',
                'is_required' => true,
            ],
            [
                'code'        => 'MED_CERT',
                'name'        => 'Medical Certificate',
                'is_required' => true,
            ],
            [
                'code'        => 'BIRTH_CERT',
                'name'        => 'Birth Certificate',
                'is_required' => true,
            ],
            [
                'code'        => 'GOOD_MORAL',
                'name'        => 'Good Moral Certificate',
                'is_required' => false,
            ],
            [
                'code'        => 'ID_PHOTO',
                'name'        => '2x2 ID Photo',
                'is_required' => false,
            ],
            [
                'code'        => 'PARENT_CONSENT',
                'name'        => 'Parental Consent Form',
                'is_required' => false,
            ],
            [
                'code'        => 'INSURANCE',
                'name'        => 'Student Insurance Form',
                'is_required' => false,
            ],
        ];

        foreach ($documentTypes as &$type) {
            $type['created_at'] = $now;
            $type['updated_at'] = $now;
        }

        DB::table('document_types')->insert($documentTypes);
    }
}