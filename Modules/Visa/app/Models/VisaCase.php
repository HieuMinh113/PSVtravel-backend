<?php

namespace Modules\Visa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Một hồ sơ visa = một khách. Đi theo gia đình / đoàn thì mỗi người một hồ sơ,
 * ghi chung "nhóm" để lọc ra cả nhà (mỗi người có kết quả đậu / trượt riêng).
 */
class VisaCase extends Model
{
    use LogsActivity, SoftDeletes;

    public const MUC_DICH = [
        'du_lich' => 'Du lịch',
        'cong_tac' => 'Công tác',
        'tham_than' => 'Thăm thân',
        'du_hoc' => 'Du học',
        'khac' => 'Khác',
    ];

    public const DOI_TUONG = [
        'nhan_vien' => 'Nhân viên',
        'chu_doanh_nghiep' => 'Chủ doanh nghiệp',
        'tu_do' => 'Lao động tự do',
        'huu_tri' => 'Hưu trí',
        'hoc_sinh' => 'Học sinh / sinh viên',
        'tre_em' => 'Trẻ em',
        'khac' => 'Khác',
    ];

    public const TRANG_THAI = [
        'moi' => 'Mới nhận',
        'dang_gom' => 'Đang gom giấy tờ',
        'cho_nop' => 'Đủ giấy — chờ nộp',
        'da_nop' => 'Đã nộp — chờ kết quả',
        'dau' => 'Đậu',
        'truot' => 'Trượt',
        'huy' => 'Khách huỷ',
    ];

    public const MAU_TRANG_THAI = [
        'moi' => 'gray',
        'dang_gom' => 'warning',
        'cho_nop' => 'info',
        'da_nop' => 'primary',
        'dau' => 'success',
        'truot' => 'danger',
        'huy' => 'gray',
    ];

    /** Hồ sơ còn phải làm (chưa có kết quả, chưa huỷ). */
    public const DANG_XU_LY = ['moi', 'dang_gom', 'cho_nop', 'da_nop'];

    public const GIAY = [
        'thieu' => 'Chưa có',
        'da_nhan' => 'Đã nhận',
        'khong_can' => 'Không cần',
    ];

    protected $fillable = [
        'code', 'full_name', 'phone', 'email', 'birth_date', 'passport_no', 'passport_expiry', 'group_name',
        'country', 'purpose', 'profile', 'travel_date', 'visa_provider_id', 'visa_checklist_id',
        'checklist', 'files', 'file_names',
        'status', 'submitted_on', 'appointment_at', 'result_expected_on', 'result_on', 'visa_expiry',
        'fee', 'cost', 'paid', 'assigned_to', 'created_by', 'note',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'passport_expiry' => 'date',
        'travel_date' => 'date',
        'checklist' => 'array',
        'files' => 'array',
        'file_names' => 'array',
        'submitted_on' => 'date',
        'appointment_at' => 'datetime',
        'result_expected_on' => 'date',
        'result_on' => 'date',
        'visa_expiry' => 'date',
        'fee' => 'integer',
        'cost' => 'integer',
        'paid' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (VisaCase $hs) {
            $hs->created_by ??= auth()->id();
            $hs->assigned_to ??= auth()->id();
        });

        // Mã hồ sơ đọc qua điện thoại cho dễ: HSV-00012
        static::created(function (VisaCase $hs) {
            if (! $hs->code) {
                $hs->forceFill(['code' => 'HSV-'.str_pad((string) $hs->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });

        // Xoá hẳn hồ sơ (không phải xoá tạm) thì xoá luôn file scan giấy tờ
        // khách — không giữ hộ chiếu, CCCD của người ta khi không còn cần.
        static::forceDeleted(fn (VisaCase $hs) => Storage::disk('rieng')->delete($hs->files ?? []));
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VisaProvider::class, 'visa_provider_id');
    }

    public function mauChecklist(): BelongsTo
    {
        return $this->belongsTo(VisaChecklist::class, 'visa_checklist_id');
    }

    public function nguoiPhuTrach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0:int,1:int} [đã nhận, cần có] — bỏ qua giấy "không cần". */
    public function tienDoGiayTo(): array
    {
        $can = collect($this->checklist ?? [])->filter(fn ($g) => ($g['trang_thai'] ?? 'thieu') !== 'khong_can');

        return [$can->where('trang_thai', 'da_nhan')->count(), $can->count()];
    }

    /** Tên các giấy tờ khách còn phải nộp. */
    public function giayConThieu(): array
    {
        return collect($this->checklist ?? [])
            ->filter(fn ($g) => ($g['trang_thai'] ?? 'thieu') === 'thieu' && filled($g['ten'] ?? null))
            ->map(fn ($g) => trim($g['ten'].(filled($g['ghi_chu'] ?? null) ? ' ('.$g['ghi_chu'].')' : '')))
            ->values()->all();
    }

    /** Đoạn tin nhắn gửi khách qua Zalo: những giấy tờ còn thiếu. */
    public function tinNhanGiayThieu(): string
    {
        $thieu = $this->giayConThieu();
        if (! $thieu) {
            return "Chào anh/chị {$this->full_name}, hồ sơ visa {$this->country} của anh/chị đã đủ giấy tờ. PSV Travel sẽ báo lịch nộp sớm ạ.";
        }

        $dong = collect($thieu)->map(fn ($t, $i) => ($i + 1).'. '.$t)->implode("\n");

        return "Chào anh/chị {$this->full_name}, hồ sơ visa {$this->country} ({$this->code}) còn thiếu các giấy tờ sau:\n"
            .$dong."\nAnh/chị gửi bổ sung giúp PSV Travel nhé. Cảm ơn anh/chị!";
    }

    public function conPhaiThu(): int
    {
        return max(0, $this->fee - $this->paid);
    }

    /** Hộ chiếu phải còn hạn ít nhất 6 tháng tính từ ngày đi. */
    public function hoChieuSapHetHan(): bool
    {
        if (! $this->passport_expiry) {
            return false;
        }

        return $this->passport_expiry->lt(($this->travel_date ?? now())->copy()->addMonths(6));
    }

    /** Chép danh sách giấy tờ của mẫu thành checklist hồ sơ (tất cả "chưa có"). */
    public static function chepMau(?VisaChecklist $mau): array
    {
        return collect($mau?->items ?? [])
            ->filter(fn ($g) => filled($g['ten'] ?? null))
            ->map(fn ($g) => [
                'nhom' => $g['nhom'] ?? null,
                'ten' => $g['ten'],
                'ghi_chu' => $g['ghi_chu'] ?? null,
                'trang_thai' => 'thieu',
            ])->values()->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Không ghi số hộ chiếu / ngày sinh vào nhật ký — chỉ những gì cần để
        // biết ai đổi tiến độ, tiền.
        return LogOptions::defaults()
            ->logOnly(['code', 'country', 'status', 'appointment_at', 'result_on', 'fee', 'cost', 'paid', 'assigned_to'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('visa_case');
    }
}
