<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = SystemSetting::all()->groupBy('group');

        return response()->json($settings);
    }

    public function updateGroup(Request $request, string $group): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($validated['settings'] as $key => $val) {
            SystemSetting::set($key, $val, $group);
        }

        AuditLog::log('update_system_settings', 'SystemSetting', $group, ['keys' => array_keys($validated['settings'])]);

        return response()->json([
            'message' => "Settings for group '{$group}' updated successfully",
            'settings' => SystemSetting::where('group', $group)->get(),
        ]);
    }
}
