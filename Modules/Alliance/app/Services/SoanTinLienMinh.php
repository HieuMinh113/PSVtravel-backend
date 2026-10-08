<?php

namespace Modules\Alliance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Alliance\Models\AllianceDeparture;
use Modules\Alliance\Models\AllianceTour;

/**
 * Soạn tin nhắn tình trạng chỗ của tour liên minh để gửi sale / khách qua
 * Zalo, theo kiểu điều hành vẫn gõ tay:
 *
 *   THƯỢNG HẢI - Ô TRẤN - HÀNG CHÂU UPDATE 8/10/2026
 *   5N5Đ • Sun PhuQuoc Airways (9G)
 *   9G634 SGN-PVG 23:40-04:40 / 9G635 PVG-SGN 05:40-09:40
 *
 *   Tháng 10:
 *   🌳 17/10 - 21/10: NHẬN 0S HOLD 6S SURE 22S
 *   ĐỒNG GIÁ 13.990
 *
 * KHÔNG BAO GIỜ ghi tên đối tác (kể cả khi tên lọt vào tên tour / ghi chú)
 * và không ghi hoa hồng. Bỏ ngày đã qua và đoàn đã huỷ.
 */
class SoanTinLienMinh
{
    public const DAU_DONG = '🌳';

    /** Tin cho một tour: mọi ngày đi sắp tới (không tính đoàn huỷ). */
    public static function tour(AllianceTour $tour): string
    {
        $dsDot = $tour->departures()
            ->where(fn ($q) => $q->whereDate('departure_date', '>=', today())->orWhereNull('departure_date'))
            ->where('status', '!=', 'huy')
            ->get();

        return self::soan($tour, $dsDot);
    }

    /** Tin cho các ngày đi đã chọn trong bảng — mỗi tour một đoạn. */
    public static function nhieu(Collection $dsDot): string
    {
        return $dsDot->loadMissing('tour.source')
            ->filter(fn (AllianceDeparture $d) => $d->tour && $d->status !== 'huy')
            ->sortBy(fn (AllianceDeparture $d) => $d->departure_date?->timestamp ?? PHP_INT_MAX)
            ->groupBy('alliance_tour_id')
            ->map(fn (Collection $g) => self::soan($g->first()->tour, $g))
            ->implode("\n\n\n");
    }

    /** @param  Collection<int, AllianceDeparture>  $dsDot */
    public static function soan(AllianceTour $tour, Collection $dsDot): string
    {
        $ngayCapNhat = $tour->source?->last_success_at ?? now();
        $dong = [mb_strtoupper(trim($tour->name)).' UPDATE '.$ngayCapNhat->format('j/n/Y')];

        [$hang, $chuyenBay] = self::hangBay($tour->airline);
        $dong2 = collect([$tour->duration, $hang])->filter()->implode(' • ');
        $dong2 !== '' && $dong[] = $dong2;
        $chuyenBay && $dong[] = $chuyenBay;

        $soNgay = self::soNgay($tour->duration);
        $coNgay = $dsDot->filter(fn ($d) => $d->departure_date)->sortBy(fn ($d) => $d->departure_date->timestamp)->values();
        $hangTuan = $dsDot->reject(fn ($d) => $d->departure_date)->values();

        if ($coNgay->isEmpty() && $hangTuan->isEmpty()) {
            $dong[] = '';
            $dong[] = 'Hiện chưa có ngày khởi hành.';
        }

        $namDau = $coNgay->first()?->departure_date->year;
        foreach ($coNgay->groupBy(fn ($d) => $d->departure_date->format('Y-m')) as $thang => $trongThang) {
            $t = Carbon::createFromFormat('!Y-m', $thang);
            $dong[] = '';
            $dong[] = 'Tháng '.$t->month.($t->year !== $namDau ? '/'.$t->year : '').':';
            array_push($dong, ...self::khoi($trongThang, function (AllianceDeparture $d) use ($soNgay) {
                $ve = $soNgay ? $d->departure_date->copy()->addDays($soNgay - 1)->format('d/m') : null;

                return $d->departure_date->format('d/m').($ve ? ' - '.$ve : '');
            }));
        }

        if ($hangTuan->isNotEmpty()) {
            $dong[] = '';
            $dong[] = 'Khởi hành hằng tuần:';
            array_push($dong, ...self::khoi($hangTuan, fn (AllianceDeparture $d) => (string) $d->weekly));
        }

        // Hạn nộp visa theo từng đoàn, gom theo tháng
        $coHanVisa = $coNgay->filter(fn ($d) => filled($d->visa_deadline));
        foreach ($coHanVisa->groupBy(fn ($d) => $d->departure_date->format('Y-m')) as $thang => $g) {
            $t = Carbon::createFromFormat('!Y-m', $thang);
            $dong[] = '';
            $dong[] = 'DEADLINE VISA THÁNG '.$t->month.($t->year !== $namDau ? '/'.$t->year : '');
            foreach ($g as $d) {
                $dong[] = self::DAU_DONG.' Đoàn '.$d->departure_date->format('d/m').': deadline visa '.trim($d->visa_deadline);
            }
        }

        return self::boTenDoiTac(implode("\n", $dong), $tour->source?->name);
    }

    /**
     * Các dòng ngày đi của một tháng. Mỗi mức giá nằm liền một khúc → dòng
     * "ĐỒNG GIÁ" sau khúc đó (như điều hành vẫn ghi). Giá xen kẽ nhau (39.900,
     * 40.900, 39.900…) thì ghi giá ngay cuối từng dòng cho khỏi rối.
     *
     * @return list<string>
     */
    private static function khoi(Collection $ds, callable $nhanNgay): array
    {
        $khuc = self::theoGia($ds);
        $soMucGia = collect($khuc)->map(fn ($k) => self::gia($k))->unique()->count();
        $xenKe = count($khuc) > $soMucGia;

        $dong = [];
        foreach ($khuc as $k) {
            foreach ($k as $d) {
                $dong[] = self::DAU_DONG.' '.$nhanNgay($d).': '.self::cho($d)
                    .($xenKe && ($g = self::gia(collect([$d]))) ? ' — GIÁ '.$g : '');
            }
            if (! $xenKe && ($g = self::gia($k))) {
                $dong[] = ($k->count() > 1 ? 'ĐỒNG GIÁ ' : 'GIÁ ').$g;
            }
        }

        return $dong;
    }

    /** "NHẬN 4S HOLD 9S SURE 11S (NOEL)" — sheet không ghi số thì ghi tình trạng. */
    public static function cho(AllianceDeparture $d): string
    {
        $phan = [];
        if ($d->seats_left !== null && $d->status !== 'tam_ngung') {
            $phan[] = 'NHẬN '.max(0, $d->seats_left).'S';
        }
        if ((int) $d->seats_hold > 0) {
            $phan[] = 'HOLD '.$d->seats_hold.'S';
        }
        if ((int) $d->seats_sold > 0) {
            $phan[] = 'SURE '.$d->seats_sold.'S';
        }
        $trangThai = match ($d->status) {
            'het_cho' => $d->seats_left === null ? 'HẾT CHỖ' : null,
            'tam_ngung' => 'TẠM NGƯNG NHẬN',
            'lien_he' => $phan ? null : 'LIÊN HỆ',
            'con_cho' => $phan ? null : 'CÒN CHỖ',
            default => null,
        };
        $trangThai && array_unshift($phan, $trangThai);
        $ghiChu = trim((string) $d->note);
        // Ghi chú trùng ý tình trạng ("TẠM NGƯNG NHẬN KHÁCH") thì khỏi lặp
        if ($ghiChu !== '' && ! ($trangThai && str_contains(mb_strtoupper($ghiChu), $trangThai))) {
            $phan[] = '('.preg_replace('/\s+/u', ' ', $ghiChu).')';
        }

        return implode(' ', $phan);
    }

    /**
     * Gom các ngày đi liền nhau cùng giá → mỗi nhóm một dòng "ĐỒNG GIÁ".
     *
     * @return list<Collection<int, AllianceDeparture>>
     */
    private static function theoGia(Collection $ds): array
    {
        $nhom = [];
        $truoc = false;
        foreach ($ds as $d) {
            $khoa = $d->price ? $d->price.'|'.$d->price_original : trim((string) $d->price_text);
            if ($khoa !== $truoc || ! $nhom) {
                $nhom[] = collect();
                $truoc = $khoa;
            }
            $nhom[array_key_last($nhom)]->push($d);
        }

        return $nhom;
    }

    private static function gia(Collection $dot): ?string
    {
        $d = $dot->first();
        if ($d->price) {
            return self::nghin($d->price).($d->price_original ? ' (giá gốc '.self::nghin($d->price_original).')' : '');
        }

        return filled($d->price_text) ? trim($d->price_text) : null;
    }

    /** 13990000 → "13.990" (nghìn đồng, như điều hành vẫn ghi). */
    public static function nghin(int $so): string
    {
        return $so % 1000 === 0
            ? number_format(intdiv($so, 1000), 0, ',', '.')
            : number_format($so, 0, ',', '.').'đ';
    }

    /** "5N4Đ" / "06N05Đ" / "5 ngày 4 đêm" → 5. */
    public static function soNgay(?string $thoiLuong): ?int
    {
        if ($thoiLuong && preg_match('/(\d{1,2})\s*(N\b|N\d|NGÀY|ngày|N\s)/u', $thoiLuong.' ', $m)) {
            $n = (int) $m[1];

            return $n >= 1 && $n <= 30 ? $n : null;
        }

        return null;
    }

    /**
     * Ô hãng bay của sheet → [hãng ngắn, dòng chuyến bay]. "VNA" → ["VNA", null];
     * "Chuyến đi⏎VJ862 HCM - INC 02:35 - 09:40⏎Chuyến về⏎VJ861 INC - HCM⏎21:20 - 00:30+1"
     * → [null, "VJ862 HCM - INC 02:35 - 09:40 / VJ861 INC - HCM 21:20 - 00:30+1"].
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function hangBay(?string $o): array
    {
        $dong = collect(preg_split('/\R/u', (string) $o))
            ->map(fn ($x) => trim($x))
            ->filter(fn ($x) => $x !== '' && ! preg_match('/^chuy[ếe]n\s*(đi|về|bay)?\s*:?$/iu', $x))
            ->values();
        if ($dong->isEmpty()) {
            return [null, null];
        }
        $laChuyen = fn ($x) => (bool) preg_match('/^[A-Z0-9]{2}\s?\d{2,4}\b/u', $x);
        if (! $dong->contains($laChuyen)) {
            return [$dong->implode(' '), null];
        }

        // Dòng chỉ có giờ ("21:20 - 00:30+1") nối vào chặng phía trên
        $chang = [];
        $hang = [];
        foreach ($dong as $x) {
            if ($laChuyen($x)) {
                $chang[] = $x;
            } elseif ($chang && preg_match('/^\d{1,2}[:h]\d{2}/u', $x)) {
                $chang[array_key_last($chang)] .= ' '.$x;
            } else {
                $hang[] = $x;
            }
        }

        return [$hang ? implode(' ', $hang) : null, implode(' / ', $chang)];
    }

    /** Lưới an toàn: tên đối tác lọt vào tên tour / ghi chú thì xoá đi. */
    public static function boTenDoiTac(string $tin, ?string $tenDoiTac): string
    {
        $ten = trim((string) $tenDoiTac);
        if (mb_strlen($ten) < 2) {
            return $tin;
        }
        // Chỉ xoá khi đứng thành chữ riêng ("AZ" không ăn vào "KAZAN")
        $tin = preg_replace('/[ \t]*[-–—]?[ \t]*(?<![\p{L}\d])'.preg_quote($ten, '/').'(?![\p{L}\d])/iu', '', $tin);

        return preg_replace('/[ \t]+$/mu', '', $tin);
    }
}
