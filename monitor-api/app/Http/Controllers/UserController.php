<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        if (!request()->user()->hasPermission('users', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view users.'], 403);
        }
        return response()->json(User::select(['id', 'name', 'email', 'permissions', 'created_at'])->get());
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('users', 'create')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to create users.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'permissions' => 'nullable|array',
        ]);

        $plainPassword = Str::random(12);

        $defaultPermissions = [
            'servers' => ['view', 'create', 'update', 'delete'],
            'users' => ['view', 'create', 'update', 'delete'],
            'maintenance' => ['view', 'update'],
            'uptime' => ['view'],
            'incidents' => ['view']
        ];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'permissions' => $validated['permissions'] ?? $defaultPermissions,
        ]);

        try {
            $appUrl = env('FRONTEND_URL');
            if (!$appUrl) {
                $baseAppUrl = rtrim(config('app.url'), '/');
                if ($baseAppUrl === 'http://localhost' || $baseAppUrl === 'http://127.0.0.1') {
                    $appUrl = 'http://localhost:5173/watcher/';
                } else {
                    $appUrl = $baseAppUrl . '/watcher/';
                }
            }
            $appUrl = rtrim($appUrl, '/') . '/';

            $data = [
                'subject'   => 'Your ServerWatcher Account Credentials',
                'appUrl'    => $appUrl,
                'title'     => 'Account Created',
                'greeting'  => "Hello {$user->name},",
                'bodyText'  => 'Your BIT DevOps ServerWatcher account has been created. Use the credentials below to log in to your dashboard.',
                'email'     => $user->email,
                'password'  => $plainPassword,
                'note'      => 'For security, please change your password after logging in.',
            ];

            Mail::send('emails.credentials', $data, function ($message) use ($user, $data) {
                $message->to($user->email)->subject($data['subject']);
            });
        } catch (\Exception $e) {
            $user->delete();
            return response()->json([
                'message' => 'Failed to send account credentials email: ' . $e->getMessage() . '. Please verify your SMTP settings before adding users.'
            ], 500);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->permissions,
            'created_at' => $user->created_at,
        ], 201);
    }

    public function show(User $user)
    {
        if (request()->user()->id !== $user->id && !request()->user()->hasPermission('users', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view this user.'], 403);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->permissions,
            'created_at' => $user->created_at,
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id && !$request->user()->hasPermission('users', 'update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update this user.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'permissions' => 'nullable|array',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (isset($validated['permissions']) && $request->user()->hasPermission('users', 'update')) {
            $user->permissions = $validated['permissions'];
        }

        $user->save();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->permissions,
            'created_at' => $user->created_at,
        ]);
    }

    public function destroy(User $user)
    {
        if (!request()->user()->hasPermission('users', 'delete')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to delete users.'], 403);
        }

        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function resetPassword(User $user)
    {
        if (!request()->user()->hasPermission('users', 'update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to reset user passwords.'], 403);
        }
        $newPassword = Str::random(12);
        $oldPasswordHash = $user->password;

        $user->password = Hash::make($newPassword);
        $user->save();

        try {
            $appUrl = env('FRONTEND_URL');
            if (!$appUrl) {
                $baseAppUrl = rtrim(config('app.url'), '/');
                if ($baseAppUrl === 'http://localhost' || $baseAppUrl === 'http://127.0.0.1') {
                    $appUrl = 'http://localhost:5173/watcher/';
                } else {
                    $appUrl = $baseAppUrl . '/watcher/';
                }
            }
            $appUrl = rtrim($appUrl, '/') . '/';

            $data = [
                'subject'   => 'Your ServerWatcher Password Reset',
                'appUrl'    => $appUrl,
                'title'     => 'Password Reset',
                'greeting'  => "Hello {$user->name},",
                'bodyText'  => 'Your BIT DevOps ServerWatcher account password has been reset. Here are your new credentials:',
                'email'     => $user->email,
                'password'  => $newPassword,
                'note'      => 'For security, please change your password after logging in.',
            ];

            Mail::send('emails.credentials', $data, function ($message) use ($user, $data) {
                $message->to($user->email)->subject($data['subject']);
            });
        } catch (\Exception $e) {
            $user->password = $oldPasswordHash;
            $user->save();

            return response()->json([
                'message' => 'Failed to send reset email: ' . $e->getMessage() . '. Password has not been changed.'
            ], 500);
        }

        return response()->json([
            'message' => "Password reset successfully. A new random 12-character password has been sent to {$user->email}."
        ]);
    }
}
