<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Chỉ chữ thường, số và gạch dưới — tránh ký tự lạ gây rối khi hiển thị/đăng nhập
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],

            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],

            // Số điện thoại Việt Nam: bắt đầu 0 hoặc +84, tổng 9-11 số
            'phone' => ['required', 'string', 'regex:/^(0|\+84)[0-9]{8,10}$/', 'unique:users,phone'],

            // Password::defaults() của Laravel: tối thiểu 8 ký tự + kiểm tra rò rỉ dữ liệu đã biết
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->username)),
            'email' => strtolower(trim((string) $this->email)),
            'phone' => str_replace([' ', '.', '-'], '', (string) $this->phone),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.regex' => 'Tên đăng nhập chỉ gồm chữ thường, số và dấu gạch dưới.',
            'username.unique' => 'Tên đăng nhập này đã có người dùng.',
            'username.min' => 'Tên đăng nhập phải từ 3 ký tự trở lên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được đăng ký.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'phone.unique' => 'Số điện thoại này đã được đăng ký.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ];
    }
}
