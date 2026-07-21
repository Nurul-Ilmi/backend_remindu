<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * Update the authenticated user's profile settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'phone_wa'      => 'sometimes|string|max:20|nullable',
            'extreme_mode'  => 'sometimes|boolean',
            'study_program' => 'sometimes|string|max:150|nullable',
            'batch_year'    => 'sometimes|string|max:10|nullable',
            'university'    => 'sometimes|string|max:150|nullable',
        ]);

        $user = $this->profileService->updateProfile($request->user(), $validated);

        return response()->json($user);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_wa' => 'required|string|max:20',
        ]);

        try {
            $this->profileService->requestOtp($request->user(), $validated['phone_wa']);
            return response()->json(['message' => 'OTP terkirim ke WhatsApp.']);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 500;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_wa' => 'required|string|max:20',
            'otp'      => 'required|string|size:6',
        ]);

        try {
            $user = $this->profileService->verifyOtp($request->user(), $validated['phone_wa'], $validated['otp']);
            return response()->json([
                'message' => 'Nomor WhatsApp berhasil diverifikasi.',
                'user' => $user
            ]);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 400;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists('avatars/' . $user->avatar)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete('avatars/' . $user->avatar);
            }

            $file = $request->file('avatar');
            $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('avatars', $filename, 'public');

            $user->avatar = $filename;
            $user->save();

            return response()->json([
                'message' => 'Avatar berhasil diperbarui.',
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'Gagal mengunggah avatar.'], 400);
    }
}
