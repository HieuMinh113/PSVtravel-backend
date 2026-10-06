<?php

namespace Modules\Alliance\Services;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
 *  4. Tour PSV có nối với tour liên minh → cập nhật số chỗ các ngày đi trên
 *     website ("Còn 5 chỗ").
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
     * @return array{ok: bool, so_ngay: int, moi: int, bao_dong: int, cap_nhat_web: int, loi: ?string}
     */
    public function dongBo(AllianceSource $nguon): array
    {
        $dangLoiTruoc = $nguon->last_error !== null;
        $nguon->last_checked_at = now();

        $file = null;
        try {
            $file = $this->tai->tai($nguon->sheet_url);
            $kq = $this->doc->doc($file);
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
            'sheet_updated_on' => $kq['ngay_cap_nhat'],
            'departures_count' => count($kq['dong']),
        ])->save();

        $capNhatWeb = $this->capNhatTourPsv($tourIds);

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

        // Trạng thái cũ theo "khoá tour|ngày" để so sánh
        $cu = AllianceDeparture::query()
            ->where('alliance_departures.alliance_source_id', $nguon->id)
            ->join('alliance_tours', 'alliance_tours.id', '=', 'alliance_departures.alliance_tour_id')
            ->get(['alliance_departures.id', 'alliance_tours.key', 'departure_date', 'seats_left', 'status'])
            ->keyBy(fn ($d) => $d->key.'|'.$d->departure_date->toDateString());

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
            $truoc = $cu[$d['khoa_tour'].'|'.$d['ngay_di']] ?? null;
            $ngayDi = $truoc ? AllianceDeparture::findOrFail($truoc->id) : new AllianceDeparture([
                'alliance_tour_id' => $tour->id,
                'departure_date' => $d['ngay_di'],
            ]);
            $ngayDi->fill([
                'alliance_source_id' => $nguon->id,
                'price' => $d['gia'],
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

        // Ngày không còn trong sheet (đối tác xoá dòng, hoặc ngày đã qua)
        AllianceDeparture::where('alliance_source_id', $nguon->id)
            ->whereNotIn('id', $giu ?: [0])
            ->delete();

        return [$baoDong, $soMoi, array_values(array_map(fn ($t) => $t->id, $tours))];
    }

    private function soSanh(AllianceDeparture $truoc, AllianceDeparture $sau, AllianceTour $tour, bool $dangBan, int $nguong): ?string
    {
        $ten = e(mb_strimwidth($tour->name, 0, 60, '…')).' '.$sau->departure_date->format('d/m');
        $conTruoc = $truoc->status === 'con_cho' ? (int) $truoc->seats_left : 0;
        $conSau = $sau->status === 'con_cho' ? (int) $sau->seats_left : 0;
        $ghiChuBan = $dangBan ? ' <em>(tour PSV đang bán)</em>' : '';

        if ($conTruoc === 0 && $conSau > 0) {
            return "{$ten}: <strong>có lại {$conSau} chỗ</strong>{$ghiChuBan}";
        }
        if ($conSau > 0 && $conSau <= $nguong && ($conTruoc > $nguong)) {
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

        return null;
    }

    /**
     * Tour PSV đã nối tour liên minh: cập nhật số chỗ các ngày đi sắp tới theo
     * sheet. Chỉ đụng những ngày có trong sheet và sheet có ghi số chỗ; ngày
     * PSV tự mở thêm hoặc sheet không ghi số chỗ thì giữ nguyên.
     *
     * @param  list<int>  $tourIds  id tour liên minh vừa đọc
     * @return int số ngày đi trên website đã đổi số chỗ
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
            $lm = AllianceDeparture::where('alliance_tour_id', $tour->alliance_tour_id)
                ->get()->keyBy(fn ($d) => $d->departure_date->toDateString());

            foreach ($tour->departures as $dot) {
                $d = $lm[$dot->start_date->toDateString()] ?? null;
                if (! $d) {
                    continue;
                }
                $con = $this->soChoTrenWeb($d);
                if ($con === null || $con === $dot->seats_left) {
                    continue;
                }
                $dot->seats_left = $con;
                $dot->seats_total = max((int) $dot->seats_total, (int) ($d->seats_total ?? 0), $con);
                $dot->save(); // trạng thái mở/hết tự tính theo số chỗ (xem TourDeparture)
                $doi++;
            }
        }

        return $doi;
    }

    /** Số chỗ hiện trên web cho một ngày liên minh; null = sheet không ghi, giữ nguyên. */
    public function soChoTrenWeb(AllianceDeparture $d): ?int
    {
        if (in_array($d->status, ['huy', 'tam_ngung', 'het_cho'], true)) {
            return 0;
        }

        return $d->seats_left;
    }

    /**
     * Thêm vào tour PSV các ngày đi có trong sheet liên minh mà web chưa có.
     * Ngày sheet không ghi số chỗ thì bỏ qua (không biết mở bao nhiêu chỗ).
     *
     * @return array{them: int, bo_qua: int}
     */
    public function themNgayDi(Tour $tour): array
    {
        if (! $tour->alliance_tour_id) {
            return ['them' => 0, 'bo_qua' => 0];
        }

        $daCo = TourDeparture::where('tour_id', $tour->id)->pluck('start_date')
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())->flip();
        $them = 0;
        $boQua = 0;

        $lm = AllianceDeparture::where('alliance_tour_id', $tour->alliance_tour_id)
            ->whereDate('departure_date', '>=', today())->orderBy('departure_date')->get();
        foreach ($lm as $d) {
            if (isset($daCo[$d->departure_date->toDateString()])) {
                continue;
            }
            $con = $this->soChoTrenWeb($d);
            if ($con === null) {
                $boQua++;
                continue;
            }
            TourDeparture::create([
                'tour_id' => $tour->id,
                'start_date' => $d->departure_date->toDateString(),
                'seats_total' => max((int) ($d->seats_total ?? 0), $con),
                'seats_left' => $con,
            ]);
            $them++;
        }

        return ['them' => $them, 'bo_qua' => $boQua];
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
            return \App\Filament\Resources\AllianceDepartures\AllianceDepartureResource::getUrl('index', [
                'filters' => ['alliance_source_id' => ['value' => $nguon->id]],
            ], panel: 'admin');
        } catch (\Throwable) {
            return url('/admin');
        }
    }
}
