<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use SoftDeletes;

    public const NHOM = [
        'thanh-toan' => 'Thanh toán',
        'huy-tour' => 'Đổi / huỷ tour',
        'visa' => 'Visa & giấy tờ',
        'dat-tour' => 'Đặt tour',
        // Hiện ở trang Team building / Du lịch công ty (kèm dữ liệu FAQ cho Google)
        'doanh-nghiep' => 'Doanh nghiệp / Team building',
        'chung' => 'Chung',
    ];

    protected $fillable = [
        'question', 'answer', 'category', 'status', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('id');
    }
}
