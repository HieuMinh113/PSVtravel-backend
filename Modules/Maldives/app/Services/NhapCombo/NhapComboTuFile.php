<?php

namespace Modules\Maldives\Services\NhapCombo;

use GdImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesResort;
use Modules\Maldives\Models\MaldivesRoomType;
use Modules\Tour\Services\NhapTour\DocPdf;
use Modules\Tour\Services\NhapTour\DocWord;
use RuntimeException;
use Throwable;

/**
 * Nhập nhanh một combo Maldives từ file chương trình của đối tác (.docx / .pdf).
 *
 *  - doc():     đọc file → kết quả phân tích để xem trước
 *  - tao():     tạo combo NHÁP kèm ngày khởi hành + giá theo hạng phòng, lịch
 *               trình. Resort chưa có thì tạo mới (giới thiệu, tiện ích, quy
 *               định, hạng phòng); đã có thì CHỈ thêm hạng phòng còn thiếu —
 *               không ghi đè giới thiệu / tiện ích / quy định đã sửa tay
 *  - capNhat(): combo đã có → chỉ cập nhật bảng giá + ngày khởi hành, giữ
 *               nguyên nội dung đã sửa tay
 * Ảnh trong file (có thể có bản quyền / logo đối tác) chỉ lấy khi nhân viên chọn.
 */
class NhapComboTuFile
{
    /** Ảnh lưu lên web: cạnh dài tối đa (px) */
    private const CANH_TOI_DA = 2000;

    /** Số ảnh tối đa của một ngày (khớp ô ảnh lịch trình trong admin) */
    private const ANH_MOI_NGAY = 6;

    /** @var list<string> ảnh đã lưu trong lần nhập này — xoá đi nếu lưu lỗi */
    private array $daLuu = [];

    /** @return array<string, mixed> */
    public function doc(string $duong, string $tenFile): array
    {
        $muc = match (strtolower(pathinfo($tenFile, PATHINFO_EXTENSION))) {
            'docx' => (new DocWord)->doc($duong),
            'pdf' => $this->mucPdf($duong),
            'doc' => throw new RuntimeException('File .doc (Word đời cũ) chưa đọc được — mở bằng Word rồi "Lưu thành" .docx.'),
            default => throw new RuntimeException('Chỉ nhận file Word (.docx) hoặc PDF.'),
        };

        return (new PhanTichComboMaldives)->phanTich($muc, $tenFile);
    }

    /**
     * Dãy mục của file PDF: đọc từng dòng (DocPdf), rồi thay vùng bảng giá và
     * các khung tiện ích nhiều cột bằng bản dựng lại theo toạ độ (BoCucPdf).
     *
     * @return list<array>
     */
    private function mucPdf(string $duong): array
    {
        $docPdf = new DocPdf;
        $muc = $docPdf->doc($duong);
        $boCuc = BoCucPdf::doc($docPdf->manhChu());

        $thay = [];
        if ($boCuc['bang']) {
            $thay[] = [$boCuc['bang']['vung'], ['loai' => 'bang_gia', 'cot' => $boCuc['bang']['cot'], 'hang' => $boCuc['bang']['hang']]];
        }
        foreach ($boCuc['khoi'] as $k) {
            $thay[] = [[$k['vung']], ['loai' => 'nhom_tien_ich', 'nhom' => $k['nhom']]];
        }
        foreach ($thay as [$vung, $moi]) {
            $trong = fn (array $m) => $m['loai'] === 'chu' && isset($m['tren'], $m['trang'])
                && array_filter($vung, fn ($v) => $m['trang'] === $v[0] && $m['tren'] >= $v[1] && $m['tren'] <= $v[2]);
            $ra = [];
            $vao = null;
            foreach ($muc as $m) {
                if ($trong($m)) {
                    $vao ??= count($ra);

                    continue;
                }
                $ra[] = $m;
            }
            if ($vao === null) {
                $vao = count($ra);
                foreach ($ra as $k => $m) {
                    if (isset($m['trang'], $m['tren']) && ($m['trang'] > $vung[0][0] || ($m['trang'] === $vung[0][0] && $m['tren'] > $vung[0][1]))) {
                        $vao = $k;
                        break;
                    }
                }
            }
            array_splice($ra, $vao, 0, [$moi + ['trang' => $vung[0][0], 'tren' => $vung[0][1]]]);
            $muc = $ra;
        }

        return $muc;
    }

    /** Resort đã có cùng tên (so theo đường dẫn không dấu; tên này chứa tên kia cũng tính) */
    public function resortTrung(array $kq): ?MaldivesResort
    {
        $slug = Str::slug((string) $kq['resort']['ten']);
        if ($slug === '') {
            return null;
        }
        $ds = MaldivesResort::query()->get(['id', 'name', 'slug']);

        return $ds->first(fn (MaldivesResort $r) => $r->slug === $slug || Str::slug($r->name) === $slug)
            ?? $ds->first(fn (MaldivesResort $r) => strlen(Str::slug($r->name)) >= 6 && (str_contains($slug, Str::slug($r->name)) || str_contains(Str::slug($r->name), $slug)));
    }

    /**
     * Combo đã có: cùng tên (đường dẫn), hoặc combo duy nhất của resort đó cùng
     * hãng bay + số ngày (nhân viên đã đổi tên combo sau lần nhập trước).
     */
    public function comboTrung(array $kq): ?MaldivesCombo
    {
        $slug = array_values(array_filter([$this->slugGoc($kq), Str::slug((string) $kq['ten'])]));
        $combo = $slug ? MaldivesCombo::query()->whereIn('slug', $slug)->first() : null;
        if ($combo) {
            return $combo;
        }
        $resort = $this->resortTrung($kq);
        if (! $resort || ! $kq['hang_bay']) {
            return null;
        }
        $cung = MaldivesCombo::query()->where('resort_id', $resort->id)->get()
            ->filter(fn (MaldivesCombo $c) => Str::slug((string) $c->airline) === Str::slug($kq['hang_bay'])
                && (! $kq['so_ngay'] || (int) $c->duration_days === (int) $kq['so_ngay']));

        return $cung->count() === 1 ? $cung->first() : null;
    }

    /**
     * Tạo combo nháp từ kết quả đọc file.
     *
     * @return array{0: MaldivesCombo, 1: list<string>} combo + tóm tắt đã làm
     */
    public function tao(array $kq, bool $layAnh = false): array
    {
        $this->daLuu = [];
        try {
            return DB::transaction(fn () => $this->taoCombo($kq, $layAnh));
        } catch (Throwable $e) {
            Storage::disk('public')->delete($this->daLuu);
            throw $e;
        }
    }

    /** @return array{0: MaldivesCombo, 1: list<string>} */
    private function taoCombo(array $kq, bool $layAnh): array
    {
        $viec = [];
        $anhChung = collect($kq['anh'])->whereNull('ngay')->values();

        // ---- Resort ----
        $resort = $this->resortTrung($kq);
        if (! $resort) {
            $r = $kq['resort'];
            $resort = MaldivesResort::create([
                'name' => Str::limit($r['ten'], 250, ''),
                'stars' => $r['sao'],
                'atoll' => $r['dao'],
                'package_name' => $r['goi'],
                'intro' => $r['gioi_thieu'],
                'highlights' => $r['noi_bat'],
                'amenities' => array_map(fn ($n) => ['title' => Str::limit($n['title'], 250, ''), 'items' => $n['items']], $r['tien_ich']),
                'policies' => $r['quy_dinh'],
                'status' => 'published',
                'sort_order' => 0,
            ]);
            $viec[] = 'tạo resort "'.$resort->name.'"';
        }
        if ($layAnh && $anhChung->isNotEmpty() && ! $resort->cover_image) {
            $bia = $this->anhBia($anhChung->all());
            $resort->cover_image = $this->luuAnh($anhChung[$bia]['du_lieu']);
            if (! $resort->gallery) {
                $resort->gallery = array_values(array_filter($anhChung->except($bia)->take(12)->map(fn ($a) => $this->luuAnh($a['du_lieu']))->all()));
            }
            $resort->save();
            $viec[] = 'ảnh bìa + '.count($resort->gallery ?? []).' ảnh resort';
        }
        [$idPhong, $phongMoi] = $this->hangPhong($resort, $kq);
        if ($phongMoi) {
            $viec[] = "thêm {$phongMoi} hạng phòng";
        }

        // ---- Combo ----
        $combo = MaldivesCombo::create([
            'resort_id' => $resort->id,
            'name' => Str::limit((string) ($kq['ten'] ?: 'Combo Maldives nhập từ file'), 250, ''),
            'slug' => $this->slugMoi($this->slugGoc($kq)),
            'summary' => $kq['tom_tat'] ? Str::limit($kq['tom_tat'], 490, '…') : null,
            'duration_days' => $kq['so_ngay'],
            'duration_nights' => $kq['so_dem'],
            'stay_summary' => $kq['luu_tru'],
            'airline' => $kq['hang_bay'],
            'transfer' => $kq['van_chuyen'],
            'departure_cities' => $kq['noi_khoi_hanh'],
            'child_percent' => $kq['tre_em_phan_tram'],
            'child_age' => $kq['tuoi_tre_em'] ?? '2 – 12 tuổi',
            'infant_note' => 'Liên hệ',
            'min_group' => $kq['toi_thieu'],
            'included' => $kq['bao_gom'],
            'excluded' => $kq['khong_bao_gom'],
            'notes' => $kq['luu_y'],
            // Nháp: chưa hiện ngoài web cho tới khi nhân viên xem lại và đăng bán
            'status' => 'draft',
            'is_featured' => false,
            'sort_order' => 0,
        ]);
        foreach ($kq['ngay'] as $n) {
            $combo->dates()->create(['start_date' => $n['ngay'], 'label' => $n['nhan'], 'prices' => $this->giaTheoId($n['gia'], $idPhong), 'status' => 'open']);
        }

        $soAnhNgay = 0;
        foreach ($kq['lich_trinh'] as $n) {
            $anh = [];
            if ($layAnh) {
                foreach ($kq['anh'] as $a) {
                    if ($a['ngay'] === $n['ngay'] && count($anh) < self::ANH_MOI_NGAY && ($duong = $this->luuAnh($a['du_lieu']))) {
                        $anh[] = $duong;
                    }
                }
            }
            $soAnhNgay += count($anh);
            $combo->days()->create([
                'day_number' => $n['ngay'],
                'title' => Str::limit($n['tieu_de'] ?: 'Ngày '.$n['ngay'], 250, ''),
                'meals' => $n['bua'] ? Str::limit($n['bua'], 250, '') : null,
                'description' => $n['noi_dung'],
                'images' => $anh,
            ]);
        }

        return [$combo, array_values(array_filter([
            ...$viec,
            count($kq['ngay']).' ngày khởi hành kèm giá',
            count($kq['lich_trinh']).' ngày lịch trình',
            $soAnhNgay ? "{$soAnhNgay} ảnh trong lịch trình" : null,
        ]))];
    }

    /**
     * Combo đã có: thêm hạng phòng còn thiếu cho resort, thêm ngày khởi hành
     * mới, sửa giá ngày đã có (chỉ các hạng phòng có trong file). Không đụng
     * nội dung, lịch trình, ảnh, ngày không có trong file.
     *
     * @return array{0: list<string>, 1: list<string>} việc đã làm + điều cần xem lại
     */
    public function capNhat(MaldivesCombo $combo, array $kq): array
    {
        return DB::transaction(function () use ($combo, $kq) {
            [$idPhong, $phongMoi] = $this->hangPhong($combo->resort, $kq);
            $moi = 0;
            $sua = 0;
            foreach ($kq['ngay'] as $n) {
                $gia = $this->giaTheoId($n['gia'], $idPhong);
                $ngay = $combo->dates()->whereDate('start_date', $n['ngay'])->first();
                if (! $ngay) {
                    $combo->dates()->create(['start_date' => $n['ngay'], 'label' => $n['nhan'], 'prices' => $gia, 'status' => 'open']);
                    $moi++;

                    continue;
                }
                $cu = (array) ($ngay->prices ?? []);
                $ghep = $cu;
                foreach ($idPhong as $id) {
                    unset($ghep[$id], $ghep[(string) $id]);
                }
                $ghep = $gia + $ghep;
                if ($this->khacGia($cu, $ghep) || (blank($ngay->label) && filled($n['nhan']))) {
                    $ngay->update(['prices' => $ghep, 'label' => $ngay->label ?: $n['nhan']]);
                    $sua++;
                }
            }

            // Phụ thu khác file: chỉ báo, không tự sửa (nhân viên có thể đã chỉnh theo giá mới của hãng)
            $xemLai = [];
            foreach ($kq['noi_khoi_hanh'] as $n) {
                $dang = $combo->noiKhoiHanh($n['city']);
                if ($dang && $n['surcharge_usd'] !== null && (string) ($dang['surcharge_usd'] ?? '') !== (string) $n['surcharge_usd']) {
                    $xemLai[] = "Phụ thu khởi hành từ {$n['city']} trong file là \${$n['surcharge_usd']}, combo đang ghi "
                        .(is_numeric($dang['surcharge_usd'] ?? null) ? '$'.$dang['surcharge_usd'] : 'trống').' — sửa tay nếu cần.';
                }
            }

            return [array_values(array_filter([
                $phongMoi ? "thêm {$phongMoi} hạng phòng cho resort" : null,
                $moi ? "thêm {$moi} ngày khởi hành" : null,
                $sua ? "sửa giá {$sua} ngày khởi hành" : null,
            ])) ?: ['bảng giá và ngày khởi hành đã khớp file — không có gì thay đổi'], $xemLai];
        });
    }

    /**
     * Hạng phòng trong file → id hạng phòng của resort; hạng chưa có thì thêm.
     * Hạng đã có giữ nguyên (tên, sức chứa, mô tả đã sửa tay).
     *
     * @return array{0: array<int, int>, 1: int} [thứ tự trong file => id], số hạng thêm mới
     */
    private function hangPhong(MaldivesResort $resort, array $kq): array
    {
        $daCo = $resort->roomTypes()->get();
        $thuTu = (int) $daCo->max('sort_order');
        $id = [];
        $moi = 0;
        foreach ($kq['phong'] as $k => $p) {
            $phong = $daCo->first(fn (MaldivesRoomType $r) => Str::slug($r->name) === Str::slug($p['ten']));
            if (! $phong) {
                $phong = $resort->roomTypes()->create([
                    'name' => $p['ten'],
                    'capacity' => $p['suc_chua'],
                    'adults_only' => $p['chi_nguoi_lon'],
                    'sort_order' => ++$thuTu,
                ]);
                $daCo->push($phong);
                $moi++;
            }
            $id[$k] = $phong->id;
        }

        return [$id, $moi];
    }

    /**
     * @param  array<int, ?int>  $gia  [thứ tự phòng trong file => USD | null (NA)]
     * @return array<int, int> [id hạng phòng => USD] — bỏ ô NA (không bán)
     */
    private function giaTheoId(array $gia, array $idPhong): array
    {
        $ra = [];
        foreach ($gia as $k => $v) {
            if ($v !== null && isset($idPhong[$k])) {
                $ra[$idPhong[$k]] = (int) $v;
            }
        }

        return $ra;
    }

    private function khacGia(array $a, array $b): bool
    {
        $chuan = fn (array $x) => collect($x)->filter(fn ($v) => $v !== null && $v !== '')->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v])->sortKeys()->all();

        return $chuan($a) !== $chuan($b);
    }

    /**
     * Đường dẫn gọn cho Google: resort + hạng sao + hãng bay chính
     * ("sun-siyam-olhuveli-4-5-sao-bay-singapore-airlines").
     */
    private function slugGoc(array $kq): string
    {
        $r = $kq['resort'];
        $hang = trim((string) preg_split('/\s*\/\s*/u', (string) $kq['hang_bay'])[0]);

        return Str::slug(implode(' ', array_filter([
            $r['ten'],
            $r['sao'] !== null ? str_replace('.', ' ', (string) $r['sao']).' sao' : null,
            $hang !== '' ? 'bay '.$hang : null,
        ])));
    }

    private function slugMoi(string $goc): string
    {
        $goc = rtrim(Str::limit($goc ?: 'combo-maldives', 140, ''), '-');
        $slug = $goc;
        for ($i = 2; MaldivesCombo::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$goc}-{$i}";
        }

        return $slug;
    }

    /** Ảnh bìa: ảnh ngang lớn nhất (tỉ lệ hợp khung bìa), không có thì ảnh lớn nhất */
    private function anhBia(array $anh): int
    {
        $ngang = collect($anh)->filter(fn ($a) => $a['rong'] / max(1, $a['cao']) >= 1.2 && $a['rong'] / max(1, $a['cao']) <= 2.2);

        return ($ngang->isNotEmpty() ? $ngang : collect($anh))->sortByDesc(fn ($a) => $a['rong'] * $a['cao'])->keys()->first();
    }

    /**
     * Lưu ảnh lên ổ public dạng JPEG: vẽ lại qua GD (bỏ siêu dữ liệu, chặn
     * file giả dạng ảnh), thu nhỏ ảnh quá lớn, nền trắng cho ảnh trong suốt.
     */
    private function luuAnh(string $duLieu): ?string
    {
        $kt = @getimagesizefromstring($duLieu);
        if (! $kt || $kt[0] * $kt[1] > 40_000_000) {
            return null; // quá nhiều điểm ảnh — giải nén ra sẽ tràn bộ nhớ
        }
        $goc = @imagecreatefromstring($duLieu);
        if (! $goc instanceof GdImage) {
            return null;
        }
        $rong = imagesx($goc);
        $cao = imagesy($goc);
        $tiLe = min(1, self::CANH_TOI_DA / max($rong, $cao));
        $r = max(1, (int) round($rong * $tiLe));
        $c = max(1, (int) round($cao * $tiLe));

        $anh = imagecreatetruecolor($r, $c);
        imagefill($anh, 0, 0, imagecolorallocate($anh, 255, 255, 255));
        imagecopyresampled($anh, $goc, 0, 0, 0, 0, $r, $c, $rong, $cao);

        ob_start();
        imagejpeg($anh, null, 85);
        $jpg = (string) ob_get_clean();

        $duong = 'maldives/'.Str::random(32).'.jpg';
        Storage::disk('public')->put($duong, $jpg);
        $this->daLuu[] = $duong;

        return $duong;
    }
}
