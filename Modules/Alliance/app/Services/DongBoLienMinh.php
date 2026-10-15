<?php

namespace Modules\Alliance\Services;

use App\Filament\Resources\AllianceDepartures\AllianceDepartureResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Alliance\Models\AllianceDeparture;
use Modules\Alliance\Models\AllianceSource;
use Modules\Alliance\Models\AllianceTour;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;

/**
 * Đọc lại một sheet đối tác và cập nhật hệ thống:
 *  1. Tải + đọc sheet → danh sách ngày đi (từ hôm nay trở đi).
 *  2. Ghi đè tour / ngày đi liên minh của nguồn đó; ngày không còn trong
 *     sheet thì xoá.
 *  3. So với lần trước → báo động cho điều hành (chuông trong trang quản trị):
 *     có chỗ trở lại, sắp hết chỗ, tour PSV đang bán vừa hết chỗ, ngày mới.
 *  4. Tour PSV có nối với tour liên minh → lịch khởi hành trên website theo
 *     sheet: tự thêm ngày mới, cập nhật số chỗ ("Còn 5 chỗ") và giá riêng
 *     từng ngày (người lớn / trẻ em / em bé) — giá nhân viên tự sửa thì giữ.
 */
class DongBoLienMinh
{
    /** Số dòng tối đa trong một thông báo, còn lại gộp "…và N thay đổi khác". */
    private const SO_DONG_BAO = 6;

    public function __construct(
        private TaiSheetLienMinh $tai,
        private DocBangLienMinh $doc,
    ) {}

    /**
     * @return array{ok: bool, so_ngay: int, moi: int, bao_dong: int, cap_nhat_web: int, them_web?: int, loi: ?string}
     */
    public function dongBo(AllianceSource $nguon): array
    {
        $dangLoiTruoc = $nguon->last_error !== null;
        $nguon->last_checked_at = now();

        $file = null;
        try {
            $file = $this->tai->tai($nguon->sheet_url);
            // Sheet đối tác lớn (Triều Hảo 1,4MB, 25 tab) cần nhiều bộ nhớ hơn mức mặc định
            @ini_set('memory_limit', '1024M');
            $kq = $this->doc->doc($file, null, $nguon->tuyChonDoc());
        } catch (\Throwable $e) {
            $nguon->last_error = mb_substr($e->getMessage(), 0, 1000);
            $nguon->save();
            Log::warning('Liên minh: không đọc được sheet', ['nguon' => $nguon->id, 'loi' => $e->getMessage()]);

            // Chỉ báo khi vừa CHUYỂN sang lỗi — không báo lại mỗi 10 phút.
            if (! $dangLoiTruoc) {
                $this->gui(
                    "Không đọc được sheet: {$nguon->name}",
                    [e($nguon->last_error)],
                    $nguon,
                    'danger',
                );
            }

            return ['ok' => false, 'so_ngay' => 0, 'moi' => 0, 'bao_dong' => 0, 'cap_nhat_web' => 0, 'loi' => $nguon->last_error];
        } finally {
            if ($file && is_file($file)) {
                @unlink($file);
            }
        }

        $lanDau = $nguon->last_success_at === null;
        [$thayDoi, $soMoi, $tourIds] = DB::transaction(fn () => $this->luu($nguon, $kq));

        $nguon->forceFill([
            'last_success_at' => now(),
            'last_error' => null,
            'warnings' => $kq['canh_bao'] ?: null,
            'bao_cao' => $kq['bao_cao'] ?? null,
            'sheet_updated_on' => $kq['ngay_cap_nhat'],
            'departures_count' => count($kq['dong']),
        ])->save();

        $capNhatWeb = $this->capNhatTourPsv($tourIds);
        $themWeb = $this->themNgayChoTourDaNoi($tourIds);

        $dong = $thayDoi;
        if (! $lanDau && $soMoi > 0) {
            $dong[] = "Thêm {$soMoi} ngày khởi hành mới";
        }
        if ($dangLoiTruoc) {
            array_unshift($dong, 'Đã đọc lại được sheet bình thường');
        }
        if (! $lanDau && $dong) {
            $this->gui("Liên minh {$nguon->name}: ".count($dong).' thay đổi', $dong, $nguon);
        }

        return [
            'ok' => true,
            'so_ngay' => count($kq['dong']),
            'moi' => $soMoi,
            'bao_dong' => $lanDau ? 0 : count($dong),
            'cap_nhat_web' => $capNhatWeb,
            'them_web' => $themWeb,
            'loi' => null,
        ];
    }

    /**
     * Ghi kết quả đọc vào CSDL.
     *
     * @return array{0: list<string>, 1: int, 2: list<int>} [dòng báo động, số ngày mới, id tour liên minh]
     */
    private function luu(AllianceSource $nguon, array $kq): array
    {
        $nguong = (int) config('alliance.nguong_sap_het', 3);

        // Trạng thái cũ theo "khoá tour|ngày" (hoặc "khoá tour|tuần:Thứ 5...") để so sánh
        $cu = AllianceDeparture::query()
            ->where('alliance_departures.alliance_source_id', $nguon->id)
            ->join('alliance_tours', 'alliance_tours.id', '=', 'alliance_departures.alliance_tour_id')
            ->get(['alliance_departures.id', 'alliance_tours.key', 'departure_date', 'weekly', 'seats_left', 'status', 'alliance_departures.price', 'alliance_departures.price_net'])
            ->keyBy(fn ($d) => $d->key.'|'.($d->departure_date?->toDateString() ?? 'tuan:'.$d->weekly));

        // Tour liên minh đang có tour PSV nối vào — báo kỹ hơn cho các tour này
        $dangBan = Tour::query()->whereNotNull('alliance_tour_id')
            ->whereIn('alliance_tour_id', AllianceTour::where('alliance_source_id', $nguon->id)->select('id'))
            ->pluck('alliance_tour_id')->flip();

        $tours = [];
        $giu = [];
        $baoDong = [];
        $soMoi = 0;
        $bayGio = now();

        foreach ($kq['dong'] as $d) {
            $tour = $tours[$d['khoa_tour']] ??= tap(AllianceTour::updateOrCreate(
                ['alliance_source_id' => $nguon->id, 'key' => $d['khoa_tour']],
                [
                    'name' => $d['ten_tour'],
                    'details' => $d['chi_tiet'],
                    'duration' => $d['thoi_gian'] ? mb_substr($d['thoi_gian'], 0, 30) : null,
                    'airline' => $d['hang_bay'],
                    'sheet_tab' => mb_substr($d['tab'], 0, 250),
                    'section' => $d['muc'] ? mb_substr($d['muc'], 0, 250) : null,
                    'last_seen_at' => $bayGio,
                ],
            ), function (AllianceTour $t) use ($d) {
                if ($d['link_chuong_trinh'] && $t->program_url !== $d['link_chuong_trinh']) {
                    $t->forceFill(['program_url' => $d['link_chuong_trinh']])->save();
                }
            });
            if (! $tour->program_url && $d['link_chuong_trinh']) {
                $tour->forceFill(['program_url' => $d['link_chuong_trinh']])->save();
            }

            // Tìm theo id đã nạp ở trên chứ không updateOrCreate theo ngày: cột
            // ngày so chuỗi trên SQLite ("2026-10-14" ≠ "2026-10-14 00:00:00")
            // sẽ không khớp và tạo bản ghi trùng.
            $truoc = $cu[$d['khoa_tour'].'|'.($d['ngay_di'] ?? 'tuan:'.$d['lich_tuan'])] ?? null;
            $ngayDi = $truoc ? AllianceDeparture::findOrFail($truoc->id) : new AllianceDeparture([
                'alliance_tour_id' => $tour->id,
                'departure_date' => $d['ngay_di'],
                'weekly' => $d['ngay_di'] ? null : mb_substr((string) $d['lich_tuan'], 0, 80),
            ]);
            $ngayDi->fill([
                'alliance_source_id' => $nguon->id,
                'tour_code' => $d['ma_tour'] ?? null,
                'price' => $d['gia'],
                'price_original' => $d['gia_niem_yet'] ?? null,
                'price_net' => (bool) ($d['gia_net'] ?? false),
                'price_text' => $d['gia_goc'],
                'price_child' => $d['gia_tre_em'],
                'price_infant' => $d['gia_em_be'],
                'commission' => $d['hoa_hong'],
                'seats_total' => $d['tong_cho'],
                'seats_sold' => $d['da_chot'],
                'seats_hold' => $d['dang_giu'],
                'seats_left' => $d['con_cho'],
                'status' => $d['trang_thai'],
                'note' => $d['ghi_chu'],
                'visa_deadline' => $d['han_visa'] ? mb_substr($d['han_visa'], 0, 40) : null,
                'sheet_tab' => mb_substr($d['tab'], 0, 250),
                'sheet_row' => $d['dong_sheet'],
            ]);
            $ngayDi->alliance_tour_id = $tour->id;
            $ngayDi->save();
            $giu[] = $ngayDi->id;

            if (! $truoc) {
                $soMoi++;

                continue;
            }
            if ($dong = $this->soSanh($truoc, $ngayDi, $tour, isset($dangBan[$tour->id]), $nguong)) {
                $baoDong[] = $dong;
            }
        }

        // Ngày không còn trong sheet (đối tác xoá dòng, hoặc ngày đã qua). Ngày sắp
        // tới của tour PSV đang bán bị xoá khỏi sheet → báo để điều hành kiểm tra
        // (website giữ nguyên đợt đó, không tự đóng).
        $biXoa = AllianceDeparture::where('alliance_source_id', $nguon->id)->whereNotIn('id', $giu ?: [0]);
        foreach ((clone $biXoa)->whereDate('departure_date', '>=', today())->whereIn('alliance_tour_id', $dangBan->keys())->with('tour')->get() as $x) {
            $baoDong[] = e(mb_strimwidth((string) $x->tour?->name, 0, 60, '…')).' '.e($x->nhanNgay())
                .': <strong>không còn trong sheet đối tác</strong> — kiểm tra lại đợt này trên website';
        }
        $biXoa->delete();

        return [$baoDong, $soMoi, array_values(array_map(fn ($t) => $t->id, $tours))];
    }

    private function soSanh(AllianceDeparture $truoc, AllianceDeparture $sau, AllianceTour $tour, bool $dangBan, int $nguong): ?string
    {
        $ten = e(mb_strimwidth($tour->name, 0, 60, '…')).' '.e($sau->nhanNgay());
        // "Còn nhận" không ghi số chỗ (HIGO) vẫn là đang nhận khách
        $khongSo = fn (AllianceDeparture $d) => $d->status === 'con_cho' && $d->seats_left === null;
        $conTruoc = $truoc->status === 'con_cho' ? (int) ($truoc->seats_left ?? 1) : 0;
        $conSau = $sau->status === 'con_cho' ? (int) ($sau->seats_left ?? 1) : 0;
        $ghiChuBan = $dangBan ? ' <em>(tour PSV đang bán)</em>' : '';

        if ($conTruoc === 0 && $khongSo($sau)) {
            return "{$ten}: <strong>nhận khách trở lại</strong>{$ghiChuBan}";
        }
        if ($conTruoc === 0 && $conSau > 0) {
            return "{$ten}: <strong>có lại {$conSau} chỗ</strong>{$ghiChuBan}";
        }
        if ($conSau > 0 && $conSau <= $nguong && ($conTruoc > $nguong) && ! $khongSo($sau)) {
            return "{$ten}: sắp hết, còn <strong>{$conSau} chỗ</strong>{$ghiChuBan}";
        }
        if ($conTruoc > 0 && $conSau === 0) {
            $lyDo = AllianceDeparture::TRANG_THAI[$sau->status] ?? 'Hết chỗ';
            // Hết chỗ chỉ đáng báo khi tour PSV đang bán (web vừa tự đóng đợt);
            // huỷ đoàn / tạm ngưng thì luôn báo.
            if ($dangBan || in_array($sau->status, ['huy', 'tam_ngung'], true)) {
                return "{$ten}: <strong>".mb_strtolower($lyDo).'</strong>'.$ghiChuBan;
            }
        }
        // Tour PSV đang bán: đối tác đổi giá ngày này (web tự đổi giá đợt theo sheet)
        if ($dangBan && $truoc->price && $sau->price && $truoc->price !== $sau->price) {
            $vnd = fn (int $v) => number_format($v, 0, ',', '.');

            return "{$ten}: giá đổi {$vnd($truoc->price)} → <strong>{$vnd($sau->price)}</strong>".($sau->price_net ? ' (giá NET)' : '').$ghiChuBan;
        }

        return null;
    }

    /**
     * Tour PSV đã nối tour liên minh: cập nhật số chỗ + giá riêng các ngày đi
     * sắp tới theo sheet. Chỉ đụng những ngày có trong sheet; ngày PSV tự mở
     * thêm thì giữ nguyên (xem apDot).
     *
     * @param  list<int>  $tourIds  id tour liên minh vừa đọc
     * @return int số ngày đi trên website đã đổi số chỗ / giá
     */
    public function capNhatTourPsv(array $tourIds): int
    {
        if (! $tourIds) {
            return 0;
        }

        $doi = 0;
        $tourPsv = Tour::query()->whereIn('alliance_tour_id', $tourIds)
            ->with(['departures' => fn ($q) => $q->whereDate('start_date', '>=', today())])
            ->get();

        foreach ($tourPsv as $tour) {
            [$lm, $macDinh] = $this->ngayLienMinh($tour);
            foreach ($tour->departures as $dot) {
                $d = $lm[$dot->start_date->toDateString()] ?? null;
                if ($d && $this->apDot($dot, $d, $macDinh)) {
                    $doi++;
                }
            }
        }

        return $doi;
    }

    /**
     * Số chỗ trên web cho một ngày liên minh: hết / huỷ / tạm ngưng → 0, sheet
     * ghi số → số đó; null = sheet không ghi số chỗ ("Còn nhận", không ghi gì).
     */
    public function soChoTrenWeb(AllianceDeparture $d): ?int
    {
        if (in_array($d->status, ['huy', 'tam_ngung', 'het_cho'], true)) {
            return 0;
        }

        return $d->seats_left;
    }

    /**
     * Ghi số chỗ + giá của ngày trong sheet vào đợt khởi hành của tour PSV.
     *
     *  - Số chỗ: sheet ghi số / hết chỗ → theo sheet. Sheet không ghi số (đa số
     *    đối tác chỉ đánh dấu "Full") → giữ số chỗ của web (đã trừ khách PSV
     *    đặt); riêng đợt mà chính sheet đã đóng trước đó thì mở lại với số chỗ
     *    mặc định của đối tác.
     *  - Giá người lớn / trẻ em / em bé: theo sheet, trừ khi nhân viên đã tự sửa
     *    (giá đang khác giá sheet ghi lần trước). Xoá trống giá thì lần sau lại
     *    theo sheet. Giá NET (VNA "NET THU VỀ") không phải giá bán → không điền.
     *
     * @return bool có đổi số chỗ / giá không
     */
    private function apDot(TourDeparture $dot, AllianceDeparture $d, int $macDinh): bool
    {
        $da = $dot->alliance_sync ?? [];

        $con = $this->soChoTrenWeb($d);
        if ($con === null && (int) $dot->seats_left === 0 && ($da['seats'] ?? null) === 0) {
            $con = $macDinh; // sheet mở lại ngày trước đó ghi hết chỗ
        }
        if ($con !== null) {
            if ($con !== $dot->seats_left) {
                $dot->seats_left = $con;
                $dot->seats_total = max((int) $dot->seats_total, (int) ($d->seats_total ?? 0), $con);
            }
            $da['seats'] = $con;
        }

        foreach (self::giaSheet($d) as $cot => $gia) {
            if ($gia !== null && ($dot->{$cot} === null || $dot->{$cot} === ($da[$cot] ?? null))) {
                $dot->{$cot} = $gia;
                $da[$cot] = $gia;
            }
        }

        $doi = $dot->isDirty(['seats_left', 'price_override', 'child_price_override', 'infant_price_override']);
        $dot->alliance_sync = $da;
        if ($dot->isDirty()) {
            $dot->save(); // trạng thái mở/hết tự tính theo số chỗ (xem TourDeparture)
        }

        return $doi;
    }

    /** @return array{price_override: ?int, child_price_override: ?int, infant_price_override: ?int} */
    private static function giaSheet(AllianceDeparture $d): array
    {
        return [
            'price_override' => $d->price_net ? null : $d->price,
            'child_price_override' => $d->price_net ? null : $d->price_child,
            'infant_price_override' => $d->price_net ? null : $d->price_infant,
        ];
    }

    /**
     * Ngày đi trong sheet (từ hôm nay) của tour liên minh đã nối + số chỗ mặc định của đối tác.
     *
     * @return array{0: Collection<string, AllianceDeparture>, 1: int}
     */
    private function ngayLienMinh(Tour $tour): array
    {
        $lm = AllianceDeparture::where('alliance_tour_id', $tour->alliance_tour_id)
            ->whereDate('departure_date', '>=', today()) // lịch "Thứ 5 hằng tuần" không khớp ngày cụ thể
            ->orderBy('departure_date')->with('source:id,default_seats')
            ->get()->keyBy(fn ($d) => $d->departure_date->toDateString());

        return [$lm, (int) ($lm->first()?->source?->default_seats ?? 10)];
    }

    /**
     * Thêm vào tour PSV các ngày đi có trong sheet liên minh mà web chưa có,
     * kèm số chỗ (sheet không ghi số → số chỗ mặc định của đối tác) và giá
     * riêng từng ngày.
     *
     * @return array{them: int, bo_qua: int}
     */
    public function themNgayDi(Tour $tour): array
    {
        if (! $tour->alliance_tour_id) {
            return ['them' => 0, 'bo_qua' => 0];
        }

        $daCo = TourDeparture::where('tour_id', $tour->id)->pluck('start_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->flip();
        [$lm, $macDinh] = $this->ngayLienMinh($tour);
        $them = 0;
        foreach ($lm as $ngay => $d) {
            if (isset($daCo[$ngay])) {
                continue;
            }
            $con = $this->soChoTrenWeb($d) ?? $macDinh;
            $dot = new TourDeparture([
                'tour_id' => $tour->id,
                'start_date' => $ngay,
                'seats_total' => max((int) ($d->seats_total ?? 0), $con),
                'seats_left' => $con,
                ...self::giaSheet($d),
            ]);
            $dot->alliance_sync = array_filter(['seats' => $con, ...self::giaSheet($d)], fn ($v) => $v !== null);
            $dot->save();
            $them++;
        }

        return ['them' => $them, 'bo_qua' => 0];
    }

    /** Sau mỗi lần đọc sheet: tour PSV đã nối tự có thêm các ngày mới trong sheet. */
    private function themNgayChoTourDaNoi(array $tourIds): int
    {
        if (! $tourIds) {
            return 0;
        }

        return Tour::query()->whereIn('alliance_tour_id', $tourIds)->get()
            ->sum(fn (Tour $tour) => $this->themNgayDi($tour)['them']);
    }

    /** Gửi thông báo vào chuông của những người có quyền xem liên minh. */
    private function gui(string $tieuDe, array $dong, AllianceSource $nguon, string $kieu = 'info'): void
    {
        $nguoiNhan = $this->nguoiNhan();
        if ($nguoiNhan->isEmpty()) {
            return;
        }

        $hien = array_slice($dong, 0, self::SO_DONG_BAO);
        if (count($dong) > self::SO_DONG_BAO) {
            $hien[] = '…và '.(count($dong) - self::SO_DONG_BAO).' thay đổi khác';
        }

        $tb = Notification::make()
            ->title($tieuDe)
            ->body(implode('<br>', $hien))
            ->status($kieu)
            ->actions([
                Action::make('xem')->label('Xem bảng chỗ')->button()->markAsRead()
                    ->url($this->duongDanTraCho($nguon)),
            ]);

        try {
            $tb->sendToDatabase($nguoiNhan, isEventDispatched: true);
        } catch (\Throwable $e) {
            Log::warning('Liên minh: không gửi được thông báo', ['loi' => $e->getMessage()]);
        }
    }

    private function nguoiNhan(): Collection
    {
        try {
            return User::permission('ViewAny:AllianceDeparture')->get();
        } catch (\Throwable) {
            return collect(); // quyền chưa được tạo (chưa chạy migration)
        }
    }

    private function duongDanTraCho(AllianceSource $nguon): string
    {
        try {
            return AllianceDepartureResource::getUrl('index', [
                'filters' => ['alliance_source_id' => ['value' => $nguon->id]],
            ], panel: 'admin');
        } catch (\Throwable) {
            return url('/admin');
        }
    }
}
