<?php

namespace Modules\Visa\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Support\PhieuThongTin;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIO;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use ZipArchive;

/**
 * Xuất hồ sơ visa ra file ZIP, sắp giống thư mục nhân viên vẫn tự gom tay:
 *
 *   <Tên nhóm>.zip                       (xuất cả đoàn / gia đình)
 *     DANH SÁCH <Tên nhóm>.xlsx
 *     NGUYỄN TÔ THỤY BẢO CHÂU/
 *       Hộ chiếu.pdf, Căn cước.jpg, Hợp đồng lao động (1).pdf, (2).pdf…
 *       Giấy tờ khác/<tên file gốc>
 *       Phiếu thông tin.docx
 *       GIẤY TỜ CÒN THIẾU.txt           (chỉ khi còn thiếu)
 *
 * Trả về đường dẫn file ZIP tạm — bên gọi gửi cho trình duyệt rồi xoá.
 */
class XuatHoSoVisa
{
    /** @return array{0:string,1:string} [đường dẫn zip tạm, tên file tải về] */
    public function motNguoi(VisaCase $hs): array
    {
        $ten = self::tenAnToan($hs->full_name) ?: $hs->code;

        return [$this->dong(fn (ZipArchive $zip) => $this->themHoSo($zip, $hs, $ten.'/')), $ten.'.zip'];
    }

    /**
     * @param  Collection<int, VisaCase>  $dsHoSo
     * @return array{0:string,1:string}
     */
    public function caDoan(Collection $dsHoSo, ?string $tenNhom = null): array
    {
        // Mọi người cùng một nhóm → lấy tên nhóm; lẫn nhiều nhóm → tên theo ngày
        $cacNhom = $dsHoSo->pluck('group_name')->filter()->unique();
        $tenNhom = self::tenAnToan($tenNhom ?: ($cacNhom->count() === 1 ? $cacNhom->first() : ''))
            ?: 'Hồ sơ visa '.now()->format('d-m-Y');

        $duong = $this->dong(function (ZipArchive $zip) use ($dsHoSo, $tenNhom) {
            $zip->addFromString("DANH SÁCH {$tenNhom}.xlsx", $this->excelDanhSach($dsHoSo));

            $daDung = [];
            foreach ($dsHoSo as $hs) {
                $thuMuc = self::tenAnToan($hs->full_name) ?: $hs->code;
                // Hai người trùng họ tên trong đoàn → thêm mã hồ sơ cho khỏi đè nhau
                if (isset($daDung[mb_strtolower($thuMuc)])) {
                    $thuMuc .= ' - '.$hs->code;
                }
                $daDung[mb_strtolower($thuMuc)] = true;
                $this->themHoSo($zip, $hs, $thuMuc.'/', $dsHoSo);
            }
        });

        return [$duong, $tenNhom.'.zip'];
    }

    private function dong(callable $them): string
    {
        $duong = tempnam(sys_get_temp_dir(), 'ho-so-visa-');
        $zip = new ZipArchive;
        if ($zip->open($duong, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file ZIP.');
        }
        $them($zip);
        $zip->close();

        return $duong;
    }

    private function themHoSo(ZipArchive $zip, VisaCase $hs, string $thuMuc, ?Collection $doan = null): void
    {
        $dia = Storage::disk('rieng');
        $mat = [];

        foreach ($hs->checklist ?? [] as $giay) {
            $tep = array_values((array) ($giay['tep'] ?? []));
            $tenGiay = self::tenAnToan($giay['ten'] ?? '') ?: 'Giấy tờ';
            foreach ($tep as $i => $duong) {
                if (! $dia->exists($duong)) {
                    $mat[] = ($giay['ten'] ?? '').' — file '.($giay['ten_tep'][$duong] ?? basename($duong));

                    continue;
                }
                $soThuTu = count($tep) > 1 ? ' ('.($i + 1).')' : '';
                $this->themTep($zip, $dia->path($duong), $thuMuc.$tenGiay.$soThuTu.'.'.self::duoi($duong));
            }
        }

        foreach ($hs->files ?? [] as $duong) {
            if (! $dia->exists($duong)) {
                $mat[] = 'Giấy tờ khác — file '.($hs->file_names[$duong] ?? basename($duong));

                continue;
            }
            $tenGoc = self::tenAnToan(pathinfo($hs->file_names[$duong] ?? '', PATHINFO_FILENAME)) ?: pathinfo($duong, PATHINFO_FILENAME);
            $this->themTep($zip, $dia->path($duong), $thuMuc.'Giấy tờ khác/'.$tenGoc.'.'.self::duoi($duong));
        }

        $zip->addFromString($thuMuc.'Phiếu thông tin.docx', $this->phieuThongTin($hs, $doan));

        $thieu = $hs->giayConThieu();
        if ($thieu || $mat) {
            $dong = ["Hồ sơ {$hs->code} — {$hs->full_name} — visa {$hs->country}", 'Xuất lúc '.now()->format('H:i d/m/Y'), ''];
            if ($thieu) {
                $dong[] = 'GIẤY TỜ CÒN THIẾU:';
                foreach ($thieu as $i => $g) {
                    $dong[] = ($i + 1).'. '.$g;
                }
            }
            if ($mat) {
                $dong[] = '';
                $dong[] = 'FILE KHÔNG CÒN TRÊN MÁY CHỦ (cần xin lại khách):';
                foreach ($mat as $g) {
                    $dong[] = '- '.$g;
                }
            }
            // \r\n + BOM để Notepad trên Windows hiện đúng xuống dòng và tiếng Việt
            $zip->addFromString($thuMuc.'GIẤY TỜ CÒN THIẾU.txt', "\u{FEFF}".implode("\r\n", $dong)."\r\n");
        }
    }

    /** Trùng tên trong cùng thư mục (vd. hai dòng cùng tên giấy) → thêm (2), (3)… */
    private function themTep(ZipArchive $zip, string $nguon, string $dich): void
    {
        $goc = $dich;
        for ($n = 2; $zip->locateName($dich) !== false; $n++) {
            $dich = preg_replace('/(\.[^.\/]+)$/', " ({$n})$1", $goc);
        }
        $zip->addFile($nguon, $dich);
    }

    /** Phiếu thông tin xin visa — các câu hỏi như phiếu giấy, kèm câu trả lời. */
    public function phieuThongTin(VisaCase $hs, ?Collection $doan = null): string
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        $trang = $word->addSection(['marginTop' => 900, 'marginBottom' => 900, 'marginLeft' => 1000, 'marginRight' => 1000]);

        $trang->addText('PHIẾU THÔNG TIN XIN VISA '.mb_strtoupper($hs->country), ['bold' => true, 'size' => 15], ['alignment' => 'center']);
        $trang->addText("Mã hồ sơ {$hs->code}", ['italic' => true, 'size' => 10, 'color' => '555555'], ['alignment' => 'center', 'spaceAfter' => 200]);

        $ttin = $hs->thong_tin ?? [];
        $nguoiDiCung = $ttin['nguoi_di_cung'] ?? null;
        if (! $nguoiDiCung && $doan) {
            $nguoiDiCung = $doan->reject(fn ($k) => $k->is($hs))
                ->map(fn ($k) => trim($k->full_name.($k->birth_date ? ' — '.$k->birth_date->year : '').($k->phone ? ' — '.$k->phone : '')))
                ->implode("\n");
        }

        $nhom = [
            'Thông tin cơ bản' => [
                'Họ và tên' => $hs->full_name,
                'Ngày sinh' => $hs->birth_date?->format('d/m/Y'),
                'Số hộ chiếu' => $hs->passport_no,
                'Ngày hết hạn hộ chiếu' => $hs->passport_expiry?->format('d/m/Y'),
                'Điện thoại di động' => $hs->phone,
                'Email' => $hs->email,
                'Mục đích chuyến đi' => VisaCase::MUC_DICH[$hs->purpose] ?? null,
                'Ngày dự kiến đi' => $hs->travel_date?->format('d/m/Y'),
            ],
        ];
        foreach (PhieuThongTin::NHOM as $tieuDe => $cauHoi) {
            foreach ($cauHoi as $khoa => [$nhan]) {
                $nhom[$tieuDe][$nhan] = $khoa === 'nguoi_di_cung' ? $nguoiDiCung : PhieuThongTin::hienThi($khoa, $ttin[$khoa] ?? null);
            }
        }

        $bang = $trang->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        foreach ($nhom as $tieuDe => $dong) {
            $bang->addRow();
            $bang->addCell(9600, ['gridSpan' => 2, 'bgColor' => 'DCEBF5'])->addText($tieuDe, ['bold' => true]);
            foreach ($dong as $nhan => $giaTri) {
                $bang->addRow();
                $bang->addCell(4200)->addText($nhan, ['size' => 11]);
                $o = $bang->addCell(5400);
                $dongGiaTri = preg_split('/\R/', trim((string) $giaTri)) ?: [''];
                foreach ($dongGiaTri as $d) {
                    $o->addText($d === '' ? '' : $d, ['size' => 11, 'bold' => true]);
                }
            }
        }

        $trang->addTextBreak();
        $trang->addText('Chữ ký của đương đơn: ………………………………    Họ và tên: ………………………………', ['size' => 11]);

        $duong = tempnam(sys_get_temp_dir(), 'phieu-');
        WordIO::createWriter($word, 'Word2007')->save($duong);
        $noiDung = file_get_contents($duong);
        @unlink($duong);

        return $noiDung;
    }

    /** DANH SÁCH đoàn — một dòng một người, cột theo thông tin đại sứ quán hay hỏi. */
    public function excelDanhSach(Collection $dsHoSo): string
    {
        $cot = [
            'STT', 'Họ và tên', 'Giới tính', 'Ngày sinh', 'Nơi sinh', 'Số hộ chiếu', 'Ngày cấp HC', 'Ngày hết hạn HC',
            'Số CCCD', 'Điện thoại', 'Email', 'Nghề nghiệp', 'Nơi làm việc', 'Tình trạng hôn nhân',
            'Nước', 'Mục đích', 'Ngày đi', 'Mã hồ sơ', 'Trạng thái', 'Giấy tờ đã nhận', 'Còn thiếu',
        ];

        $so = new Spreadsheet;
        $bang = $so->getActiveSheet()->setTitle('Danh sách');
        $bang->fromArray($cot, null, 'A1');

        $hang = 2;
        foreach ($dsHoSo->values() as $i => $hs) {
            $t = $hs->thong_tin ?? [];
            [$co, $can] = $hs->tienDoGiayTo();
            $bang->fromArray([
                $i + 1,
                $hs->full_name,
                PhieuThongTin::hienThi('gioi_tinh', $t['gioi_tinh'] ?? null),
                $hs->birth_date?->format('d/m/Y'),
                $t['noi_sinh'] ?? '',
                $hs->passport_no,
                PhieuThongTin::hienThi('ngay_cap_ho_chieu', $t['ngay_cap_ho_chieu'] ?? null),
                $hs->passport_expiry?->format('d/m/Y'),
                $t['so_cccd'] ?? '',
                $hs->phone,
                $hs->email,
                $t['nghe_nghiep'] ?? (VisaCase::DOI_TUONG[$hs->profile] ?? ''),
                $t['noi_lam_viec'] ?? '',
                PhieuThongTin::hienThi('hon_nhan', $t['hon_nhan'] ?? null),
                $hs->country,
                VisaCase::MUC_DICH[$hs->purpose] ?? '',
                $hs->travel_date?->format('d/m/Y'),
                $hs->code,
                VisaCase::TRANG_THAI[$hs->status] ?? $hs->status,
                $can ? "{$co}/{$can}" : '',
                implode('; ', $hs->giayConThieu()),
            ], null, 'A'.$hang);
            // Số hộ chiếu / CCCD / SĐT để dạng chữ — Excel không được tự bỏ số 0 đầu
            foreach (['F', 'I', 'J'] as $c) {
                $bang->getCell($c.$hang)->setValueExplicit((string) $bang->getCell($c.$hang)->getValue(), DataType::TYPE_STRING);
            }
            $hang++;
        }

        $cuoi = $bang->getHighestColumn();
        $bang->getStyle("A1:{$cuoi}1")->getFont()->setBold(true);
        $bang->getStyle("A1:{$cuoi}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCEBF5');
        foreach (range('A', $cuoi) as $c) {
            $bang->getColumnDimension($c)->setAutoSize(true);
        }
        $bang->freezePane('C2');

        $duong = tempnam(sys_get_temp_dir(), 'ds-');
        (new Xlsx($so))->save($duong);
        $noiDung = file_get_contents($duong);
        @unlink($duong);

        return $noiDung;
    }

    /** Tên file / thư mục an toàn trên Windows, macOS: bỏ ký tự cấm, giữ dấu tiếng Việt. */
    public static function tenAnToan(?string $ten): string
    {
        $ten = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', (string) $ten);
        $ten = trim(preg_replace('/\s+/u', ' ', $ten), ' .');

        return Str::limit($ten, 100, '');
    }

    private static function duoi(string $duong): string
    {
        return strtolower(pathinfo($duong, PATHINFO_EXTENSION)) ?: 'bin';
    }
}
