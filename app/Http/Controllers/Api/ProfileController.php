<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    // PUT /api/v1/auth/profile  (cần token)
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required', 'string', 'regex:/^(0|\+84)[0-9]{8,10}$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'avatar' => ['nullable', 'image', 'max:2048'], // tối đa 2MB
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'phone.unique' => 'Số điện thoại này đã được người khác sử dụng.',
            'avatar.image' => 'Ảnh đại diện phải là tệp hình ảnh.',
            'avatar.max' => 'Ảnh đại diện tối đa 2MB.',
        ]);

        // Email và username KHÔNG cho tự đổi ở đây — tránh chiếm tên người khác
        // và tránh mất liên kết xác thực. Muốn đổi email phải qua luồng OTP riêng.
        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'],
        ]);

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'Cập nhật hồ sơ thành công.',
            'data' => new UserResource($user),
        ]);
    }

    // PUT /api/v1/auth/password  (cần token)
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mật khẩu hiện tại không đúng.',
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Đổi mật khẩu thì thu hồi toàn bộ token cũ — nếu ai đó đang chiếm phiên sẽ bị đá ra
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
        ]);
    }
}
