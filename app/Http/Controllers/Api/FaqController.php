<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class FaqController extends Controller
{
    // GET /api/v1/faqs — nhóm theo chuyên mục
    public function index()
    {
        $items = Faq::query()->dangHienThi()->get();

        return response()->json([
            'data' => $items->map(fn ($f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'category' => $f->category,
                'category_label' => Faq::NHOM[$f->category] ?? 'Chung',
            ])->values(),
        ]);
    }
}
