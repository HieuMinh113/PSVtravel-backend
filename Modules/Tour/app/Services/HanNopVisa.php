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
 * "Nộp trước ngày bay N ngày" ở mọi chỗ cùng một nghĩa: bay 22, N = 7 → từ ngày
 * 15 (22 − 7) đã ẩn, hạn chót là ngày 14 (= ngày bay − N − 1).
 *
 * Lấy hạn SỚM NHẤT trong các nguồn, để không bao giờ nhận khách sau hạn của
 * bất kỳ bên nào:
 *  - Số ngày của tour = số lớn hơn trong: danh sách theo nước + dạng visa (nhân
 *    viên visa đặt, "Trung Quốc — visa đoàn 7 ngày", chỉ tour nước ngoài) và số
 *    riêng của tour (đối tác yêu cầu nộp sớm hơn). Tour không chép số của danh
 *    sách: danh sách đổi thì mọi tour tự theo.
 *  - Hạn riêng của đợt (đối tác chốt ngày cụ thể).
 *
 * Hạn chót được lưu sẵn vào tour_departures.visa_last_day để lọc bằng SQL (danh
 * sách tour, đếm theo danh mục) — tính lại khi đổi đợt, đổi tour, đổi danh sách.
 */
class HanNopVisa
{
    /** Khoá bộ nhớ tạm (kho "array": sống theo request; tiến trình chạy lâu nạp lại sau 1 phút). */
    private const KHOA = 'han_nop_visa_theo_nuoc';

    /**
     * Số ngày nộp visa theo danh sách cho tour: ô "Quốc gia" + dạng visa của tour.
     * Nhiều nước thì lấy số lớn nhất.
     */
    public static function soNgayTheoNuoc(?string $quocGia, ?string $dang = null): ?int
    {
        $khop = self::nuocKhop($quocGia, $dang);

        return $khop ? $khop[0]['ngay'] : null;
    }

    /**
     * Dòng hạn nộp áp cho từng nước trong ô "Quốc gia" của tour ("Pháp - Thụy Sĩ - Ý"
     * → từng nước), không phân biệt dấu / chữ hoa, bỏ phần trong ngoặc.
     *
     * Mỗi nước: tour đã chọn dạng visa và nước có dòng đúng dạng đó → lấy dòng đó;
     * tour chưa chọn dạng, hoặc nước chưa có dòng dạng đó → lấy dòng số ngày lớn
     * nhất của nước (thà ẩn sớm còn hơn nhận khách trễ hạn).
     *
     * thieu_dang = tour đã chọn dạng visa mà nước chưa có dòng dạng đó.
     *
     * @return list<array{nuoc: string, dang: string, ngay: int, thieu_dang: bool}> số ngày giảm dần
     */
    public static function nuocKhop(?string $quocGia, ?string $dang = null): array
    {
        if (! filled($quocGia)) {
            return [];
        }
        $danhSach = Cache::store('array')->remember(self::KHOA, 60, fn () => VisaDeadline::query()->get(['country', 'kind', 'days'])
            ->groupBy(fn (VisaDeadline $v) => self::chuan($v->country))
            ->map(fn ($nhom) => $nhom->sortByDesc('days')
                ->mapWithKeys(fn (VisaDeadline $v) => [$v->kind => ['nuoc' => $v->country, 'dang' => $v->kind, 'ngay' => $v->days]])
                ->all())
            ->all());

        $kq = [];
        foreach (self::phanNuoc($quocGia) as $p) {
            if (! isset($danhSach[$p]) || isset($kq[$p])) {
                continue;
            }
            $dung = filled($dang) ? ($danhSach[$p][$dang] ?? null) : null;
            $kq[$p] = $dung
                ? [...$dung, 'thieu_dang' => false]
                : [...reset($danhSach[$p]), 'thieu_dang' => filled($dang)];
        }
        usort($kq, fn ($a, $b) => $b['ngay'] <=> $a['ngay']);

        return $kq;
    }

    /** "Trung Quốc · Visa đoàn" */
    public static function tenDong(array $dong): string
    {
        return $dong['nuoc'].' · '.VisaDeadline::tenDang($dong['dang']);
    }

    /**
     * Tour nước ngoài có ô "Quốc gia" khớp một nước, kèm dạng visa của tour (để
     * nhân viên visa kiểm tra tên nước đã ghi đúng chưa).
     *
     * @return list<string> "Tour A (Visa đoàn)"
     */
    public static function tourTheoNuoc(string $nuoc): array
    {
        $khoa = self::chuan($nuoc);
        if ($khoa === '') {
            return [];
        }

        return Tour::query()->where('type', 'abroad')->whereNotNull('country')->orderBy('name')->get(['name', 'country', 'visa_kind'])
            ->filter(fn (Tour $t) => in_array($khoa, self::phanNuoc($t->country), true))
            ->map(fn (Tour $t) => $t->name.' ('.(VisaDeadline::tenDang($t->visa_kind) ?? 'chưa chọn dạng visa').')')
            ->values()->all();
    }

    /** Dòng đã có cùng nước (bỏ dấu / chữ hoa: "trung quoc" = "Trung Quốc") và cùng dạng visa. */
    public static function trungTen(string $nuoc, ?string $dang, ?int $truId = null): ?string
    {
        $khoa = self::chuan($nuoc);

        return VisaDeadline::query()->where('kind', $dang ?: 'doan')->when($truId, fn ($q) => $q->whereKeyNot($truId))->pluck('country')
            ->first(fn (string $c) => self::chuan($c) === $khoa);
    }

    /**
     * Số ngày nộp hồ sơ visa đang áp cho tour (trước ngày bay N ngày): số lớn hơn
     * trong danh sách theo nước + dạng visa và số riêng của tour. Null = không có.
     */
    public static function soNgayNop(?Tour $tour): ?int
    {
        $n = max((int) $tour?->visa_deadline_days, (int) self::soNgayDanhSach($tour));

        return $n > 0 ? $n : null;
    }

    /** Số ngày theo danh sách "Hạn nộp visa theo nước" cho tour (chỉ tour nước ngoài). */
    public static function soNgayDanhSach(?Tour $tour): ?int
    {
        return $tour?->type === 'abroad' ? self::soNgayTheoNuoc($tour->country, $tour->visa_kind) : null;
    }

    /** Số ngày đang áp lấy từ đâu: "theo danh sách: Trung Quốc · Visa đoàn" / "riêng của tour". */
    public static function nguonSoNgay(?Tour $tour): ?string
    {
        $khop = $tour?->type === 'abroad' ? self::nuocKhop($tour->country, $tour->visa_kind) : [];
        $rieng = (int) $tour?->visa_deadline_days;

        return match (true) {
            $khop && $khop[0]['ngay'] >= $rieng => 'theo danh sách: '.self::tenDong($khop[0]),
            $rieng > 0 => 'riêng của tour',
            default => null,
        };
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
        if ($n = self::soNgayNop($tour)) {
            $han[] = $dot->start_date->copy()->startOfDay()->subDays($n + 1);
        }

        return $han ? min($han) : null;
    }

    /** Hạn chót đến từ đâu: "hạn riêng" / "theo tour: trước N ngày" / "theo nước: Trung Quốc · Visa đoàn 7 ngày". */
    public static function nguonHan(TourDeparture $dot, ?Tour $tour): ?string
    {
        $han = self::hanChot($dot, $tour);
        if (! $han || ! $dot->start_date) {
            return null;
        }
        if ($dot->visa_deadline && $dot->visa_deadline->isSameDay($han)) {
            return 'Hạn riêng';
        }
        $khop = $tour?->type === 'abroad' ? self::nuocKhop($tour->country, $tour->visa_kind) : [];
        if ($khop && $khop[0]['ngay'] >= (int) $tour->visa_deadline_days) {
            return 'Theo nước: '.self::tenDong($khop[0]).' '.$khop[0]['ngay'].' ngày';
        }

        return (int) $tour?->visa_deadline_days > 0 ? 'Theo tour: trước '.$tour->visa_deadline_days.' ngày' : null;
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
