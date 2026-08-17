<?php

namespace Modules\Page\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Page\Models\Setting;

class SettingApiController extends Controller
{
    // GET /api/v1/settings — trả toàn bộ cấu hình dạng key => value
    public function index()
    {
        $settings = Setting::query()
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