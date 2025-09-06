<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'referral_code' => $this->generateReferralCode(),
            'demo_balance' => 10000, // Starting demo balance
            'live_balance' => 0,
        ]);

        // Generate and send OTP for email verification
        $otp = $this->otpService->generateOtp($user);
        $this->otpService->sendOtpEmail($user, $otp);

        return response()->json([
            'message' => 'User registered successfully. Please check your email for OTP verification.',
            'user' => $user,
            'requires_otp' => true,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Check if user is locked
        $user = User::where('email', $request->email)->first();
        // if ($user && $user->isLocked()) {
        //     return response()->json([
        //         'error' => 'Account is temporarily locked due to too many failed attempts. Please try again in 15 minutes.',
        //     ], 423);
        // }

        if (!Auth::attempt($request->only('email', 'password'))) {
            // Increment login attempts
            if ($user) {
                $user->incrementLoginAttempts();
            }
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        // Reset login attempts on successful password verification
        $user->resetLoginAttempts();

        // Check if OTP verification is required
        if ($this->otpService->needsOtpVerification($user)) {
            // Generate and send OTP
            $otp = $this->otpService->generateOtp($user);
            $this->otpService->sendOtpEmail($user, $otp);

            return response()->json([
                'message' => 'OTP sent to your email for verification.',
                'requires_otp' => true,
                'user_id' => $user->id,
            ]);
        }

        // If no OTP required, proceed with login
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $user,
            'token' => $token,
            'requires_otp' => false,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if user is locked
        // if ($user->isLocked()) {
        //     return response()->json([
        //         'error' => 'Account is temporarily locked due to too many failed attempts. Please try again in 15 minutes.',
        //     ], 423);
        // }

        // Verify OTP
        if (!$this->otpService->verifyOtp($user, $request->otp)) {
            // Increment login attempts for failed OTP
            $user->incrementLoginAttempts();
            
            return response()->json([
                'error' => 'Invalid or expired OTP. Please try agaiasdn.',
            ], 400);
        }

        // Reset login attempts on successful OTP verification
        $user->resetLoginAttempts();

        // Mark email as verified if this is first time
        if (!$user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        // Generate authentication token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'OTP verified successfully. Logged in successfully.',
            'user' => $user,
            'token' => $token,
            'requires_otp' => false,
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $result = $this->otpService->resendOtp($user);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'error' => $result['message'],
        ], 400);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
