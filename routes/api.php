<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ModelController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserGroupController;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\User\ApiKeyController;
use App\Http\Controllers\User\ChatController;
use App\Http\Controllers\User\FileController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ProjectController;
use App\Http\Controllers\User\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

/*
|--------------------------------------------------------------------------
| Authenticated User Workspace Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Auth & Profile
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });

    // Chat & Coding Workspace
    Route::prefix('chat')->group(function () {
        Route::get('catalog', [ChatController::class, 'catalog']);
        Route::get('conversations', [ChatController::class, 'index']);
        Route::post('conversations', [ChatController::class, 'store']);
        Route::get('conversations/{conversation}', [ChatController::class, 'show']);
        Route::put('conversations/{conversation}', [ChatController::class, 'update']);
        Route::delete('conversations/{conversation}', [ChatController::class, 'destroy']);
        Route::post('conversations/{conversation}/send', [ChatController::class, 'send']);
        Route::post('conversations/{conversation}/stream', [ChatController::class, 'stream']);
        Route::post('stop', [ChatController::class, 'stop']);
    });

    // Projects & Files Workspace
    Route::prefix('projects')->group(function () {
        Route::get('', [ProjectController::class, 'index']);
        Route::post('', [ProjectController::class, 'store']);
        Route::get('{project}', [ProjectController::class, 'show']);
        Route::put('{project}', [ProjectController::class, 'update']);
        Route::delete('{project}', [ProjectController::class, 'destroy']);

        // File Management in Workspace (Requirement 20)
        Route::get('{project}/files', [FileController::class, 'index']);
        Route::get('{project}/files/{file}/content', [FileController::class, 'getContent']);
        Route::put('{project}/files/{file}/content', [FileController::class, 'saveContent']);
        Route::post('{project}/files', [FileController::class, 'createFile']);
        Route::post('{project}/folders', [FileController::class, 'createFolder']);
        Route::post('{project}/upload', [FileController::class, 'upload']);
        Route::delete('{project}/files/{file}', [FileController::class, 'destroy']);
    });

    // User Usage & Quota Dashboard
    Route::get('usage', [UsageController::class, 'index']);

    // API Keys Management
    Route::prefix('api-keys')->group(function () {
        Route::get('', [ApiKeyController::class, 'index']);
        Route::post('', [ApiKeyController::class, 'store']);
        Route::post('{apiKey}/revoke', [ApiKeyController::class, 'revoke']);
        Route::post('{apiKey}/rotate', [ApiKeyController::class, 'rotate']);
    });

    // User Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('', [NotificationController::class, 'index']);
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin Management API Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'admin'])->prefix('admin')->group(function () {
    // Dashboard & Analytics
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('analytics', [DashboardController::class, 'analytics']);
    Route::get('usage-logs', [AnalyticsController::class, 'logs']);
    Route::get('cost-report', [AnalyticsController::class, 'costReport']);
    Route::get('export-csv', [AnalyticsController::class, 'exportCsv']);

    // User Management
    Route::prefix('users')->group(function () {
        Route::get('', [UserController::class, 'index']);
        Route::post('', [UserController::class, 'store']);
        Route::get('{user}', [UserController::class, 'show']);
        Route::put('{user}', [UserController::class, 'update']);
        Route::post('{user}/suspend', [UserController::class, 'toggleSuspend']);
        Route::post('{user}/quota', [UserController::class, 'updateQuota']);
        Route::post('{user}/reset-quota', [UserController::class, 'resetQuota']);
        Route::delete('{user}', [UserController::class, 'destroy']);
    });

    // User Groups / Plans
    Route::prefix('user-groups')->group(function () {
        Route::get('', [UserGroupController::class, 'index']);
        Route::post('', [UserGroupController::class, 'store']);
        Route::put('{userGroup}', [UserGroupController::class, 'update']);
        Route::delete('{userGroup}', [UserGroupController::class, 'destroy']);
    });

    // AI Providers
    Route::prefix('providers')->group(function () {
        Route::get('', [ProviderController::class, 'index']);
        Route::post('', [ProviderController::class, 'store']);
        Route::put('{modelProvider}', [ProviderController::class, 'update']);
        Route::post('{modelProvider}/health', [ProviderController::class, 'checkHealth']);
        Route::post('health-check-all', [ProviderController::class, 'checkAllHealth']);
        Route::delete('{modelProvider}', [ProviderController::class, 'destroy']);
    });

    // AI Models
    Route::prefix('models')->group(function () {
        Route::get('', [ModelController::class, 'index']);
        Route::post('', [ModelController::class, 'store']);
        Route::put('{aiModel}', [ModelController::class, 'update']);
        Route::post('{aiModel}/toggle', [ModelController::class, 'toggleStatus']);
        Route::delete('{aiModel}', [ModelController::class, 'destroy']);
    });

    // Coding Agents
    Route::prefix('agents')->group(function () {
        Route::get('', [AgentController::class, 'index']);
        Route::post('', [AgentController::class, 'store']);
        Route::put('{codingAgent}', [AgentController::class, 'update']);
        Route::post('{codingAgent}/toggle', [AgentController::class, 'toggleStatus']);
    });

    // Security & Audit Logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    // System Settings
    Route::get('settings', [SystemSettingController::class, 'index']);
    Route::post('settings/{group}', [SystemSettingController::class, 'updateGroup']);
});

/*
|--------------------------------------------------------------------------
| External REST API (v1) for Third-Party Applications
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('chat', [ApiController::class, 'chat'])->middleware('api.key:chat');
    Route::get('models', [ApiController::class, 'models'])->middleware('api.key:models.read');
    Route::get('usage', [ApiController::class, 'usage'])->middleware('api.key:usage.read');
    Route::get('projects', [ApiController::class, 'projects'])->middleware('api.key:projects.read');
});
