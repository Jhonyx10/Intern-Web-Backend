<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'required|string|max:255',
        ]);

        $role = Role::create($validated);
        return response()->json($role, 201);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'required|string|max:255',
        ]);

        $role->update($validated);
        return response()->json($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return response()->json(null, 204);
    }
}
