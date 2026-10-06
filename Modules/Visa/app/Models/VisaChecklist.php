<?php

namespace Modules\Visa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mẫu checklist giấy tờ: nước + mục đích + đối tượng → danh sách giấy tờ.
 * Hồ sơ chọn mẫu thì chép danh sách về rồi sửa riêng cho case đó; sửa mẫu
 * về sau không làm đổi các hồ sơ đã tạo.
 */
class VisaChecklist extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'country', 'purpose', 'profile', 'items', 'note',
        'attachments', 'attachment_names', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'items' => 'array',
        'attachments' => 'array',
        'attachment_names' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeDangDung($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Mẫu khớp nhất với nước / mục đích / đối tượng của hồ sơ. Nước phải
     * trùng; mục đích, đối tượng trùng thì cộng điểm, mẫu ghi "chung" (để
     * trống) vẫn dùng được nhưng xếp sau.
     */
    public static function timMau(?string $nuoc, ?string $mucDich, ?string $doiTuong): ?self
    {
        if (blank($nuoc)) {
            return null;
        }
        $chuan = fn (?string $s) => mb_strtolower(trim((string) $s));

        return static::dangDung()->get()
            ->filter(fn (self $m) => $chuan($m->country) === $chuan($nuoc))
            ->map(function (self $m) use ($mucDich, $doiTuong) {
                $diem = 0;
                foreach (['purpose' => $mucDich, 'profile' => $doiTuong] as $cot => $giaTri) {
                    if (blank($m->{$cot})) {
                        $diem += 1;
                    } elseif ($m->{$cot} === $giaTri) {
                        $diem += 3;
                    } else {
                        return null; // ghi rõ mục đích / đối tượng khác → không dùng
                    }
                }

                return [$diem, $m];
            })
            ->filter()
            ->sortByDesc(fn ($x) => $x[0])
            ->first()[1] ?? null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'country', 'purpose', 'profile', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('visa_checklist');
    }
}
