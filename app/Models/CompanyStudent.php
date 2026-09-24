<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CompanyStudent extends Pivot
{
    protected $table = 'company_student';

    /**
     * @var bool
     */
    public $incrementing = true; // Pivots usually have incrementing IDs if they have an 'id' column

    public function ojtSchedule()
    {
        return $this->hasOne(OjtSchedule::class, 'company_student_id');
    }
}
