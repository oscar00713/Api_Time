<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Update a setting value in the settings table
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSetting(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'value' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid data',
                    'details' => $validator->errors()
                ], 422);
            }

            // Get database connection from request
            $dbConnection = $request->get('db_connection');
            if (!$dbConnection) {
                return response()->json([
                    'error' => 'Database connection not specified'
                ], 400);
            }

            $query = DB::connection($dbConnection);

            // Check if the setting exists
            $existingSetting = $query->table('settings')
                ->where('name', $request->name)
                ->first();

            if ($existingSetting) {
                // Update existing setting
                $query->table('settings')
                    ->where('name', $request->name)
                    ->update(['value' => $request->value]);

                return response()->json([
                    'message' => 'Setting updated successfully',
                    'setting' => [
                        'name' => $request->name,
                        'value' => $request->value
                    ]
                ], 200);
            }
            // else {
            //     // Setting doesn't exist, insert new one
            //     $query->table('settings')->insert([
            //         'name' => $request->name,
            //         'value' => $request->value
            //     ]);

            //     return response()->json([
            //         'message' => 'Setting created successfully',
            //         'setting' => [
            //             'name' => $request->name,
            //             'value' => $request->value
            //         ]
            //     ], 201);
            // }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update setting',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all settings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettings(Request $request)
    {
        try {
            // Get database connection from request
            $dbConnection = $request->get('db_connection');
            if (!$dbConnection) {
                return response()->json([
                    'error' => 'Database connection not specified'
                ], 400);
            }

            $query = DB::connection($dbConnection);

            // Get all settings
            $settings = $query->table('settings')->get();

            // Convert to associative array for easier consumption
            $settingsArray = [];
            foreach ($settings as $setting) {
                $settingsArray[$setting->name] = $setting->value;
            }

            return response()->json([
                'settings' => $settingsArray
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve settings',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific setting by name
     *
     * @param Request $request
     * @param string $name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSetting(Request $request, $name)
    {
        try {
            // Get database connection from request
            $dbConnection = $request->get('db_connection');
            if (!$dbConnection) {
                return response()->json([
                    'error' => 'Database connection not specified'
                ], 400);
            }

            $query = DB::connection($dbConnection);

            // Get the specific setting
            $setting = $query->table('settings')
                ->where('name', $name)
                ->first();

            if (!$setting) {
                return response()->json([
                    'error' => 'Setting not found'
                ], 404);
            }

            return response()->json([
                'setting' => [
                    'name' => $setting->name,
                    'value' => $setting->value
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve setting',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
