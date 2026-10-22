<?php

namespace App\Models;

use App\Jobs\PingIndexNow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tour\Models\Tour;

/**
 * Trang tour theo mùa / dịp: /tour-tet, /tour-he… Tour không gắn tay vào trang —
 * tour nào còn đợt khởi hành (đặt được) trong khoảng ngày của trang thì tự hiện.
 */
class SeasonPage extends Model
{
    use SoftDeletes;

    /** Đường dẫn trang nằm ngay gốc website nên bắt buộc "tour-…" và không trùng trang có sẵn. */
    public const MAU_SLUG = '/^tour-[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const SLUG_CAM = ['tour-trong-nuoc', 'tour-nuoc-ngoai'];

    public const LOAI = ['domestic' => 'Tour trong nước', 'abroad' => 'Tour nước ngoài'];

    protected static function booted(): void
    {
        // Báo máy tìm kiếm trang vừa đổi (như tour) — chỉ trên máy chủ thật
        static::saved(function (self $p) {
            if ($p->status === 'published' && app()->isProduction()) {
                PingIndexNow::dispatch([rtrim((string) config('app.frontend_url'), '/').'/'.$p->slug]);
            }
        });
    }

    protected $fillable = [
        'name', 'slug', 'type', 'start_from', 'start_to', 'intro', 'faqs', 'cover_image',
        'seo_title', 'seo_description', 'status', 'sort_order',
    ];

    protected $casts = [
        'start_from' => 'date',
        'start_to' => 'date',
        'faqs' => 'array',
    ];

    public function scopeDangHienThi(Builder $query): Builder
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Tour của trang: đang hiện cho khách, có đợt đặt được (còn nhận, chưa đi, chưa
     * quá hạn visa) khởi hành trong khoảng ngày; kèm sẵn đúng các đợt đó để thẻ
     * tour hiện ngày đi của dịp. Đợt sớm nhất lên trước.
     */
    public function truyVanTour(): Builder
    {
        $trongKhoang = fn ($q) => $q->hienTrenWeb()
            ->whereDate('tour_departures.start_date', '>=', $this->start_from)
            ->whereDate('tour_departures.start_date', '<=', $this->start_to);

        return Tour::query()->hienChoKhach()
            ->when($this->type, fn ($q) => $q->where('tours.type', $this->type))
            ->whereHas('departures', $trongKhoang)
            ->withMin(['departures as dot_som_nhat' => $trongKhoang], 'start_date')
            ->with(['categories:id,slug', 'departures' => fn ($q) => $trongKhoang($q)->orderBy('start_date')])
            ->orderBy('dot_som_nhat')
            ->orderBy('tours.id');
    }
}
