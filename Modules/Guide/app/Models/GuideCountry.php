<?php

namespace Modules\Guide\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/**
 * Nước trên bản đồ cẩm nang. `code` là mã ADM0_A3 của Natural Earth — tra
 * hình dạng, khung bao, vị trí nhãn trong resources/geo/quoc-gia.json.
 */
class GuideCountry extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'intro', 'cover_image', 'status', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    /** @var array<string, array>|null */
    private static ?array $ranhGioi = null;

    /**
     * Ranh giới 242 nước (Natural Earth 1:50m, phạm vi công cộng — đã rút gọn
     * cho nhẹ). Mỗi nước: ten, ten_en, nhan [lng, lat], khung [w, s, e, n], hinh.
     *
     * @return array<string, array>
     */
    public static function ranhGioi(): array
    {
        return self::$ranhGioi ??= json_decode(
            (string) file_get_contents(module_path('Guide', 'resources/geo/quoc-gia.json')), true
        ) ?? [];
    }

    public static function duLieuNuoc(?string $code): ?array
    {
        return $code ? (self::ranhGioi()[strtoupper($code)] ?? null) : null;
    }

    /** Ô chọn nước trong trang quản trị: "Trung Quốc (CHN)". @return array<string, string> */
    public static function luaChonNuoc(): array
    {
        return collect(self::ranhGioi())
            ->map(fn ($n, $code) => "{$n['ten']} ({$code})")
            ->sortBy(fn ($ten) => Str::ascii($ten))
            ->all();
    }

    public function cities(): HasMany
    {
        return $this->hasMany(GuideCity::class)->orderBy('sort_order')->orderBy('name');
    }

    public function spots(): HasManyThrough
    {
        return $this->hasManyThrough(GuideSpot::class, GuideCity::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('name');
    }
}
