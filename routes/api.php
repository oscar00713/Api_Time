<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServerController;
use App\Http\Middleware\CompanyMiddleware;
use App\Http\Controllers\api\UserController;
use App\Http\Controllers\auth\AuthController;
use App\Http\Controllers\api\CompanyController;
use App\Http\Controllers\api\db\ClientController;
use App\Http\Controllers\api\db\UserDBController;
use App\Http\Controllers\api\db\ProductsController;
use App\Http\Controllers\api\db\ServicesController;
use App\Http\Controllers\api\db\SettingsController;
use App\Http\Controllers\api\email\EmailController;
use App\Http\Controllers\api\db\CategoriesController;
use App\Http\Controllers\api\db\SpecialistController;
use App\Http\Controllers\api\db\VacacionesController;
use App\Http\Controllers\api\db\VariationsController;
use App\Http\Controllers\api\db\StockHistoryController;
use App\Http\Controllers\api\history\HistoryController;
use App\Http\Controllers\api\CompanyEmployeesController;
use App\Http\Controllers\api\db\AppoimentCRUDController;
use App\Http\Controllers\api\email\InvitacionController;
use App\Http\Controllers\api\db\BlockAppointmentController;
use App\Http\Controllers\api\client\ClientSummaryController;
use App\Http\Controllers\api\appointments\ByClientController;
use App\Http\Controllers\api\service\ServiceSpecialController;
use App\Http\Controllers\api\db\AppoimentSuggestionsController;
use App\Http\Controllers\api\user_option\UserOptionsController;
use App\Http\Controllers\api\invitacion\UserInvitationController;
use App\Http\Controllers\api\appointments\AppointmentsdaysController;
use App\Http\Controllers\api\appointments\AppointmentsweekController;
use App\Http\Controllers\api\appointments\AppointmentsmonthController;
use App\Http\Controllers\api\appointments\AppointmentlastestController;
use App\Http\Controllers\api\appointments\SearchController;
use App\Http\Controllers\api\lobby\CallController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/confirmEmail', [EmailController::class, 'email_verified']);
Route::post('/resendPin', [AuthController::class, 'resendPin']);


Route::middleware('central')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::apiResource('/company',  CompanyController::class);
    Route::get('/status',  [AuthController::class, 'status']);
    Route::get('/userOptions',  [UserOptionsController::class, 'index']);
    Route::post('/userOptions',  [UserOptionsController::class, 'createOrUpdate']);

    Route::post('/invitation', [UserInvitationController::class, 'acceptInvitations']);
    // Route::post('/accept-invitation/{token}', [InvitacionController::class, 'acceptInvitation']);

    //actulizar users
    Route::apiResource('/users',  UserController::class);
});

Route::get('/invitation/{hash}', [UserInvitationController::class, 'mostrarDataInvitacion']);
//Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('company')->group(function () {
    Route::apiResource('/employees',  SpecialistController::class);
    Route::apiResource('/companyUsers',  UserDBController::class);
    Route::get('/statusCompany',  [UserController::class, 'statusCompany']);
    Route::apiResource('/service', ServicesController::class);
    Route::apiResource('/clients', ClientController::class);
    Route::apiResource('/appointments', AppoimentCRUDController::class);
    Route::post('/appointmentSuggestions', [AppoimentSuggestionsController::class, 'getSuggestions']);
    Route::post('/blockAppointment', [BlockAppointmentController::class, 'manageBlock']);
    Route::post('/appointments/day', [AppointmentsdaysController::class, 'getAppointmentsByDay']);
    Route::post('/appointments/day/check', [AppointmentsdaysController::class, 'checkLastUpdate']);
    Route::post('/appointments/week', [AppointmentsweekController::class, 'getAppointmentsByWeek']);
    Route::post('/appointments/month', [AppointmentsmonthController::class, 'getAppointmentsByMonth']);
    Route::post('/appointments/latest', [AppointmentlastestController::class, 'index']);
    Route::get('/appointments/byClient/{client_id}', [ByClientController::class, 'index']);
    Route::get('/clients/summary/{client_id}', [ClientSummaryController::class, 'show']);

    Route::get('/openDays', [ServiceSpecialController::class, 'openDays']);
    Route::post('/call', [CallController::class, 'call']);
    Route::get('/searchAppointment', [SearchController::class, 'index']);

    Route::get('/settings', [SettingsController::class, 'getSettings']);
    Route::post('/settings', [SettingsController::class, 'updateSetting']);
    Route::apiResource('/blockOffDays', VacacionesController::class);
    Route::apiResource('/products', ProductsController::class);
    // Route::apiResource('/stockHistory', StockHistoryController::class);
    Route::get('/stockHistory/{id}', [StockHistoryController::class, 'index']);
    Route::apiResource('/categories', CategoriesController::class);
    Route::apiResource('/variants', VariationsController::class);
    Route::apiResource('/history', HistoryController::class);
});
