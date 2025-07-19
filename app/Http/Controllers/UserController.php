<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\BulkUserCreateJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function list(Request $request)
    {
        try {
            $user = $request->user();

            // For SuperAdmin
            if ($user->roles->pluck('name')->contains('SuperAdmin')) {
                $cacheKey = 'user_list';
                $users = Redis::get($cacheKey);

                if (!$users) {
                    $users = User::with('roles')->get();
                    Redis::setex($cacheKey, 60, json_encode($users));
                } else {
                    $users = json_decode($users);
                }

                return response()->json([
                    'status'  => 'success',
                    'data'    => $users
                ], 200);
            }

            // For Admin
            if ($user->roles->pluck('name')->contains('Admin')) {
                $users = User::whereDoesntHave('roles', function ($q) {
                    $q->whereIn('name', ['Admin', 'SuperAdmin']);
                })->with('roles')->get();

                return response()->json([
                    'status'  => 'success',
                    'data'    => $users
                ], 200);
            }

            // For Regular User
            return response()->json([
                'status'  => 'success',
                'data'    => $user->load('roles')
            ], 200);
        } catch (\Exception $e) {
            Log::error('User listing failed', [
                'user_id' => $request->user()->id ?? null,
                'error'   => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Unable to fetch user list. Please try again later.'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'users'            => 'required|array',
                'users.*.name'     => 'required|string|max:255',
                'users.*.email'    => 'required|email|unique:users,email',
                'users.*.password' => 'required|string|min:6'
            ]);

            // Dispatch job to handle bulk user creation
            BulkUserCreateJob::dispatch($validated['users']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Users are being processed.'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Log and handle any unexpected error
            Log::error('Bulk user creation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }


    public function update(Request $request, $id)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'name'     => 'nullable|string|max:255',
                'email'    => 'required|email',
                'password' => 'nullable|string|min:6',
            ]);

            // Find user
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found.'
                ], 404);
            }

            // Check if email is already taken by another user
            $exists = User::where('email', $validated['email'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The email has already been taken.'
                ], 422);
            }

            // Update user details
            $user->name  = $validated['name'] ?? $user->name;
            $user->email = $validated['email'];

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'User updated successfully.',
                'user'    => $user
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('User update failed', [
                'user_id' => $id,
                'error'   => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found.'
                ], 404);
            }
            // Soft delete
            $user->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'User soft deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            // Log exception if needed
            Log::error('User deletion failed', [
                'user_id' => $id,
                'error'   => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }


    public function assignRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'role'    => 'required|exists:roles,name'
            ]);

            $user = User::find($validated['user_id']);
            $role = Role::where('name', $validated['role'])->first();

            if (!$role) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Role not found.'
                ], 404);
            }

            // Attach role without removing existing roles
            $user->roles()->syncWithoutDetaching([$role->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Role assigned successfully.'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Role assignment failed', [
                'user_id' => $request->user_id ?? null,
                'error'   => $e->getMessage()
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    public function runMigrationsAndSeeders()
    {
        try {
            // Check if "passport:install" command exists
            $commands = Artisan::all();
            if (array_key_exists('passport:install', $commands)) {
                Artisan::call('passport:install', ['--force' => true]);
            } else {
                Log::warning('Passport command not found. Ensure laravel/passport is installed.');
            }
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Run all seeders
            Artisan::call('db:seed', ['--force' => true]);
            //  Ensure Passport personal access client exists
            $clientRepository = new ClientRepository();
            if (DB::table('oauth_clients')->where('personal_access_client', 1)->count() == 0) {
                $clientRepository->createPersonalAccessClient(
                    null,
                    'Default Personal Access Client',
                    config('app.url')
                );
            }

            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('optimize:clear');



            return response()->json([
                'status'  => 'success',
                'message' => 'Migrations, seeders, and Passport executed successfully.',
                'output'  => Artisan::output()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Migration/Seeder failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to run migrations or seeders.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function resetDatabase()
    {
        try {
            // Step 1: Refresh migrations
            Artisan::call('migrate:refresh', ['--force' => true]);

            // Step 2: Run all seeders
            Artisan::call('db:seed', ['--force' => true]);

            // Step 3: Ensure Passport personal access client exists
            $clientRepository = new ClientRepository();
            if (DB::table('oauth_clients')->where('personal_access_client', 1)->count() == 0) {
                $clientRepository->createPersonalAccessClient(
                    null,
                    'Default Personal Access Client',
                    config('app.url')
                );
            }

            // Step 4: Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('optimize:clear');

            return response()->json([
                'status'  => 'success',
                'message' => 'Database reset, seeded, Passport personal client created, and cache cleared successfully.',
                'output'  => Artisan::output()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Database reset failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reset database.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
