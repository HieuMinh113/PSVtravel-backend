<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Có thể là email, tên đăng nhập hoặc số điện thoại
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Vui lòng nhập email, tên đăng nhập hoặc số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }
}
