<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    // POST /api/v1/auth/register
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            // email_verified_at để trống — chưa xác thực OTP thì chưa đăng nhập được
        ]);

        // Người tự đăng ký luôn là khách hàng, không bao giờ tự lên được admin
        $user->assignRole('customer');

        $this->otp->gui($user->email, OtpService::MUC_DICH_DANG_KY, $request->ip(), $user->name);

        return response()->json([
            'message' => 'Đăng ký thành công! Mã xác thực đã được gửi tới email của bạn.',
            'data' => ['email' => $user->email],
        ], 201);
    }

    // POST /api/v1/auth/verify-otp
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Không tìm thấy tài khoản với email này.',
            ]);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Tài khoản đã được xác thực trước đó.'], 200);
        }

        $this->otp->xacThuc($data['email'], OtpService::MUC_DICH_DANG_KY, $data['code']);

        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json([
            'message' => 'Xác thực thành công!',
            'data' => [
                'user' => new UserResource($user),
                'token' => $this->taoToken($user),
            ],
        ]);
    }

    // POST /api/v1/auth/resend-otp
    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->whereNull('email_verified_at')->first();

        // Không tiết lộ email có tồn tại hay không — chống dò tài khoản
        if ($user) {
            $this->otp->gui($user->email, OtpService::MUC_DICH_DANG_KY, $request->ip(), $user->name);
        }

        return response()->json([
            'message' => 'Nếu email hợp lệ, mã xác thực mới đã được gửi. Vui lòng kiểm tra hộp thư.',
        ]);
    }

    // POST /api/v1/auth/login — đăng nhập bằng email HOẶC username HOẶC số điện thoại
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $dinhDanh = trim($data['login']);

        $user = User::where('email', $dinhDanh)
            ->orWhere('username', $dinhDanh)
            ->orWhere('phone', $dinhDanh)
            ->first();

        // Thông báo chung cho cả 2 trường hợp sai — không cho biết tài khoản có tồn tại hay không
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Thông tin đăng nhập không chính xác.',
            ]);
        }

        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'Tài khoản chưa xác thực. Vui lòng nhập mã OTP đã gửi tới email.',
                'need_verify' => true,
                'data' => ['email' => $user->email],
            ], 403);
        }

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $this->taoToken($user),
            ],
        ]);
    }

    // POST /api/v1/auth/logout  (cần token)
    public function logout(Request $request): JsonResponse
    {
        // Chỉ xoá đúng token đang dùng — các thiết bị khác vẫn đăng nhập bình thường
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    // GET /api/v1/auth/me  (cần token)
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    /**
     * Token hết hạn sau 7 ngày — hạn chế thiệt hại nếu token bị lộ.
     */
    private function taoToken(User $user): string
    {
        return $user->createToken('web', ['*'], now()->addDays(7))->plainTextToken;
    }
}
