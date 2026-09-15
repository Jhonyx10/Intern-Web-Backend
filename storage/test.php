<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::whereHas('role', fn($q)=>$q->where('name', 'coordinator'))->first();
if (!$user) {
    echo "No coordinator user found.";
    exit;
}

try {
    echo App\Models\Company::whereHas('students.section', function ($q) use ($user) {
        $q->whereIn('id', $user->coordinatedSections()->pluck('id'));
    })->toSql();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
