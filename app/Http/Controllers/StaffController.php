<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Office staff logins, managed by the owner from Settings. Staff can only view daily entries, without amounts. */
class StaffController extends Controller
{
    public const MAX_STAFF = 5;

    public function store(Request $request)
    {
        if (User::staff()->count() >= self::MAX_STAFF) {
            return back()->withErrors(['staff' => 'You already have '.self::MAX_STAFF.' staff logins. Delete one to add another.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        User::create($data + ['role' => User::ROLE_STAFF]);

        return back()->with('ok', "Staff login added for {$data['name']}.");
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($staff->isStaff(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'password' => ['nullable', 'min:8'],
        ]);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $staff->update($data);

        return back()->with('ok', "Staff login for {$staff->name} saved.");
    }

    public function destroy(User $staff)
    {
        abort_unless($staff->isStaff(), 404);
        $staff->delete();

        return back()->with('ok', "Staff login for {$staff->name} deleted.");
    }
}
