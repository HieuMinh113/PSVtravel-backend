<?php

namespace Modules\Alliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Một sheet chỗ trống của đối tác liên minh (điều hành nhập link). */
class AllianceSource extends Model
{
    use LogsActivity;

    public const MAU_DO = [
        'tat' => 'Không — màu đỏ chỉ để đánh dấu (lễ, Tết, khuyến mãi)',
        'theo_chu_thich' => 'Có, ở tab có ghi chú “đỏ hết chỗ”',
        'tat_ca' => 'Có, ở mọi tab',
    ];

    /** Vai trò cột khai tay được (tab không có dòng tiêu đề). */
    public const VAI_TRO_COT = [
        'ngay_di' => 'Ngày đi',
        'thang' => 'Tháng (nếu tách riêng)',
        'ten' => 'Tên tour',
        'gia' => 'Giá',
        'hoa_hong' => 'COM',
        'con_cho' => 'Còn chỗ',
        'tong_cho' => 'Tổng chỗ',
        'thoi_gian' => 'Thời gian',
        'hang_bay' => 'Hãng bay',
        'ghi_chu' => 'Ghi chú',
    ];

    protected $fillable = ['name', 'sheet_url', 'contact', 'note', 'is_active', 'mau_do', 'tab_bo_qua', 'cot_tay'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_success_at' => 'datetime',
        'sheet_updated_on' => 'date',
        'warnings' => 'array',
        'tab_bo_qua' => 'array',
        'cot_tay' => 'array',
        'bao_cao' => 'array',
        'departures_count' => 'integer',
    ];

    /**
     * Sheet đối tác đã biết (config alliance.cau_hinh_san) → điền sẵn tuỳ chọn
     * đọc khi thêm / đổi link, nếu điều hành chưa tự chỉnh.
     */
    protected static function booted(): void
    {
        static::saving(function (AllianceSource $nguon) {
            if (! $nguon->isDirty('sheet_url')) {
                return;
            }
            $ma = \Modules\Alliance\Services\TaiSheetLienMinh::maFile((string) $nguon->sheet_url);
            $san = $ma ? config("alliance.cau_hinh_san.{$ma}") : null;
            if (! $san) {
                return;
            }
            if (($nguon->mau_do ?? 'tat') === 'tat' && isset($san['mau_do'])) {
                $nguon->mau_do = $san['mau_do'];
            }
            if (empty($nguon->cot_tay) && isset($san['cot_tay'])) {
                $nguon->cot_tay = $san['cot_tay'];
            }
        });
    }

    /** Tuỳ chọn truyền cho bộ đọc sheet. */
    public function tuyChonDoc(): array
    {
        $cotTay = [];
        foreach ($this->cot_tay ?? [] as $dong) {
            if (! empty($dong['tab'])) {
                $cotTay[$dong['tab']] = array_filter(
                    array_intersect_key($dong, self::VAI_TRO_COT),
                    fn ($v) => is_string($v) && trim($v) !== '',
                );
            }
        }

        return [
            'mau_do' => $this->mau_do ?: 'tat',
            'tab_bo_qua' => array_values($this->tab_bo_qua ?? []),
            'cot_tay' => $cotTay,
        ];
    }

    public function tours(): HasMany
    {
        return $this->hasMany(AllianceTour::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(AllianceDeparture::class);
    }

    /** Lần đọc gần nhất có lỗi (link hỏng, sheet bị khoá...). */
    public function dangLoi(): bool
    {
        return $this->last_error !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sheet_url', 'contact', 'is_active', 'mau_do', 'tab_bo_qua', 'cot_tay'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('alliance_source');
    }
}
