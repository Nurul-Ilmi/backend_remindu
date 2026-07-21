<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProfileService
{
    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['phone_wa']) && !empty($data['phone_wa'])) {
            $data['wa_connected'] = true;
        } elseif (isset($data['phone_wa']) && empty($data['phone_wa'])) {
            $data['wa_connected'] = false;
        }

        $user->update($data);

        return $user;
    }

    public function requestOtp(User $user, string $phoneWa): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->update([
            'wa_otp' => $otp,
            'wa_otp_expires_at' => now()->addMinutes(10),
        ]);

        $fonnteToken = config('services.fonnte.token');
        if (!$fonnteToken) {
            throw new Exception('Fonnte token not configured.', 500);
        }

        $message = "Halo {$user->name}, kode OTP kamu untuk memverifikasi nomor WhatsApp di remind.u adalah: *{$otp}*\n\nBerlaku selama 10 menit. Jangan berikan kode ini kepada siapa pun.";

        try {
            $response = Http::withHeaders([
                'Authorization' => $fonnteToken,
            ])->post('https://api.fonnte.com/send', [
                'target'  => $phoneWa,
                'message' => $message,
            ]);

            if (!$response->successful()) {
                Log::error("Fonnte request OTP failed: " . $response->body());
                throw new Exception('Gagal mengirim OTP.', 500);
            }
        } catch (Exception $e) {
            Log::error("Fonnte exception on request OTP: " . $e->getMessage());
            throw new Exception('Terjadi kesalahan internal saat mengirim OTP.', 500);
        }
    }

    public function verifyOtp(User $user, string $phoneWa, string $otp): User
    {
        if ($user->wa_otp !== $otp || !$user->wa_otp_expires_at || $user->wa_otp_expires_at < now()) {
            throw new Exception('Kode OTP tidak valid atau sudah kedaluwarsa.', 400);
        }

        $user->update([
            'phone_wa' => $phoneWa,
            'wa_connected' => true,
            'wa_otp' => null,
            'wa_otp_expires_at' => null,
        ]);

        return $user;
    }
}
