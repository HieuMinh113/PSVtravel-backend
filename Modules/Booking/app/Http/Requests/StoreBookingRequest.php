<?php

namespace Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // khách vãng lai, không cần đăng nhập
    }

    public function rules(): array
    {
        return [
            'tour_id' => ['required', 'integer', 'exists:tours,id'],
            'tour_departure_id' => ['nullable', 'integer', 'exists:tour_departures,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tour_id.required' => 'Vui lòng chọn tour.',
            'tour_id.exists' => 'Tour không tồn tại.',
            'customer_name.required' => 'Vui lòng nhập họ tên.',
            'customer_phone.required' => 'Vui lòng nhập số điện thoại.',
            'customer_phone.regex' => 'Số điện thoại không hợp lệ.',
            'adults.required' => 'Vui lòng nhập số người lớn.',
            'adults.min' => 'Phải có ít nhất 1 người lớn.',
        ];
    }
}