<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class UsersController extends Controller
{
    public function index()
    {
        return view('admin::users.index');
    }

    public function create()
    {
        return view('admin::users.create');
    }

    public function edit(User $user)
    {
        // if user doesnt have an address for whatever reason, create one
        $user->createEmptyAddress();

        return view('admin::users.edit', compact('user'));
    }

    public function sendEmail(User $user)
    {
        return view('admin::users.send-email', compact('user'));
    }

    public function impersonate(User $user)
    {
        if ($user->isStaff()) {
            return redirect()->route('admin.users.edit', $user->id)->with('error', 'You cannot impersonate staff members.');
        }

        session(['impersonate' => $user->id]);

        return redirect()->route('dashboard');
    }

    public function exitImpersonate()
    {
        $userId = session('impersonate');

        if (! $userId) {
            return redirect()->route('admin.users.index');
        }

        session()->forget('impersonate');

        return redirect()->route('admin.users.edit', $userId);
    }
}
