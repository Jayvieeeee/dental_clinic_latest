<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        
        // Check if user is admin and redirect to admin profile
        if ($user->role === 'admin') {
            return Inertia::render('Admin/Profile/Edit', [
                'mustVerifyEmail' => $user instanceof MustVerifyEmail,
                'status' => session('status'),
                // User is passed explicitly for Admin/Staff routes
                'user' => $user,
            ]);
        }
        
        // Check if user is staff and redirect to staff profile
        if ($user->role === 'staff') {
            return Inertia::render('Staff/Profile/Edit', [
                'mustVerifyEmail' => $user instanceof MustVerifyEmail,
                'status' => session('status'),
                // User is passed explicitly for Admin/Staff routes
                'user' => $user,
            ]);
        }
        
        // Regular customer profile
        // FIX: Explicitly pass the user object to ensure data freshness
        // and consistency after a successful update/redirect.
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'currentUser' => $user, // Use a unique name for the explicitly passed user
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            Log::error('No authenticated user found during profile update.');
            abort(401, 'Not authenticated');
        }

        $data = array_filter($request->only([
            'first_name',
            'last_name',
            'email',
            'contact_no',
        ]), fn($value) => !is_null($value) && $value !== '');

        $user->fill($data);
        $user->save();
        // Since we are redirecting back to the edit route, the new
        // data will be correctly picked up by the fixed edit method above.

        // Redirect based on role
        if ($user->role === 'admin') {
            return Redirect::route('admin.profile.edit')->with('status', 'profile-updated');
        }
        
        if ($user->role === 'staff') {
            return Redirect::route('staff.profile.edit')->with('status', 'profile-updated');
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}