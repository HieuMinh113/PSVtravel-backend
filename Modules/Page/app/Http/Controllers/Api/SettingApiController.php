<?php

namespace Modules\Page\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Page\Models\Setting;

class SettingApiController extends Controller
{
    // Chỉ những nhóm này được phép lộ ra ngoài công khai.
    // Cấu hình bí mật (khoá cổng thanh toán, SMTP...) hãy để group khác
    // — mặc định sẽ KHÔNG bao giờ xuất hiện ở API này.
    private const NHOM_CONG_KHAI = ['general', 'contact', 'social', 'seo'];

    // GET /api/v1/settings — trả cấu hình công khai dạng key => value
    public function index()
    {
        $settings = Setting::query()
            ->whereIn('group', self::NHOM_CONG_KHAI)
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(function ($s) {
                $value = $s->value;

                // Cấu hình kiểu ảnh: trả URL đầy đủ
                if ($s->type === 'image' && $value) {
                    $value = str_starts_with($value, 'http') ? $value : asset('storage/'.$value);
                }

                return [$s->key => $value];
            });

        return response()->json(['data' => $settings]);
    }
}