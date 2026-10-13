<?php

namespace Modules\Tour\Services\NhapTour;

use GdImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use RuntimeException;
use Throwable;

/**
 * Nhập nhanh một tour từ file chương trình tour của đối tác (.docx / .pdf).
 *
 *  - doc():     đọc file → kết quả phân tích (tên, giá, ngày khởi hành, lịch
 *               trình, ảnh, cảnh báo) để xem trước
 *  - tao():     tạo tour NHÁP (chưa hiện ngoài web) kèm ngày khởi hành, lịch
 *               trình, ảnh — nhân viên mở ra sửa lại rồi mới đăng bán
 *  - capNhat(): tour đã có (cùng tên) → chỉ cập nhật giá + thêm / sửa giá các
 *               ngày khởi hành, KHÔNG đụng nội dung và ảnh đã chỉnh tay
 */
class NhapTourTuFile
{
    /** Số chỗ mặc định của mỗi ngày khởi hành mới */
    public const SO_CHO = 24;

    /** Ảnh lưu lên web: cạnh dài tối đa (px) */
    private const CANH_TOI_DA = 2000;

    /** Số ảnh tối đa của một ngày (khớp giới hạn ô ảnh lịch trình trong admin) */
    private const ANH_MOI_NGAY = 10;

    /** @var list<string> ảnh đã lưu trong lần nhập này — xoá đi nếu lưu tour lỗi */
    private array $daLuu = [];

    /** @return array<string, mixed> */
    public function doc(string $duong, string $tenFile): array
    {
        $muc = match (strtolower(pathinfo($tenFile, PATHINFO_EXTENSION))) {
            'docx' => (new DocWord)->doc($duong),
            'pdf' => (new DocPdf)->doc($duong),
            'doc' => throw new RuntimeException('File .doc (Word đời cũ) chưa đọc được — mở bằng Word rồi "Lưu thành" .docx.'),
            default => throw new RuntimeException('Chỉ nhận file Word (.docx) hoặc PDF.'),
        };

        return (new PhanTichChuongTrinh)->phanTich($muc, $tenFile);
    }

    /** Tour đã có cùng tên (so theo đường dẫn không dấu) */
    public function tourTrung(array $kq): ?Tour
    {
        $slug = Str::slug((string) $kq['ten']);
        if ($slug === '') {
            return null;
        }

        return Tour::query()->where('slug', $slug)->first()
            ?? Tour::query()->get(['id', 'name', 'slug'])->first(fn (Tour $t) => Str::slug($t->name) === $slug);
    }

    /**
     * Tạo tour nháp từ kết quả đọc file.
     *
     * @return array{0: Tour, 1: list<string>} tour + tóm tắt đã làm
     */
    public function tao(array $kq): array
    {
        $this->daLuu = [];
        try {
            return DB::transaction(fn () => $this->taoTour($kq));
        } catch (Throwable $e) {
            Storage::disk('public')->delete($this->daLuu);
            throw $e;
        }
    }

    /** @return array{0: Tour, 1: list<string>} */
    private function taoTour(array $kq): array
    {
        $soNgay = max(1, (int) ($kq['so_ngay'] ?: count($kq['lich_trinh']) ?: 1));
        // Ô "Số đêm" trong admin chỉ nhận bằng số ngày hoặc ít hơn 1
        $soDem = min($soNgay, max($soNgay - 1, (int) ($kq['so_dem'] ?? $soNgay - 1)));
        $giaNguoiLon = (int) ($kq['gia_nguoi_lon'] ?? 0);
        $nuocNgoai = $kq['loai'] === 'abroad';

        // Ảnh bìa: ảnh ngang lớn nhất (tỉ lệ hợp khung bìa), không có thì ảnh lớn nhất
        $anh = $kq['anh'];
        $bia = collect($anh)->filter(fn ($a) => $a['rong'] / max(1, $a['cao']) >= 1.2 && $a['rong'] / max(1, $a['cao']) <= 2.2)
            ->sortByDesc(fn ($a) => $a['rong'] * $a['cao'])->keys()->first()
            ?? collect($anh)->sortByDesc(fn ($a) => $a['rong'] * $a['cao'])->keys()->first();

        $tour = Tour::create([
            'name' => Str::limit((string) ($kq['ten'] ?: 'Tour nhập từ file'), 250, ''),
            'slug' => $this->slugMoi((string) ($kq['ten'] ?: 'tour-nhap-tu-file')),
            'type' => $nuocNgoai ? 'abroad' : 'domestic',
            'region' => $kq['vung'],
            'country' => $kq['quoc_gia'],
            'duration_days' => $soNgay,
            'duration_nights' => $soDem,
            'departure_from' => $kq['khoi_hanh_tu'],
            'visa_deadline_days' => $nuocNgoai ? $kq['han_visa_ngay'] : null,
            'adult_price' => $giaNguoiLon,
            'child_price' => $kq['gia_tre_em'] && $kq['gia_tre_em'] <= $giaNguoiLon ? $kq['gia_tre_em'] : null,
            'infant_price' => $kq['gia_em_be'] && $kq['gia_em_be'] <= $giaNguoiLon ? $kq['gia_em_be'] : null,
            'tag' => $kq['nhan'],
            'cover_image' => $bia !== null ? $this->luuAnh($anh[$bia]['du_lieu'], 'tours') : null,
            'highlights' => $kq['noi_bat'],
            'included' => $kq['bao_gom'],
            'excluded' => $kq['khong_bao_gom'],
            'cancellation_policy' => $kq['huy'],
            'notes' => $kq['luu_y'],
            'description' => $kq['mo_ta'],
            // Nháp: chưa hiện ngoài web cho tới khi nhân viên xem lại và đăng bán
            'status' => 'draft',
            'is_featured' => false,
            'sort_order' => 0,
        ]);

        $danhMuc = $this->danhMuc($tour);
        if ($danhMuc) {
            $tour->categories()->sync([$danhMuc->id]);
        }

        foreach (collect($kq['dot'])->unique('ngay') as $d) {
            $this->themDot($tour, $d);
        }

        // Lịch trình từng ngày + ảnh của ngày (ảnh bìa là bản chép riêng — ngày vẫn giữ ảnh đó)
        $soAnhNgay = 0;
        foreach ($kq['lich_trinh'] as $i => $n) {
            $anhNgay = [];
            foreach ($anh as $a) {
                if ($a['ngay'] === $n['ngay'] && count($anhNgay) < self::ANH_MOI_NGAY && ($duong = $this->luuAnh($a['du_lieu'], 'tours/lich-trinh'))) {
                    $anhNgay[] = $duong;
                }
            }
            $soAnhNgay += count($anhNgay);
            $tour->itineraries()->create([
                'day_number' => $n['ngay'],
                'title' => Str::limit($n['tieu_de'].($n['bua'] ? " ({$n['bua']})" : ''), 250, ''),
                'description' => $n['noi_dung'],
                'images' => $anhNgay,
                'sort_order' => $i,
            ]);
        }

        // Ảnh ngoài lịch trình (trang bìa, phần giá…) → thư viện ảnh
        $soAnhChung = 0;
        foreach ($anh as $k => $a) {
            if ($a['ngay'] === null && $k !== $bia && ($duong = $this->luuAnh($a['du_lieu'], 'tours/gallery'))) {
                $tour->images()->create(['path' => $duong, 'alt' => $tour->name, 'sort_order' => $soAnhChung++]);
            }
        }

        $tomTat = array_filter([
            count($kq['dot']).' ngày khởi hành ('.self::SO_CHO.' chỗ mỗi ngày)',
            count($kq['lich_trinh']).' ngày lịch trình',
            $soAnhNgay ? "{$soAnhNgay} ảnh trong lịch trình" : null,
            $tour->cover_image ? '1 ảnh bìa' : null,
            $soAnhChung ? "{$soAnhChung} ảnh thư viện" : null,
            $danhMuc ? 'danh mục "'.$danhMuc->name.'"' : null,
        ]);

        return [$tour, array_values($tomTat)];
    }

    /**
     * Tour đã có: cập nhật giá cơ bản, thêm ngày khởi hành mới, sửa giá ngày
     * đã có. Không đụng nội dung / ảnh / số chỗ đã chỉnh tay.
     *
     * @return list<string> tóm tắt đã làm
     */
    public function capNhat(Tour $tour, array $kq): array
    {
        return DB::transaction(function () use ($tour, $kq) {
            $gia = array_filter([
                'adult_price' => $kq['gia_nguoi_lon'],
                'child_price' => $kq['gia_tre_em'],
                'infant_price' => $kq['gia_em_be'],
            ]);
            $tour->fill($gia);
            // Giá gốc (gạch ngang) phải cao hơn giá bán — giá mới vượt lên thì bỏ giá gốc
            if ($tour->old_price && $tour->old_price <= $tour->adult_price) {
                $tour->old_price = null;
            }
            $doiGia = $tour->isDirty(['adult_price', 'child_price', 'infant_price']);
            $tour->save();

            $moi = 0;
            $suaGia = 0;
            foreach (collect($kq['dot'])->unique('ngay') as $d) {
                $dot = $tour->departures()->whereDate('start_date', $d['ngay'])->first();
                if (! $dot) {
                    $this->themDot($tour, $d);
                    $moi++;

                    continue;
                }
                $giaRieng = $this->giaRieng($tour, $d);
                if ($dot->price_override !== $giaRieng) {
                    $dot->update(['price_override' => $giaRieng]);
                    $suaGia++;
                }
            }

            return array_values(array_filter([
                $doiGia ? 'cập nhật giá tour' : null,
                $moi ? "thêm {$moi} ngày khởi hành" : null,
                $suaGia ? "sửa giá {$suaGia} ngày khởi hành" : null,
            ])) ?: ['giá và ngày khởi hành đã khớp file — không có gì thay đổi'];
        });
    }

    private function themDot(Tour $tour, array $d): void
    {
        TourDeparture::create([
            'tour_id' => $tour->id,
            'start_date' => $d['ngay'],
            'price_override' => $this->giaRieng($tour, $d),
            'seats_total' => self::SO_CHO,
            'seats_left' => self::SO_CHO,
            'status' => 'open',
        ]);
    }

    /** Giá riêng của ngày khởi hành: chỉ ghi khi khác giá người lớn của tour */
    private function giaRieng(Tour $tour, array $d): ?int
    {
        $gia = (int) ($d['gia']['nguoi_lon'] ?? 0);

        return $gia > 0 && $gia !== (int) $tour->adult_price ? $gia : null;
    }

    private function slugMoi(string $ten): string
    {
        $goc = Str::limit(Str::slug($ten) ?: 'tour', 200, '');
        $slug = $goc;
        for ($i = 2; Tour::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$goc}-{$i}";
        }

        return $slug;
    }

    /** Danh mục cùng loại tour, trùng tên nước đến hoặc vùng ("Trung Quốc", "Đông Nam Á"…) */
    private function danhMuc(Tour $tour): ?Category
    {
        $khoa = fn (?string $s) => strtoupper(Str::ascii(trim((string) $s)));
        $ds = Category::query()->where('type', $tour->type)->get(['id', 'name']);
        foreach ([$tour->country, $tour->region] as $ten) {
            if (filled($ten) && ($dm = $ds->first(fn ($c) => $khoa($c->name) === $khoa($ten)))) {
                return $dm;
            }
        }

        return null;
    }

    /**
     * Lưu ảnh lên ổ public dạng JPEG: vẽ lại qua GD (bỏ siêu dữ liệu, chặn
     * file giả dạng ảnh), thu nhỏ ảnh quá lớn, nền trắng cho ảnh trong suốt.
     */
    private function luuAnh(string $duLieu, string $thuMuc): ?string
    {
        // Ảnh quá nhiều điểm ảnh (> 40 triệu) giải nén ra sẽ tràn bộ nhớ — bỏ qua
        $kt = @getimagesizefromstring($duLieu);
        if (! $kt || $kt[0] * $kt[1] > 40_000_000) {
            return null;
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

        $duong = $thuMuc.'/'.Str::random(32).'.jpg';
        Storage::disk('public')->put($duong, $jpg);
        $this->daLuu[] = $duong;

        return $duong;
    }
}
