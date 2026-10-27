<?php

namespace Modules\Maldives\Models;

use App\Services\DoiDuongDan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Combo Maldives: một resort + hãng bay + lịch trình + các ngày khởi hành
 * (giá USD theo hạng phòng). Trang web: /maldives/{slug}.
 */
class MaldivesCombo extends Model
{
    use LogsActivity, SoftDeletes;

    public const TRANG_THAI = ['draft' => 'Nháp', 'published' => 'Đang bán', 'hidden' => 'Tạm ẩn'];

    protected $fillable = [
        'resort_id', 'slug', 'name', 'seo_title', 'summary', 'duration_days', 'duration_nights', 'stay_summary',
        'airline', 'transfer', 'cover_image', 'highlights', 'departure_cities', 'child_percent', 'child_age',
        'infant_note', 'min_group', 'included', 'excluded', 'notes', 'status', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'duration_nights' => 'integer',
        'highlights' => 'array',
        'departure_cities' => 'array',
        'child_percent' => 'integer',
        'min_group' => 'integer',
        'included' => 'array',
        'excluded' => 'array',
        'notes' => 'array',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $c) {
            if (blank($c->slug)) {
                $goc = Str::slug((string) $c->name) ?: 'combo-maldives';
                $slug = $goc;
                for ($i = 2; static::withTrashed()->where('slug', $slug)->whereKeyNot($c->getKey())->exists(); $i++) {
                    $slug = "{$goc}-{$i}";
                }
                $c->slug = $slug;
            }
        });
        // Đổi đường dẫn combo đang bán → link cũ tự chuyển sang link mới (301)
        static::updated(function (self $c) {
            if ($c->wasChanged('slug') && $c->getOriginal('status') === 'published') {
                DoiDuongDan::ghi('/maldives/'.$c->getOriginal('slug'), '/maldives/'.$c->slug);
            }
        });
    }

    public function resort(): BelongsTo
    {
        return $this->belongsTo(MaldivesResort::class, 'resort_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(MaldivesComboDay::class, 'combo_id')->orderBy('day_number')->orderBy('id');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(MaldivesComboDate::class, 'combo_id')->orderBy('start_date');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MaldivesBooking::class, 'combo_id');
    }

    /** Combo khách thấy trên website: đang bán và resort đang hiện */
    public function scopeDangBan(Builder $q): Builder
    {
        return $q->where('maldives_combos.status', 'published')
            ->whereHas('resort', fn ($r) => $r->where('status', 'published'));
    }

    /** Giá thấp nhất (USD / người lớn) của các ngày sắp tới còn nhận khách */
    public function giaTu(): ?int
    {
        $ds = $this->relationLoaded('dates') ? $this->dates : $this->dates()->get();
        $gia = $ds->filter(fn (MaldivesComboDate $d) => $d->status === 'open' && ! $d->start_date->lt(today()))
            ->flatMap(fn (MaldivesComboDate $d) => $d->cacGia());

        return $gia->isEmpty() ? null : (int) $gia->min();
    }

    /** Nơi khởi hành theo tên (Hà Nội, TP.HCM…) */
    public function noiKhoiHanh(?string $ten): ?array
    {
        foreach ($this->departure_cities ?? [] as $n) {
            if (filled($ten) && mb_strtolower(trim((string) ($n['city'] ?? ''))) === mb_strtolower(trim($ten))) {
                return $n;
            }
        }

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'slug', 'status'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('maldives');
    }
}
