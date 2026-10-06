<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Dựng file xlsx MÔ PHỎNG 3 kiểu sheet liên minh thật (V1, AZ, M Tour) để
 * kiểm thử bộ đọc — cùng bố cục ô gộp, tiêu đề, cách ghi ngày/giá/chỗ như
 * bản thật, nhưng dữ liệu rút gọn và không chứa thông tin liên hệ đối tác.
 */
class MauSheetLienMinh
{
    /** Kiểu V1 Travel: tiêu đề 2 tầng, khối tour gộp, SIZE/SURE/HOLD/NHẬN. */
    public static function v1(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('1.HÀN');
        self::v1TieuDe($t, 4, 'HÀN QUỐC');
        $t->setCellValue('A3', 'Ngày cập nhật:')->setCellValue('B3', '06/10/2026');

        // Tour 1: 8 dòng, có dòng cũ (đã qua), FULL, HUỶ ĐOÀN, giá cũ/mới, năm mới
        self::khoi($t, 6, 13, '1', "SEOUL - NAMI - EVERLAND | 5N4D | TỐI THỨ 4\n(Hái trái cây / Đào sâm / Hero Show)\n\nVietjet Air\nVJ862 SGN ICN 0235 0940\nVJ861 ICN SGN 2115 0045");
        self::dong($t, 6, 'Tháng 4:', '08/04', 17990, 1000, 28, 28, 0, '-', 'FULL');
        self::dong($t, 7, null, '15/04', "Giá cũ: 17990\nGiá mới: 16990", 1000, 27, 27, 0, '-', 'FULL');
        $t->mergeCells('D6:D7');
        self::dong($t, 8, 'Tháng 9:', '16/09', 15990, 1000, 29, 23, 8, '(2)', 'HUỶ ĐOÀN');
        self::dong($t, 9, 'Tháng 10:', '14/10', "Giá cũ: 18990\nGiá mới: 17990", 1000, 29, 0, 0, 29, 'Mùa Thu');
        self::dong($t, 10, null, '28/10', 18990, 1000, 29, 29, 0, '-', 'FULL');
        $t->mergeCells('D9:D10');
        self::dong($t, 11, 'Tháng 11:', '04/11', 18990, 1000, 29, 5, 2, 22, null);
        self::dong($t, 12, 'Tháng 12:', '30/12', 21990, 1000, 29, 26, 0, 3, 'TẾT DƯƠNG LỊCH');
        self::dong($t, 13, 'T1/2027', '*01/01', 20990, 1000, 24, 0, 0, 24, null);
        $t->setCellValue('M6', 'TẢI VỀ');
        $t->getCell('M6')->getHyperlink()->setUrl('https://drive.google.com/drive/folders/chuong-trinh-seoul');

        // Dòng tháng không có ngày → bỏ qua
        $t->setCellValue('D14', 'Tháng 5:')->setCellValue('F14', '-');

        // Tour 2: giá "Chờ cập nhật", cột NHẬN trống → tính SIZE − SURE − HOLD
        self::khoi($t, 15, 16, '2', "BUSAN - GYEONGJU - SEOUL - NAMI | 6N5Đ\n(Tàu KTX + Tàu ngắm biển Haeundae)\nVietnam Airlines\n VN422 SGN PUS 0110 0710");
        self::dong($t, 15, 'Tháng 12:', '25/12', 'Chờ cập nhật', 1200, 24, 3, 1, null, null);
        self::dong($t, 16, 'T2/27:', '*05/02', 21990, 1200, 24, 0, 0, 24, 'M7 TẾT');

        // Tab Tết lặp lại tour 1 (số chỗ ít hơn) + tour Nhật
        $tet = $s->createSheet()->setTitle('Tour Tết 2027');
        self::v1TieuDe($tet, 2, 'TẾT DƯƠNG LỊCH + TẾT NGUYÊN ĐÁN 2027');
        $tet->setCellValue('A4', 'HÀN QUỐC')->mergeCells('A4:P4');
        self::khoi($tet, 5, 5, '1', "SEOUL - NAMI - EVERLAND | 5N4D | TỐI THỨ 4\n(Hái trái cây / Đào sâm / Hero Show)\nVietjet Air\nVJ862 SGN ICN 0235 0940");
        self::dong($tet, 5, 'Tháng 12:', '30/12', 21990, 1000, 29, 27, 0, 2, 'TẾT DƯƠNG LỊCH');
        $tet->setCellValue('A6', 'NHẬT BẢN')->mergeCells('A6:P6');
        self::khoi($tet, 7, 8, '1', "TOKYO - FUJI - NAGOYA - KYOTO - OSAKA\n6 NGÀY 5 ĐÊM\nVietnam Airlines\nVN306 SGN NRT (00:20 - 07:45)\n(Có mặt lúc 21h00 ngày khởi hành)");
        self::dong($tet, 7, 'Tháng 12: ', '29/12', 43990, 2000, 25, 0, 0, 25, 'Tết DL');
        self::dong($tet, 8, 'T2/2027', '03/02', 39990, 2000, 25, 4, 0, 21, '27 Tết');

        // Tab ẩn không được đọc
        $an = $s->createSheet()->setTitle('Nháp');
        self::v1TieuDe($an, 2, 'NHÁP');
        self::khoi($an, 4, 4, '1', 'TOUR NHÁP');
        self::dong($an, 4, 'Tháng 12:', '20/12', 9990, 500, 10, 0, 0, 10, null);
        $an->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return self::luu($s, 'v1');
    }

    /** Kiểu AZ: bảng phẳng, mục "THÁNG mm/yyyy", ô ngày Excel lưu sai năm. */
    public static function az(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('SÀI GÒN 2026');
        $t->setCellValue('A1', " NHẬT BẢN AZ \nLiên hệ: điều hành")->mergeCells('A1:J1');
        $t->fromArray(['STT', 'CUNG ĐƯỜNG', 'THỜI GIAN', 'CHƯƠNG TRÌNH', 'HK', 'NGÀY KHỞI HÀNH', 'DEADLINE VISA', 'SỐ CHỖ CÒN NHẬN', 'GIÁ BÁN', 'GHI CHÚ'], null, 'A2');
        $t->setCellValue('A3', 'THÁNG 10/2026')->mergeCells('A3:J3');
        // Ô ngày lưu năm 2025 (chép từ năm ngoái) nhưng hiện "19/10"
        self::azDong($t, 4, 1, "ĐẾN HOKKAIDO NGẮM MÙA THU LÁ ĐỎ\nASAHIKAWA –FURANO-SAPPORO-NOBORIBETSU", '7N6Đ', 'JL', '2025-10-19', 2, 50900000, null);
        self::azDong($t, 5, 2, "ĐẾN HOKKAIDO NGẮM MÙA THU LÁ ĐỎ\nASAHIKAWA –FURANO-SAPPORO-NOBORIBETSU", '7N6Đ', 'JL', '2025-10-21', 30, 50900000, 'TẠM NGƯNG NHẬN KHÁCH');
        $t->setCellValue('A6', 'THÁNG 11/2026')->mergeCells('A6:J6');
        // Ô hiện "11/11" theo kiểu tháng/ngày → vẫn là 11/11
        self::azDong($t, 7, 1, "MÙA THU LÁ ĐỎ + GEISHA\nOSAKA - KYOTO - NAGOYA- PHÚ SỸ - TOKYO", '5N5Đ', 'VNA', '2026-11-11', 21, 39900000, null);
        self::azDong($t, 8, 2, "MÙA THU LÁ ĐỎ + GEISHA\nOSAKA - KYOTO - NAGOYA- PHÚ SỸ - TOKYO", '5N5Đ', 'VNA', '2026-11-23', 0, 40900000, 'ĐOÀN IN');
        $t->getCell('D7')->getHyperlink()->setUrl('https://docs.google.com/document/d/chuong-trinh-mua-thu');

        // Tab Hà Nội: không có cột số chỗ, ô hiện "11/10" nhưng lưu 10/11
        $hn = $s->createSheet()->setTitle('HÀ NỘI 2026');
        $hn->fromArray(['STT', 'CUNG ĐƯỜNG', 'THỜI GIAN', 'CHƯƠNG TRÌNH', 'HK', 'NGÀY KHỞI HÀNH', 'DEADLINE VISA', 'GIÁ BÁN', 'GHI CHÚ'], null, 'A2');
        $hn->setCellValue('A3', 'THÁNG 10/2026')->mergeCells('A3:I3');
        $hn->fromArray([3, 'CĐV KIX//HND về chiều', '6N5Đ', 'Link tải', 'VN'], null, 'A4');
        $hn->setCellValue('F4', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime('2026-11-10')));
        // Lưu 10/11 với định dạng tháng/ngày → hiện "11/10"
        $hn->getStyle('F4')->getNumberFormat()->setFormatCode('mm/dd');
        $hn->setCellValue('G4', '24/09')->setCellValue('H4', '39,900,000');

        return self::luu($s, 'az');
    }

    /** Kiểu M Tour: tên tour ở dòng gộp, nhiều ngày trong một ô, không có số chỗ. */
    public static function mTour(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('HÀN QUỐC');
        $t->setCellValue('A1', 'CHUYẾN BAY ')->mergeCells('A1:A2');
        $t->setCellValue('B1', 'NGÀY KHỞI HÀNH')->mergeCells('B1:C1');
        $t->setCellValue('D1', 'GIÁ TRỌN GÓI (VND)')->mergeCells('D1:F1');
        $t->setCellValue('G1', 'LINK TẢI LỊCH TRÌNH')->mergeCells('G1:G2');
        $t->fromArray(['THÁNG', 'NGÀY ', 'NGƯỜI LỚN', "TRẺ EM\n 2 - 11 tuổi", 'EM BÉ'], null, 'B2');

        $t->setCellValue('A3', 'TOUR MÙA THU 2026 ')->mergeCells('A3:G3');
        $t->setCellValue('A4', 'SEOUL - NAMI - LÀNG CỔ HANBOK - EVERLAND - HẢI ÂU ')->mergeCells('A4:G4');
        $t->setCellValue('A5', "Chuyến đi\nVJ862 HCM - INC 02:35 - 09:40 \nChuyến về\nVJ861 INC - HCM\n21:20 - 00:30+1")->mergeCells('A5:A6');
        $t->setCellValue('B5', 'THÁNG 10');
        $t->setCellValue('C5', "09/10 – 13/10\n20/10 – 24/10\n03/11 – 07/11\n");
        $t->setCellValue('B6', 'THÁNG 11');
        $t->setCellValue('C6', "\n03/11 – 07/11\n13/11 – 17/11\n");
        foreach (['D' => '17,900,000', 'E' => '16,110,000', 'F' => '6,265,000', 'G' => 'SEOUL '] as $c => $v) {
            $t->setCellValue($c.'5', $v)->mergeCells("{$c}5:{$c}6");
        }
        $t->getCell('G5')->getHyperlink()->setUrl('https://drive.google.com/file/d/lich-trinh-seoul');

        $t->setCellValue('A7', 'TOUR MÙA ĐÔNG 2026 ')->mergeCells('A7:G7');
        $t->setCellValue('A8', "Chuyến đi \nVJ862  SGN - ICN 02:40 – 09:50")->mergeCells('A8:A9');
        $t->setCellValue('B8', 'THÁNG 12')->mergeCells('B8:B9');
        $t->fromArray(["22/12 - 26/12\n( NOEL)", '20,900,000', '18,810,000', '7,315,000', 'SEOUL 22/12 '], null, 'C8');
        $t->fromArray(['18/12 - 22/12 ', '18,900,000', '17,010,000', '6,165,000', 'SEOUL 18/12'], null, 'C9');

        // "Lễ 02/09" là nhãn ngày lễ, không phải ngày đi
        $t->setCellValue('A10', 'TOUR TẾT 2027 ')->mergeCells('A10:G10');
        $t->setCellValue('A11', "Chuyến đi \nVJ862 SGN - ICN")->setCellValue('B11', 'THÁNG 02/2027');
        $t->fromArray(["Lễ 02/09\n06/02 – 10/02/2027\n (mùng 1 – mùng 5)", '22,990,000', '20,610,000', '8,015,000', 'SEOUL - TẾT 2027 '], null, 'C11');

        return self::luu($s, 'mtour');
    }

    private static function v1TieuDe(Worksheet $t, int $dong, string $tieuDe): void
    {
        $t->setCellValue('A1', $tieuDe)->mergeCells('A1:P1');
        $d2 = $dong + 1;
        $t->setCellValue("A$dong", 'STT')->mergeCells("A$dong:A$d2");
        $t->setCellValue("B$dong", 'TÊN')->mergeCells("B$dong:C$d2");
        $t->setCellValue("D$dong", 'LỊCH KHỞI HÀNH')->mergeCells("D$dong:E$dong");
        $t->setCellValue("F$dong", ' GIÁ ')->mergeCells("F$dong:F$d2");
        $t->setCellValue("G$dong", ' COM ')->mergeCells("G$dong:G$d2");
        $t->setCellValue("H$dong", ' CHECK CHỖ ')->mergeCells("H$dong:K$dong");
        $t->setCellValue("L$dong", 'NOTE')->mergeCells("L$dong:L$d2");
        $t->setCellValue("M$dong", 'CHƯƠNG TRÌNH')->mergeCells("M$dong:P$dong");
        $t->fromArray(['THÁNG', 'NGÀY', null, null, ' SIZE ', 'SURE', 'HOLD', 'NHẬN', null, 'WORD NOLOGO', 'PDF NOLOGO', 'WORD V-ONE', 'PDF V-ONE'], null, "D$d2");
    }

    private static function khoi(Worksheet $t, int $tu, int $den, string $stt, string $ten): void
    {
        $t->setCellValue("A$tu", $stt)->setCellValue("B$tu", $ten)->setCellValue("C$tu", 'Ngày khởi hành là ngày có mặt sân bay');
        if ($den > $tu) {
            $t->mergeCells("A$tu:A$den")->mergeCells("B$tu:B$den")->mergeCells("C$tu:C$den");
        }
    }

    private static function dong(Worksheet $t, int $r, ?string $thang, string $ngay, $gia, ?int $com, int $size, int $sure, int $hold, $nhan, ?string $note): void
    {
        if ($thang !== null) {
            $t->setCellValue("D$r", $thang);
        }
        $t->setCellValueExplicit("E$r", $ngay, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $t->setCellValue("F$r", $gia)->setCellValue("G$r", $com)->setCellValue("H$r", $size)->setCellValue("I$r", $sure)->setCellValue("J$r", $hold);
        if ($nhan !== null) {
            $t->setCellValue("K$r", $nhan);
        }
        if ($note !== null) {
            $t->setCellValue("L$r", $note);
        }
        foreach (['F', 'G'] as $c) {
            $t->getStyle("$c$r")->getNumberFormat()->setFormatCode('#,##0');
        }
    }

    private static function azDong(Worksheet $t, int $r, int $stt, string $ten, string $tg, string $hk, string $ngay, int $con, int $gia, ?string $ghiChu): void
    {
        $t->fromArray([$stt, $ten, $tg, 'Link tải', $hk], null, "A$r");
        $t->setCellValue("F$r", \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($ngay)));
        $t->getStyle("F$r")->getNumberFormat()->setFormatCode('d/m');
        $t->setCellValue("H$r", $con)->setCellValue("I$r", $gia)->setCellValue("J$r", $ghiChu);
        $t->getStyle("I$r")->getNumberFormat()->setFormatCode('#,##0\ [$đ-42A]');
    }

    private static function luu(Spreadsheet $s, string $ten): string
    {
        $duong = sys_get_temp_dir()."/lm-$ten-".uniqid().'.xlsx';
        (new Xlsx($s))->save($duong);
        $s->disconnectWorksheets();

        return $duong;
    }
}
