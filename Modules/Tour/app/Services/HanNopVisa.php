<?php

namespace Modules\Tour\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaDeadline;

/**
 * Hạn chót nộp hồ sơ visa của một đợt khởi hành — qua hạn thì đợt tự ẩn trên
 * website (khách không thấy, không đặt được; admin vẫn thấy).
 *
 * Lấy hạn SỚM NHẤT trong ba nguồn, để không bao giờ nhận khách sau hạn của
 * bất kỳ bên nào:
 *  - Hạn theo nước (nhân viên visa đặt, "Trung Quốc 7 ngày"): bay 22 → từ ngày
 *    15 đã ẩn, hạn chót là ngày 14 (= ngày bay − N − 1). Chỉ áp cho tour nước ngoài.
 *  - Quy tắc của tour "nộp trước ngày khởi hành N ngày": hạn chót = ngày bay − N.
 *  - Hạn riêng của đợt (đối tác chốt ngày cụ thể).
 *
 * Hạn chót được lưu sẵn vào tour_departures.visa_last_day để lọc bằng SQL (danh
 * sách tour, đếm theo danh mục) — tính lại khi đổi đợt, đổi tour, đổi danh sách.
 */
class HanNopVisa
{
    /** Khoá bộ nhớ tạm (kho "array": sống theo request; tiến trình chạy lâu nạp lại sau 1 phút). */
    private const KHOA = 'han_nop_visa_theo_nuoc';

    /** Số ngày nộp visa theo danh sách cho ô "Quốc gia" của tour; nhiều nước thì lấy số lớn nhất. */
    public static function soNgayTheoNuoc(?string $quocGia): ?int
    {
        $khop = self::nuocKhop($quocGia);

        return $khop ? max($khop) : null;
    }

    /**
     * Các nước trong danh sách khớp ô "Quốc gia" của tour ("Pháp - Thụy Sĩ - Ý" →
     * từng nước), không phân biệt dấu / chữ hoa, bỏ phần trong ngoặc.
     *
     * @return array<string, int> tên nước trong danh sách => số ngày
     */
    public static function nuocKhop(?string $quocGia): array
    {
        if (! filled($quocGia)) {
            return [];
        }
        $danhSach = Cache::store('array')->remember(self::KHOA, 60, fn () => VisaDeadline::query()->get(['country', 'days'])
            ->mapWithKeys(fn (VisaDeadline $v) => [self::chuan($v->country) => ['ten' => $v->country, 'ngay' => $v->days]])
            ->all());

        $kq = [];
        $phan = self::phanNuoc($quocGia);
        foreach ($phan as $p) {
            if ($p !== '' && isset($danhSach[$p])) {
                $kq[$danhSach[$p]['ten']] = $danhSach[$p]['ngay'];
            }
        }

        return $kq;
    }

    /**
     * Tên các tour nước ngoài có ô "Quốc gia" khớp một nước (để nhân viên visa
     * kiểm tra tên nước đã ghi đúng chưa).
     *
     * @return list<string>
     */
    public static function tourTheoNuoc(string $nuoc): array
    {
        $khoa = self::chuan($nuoc);
        if ($khoa === '') {
            return [];
        }

        return Tour::query()->where('type', 'abroad')->whereNotNull('country')->orderBy('name')->get(['name', 'country'])
            ->filter(fn (Tour $t) => in_array($khoa, self::phanNuoc($t->country), true))
            ->pluck('name')->values()->all();
    }

    /** Tên nước đã có trong danh sách trùng với $nuoc sau khi bỏ dấu / chữ hoa ("trung quoc" = "Trung Quốc"). */
    public static function trungTen(string $nuoc, ?int $truId = null): ?string
    {
        $khoa = self::chuan($nuoc);

        return VisaDeadline::query()->when($truId, fn ($q) => $q->whereKeyNot($truId))->pluck('country')
            ->first(fn (string $c) => self::chuan($c) === $khoa);
    }

    /** Hạn chót nộp hồ sơ (ngày cuối còn hiện trên web) của một đợt; null = không có hạn. */
    public static function hanChot(TourDeparture $dot, ?Tour $tour): ?Carbon
    {
        if (! $dot->start_date) {
            return $dot->visa_deadline;
        }
        $han = [];
        if ($dot->visa_deadline) {
            $han[] = $dot->visa_deadline->copy()->startOfDay();
        }
        if ((int) $tour?->visa_deadline_days > 0) {
            $han[] = $dot->start_date->copy()->startOfDay()->subDays((int) $tour->visa_deadline_days);
        }
        if ($tour?->type === 'abroad' && ($n = self::soNgayTheoNuoc($tour->country))) {
            $han[] = $dot->start_date->copy()->startOfDay()->subDays($n + 1);
        }

        return $han ? min($han) : null;
    }

    /** Hạn chót đến từ đâu: "hạn riêng" / "theo tour: trước N ngày" / "theo nước: Trung Quốc 7 ngày". */
    public static function nguonHan(TourDeparture $dot, ?Tour $tour): ?string
    {
        $han = self::hanChot($dot, $tour);
        if (! $han || ! $dot->start_date) {
            return null;
        }
        if ($dot->visa_deadline && $dot->visa_deadline->isSameDay($han)) {
            return 'Hạn riêng';
        }
        if ((int) $tour?->visa_deadline_days > 0 && $dot->start_date->copy()->subDays((int) $tour->visa_deadline_days)->isSameDay($han)) {
            return 'Theo tour: trước '.$tour->visa_deadline_days.' ngày';
        }
        $khop = self::nuocKhop($tour?->country);
        arsort($khop);

        return $khop ? 'Theo nước: '.array_key_first($khop).' '.reset($khop).' ngày' : null;
    }

    /** Tính lại hạn chót các đợt của một tour (đổi quốc gia / quy tắc visa của tour). */
    public static function tinhLaiTour(Tour $tour): void
    {
        foreach (TourDeparture::where('tour_id', $tour->id)->get() as $dot) {
            $han = self::hanChot($dot, $tour)?->toDateString();
            if ($han !== $dot->visa_last_day?->toDateString()) {
                // cập nhật thẳng: không ghi nhật ký hoạt động cho số liệu tự tính
                TourDeparture::whereKey($dot->id)->update(['visa_last_day' => $han]);
            }
        }
    }

    /** Danh sách hạn theo nước vừa đổi → tính lại mọi tour (cả tour trong nước, phòng khi đổi loại tour). */
    public static function tinhLaiTatCa(): void
    {
        self::quenDanhSach();
        Tour::withTrashed()->whereHas('departures')->chunkById(200, function ($tours) {
            foreach ($tours as $tour) {
                self::tinhLaiTour($tour);
            }
        });
    }

    /** Xoá bộ nhớ tạm danh sách (vừa thêm / sửa / xoá trong cùng request). */
    public static function quenDanhSach(): void
    {
        Cache::store('array')->forget(self::KHOA);
    }

    /** "Pháp - Thụy Sĩ - Ý" → ["PHAP THUY SI Y", "PHAP", "THUY SI", "Y"] (đã chuẩn hoá) */
    private static function phanNuoc(string $quocGia): array
    {
        return array_values(array_unique(array_filter([self::chuan($quocGia), ...array_map(
            fn ($p) => self::chuan($p),
            preg_split('/\s*(?:[-–—,;\/&+|]|\bvà\b)\s*/iu', $quocGia),
        )], fn ($p) => $p !== '')));
    }

    private static function chuan(string $s): string
    {
        $s = preg_replace('/\([^)]*\)/u', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]/', ' ', strtoupper(Str::ascii(str_replace(['đ', 'Đ'], 'D', $s))))));
    }
}
