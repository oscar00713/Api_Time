<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $rooms = $query->table('rooms')->get();
            return response()->json($rooms);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string',
            'status' => 'nullable|integer',
        ]);

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $roomId = $query->table('rooms')->insertGetId($validatedData);
            return response()->json(['id' => $roomId, 'message' => 'Room created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        try {
            $room = $query->table('rooms')->find($id);

            if (!$room) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            return response()->json($room);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'name' => 'nullable|string',
            'status' => 'nullable|integer',
        ]);

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            // Preparar datos de actualización
            $updated = $query->table('rooms')->where('id', $id)->update($validatedData);

            if (!$updated) {
                return response()->json(['error' => 'Room not found or no changes made'], 404);
            }

            return response()->json(['message' => 'Room updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        try {
            $deleted = $query->table('rooms')->where('id', $id)->delete();

            if (!$deleted) {
                return response()->json(['error' => 'Room not found'], 404);
            }

            return response()->json(['message' => 'Room deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
