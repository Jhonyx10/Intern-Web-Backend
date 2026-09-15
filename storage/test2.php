<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$student = App\Models\Student::first();
if (!$student) {
    echo "No student found."; exit;
}
$user = $student->user;
App\Models\TimeLog::firstOrCreate(['student_id' => $student->id, 'time_out' => null], ['time_in' => now()]);
$student->companies()->syncWithoutDetaching([\App\Models\Company::first()->id]);
Auth::login($user);

$req = Illuminate\Http\Request::create('/intern/time/location', 'POST', [
    'latitude' => 10,
    'longitude' => 120,
]);
$controller = new App\Http\Controllers\Api\InternLocationController();

try {
    $res = $controller->update($req);
    echo "Success: " . $res->getContent();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
