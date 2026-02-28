<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1️⃣ List Users
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'status', 'created_at')
            ->with([
                'roles:id,name',
                'client:id,user_id,clientName',
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $role = $request->string('role')->trim()->toString();
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $query->latest()
            ->paginate($request->integer('per_page', 10));
        $users->getCollection()->transform(function ($user) {
            $user->roles = $user->roles->pluck('name')->values();
            $user->client_name = optional($user->client)->clientName;
            unset($user->client);

            return $user;
        });

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function show($id)
    {
        $user = User::select('id', 'name', 'email', 'status', 'created_at')
            ->with([
                'roles:id,name',
                'client:id,user_id,clientName',
            ])
            ->findOrFail($id);

        $user->roles = $user->roles->pluck('name')->values();
        $user->client_name = optional($user->client)->clientName;
        unset($user->client);

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Update User (Edit + Status Update in Same Endpoint)
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|min:6',
            'status'   => 'required|in:active,inactive'
        ]);

        DB::beginTransaction();

        try {

            $user->name   = $request->name;
            $user->email  = $request->email;
            $user->status = $request->status;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'user' => $user
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 3️⃣ Delete User + All Related Data
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        DB::beginTransaction();
    
        try {
    
            $user = User::findOrFail($id);
    
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $database = env('DB_DATABASE');
            $key = "Tables_in_" . $database;
    
            foreach ($tables as $table) {
    
                $tableName = $table->$key;
    
                if ($tableName === 'users') {
                    continue;
                }
    
                $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
    
                if (in_array('user_id', $columns)) {
                    DB::table($tableName)
                        ->where('user_id', $user->id)
                        ->delete();
                }
            }
    
            // Remove roles
            $user->syncRoles([]);
    
            // Delete Sanctum tokens
            $user->tokens()->delete();
    
            // Delete user
            $user->delete();
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'User and all related data deleted successfully'
            ]);
    
        } catch (\Exception $e) {
    
            DB::rollBack();
    
            return response()->json([
                'status' => 'error',
                'message' => 'Deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
