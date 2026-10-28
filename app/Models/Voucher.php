<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mã voucher, dùng MỘT lần. Trạng thái khách thấy:
 *  - con_dung   còn dùng được
 *  - da_dung    đã dùng (ngày nào)
 *  - het_han    quá hạn của đợt
 *  - tam_ngung  đợt đang tạm ngưng
 *  - da_huy     mã đã huỷ (mất, hỏng, phát nhầm)
 */
class Voucher extends Model
{
    /** Bỏ chữ dễ nhầm: 0 / O, 1 / I / L */
    private const BANG_CHU = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const TRANG_THAI = [
        'con_dung' => 'Còn dùng được',
        'da_dung' => 'Đã dùng',
        'het_han' => 'Hết hạn',
        'tam_ngung' => 'Đợt tạm ngưng',
        'da_huy' => 'Đã huỷ',
    ];

    protected $fillable = ['batch_id', 'serial', 'code', 'lookup_key', 'status', 'issued_to', 'used_at', 'used_by', 'used_note', 'cancelled_at', 'cancel_reason'];

    protected $casts = [
        'serial' => 'integer',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class, 'batch_id')->withTrashed();
    }

    public function nguoiQuet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /** Mã mới: PSV-7KQ3-MZ8T (8 ký tự ngẫu nhiên ≈ 850 tỉ khả năng — không đoán được) */
    public static function maMoi(string $tienTo): string
    {
        $c = '';
        for ($i = 0; $i < 8; $i++) {
            $c .= self::BANG_CHU[random_int(0, strlen(self::BANG_CHU) - 1)];
        }

        return $tienTo.'-'.substr($c, 0, 4).'-'.substr($c, 4);
    }

    /**
     * Khoá tra cứu từ chữ khách gõ / nội dung QR quét được: link
     * "…/voucher/PSV-7KQ3-MZ8T", " psv 7kq3 mz8t " → "PSV7KQ3MZ8T".
     */
    public static function khoa(string $nhap): string
    {
        $nhap = trim($nhap);
        if (preg_match('~/voucher/([^/?#\s]+)~i', $nhap, $k)) {
            $nhap = rawurldecode($k[1]);
        }

        return substr(strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $nhap)), 0, 24);
    }

    public static function tim(string $nhap): ?self
    {
        $khoa = self::khoa($nhap);

        return strlen($khoa) >= 6 ? self::query()->with('batch')->where('lookup_key', $khoa)->first() : null;
    }

    public function trangThai(): string
    {
        return match (true) {
            $this->status === 'cancelled' => 'da_huy',
            $this->status === 'used' => 'da_dung',
            ! $this->batch || $this->batch->trashed() => 'da_huy',
            $this->batch->hetHan() => 'het_han',
            $this->batch->status !== 'active' => 'tam_ngung',
            default => 'con_dung',
        };
    }

    /**
     * Đánh dấu đã dùng. Chỉ mã còn dùng được; hai người quét cùng lúc thì chỉ
     * một người thành công (cập nhật có điều kiện status = unused).
     */
    public function danhDauDaDung(User $nhanVien, ?string $ghiChu = null): bool
    {
        if ($this->trangThai() !== 'con_dung') {
            return false;
        }
        $xong = self::query()->whereKey($this->id)->where('status', 'unused')->update([
            'status' => 'used', 'used_at' => now(), 'used_by' => $nhanVien->id,
            'used_note' => $ghiChu !== null && trim($ghiChu) !== '' ? mb_substr(trim($ghiChu), 0, 500) : null,
            'updated_at' => now(),
        ]) === 1;
        if ($xong) {
            $this->refresh();
            activity('voucher')->performedOn($this)->causedBy($nhanVien)->event('da_dung')
                ->withProperties(['ma' => $this->code, 'ghi_chu' => $this->used_note])->log('Đánh dấu voucher đã dùng');
        }

        return $xong;
    }

    /** Quét nhầm: trả mã về chưa dùng */
    public function boDanhDau(User $nhanVien): void
    {
        $cu = $this->only(['used_at', 'used_by', 'used_note']);
        $this->update(['status' => 'unused', 'used_at' => null, 'used_by' => null, 'used_note' => null]);
        activity('voucher')->performedOn($this)->causedBy($nhanVien)->event('bo_da_dung')
            ->withProperties(['ma' => $this->code] + $cu)->log('Bỏ đánh dấu đã dùng (quét nhầm)');
    }

    public function huy(User $nhanVien, ?string $lyDo = null): void
    {
        $this->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $lyDo ? mb_substr($lyDo, 0, 250) : null]);
        activity('voucher')->performedOn($this)->causedBy($nhanVien)->event('huy')
            ->withProperties(['ma' => $this->code, 'ly_do' => $lyDo])->log('Huỷ mã voucher');
    }
}
