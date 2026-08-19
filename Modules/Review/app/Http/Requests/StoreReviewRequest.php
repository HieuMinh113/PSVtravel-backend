<?php

namespace Modules\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tour_id' => ['required', 'integer', 'exists:tours,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tour_id.required' => 'Thiếu thông tin tour.',
            'rating.required' => 'Vui lòng chọn số sao.',
            'rating.min' => 'Số sao phải từ 1 đến 5.',
            'rating.max' => 'Số sao phải từ 1 đến 5.',
            'content.required' => 'Vui lòng nhập nội dung đánh giá.',
            'content.min' => 'Nội dung đánh giá quá ngắn (tối thiểu 10 ký tự).',
            'content.max' => 'Nội dung đánh giá tối đa 2000 ký tự.',
        ];
    }
}
