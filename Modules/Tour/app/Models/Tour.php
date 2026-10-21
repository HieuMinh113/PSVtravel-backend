<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Category\Models\Category;
use App\Jobs\PingIndexNow;
use Modules\Tour\Services\HanNopVisa;
class Tour extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    // Khi một tour ĐANG BÁN được lưu, báo IndexNow để Bing/Yandex lập chỉ mục
    // ngay trang đó (Bing nuôi ChatGPT/Copilot). Chạy trong hàng đợi nên không
    // làm chậm thao tác lưu; chỉ ping ở môi trường thật để khỏi nhiễu khi seed.
    protected static function booted(): void
    {
        // Đổi quốc gia / quy tắc visa / dạng visa / loại tour → tính lại hạn ẩn các đợt khởi hành
        static::saved(function (self $tour) {
            if ($tour->wasChanged(['country', 'visa_deadline_days', 'visa_kind', 'type'])) {
                HanNopVisa::tinhLaiTour($tour);
            }
        });
        static::saved(function (self $tour) {
            if ($tour->status !== 'published' || ! app()->isProduction()) {
                return;
            }
            $goc = rtrim((string) config('app.frontend_url'), '/');
            $muc = $tour->type === 'abroad' ? 'tour-nuoc-ngoai' : 'tour-trong-nuoc';
            PingIndexNow::dispatch([
                "{$goc}/{$muc}/{$tour->slug}",
                "{$goc}/{$muc}",
            ]);
        });
    }

    protected $fillable = [
        'slug', 'name', 'seo_title', 'type', 'region', 'country',
        'duration_days', 'duration_nights', 'departure_from', 'visa_deadline_days', 'visa_kind',
        'adult_price', 'child_price', 'infant_price', 'old_price',
        'tag', 'cover_image', 'video_url',
        'highlights', 'included', 'excluded',
        'cancellation_policy', 'notes', 'description',
        'rating', 'review_count',
        'status', 'is_featured', 'sort_order',
        'alliance_tour_id',
    ];

    protected $casts = [
        'highlights'   => 'array',
        'included'     => 'array',
        'excluded'     => 'array',
        'notes'        => 'array',
        'adult_price'  => 'integer',
        'child_price'  => 'integer',
        'infant_price' => 'integer',
        'old_price'    => 'integer',
        'rating'       => 'decimal:1',
        'review_count' => 'integer',
        'visa_deadline_days' => 'integer',
        'is_featured'  => 'boolean',
        'sort_order'   => 'integer',
    ];

    /**
     * Tách một danh sách (bao gồm / không bao gồm) thành từng mục riêng.
     *
     * Dùng ở CẢ HAI đầu:
     *  - lúc lưu trong admin, để dán nguyên đoạn từ file chương trình tour vào
     *    là ra đúng từng mục;
     *  - lúc trả về cho website, để những tour đã nhập từ trước — cả đoạn văn
     *    dồn thành MỘT mục có dấu ➢ ở giữa — hiện ra đúng, khỏi phải mở từng
     *    tour ra lưu lại.
     */
    public static function tachTungMuc($gia): array
    {
        if (is_array($gia)) {
            $gia = implode("\n", $gia);
        }

        return collect(preg_split('/[\r\n➢▪•]+/u', (string) $gia))
            // Cắt khoảng trắng và dấu gạch đầu dòng, GIỮ dấu chấm cuối câu.
            ->map(fn ($dong) => trim($dong, " \t\u{00A0}-"))
            ->filter()
            ->values()
            ->all();
    }

    // Đơn đặt của tour — dùng để chặn xoá vĩnh viễn khi tour đã phát sinh giao dịch
    public function bookings(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Booking::class);
    }

    /**
     * Tour liên minh (sheet đối tác) mà tour này lấy số chỗ theo. Có nối thì
     * số chỗ các ngày đi trên website tự cập nhật mỗi lần máy đọc sheet.
     */
    public function allianceTour(): BelongsTo
    {
        return $this->belongsTo(\Modules\Alliance\Models\AllianceTour::class);
    }

    /** Lượt quét mã QR của tour (in trên tờ rơi, poster...) */
    public function qrScans(): HasMany
    {
        return $this->hasMany(TourQrScan::class);
    }

    public function departures(): HasMany
    {
        // Luôn sắp theo ngày khởi hành tăng dần: ngày gần nhất lên đầu, để danh
        // sách chọn ngày trên web đúng thứ tự thời gian (không theo thứ tự nhập).
        return $this->hasMany(TourDeparture::class)->orderBy('start_date');
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(TourItinerary::class)->orderBy('day_number');
    }

    public function images(): HasMany
    {
        // Thêm id làm tiêu chí phụ: nhiều ảnh cùng "Thứ tự = 0" thì Postgres trả
        // về theo thứ tự ngẫu nhiên, mỗi lần tải trang một khác.
        return $this->hasMany(TourImage::class)->orderBy('sort_order')->orderBy('id');
    }

    // Chỉ lấy tour đang hiển thị công khai
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Tour khách thấy trên website: đang hiển thị, và KHÔNG phải tour mà mọi đợt
     * sắp tới còn nhận khách đều đã quá hạn nộp visa. Tour chưa có lịch khởi hành
     * (bán theo yêu cầu) vẫn hiện như cũ.
     */
    public function scopeHienChoKhach($query)
    {
        return $query->where('tours.status', 'published')
            ->where(fn ($q) => $q
                ->whereHas('departures', fn ($d) => $d->hienTrenWeb())
                ->orWhereDoesntHave('departures', fn ($d) => $d->where('status', 'open')->whereDate('start_date', '>=', today())));
    }

    /**
     * Có đợt sắp tới còn nhận khách nhưng TẤT CẢ đều đã quá hạn nộp visa (tour
     * đã ẩn khỏi danh sách) → trang tour báo "hết hạn nhận hồ sơ", mời liên hệ.
     */
    public function hetHanVisa(): bool
    {
        $sapToi = $this->departures->filter(fn ($d) => $d->status === 'open' && ! $d->start_date->lt(today()));

        return $sapToi->isNotEmpty() && $sapToi->every(fn ($d) => $d->quaHanVisa($this));
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'slug', 'status', 'adult_price', 'child_price', 'infant_price',
                'old_price', 'is_featured', 'type', 'visa_deadline_days', 'visa_kind',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tour');
    }
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_tour');
    }
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Review\Models\Review::class);
    }
}