<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Support\IssuerSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        // Changing the address means the new one has not been proven yet.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'data' => $this->payload($user),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Deleting your own account ends the session with it, so the page is told
     * where to go rather than being left on a dead shell.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        // The SPA authenticates through the session (Sanctum stateful), so it is
        // the web guard that has to be logged out — Auth::logout() would resolve
        // the sanctum request guard, which cannot.
        Auth::guard('web')->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['deleted' => true, 'redirect' => '/']);
    }

    public function sendVerificationNotification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'That address is already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'A new verification link has been sent to your email address.']);
    }

    /**
     * The signature LPOs are issued under, drawn or uploaded once. Replacing it
     * changes only LPOs issued from now on; each one keeps the copy it went out
     * with.
     */
    public function updateSignature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'signature_image' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! IssuerSignature::isValidImage($value)) {
                    $fail('The signature must be a PNG or JPEG image under 512 KB.');
                }
            }],
        ]);

        $request->user()->forceFill(['signature_image' => $data['signature_image']])->save();

        return response()->json([
            'message' => 'Signature saved. It will appear on every LPO you issue.',
            'data' => $this->payload($request->user()),
        ]);
    }

    public function destroySignature(Request $request): JsonResponse
    {
        $request->user()->forceFill(['signature_image' => null])->save();

        return response()->json([
            'message' => 'Signature removed. You will be asked for one before issuing your next LPO.',
            'data' => $this->payload($request->user()),
        ]);
    }

    private function payload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => ! is_null($user->email_verified_at),
            'roles' => $user->roles->pluck('name'),
            'signature' => $user->signature_image,
        ];
    }
}
