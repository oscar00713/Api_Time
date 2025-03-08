<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index()
    {
        // Consultar todos los registros de la tabla "server"
        $servers = Server::all();

        // Retornar la información como JSON
        return response()->json($servers);
    }

    public function delete(string $id)
    {
        Server::findOrFail($id)->delete();
        return response()->json(['message' => 'Server deleted successfully'], 200);
    }
}
