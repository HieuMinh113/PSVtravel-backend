<?php

namespace Modules\Visa\Models;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
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

    public const NGUON = [
        'quan_tri' => 'Nhân viên tạo',
        'website' => 'Khách nộp trên web',
    ];

    protected $fillable = [
        'code', 'full_name', 'phone', 'email', 'birth_date', 'passport_no', 'passport_expiry', 'group_name',
        'country', 'purpose', 'profile', 'travel_date', 'visa_provider_id', 'visa_checklist_id',
        'checklist', 'thong_tin', 'files', 'file_names',
        'status', 'submitted_on', 'appointment_at', 'result_expected_on', 'result_on', 'visa_expiry',
        'fee', 'cost', 'paid', 'assigned_to', 'created_by', 'note',
    ];

    protected $hidden = ['upload_token_hash'];

    protected $casts = [
        'upload_token_expires_at' => 'datetime',
        'web_files_count' => 'integer',
        'birth_date' => 'date',
        'passport_expiry' => 'date',
        'travel_date' => 'date',
        'checklist' => 'array',
        'thong_tin' => 'array',
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
        // Hồ sơ nhân viên tạo thì giao luôn cho người tạo. Hồ sơ khách nộp trên
        // web để trống — nằm ở mục "Chưa ai nhận" chờ nhân viên bấm nhận.
        static::creating(function (VisaCase $hs) {
            if ($hs->source === 'website') {
                return;
            }
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
        static::forceDeleted(fn (VisaCase $hs) => Storage::disk('rieng')->delete($hs->tatCaFile()));

        // Dòng giấy tờ đã có file mà vẫn ghi "Chưa có" → tự chuyển "Đã nhận"
        static::saving(function (VisaCase $hs) {
            if (! is_array($hs->checklist)) {
                return;
            }
            $hs->checklist = array_values(array_map(function ($g) {
                if (! empty($g['tep']) && ($g['trang_thai'] ?? 'thieu') === 'thieu') {
                    $g['trang_thai'] = 'da_nhan';
                }

                return $g;
            }, $hs->checklist));
        });
    }

    /**
     * Hồ sơ người này được thấy: có quyền xem mọi hồ sơ (admin) thì thấy hết;
     * nhân viên visa chỉ thấy hồ sơ mình phụ trách + hồ sơ chưa ai nhận.
     */
    public function scopeNhinThayBoi(Builder $query, ?Authenticatable $u): Builder
    {
        if ($u?->can('ViewAll:VisaCase')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('assigned_to', $u?->getAuthIdentifier())->orWhereNull('assigned_to'));
    }

    public function chuaAiNhan(): bool
    {
        return $this->assigned_to === null;
    }

    /**
     * Nhân viên bấm "Nhận hồ sơ". Chỉ cập nhật khi hồ sơ VẪN chưa ai nhận —
     * hai người bấm cùng lúc thì chỉ người đầu được, người sau nhận false.
     */
    public function nhanBoi(Authenticatable $u): bool
    {
        $duoc = static::whereKey($this->getKey())->whereNull('assigned_to')
            ->update(['assigned_to' => $u->getAuthIdentifier(), 'updated_at' => now()]) === 1;

        if ($duoc) {
            $this->refresh();
            activity('visa_case')->performedOn($this)->causedBy($u)->log('Nhận hồ sơ');
        }

        return $duoc;
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    /** Mọi file của hồ sơ: file gắn theo từng giấy tờ + file "giấy tờ khác". */
    public function tatCaFile(): array
    {
        $theoGiay = collect($this->checklist ?? [])->flatMap(fn ($g) => (array) ($g['tep'] ?? []));

        return $theoGiay->merge($this->files ?? [])->filter()->unique()->values()->all();
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
