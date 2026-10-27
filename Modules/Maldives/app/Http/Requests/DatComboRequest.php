<?php

namespace Modules\Maldives\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // khách vãng lai, không cần đăng nhập
    }

    public function rules(): array
    {
        return [
            'combo_id' => ['required', 'integer'],
            'combo_date_id' => ['nullable', 'integer'],
            'room_type_id' => ['nullable', 'integer'],
            'departure_city' => ['nullable', 'string', 'max:100'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:20'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:30'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Vui lòng nhập họ tên.',
            'customer_phone.required' => 'Vui lòng nhập số điện thoại.',
            'customer_phone.regex' => 'Số điện thoại không hợp lệ.',
            'adults.min' => 'Phải có ít nhất 1 người lớn.',
        ];
    }
}
