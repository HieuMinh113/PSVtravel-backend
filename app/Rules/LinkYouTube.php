<?php

namespace App\Rules;

use App\Services\YouTube;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ô "Link video" chỉ nhận link YouTube hợp lệ (để trống thì cho qua).
 *
 * Viết thành lớp quy tắc thay vì closure: Filament tự "evaluate" mọi closure
 * nằm trong ->rules([...]) bằng cơ chế tiêm tham số riêng của nó, nên closure
 * kiểu Laravel ($attribute, $value, $fail) sẽ làm form lỗi ngay khi lưu.
 */
class LinkYouTube implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && ! YouTube::id((string) $value)) {
            $fail('Link không phải video YouTube hợp lệ. Dán link dạng youtube.com/watch?v=... hoặc youtu.be/...');
        }
    }
}
