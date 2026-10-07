<?php

namespace Modules\Visa\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Http\Controllers\PhieuXacNhanController;
use Modules\Page\Models\Setting;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use PhpOffice\PhpWord\IOFactory as WordIO;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use ZipArchive;

/**
 * Xuất mẫu checklist thành file gửi khách: "Danh sách giấy tờ" dạng Word
 * và/hoặc PDF (đầu trang là băng rôn công ty), đóng ZIP cùng các file mẫu đính
 * kèm (tờ khai, thư mời mẫu…). Chọn nhiều mẫu → mỗi mẫu một thư mục.
 */
class XuatMauChecklist
{
    public const DINH_DANG = [
        'word' => 'Word (.docx) — sửa được trước khi gửi khách',
        'pdf' => 'PDF — gửi khách ngay',
        'ca_hai' => 'Cả Word và PDF',
    ];

    /** @var array<string, mixed>|null */
    private ?array $cauHinh = null;

    /**
     * @param  Collection<int, VisaChecklist>  $dsMau
     * @return array{0: string, 1: string} [file ZIP tạm, tên tải về]
     */
    public function zip(Collection $dsMau, string $dinhDang = 'word'): array
    {
        $duong = tempnam(sys_get_temp_dir(), 'mau-checklist-');
        $zip = new ZipArchive;
        if ($zip->open($duong, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file ZIP.');
        }

        $motMau = $dsMau->count() === 1;
        $daDung = [];
        foreach ($dsMau as $mau) {
            $ten = XuatHoSoVisa::tenAnToan($mau->name) ?: 'Mau '.$mau->id;
            // Hai mẫu trùng tên (bản sao) → thêm số cho khỏi đè thư mục
            $goc = $ten;
            for ($i = 2; isset($daDung[$ten]); $i++) {
                $ten = $goc.' ('.$i.')';
            }
            $daDung[$ten] = true;
            $thuMuc = $motMau ? '' : $ten.'/';

            if ($dinhDang !== 'pdf') {
                $zip->addFromString($thuMuc.'Danh sách giấy tờ - '.$ten.'.docx', $this->word($mau));
            }
            if ($dinhDang !== 'word') {
                $zip->addFromString($thuMuc.'Danh sách giấy tờ - '.$ten.'.pdf', $this->pdf($mau));
            }

            foreach ((array) $mau->attachments as $tep) {
                if (! Storage::disk('rieng')->exists($tep)) {
                    continue;
                }
                $tenTep = XuatHoSoVisa::tenAnToan($mau->attachment_names[$tep] ?? basename($tep)) ?: basename($tep);
                if (! pathinfo($tenTep, PATHINFO_EXTENSION)) {
                    $tenTep .= '.'.pathinfo($tep, PATHINFO_EXTENSION);
                }
                $zip->addFile(Storage::disk('rieng')->path($tep), $thuMuc.'File mẫu/'.$tenTep);
            }
        }
        $zip->close();

        $tenZip = $motMau
            ? 'Checklist - '.(XuatHoSoVisa::tenAnToan($dsMau->first()->name) ?: 'mau').'.zip'
            : 'Checklist visa - '.$dsMau->count().' mẫu.zip';

        return [$duong, $tenZip];
    }

    /**
     * Giấy tờ theo nhóm, giữ thứ tự trong mẫu.
     *
     * @return array<string, list<array{ten: string, ghi_chu: ?string}>>
     */
    public static function theoNhom(VisaChecklist $mau): array
    {
        $nhom = [];
        foreach ((array) $mau->items as $g) {
            if (blank($g['ten'] ?? null)) {
                continue;
            }
            $chiCho = collect((array) ($g['chi_cho'] ?? []))->map(fn ($d) => VisaCase::DOI_TUONG[$d] ?? $d)->implode(', ');
            $nhom[$g['nhom'] ?? ''][] = [
                'ten' => $g['ten'],
                'ghi_chu' => trim(collect([$g['ghi_chu'] ?? null, $chiCho ? 'Chỉ áp dụng: '.$chiCho : null])->filter()->implode("\n")) ?: null,
            ];
        }

        return $nhom;
    }

    public static function moTa(VisaChecklist $mau): string
    {
        return collect([
            $mau->purpose ? 'Mục đích: '.(VisaCase::MUC_DICH[$mau->purpose] ?? $mau->purpose) : null,
            $mau->profile ? 'Đối tượng: '.(VisaCase::DOI_TUONG[$mau->profile] ?? $mau->profile) : null,
        ])->filter()->implode(' · ');
    }

    public function word(VisaChecklist $mau): string
    {
        $cauHinh = $this->cauHinh();
        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        $trang = $word->addSection(['marginTop' => 700, 'marginBottom' => 900, 'marginLeft' => 1000, 'marginRight' => 1000]);

        $anhTam = null;
        if ($anh = PhieuXacNhanController::anhDau($cauHinh['pdf_header_image'] ?? null)) {
            $anhTam = tempnam(sys_get_temp_dir(), 'dau-');
            file_put_contents($anhTam, base64_decode(substr($anh, strpos($anh, ',') + 1)));
            $trang->addImage($anhTam, ['width' => 480, 'alignment' => 'center']);
        } else {
            $trang->addText($cauHinh['company_name'] ?? 'PSV Travel', ['bold' => true, 'size' => 14, 'color' => '0169A9']);
        }

        $trang->addText('DANH SÁCH GIẤY TỜ XIN VISA '.mb_strtoupper((string) $mau->country), ['bold' => true, 'size' => 15], ['alignment' => 'center', 'spaceBefore' => 200]);
        if ($moTa = self::moTa($mau)) {
            $trang->addText($moTa, ['italic' => true, 'size' => 11, 'color' => '444444'], ['alignment' => 'center']);
        }
        $trang->addText('Họ tên khách: ……………………………………    Ngày nhận: ……/……/………', ['size' => 11], ['spaceBefore' => 200, 'spaceAfter' => 120]);

        $bang = $trang->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        $bang->addRow(null, ['tblHeader' => true]);
        foreach ([[700, 'STT'], [4300, 'Giấy tờ cần chuẩn bị'], [3800, 'Ghi chú'], [900, 'Đã có']] as [$rong, $nhan]) {
            $bang->addCell($rong, ['bgColor' => '0169A9'])->addText($nhan, ['bold' => true, 'color' => 'FFFFFF', 'size' => 11], ['alignment' => 'center']);
        }
        $stt = 0;
        foreach (self::theoNhom($mau) as $tenNhom => $dsGiay) {
            if ($tenNhom !== '') {
                $bang->addRow();
                $bang->addCell(9700, ['gridSpan' => 4, 'bgColor' => 'DCEBF5'])->addText(mb_strtoupper($tenNhom), ['bold' => true, 'size' => 11]);
            }
            foreach ($dsGiay as $g) {
                $bang->addRow(null, ['cantSplit' => true]);
                $bang->addCell(700)->addText((string) ++$stt, ['size' => 11], ['alignment' => 'center']);
                $bang->addCell(4300)->addText($g['ten'], ['size' => 11, 'bold' => true]);
                $o = $bang->addCell(3800);
                foreach (preg_split('/\R/', (string) $g['ghi_chu']) as $dong) {
                    $o->addText($dong, ['size' => 10, 'color' => '333333']);
                }
                $bang->addCell(900)->addText('☐', ['size' => 14], ['alignment' => 'center']);
            }
        }

        if (filled($mau->note)) {
            $trang->addText('Lưu ý', ['bold' => true, 'size' => 12], ['spaceBefore' => 240]);
            foreach (preg_split('/\R/', trim($mau->note)) as $dong) {
                $trang->addText($dong, ['size' => 11]);
            }
        }
        $trang->addText($this->lienHe(), ['italic' => true, 'size' => 10, 'color' => '555555'], ['spaceBefore' => 240]);

        $tam = tempnam(sys_get_temp_dir(), 'docx-');
        WordIO::createWriter($word, 'Word2007')->save($tam);
        $noiDung = file_get_contents($tam);
        @unlink($tam);
        $anhTam && @unlink($anhTam);

        return $noiDung;
    }

    public function pdf(VisaChecklist $mau): string
    {
        $cauHinh = $this->cauHinh();

        return Pdf::loadView('pdf.mau-checklist-visa', [
            'mau' => $mau,
            'nhom' => self::theoNhom($mau),
            'moTa' => self::moTa($mau),
            'anhDau' => PhieuXacNhanController::anhDau($cauHinh['pdf_header_image'] ?? null),
            'tenCongTy' => $cauHinh['legal_name'] ?? $cauHinh['company_name'] ?? 'PSV Travel',
            'lienHe' => $this->lienHe(),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }

    private function lienHe(): string
    {
        $c = $this->cauHinh();

        return trim(collect([
            'Mọi thắc mắc vui lòng liên hệ '.($c['company_name'] ?? 'PSV Travel'),
            filled($c['hotline'] ?? null) ? 'Hotline/Zalo: '.$c['hotline'] : null,
            filled($c['email'] ?? null) ? 'Email: '.$c['email'] : null,
        ])->filter()->implode(' — '));
    }

    /** @return array<string, mixed> */
    private function cauHinh(): array
    {
        return $this->cauHinh ??= Setting::query()
            ->whereIn('key', ['company_name', 'legal_name', 'hotline', 'email', 'pdf_header_image'])
            ->pluck('value', 'key')->all();
    }
}
