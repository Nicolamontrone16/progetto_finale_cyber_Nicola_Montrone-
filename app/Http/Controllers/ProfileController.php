<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $profileData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];
        $changedFields = [];

        foreach (['name', 'email'] as $field) {
            if ($user->{$field} !== $profileData[$field]) {
                $changedFields[] = $field;
            }
        }

        if (! empty($validated['password'])) {
            $profileData['password'] = Hash::make($validated['password']);
            $changedFields[] = 'password';
        }

        $user->update($profileData);

        Log::notice('User profile updated', [
            'event' => 'user_profile_updated',
            'actor_user_id' => $user->id,
            'changed_fields' => $changedFields,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => 'success',
        ]);

        return redirect()->route('profile.edit')->with('message', 'Profilo aggiornato con successo.');
    }
}
