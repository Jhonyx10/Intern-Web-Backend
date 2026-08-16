<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyRequestController;
use App\Http\Controllers\Api\StaffAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseMajorController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\CoordinatorController;
use App\Http\Controllers\Api\SYSectionController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [StaffAuthController::class, 'login']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('/logout', [StaffAuthController::class, 'logout']);
        Route::get('/me', [StaffAuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    Route::apiResource('users',UserController::class);
    Route::apiResource('courses',CourseController::class);
    Route::apiResource('majors', CourseMajorController::class);
    Route::apiResource('roles',RolesController::class);
    Route::apiResource('coordinators',CoordinatorController::class);
    Route::apiResource('students', StudentController::class);

    Route::get('/company-requests', [CompanyRequestController::class, 'index']);
    Route::post('/company-requests/{companyRequest}/approve', [CompanyRequestController::class, 'approve']);

    // Import students
    Route::post('/students/import', [StudentController::class, 'import']);
    Route::get('/templates/students-import', [StudentController::class, 'downloadImportTemplate']);
    
    // School Years
    Route::get('/school-years', [SYSectionController::class, 'indexSchoolYears']);
    Route::post('/school-years', [SYSectionController::class, 'storeSchoolYear']);
    Route::put('/school-years/{schoolYear}', [SYSectionController::class, 'updateSchoolYear']);
    Route::delete('/school-years/{schoolYear}', [SYSectionController::class, 'destroySchoolYear']);
    Route::get('/sections/{id}', [SYSectionController::class, 'sectionDetails']);

    // Sections (nested under school year)
    Route::get('/school-years/{schoolYear}/sections', [SYSectionController::class, 'indexSections']);
    Route::post('/school-years/{schoolYear}/sections', [SYSectionController::class, 'storeSection']);
    Route::put('/school-years/{schoolYear}/sections/{section}', [SYSectionController::class, 'updateSection']);
    Route::delete('/school-years/{schoolYear}/sections/{section}', [SYSectionController::class, 'destroySection']);
});
