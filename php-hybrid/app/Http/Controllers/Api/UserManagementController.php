<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController
{
    public function index(Request $request)
    {
        $query = User::query()->select(['id', 'username', 'email', 'role', 'is_active', 'created_at']);
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(fn ($q) => $q->where('username', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }
        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        return response()->json($query->orderBy('id')->paginate(min(max((int) $request->query('per_page', 25), 1), 100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:100', 'alpha_dash', 'unique:users,username', Rule::notIn(array_filter([config('voter_auth.admin_username')]))],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:1024'],
            'role' => ['required', Rule::in(['admin', 'viewer'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $user = User::query()->create([
            'username' => $data['username'], 'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']), 'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true, 'created_at' => now()->toIso8601String(),
        ]);
        return response()->json(['success' => true, 'user' => $this->publicUser($user)], 201);
    }

    public function show(User $user)
    {
        return response()->json(['user' => $this->publicUser($user)]);
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->attributes->get('auth_user', []);
        $data = $request->validate([
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id), Rule::notIn(array_filter([config('voter_auth.admin_username')]))],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'viewer'])],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'password' => ['sometimes', 'required', 'string', 'min:12', 'max:1024'],
        ]);

        if ((int) ($actor['user_id'] ?? 0) === (int) $user->id &&
            (($data['role'] ?? $user->role) !== 'admin' || (array_key_exists('is_active', $data) && !$data['is_active']))) {
            throw ValidationException::withMessages(['user' => 'You cannot demote or deactivate your own account.']);
        }

        DB::transaction(function () use ($user, $data) {
            $newRole = $data['role'] ?? $user->role;
            $newActive = $data['is_active'] ?? $user->is_active;
            if ($user->role === 'admin' && $user->is_active && ($newRole !== 'admin' || !$newActive)) {
                $this->ensureAnotherActiveAdmin($user->id);
            }
            foreach (['username', 'email', 'role', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) $user->{$field} = $data[$field];
            }
            if (isset($data['password'])) $user->password = Hash::make($data['password']);
            $user->save();
        });
        return response()->json(['success' => true, 'user' => $this->publicUser($user->fresh())]);
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->attributes->get('auth_user', []);
        if ((int) ($actor['user_id'] ?? 0) === (int) $user->id) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }
        if ($user->role === 'admin' && $user->is_active) $this->ensureAnotherActiveAdmin($user->id);
        $user->delete();
        return response()->json(['success' => true]);
    }

    private function ensureAnotherActiveAdmin(int $exceptId): void
    {
        if (User::query()->where('role', 'admin')->where('is_active', true)->where('id', '<>', $exceptId)->count() < 1) {
            throw ValidationException::withMessages(['user' => 'At least one active database administrator must remain.']);
        }
    }

    private function publicUser(User $user): array
    {
        return [
            'id' => (int) $user->id, 'username' => $user->username, 'email' => $user->email,
            'role' => $user->role, 'is_active' => (bool) $user->is_active, 'created_at' => $user->created_at,
        ];
    }
}
