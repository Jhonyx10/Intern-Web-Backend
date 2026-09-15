<?php

use App\Http\Controllers\Api\BuildingController;
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
use App\Http\Controllers\Api\SupervisorController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\OjtEvaluationController;
use App\Http\Controllers\Api\GeofenceEventController;
use App\Http\Controllers\Api\GeofenceExcursionController;
use App\Http\Controllers\Api\InternLocationController;
use App\Http\Controllers\Api\InternController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [StaffAuthController::class, 'login']);
    Route::post('/mobile/login', [MobileAuthController::class, 'login']);
    Route::post('/face-login', [MobileAuthController::class, 'faceLogin']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::get('/me', [StaffAuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Broadcast::routes(['middleware' => ['auth:sanctum']]);
    // Role Dashboards
    Route::get('/dashboard/superadmin', [DashboardController::class, 'superAdmin']);
    Route::get('/dashboard/dean', [DashboardController::class, 'dean']);
    Route::get('/dashboard/program-head', [DashboardController::class, 'programHead']);
    Route::get('/dashboard/coordinator', [DashboardController::class, 'coordinator']);
    Route::get('/dashboard/supervisor', [DashboardController::class, 'supervisor']);

    Route::post('/intern/face/enroll', [MobileAuthController::class, 'enrollFace']);
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies/pending', [CompanyController::class, 'indexPending']);
    Route::post('/companies/{company}/approve', [CompanyController::class, 'approvePending']);
    Route::post('/companies/{company}/reject', [CompanyController::class, 'rejectPending']);
    Route::post('/companies/{company}/assign-student', [CompanyController::class, 'assignStudent']);
    Route::post('/companies/{company}/supervisors', [CompanyController::class, 'storeSupervisor']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    // Buildings (nested under company)
    Route::get('/companies/{company}/buildings', [BuildingController::class, 'index']);
    Route::post('/companies/{company}/buildings', [BuildingController::class, 'store']);
    Route::get('/companies/{company}/buildings/{building}', [BuildingController::class, 'show']);
    Route::put('/companies/{company}/buildings/{building}', [BuildingController::class, 'update']);
    Route::delete('/companies/{company}/buildings/{building}', [BuildingController::class, 'destroy']);
    Route::apiResource('users',UserController::class);
    Route::apiResource('courses',CourseController::class);
    Route::apiResource('majors', CourseMajorController::class);
    Route::apiResource('roles',RolesController::class);
    Route::apiResource('coordinators',CoordinatorController::class);
    Route::apiResource('students', StudentController::class);

    Route::get('/company-requests', [CompanyRequestController::class, 'index']);
    Route::post('/company-requests/{companyRequest}/accept', [CompanyRequestController::class, 'coordinatorAccept']);

    // Import students
    Route::post('/students/import', [StudentController::class, 'import']);
    Route::get('/templates/students-import', [StudentController::class, 'downloadImportTemplate']);
    
    //documents
    Route::get('/student/documents/{id}', [DocumentsController::class, 'fetchStudentDocuments']);
    Route::get('/student/documents/{id}/view', [DocumentsController::class, 'webViewDocument']);
    Route::get('/submitted-documents', [DocumentsController::class, 'fetchSubmittedDocuments']);
    Route::get('/document-requirements', [DocumentsController::class, 'index']);
    Route::post('/document-requirements', [DocumentsController::class, 'storeRequirement']);
    Route::patch('/student/documents/{document}/status', [DocumentsController::class, 'updateStatus']);

    Route::get('/document-types', [DocumentsController::class, 'indexTypes']);
    Route::post('/document-types', [DocumentsController::class, 'storeDocumentType']);

    Route::get('/courses/{course}/document-requirements', [DocumentsController::class, 'courseRequirements']);
    Route::put('/courses/{course}/document-requirements', [DocumentsController::class, 'syncCourseRequirements']);

    //evaluation
    Route::get('/evaluation-templates', [OjtEvaluationController::class, 'index']);
    Route::post('/evaluation-templates', [OjtEvaluationController::class, 'store']);
    Route::get('/show/evaluation/{id}', [OjtEvaluationController::class, 'showEvaluation']);
    Route::post('/evaluations/bulk-assign', [OjtEvaluationController::class, 'bulkAssign']);
    Route::post('/evaluations/{evaluation}/submit', [OjtEvaluationController::class, 'submit']);
    
    // Notifications Endpoints
    Route::get('/notifications/unread', [OjtEvaluationController::class, 'unreadNotifications']);
    Route::post('/notifications/mark-as-read', [OjtEvaluationController::class, 'markNotificationsAsRead']);

    // School Years
    Route::get('/school-years', [SYSectionController::class, 'indexSchoolYears']);
    Route::post('/school-years', [SYSectionController::class, 'storeSchoolYear']);
    Route::put('/school-years/{schoolYear}', [SYSectionController::class, 'updateSchoolYear']);
    Route::delete('/school-years/{schoolYear}', [SYSectionController::class, 'destroySchoolYear']);
    Route::get('/sections/{id}', [SYSectionController::class, 'sectionDetails']);
    Route::get('/coordinator/sections', [SYSectionController::class, 'coordinatorSections']);

    // Sections (nested under school year)
    Route::get('/school-years/{schoolYear}/sections', [SYSectionController::class, 'indexSections']);
    Route::post('/school-years/{schoolYear}/sections', [SYSectionController::class, 'storeSection']);
    Route::put('/school-years/{schoolYear}/sections/{section}', [SYSectionController::class, 'updateSection']);
    Route::delete('/school-years/{schoolYear}/sections/{section}', [SYSectionController::class, 'destroySection']);
    Route::get('/geofence-events', [GeofenceEventController::class, 'forCourse']);

    // Supervisor portal
    Route::get('/supervisor/profile', [SupervisorController::class, 'profile']);
    Route::get('/supervisor/interns', [SupervisorController::class, 'interns']);
    Route::get('/supervisor/attendance', [SupervisorController::class, 'attendance']);
    Route::get('/supervisor/schedules', [SupervisorController::class, 'indexSchedules']);
    Route::post('/supervisor/schedules', [SupervisorController::class, 'storeSchedule']);
    Route::put('/supervisor/schedules/{schedule}', [SupervisorController::class, 'updateSchedule']);
    Route::delete('/supervisor/schedules/{schedule}', [SupervisorController::class, 'destroySchedule']);
    Route::post('/buildings/{building}/assign-interns', [SupervisorController::class, 'assignInterns']);

    // User Profile & Department Settings
    Route::put('/user/profile', [UserProfileController::class, 'updateProfile']);
    Route::put('/user/password', [UserProfileController::class, 'updatePassword']);
    Route::get('/settings', [SettingController::class, 'getSettings']);
    Route::post('/dean/settings', [SettingController::class, 'updateDeanSettings']);

    // Intern Mobile API
    Route::prefix('intern')->group(function (): void {
        Route::get('/progress', [InternController::class, 'progress']);
        Route::get('/documents', [InternController::class, 'getDocuments']);
        Route::get('/profile', [InternController::class, 'profile']);
        Route::put('/password', [InternController::class, 'updatePassword']);
        
        Route::post('/documents/upload', [InternController::class, 'uploadDocument']);
        Route::get('/documents/download/{id}', [InternController::class, 'downloadDocument']);
        Route::get('/evaluations', [OjtEvaluationController::class, 'myEvaluations']);
        Route::get('/evaluations/{evaluation}', [OjtEvaluationController::class, 'myEvaluation']);
        //face enrollment
        Route::post('/face/enrollment',[MobileAuthController::class, 'enrollFace']);
        // Company Request
        Route::post('/company/request', [InternController::class, 'requestCompany']);

        // Time tracking
        Route::get('/time/status', [InternController::class, 'timeStatus']);
        Route::get('/time/logs', [InternController::class, 'timeLogs']);
        Route::post('/time/punch', [InternController::class, 'timePunch']);

        //interns location alert tracker
        Route::post('/time/geofence-event', [GeofenceEventController::class, 'store']);
        Route::post('/time/location', [InternLocationController::class, 'update']);

        // Geofence Excursions
        Route::get('/excursions/pending', [GeofenceExcursionController::class, 'pending']);
        Route::post('/excursions', [GeofenceExcursionController::class, 'start']);
        Route::post('/excursions/{id}/points', [GeofenceExcursionController::class, 'addPoint']);
        Route::patch('/excursions/{id}', [GeofenceExcursionController::class, 'complete']);
        Route::patch('/excursions/{id}/reason', [GeofenceExcursionController::class, 'updateReason']);
    });
});

