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

    /**
     * Kiểu VGI: "Tháng 10: 15, 22, 29" (nhiều tháng / ô), COM "800k", cột
     * "CHƯƠNG TRÌNH" là tên tour, nhiều biến thể cùng tour xếp liền nhau, và
     * một tab giữ cả lịch năm nay đã qua (có mốc Tết).
     */
    public static function vgi(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('TOUR ĐƯỜNG BAY');
        $t->fromArray(['TUYẾN', 'CHƯƠNG TRÌNH ', 'THỜI GIAN', 'LỊCH KHỞI HÀNH', 'GIÁ ', 'LINK CHƯƠNG TRÌNH', 'PHƯƠNG TIỆN ', 'COM'], null, 'A2');
        $t->setCellValue('A3', 'PHCT')->setCellValue('B3', "HÀ NỘI - TRƯỜNG SA - PHƯỢNG HOÀNG\n(CẬP NHẬT GIÁ)")->mergeCells('A3:A8')->mergeCells('B3:B8');
        $t->fromArray(['6N5D', "\nTháng 10: 15, 22, 29", '10,490,000', 'TRƯỜNG SA 6N5D', 'CZ', '800k'], null, 'C3');
        $t->getCell('F3')->getHyperlink()->setUrl('https://docs.google.com/document/d/phct-6n5d');
        $t->fromArray(['6N5D', "Tháng 11: 5, 12\nTháng 12: 03, 10", '10,490,000', null, 'CZ', '800k'], null, 'C4');
        $t->fromArray(['6N5D', 'Tháng 12: 31 (TẾT DƯƠNG)', '12,090,000', null, 'CZ', '800k'], null, 'C5');
        // Biến thể khác của cùng tour, liệt kê lại từ tháng 10 (không phải năm sau)
        $t->fromArray(['6N5D', 'Tháng 10: 17, 20', '9,990,000', null, 'CZ', '1000K'], null, 'C6');

        // Tab giữ cả lịch năm 2026 đã qua: không được đẩy sang 2027/2028
        $c = $s->createSheet()->setTitle('ĐÔNG NAM Á');
        $c->fromArray(['TUYẾN', 'CHƯƠNG TRÌNH ', 'THỜI GIAN', 'LỊCH KHỞI HÀNH', 'GIÁ ', 'LINK CHƯƠNG TRÌNH', 'PHƯƠNG TIỆN ', 'COM'], null, 'A2');
        $c->setCellValue('B3', 'HÀ NỘI – SINGAPORE – MALAYSIA')->mergeCells('B3:B6');
        $c->fromArray(['5N4D', "Tháng 01: 21\nTháng 03: 18\nTháng 04: 15", '13,990,000', null, 'VN', '1000K'], null, 'C3');
        $c->fromArray(['5N4D', 'Tháng 08: 31( Quốc Khánh)', '14,990,000', null, 'VN', '1000K'], null, 'C4');
        $c->fromArray(['5N4D', 'Tháng 02: 17 (Tức 1 Tết Âm Lịch)', '17,990,000', null, 'VN', '1000K'], null, 'C5');
        $c->fromArray(['5N4D', 'Tháng 06: 25, 29', '14,990,000', null, 'VN', '1000K'], null, 'C6');

        return self::luu($s, 'vgi');
    }

    /**
     * Kiểu VVT: tựa "Năm 2026", "LỊCH KHỞI HÀNH" gộp 2 cột (tháng | ngày),
     * ngày "21", "12; 27", "8.15.22", "02(26AL)", "Lễ 2/9 | 28", "06 - 10/02/2027",
     * tour nội địa "THỨ 5" hằng tuần.
     */
    public static function vvt(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('CHÂU Á');
        $t->setCellValue('B3', "LỊCH KHỞI HÀNH CHÂU Á - NĂM 2026\nKÍNH GỞI QUÝ ĐỐI TÁC")->mergeCells('B3:H3');
        $t->setCellValue('B4', 'TUYẾN DU LỊCH')->mergeCells('B4:B5');
        $t->setCellValue('C4', 'THỜI GIAN')->mergeCells('C4:C5');
        $t->setCellValue('D4', 'LỊCH KHỞI HÀNH')->mergeCells('D4:E5');
        $t->setCellValue('F4', 'GIÁ')->setCellValue('F5', 'VNĐ');
        $t->setCellValue('G4', 'HK')->mergeCells('G4:G5');
        $t->setCellValue('H4', 'HH')->mergeCells('H4:H5');
        $t->setCellValue('B6', "HÀN QUỐC\nSEOUL MONO\n(LÀNG CỔ - NAMI)")->mergeCells('B6:B11');
        $t->setCellValue('C6', '5N4Đ')->mergeCells('C6:C11');
        $dong = [['Tháng 1', '21'], ['Tháng 8', '14'], ['Lễ 2/9', '28'], ['Tháng 12', '10; 24'], ['Tháng 1', '8.15.22'], ['Tháng 2', '02(26AL)']];
        foreach ($dong as $i => [$thang, $ngay]) {
            $r = 6 + $i;
            $t->setCellValueExplicit("D$r", $thang, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                ->setCellValueExplicit("E$r", $ngay, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                ->setCellValue("F$r", '15.990.000')->setCellValue("G$r", 'VJ')->setCellValue("H$r", '1.000.000');
        }

        $tet = $s->createSheet()->setTitle('TẾT 2027');
        $tet->setCellValue('B3', 'LỊCH KHỞI HÀNH TẾT ĐINH MÙI - NĂM 2027')->mergeCells('B3:H3');
        $tet->fromArray(['TUYẾN DU LỊCH', 'THỜI GIAN', 'LỊCH KHỞI HÀNH', null, 'GIÁ', 'HK', 'HH'], null, 'B4');
        $tet->mergeCells('D4:E4');
        $tet->fromArray(['SINGAPORE - MALAYSIA 5N4Đ', '5N4Đ', 'MÙNG 1', '06 - 10/02/2027', '14,990,000', 'AK', '900,000'], null, 'B6');

        $nd = $s->createSheet()->setTitle('TOUR NỘI ĐỊA');
        $nd->setCellValue('A3', 'CÁC TUYẾN MIỀN BẮC 2026')->mergeCells('A3:I3');
        $nd->fromArray(['CODE', 'TUYẾN DU LỊCH', 'TG', 'KHỞI HÀNH ', 'GIÁ NET ', 'PHÒNG ĐƠN', 'PHỤ THU NN', 'C,TRÌNH', 'GHI CHÚ '], null, 'A4');
        $nd->fromArray(['VL01', 'HÀ NỘI - TRÀNG AN - HẠ LONG', '3N2Đ', 'THỨ 5 ', '3,450,000', '600,000', '400,000', 'CHƯƠNG TRÌNH', "TOUR GHÉP\nChưa bao gồm VMB"], null, 'A5');
        $nd->fromArray(['VL02', 'HÀ NỘI - TRÀNG AN - HẠ LONG', '4N3Đ', null, '3,750,000', '900,000', null, 'CHƯƠNG TRÌNH'], null, 'A6');
        $nd->mergeCells('D5:D6');
        $nd->fromArray(['VL08', 'ĐÀ NẴNG - HỘI AN', '2N1Đ', 'T3.T6. CN', '2,050,000'], null, 'A7');

        return self::luu($s, 'vvt');
    }

    /**
     * Kiểu Triều Hảo: "LỊCH KH", tháng ở cột tiêu đề còn số ngày ở cột bên
     * cạnh KHÔNG có tiêu đề, 3 cột COM (lấy COM AG), giá "16.990K", NHẬN ghi
     * "ĐÓNG", cột không tiêu đề ghi "ĐÓNG ĐOÀN", dòng dữ liệu có chữ giống
     * tiêu đề ("LINK", "Huỷ code").
     */
    public static function trieuHao(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('TQ tuyến khác');
        $t->setCellValue('A2', "TRUNG QUỐC TUYẾN KHÁC  2026\n")->mergeCells('A2:N2');
        $t->fromArray(['STT', 'HÀNH TRÌNH', 'THỜI GIAN', 'PHƯƠNG TIỆN', 'LỊCH KHỞI HÀNH', null, 'GIÁ TOUR', 'TỔNG COM', 'LN', 'COM AG', 'TỔNG CHỖ', 'CHỐT', 'GIỮ', 'NHẬN', 'AG', 'THT'], null, 'A3');
        $t->setCellValue('A4', '2')->setCellValue('B4', "HÀ NỘI – THÀNH ĐÔ – CỬU TRẠI CÂU")->setCellValue('C4', '6N5Đ')->setCellValue('D4', 'Sichuan airline (3U)');
        foreach (['A', 'B', 'C', 'D'] as $c) {
            $t->mergeCells("{$c}4:{$c}7");
        }
        $dong = [
            ['Tháng 10', '24', '17,990', '2,000', '1,000', '1,000', 29, 4, 0, 25, 'LINK', 'LINK', 'Huỷ code'],
            [null, '31', '16.990K', '2,000', '1,000', '1,200', 29, 29, 0, 'ĐÓNG', null, null, null],
            ['Tháng 11', '3', '18,990', '2,000', '1,000', '1,000', 29, 5, 2, 22, null, null, null],
            [null, '10', '18,990', '2,000', '1,000', '1,000', 29, 0, 0, 29, null, null, 'ĐÓNG ĐOÀN'],
        ];
        foreach ($dong as $i => $x) {
            $r = 4 + $i;
            if ($x[0]) {
                $t->setCellValue("E$r", $x[0]);
            }
            $t->setCellValueExplicit("F$r", $x[1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $t->fromArray(array_slice($x, 2), null, "G$r");
        }

        return self::luu($s, 'trieuhao');
    }

    /**
     * Kiểu Hanvina: "Tháng 10: 10, 24, 31" mà ngày 24 tô đỏ + chú thích "Đỏ hết
     * chỗ"; tab khác tô đỏ ngày lễ (không có chú thích); cột SL ghi "Full" /
     * "16/01 Đóng đoàn"; tab không có dòng tiêu đề.
     */
    public static function hanvina(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('TOUR TRUNG QUỐC HCM');
        $t->fromArray([null, 'CHƯƠNG TRÌNH', 'LỊCH TRÌNH ', 'THỜI GIAN ', 'HÀNG KHÔNG', 'NGÀY KHỞI HÀNH', 'GIÁ TOUR ', 'Com'], null, 'A1');
        $t->setCellValue('I1', 'Đỏ hết chỗ');
        $t->setCellValue('A2', 'TUYẾN CỬU TRẠI CÂU')->mergeCells('A2:I2');
        $t->fromArray([null, 'SUN_6N5D_ THÀNH ĐÔ - CỬU TRẠI CÂU', 'CTC 6N5D', '6N5D', 'SICHUAN'], null, 'A3');
        $chu = new \PhpOffice\PhpSpreadsheet\RichText\RichText;
        $chu->createTextRun('THÁNG 10: 10, ');
        $chu->createTextRun('24')->getFont()->getColor()->setRGB('FF0000');
        $chu->createTextRun(', 31');
        $t->getCell('F3')->setValue($chu);
        $t->setCellValue('G3', '16,990,000')->setCellValue('H3', '1,000,000');
        $t->fromArray([null, 'LỆ GIANG - SHANGRILA', 'LỆ GIANG 5N4D', '5N4D', 'RUILI', 'Tháng 11: 03, 17', '18,990,000', '1,000,000', 'FULL'], null, 'A4');

        $hn = $s->createSheet()->setTitle('TOUR TRUNG QUỐC HÀ NỘI');
        $hn->fromArray(['CHƯƠNG TRÌNH', 'THỜI GIAN ', 'PHƯƠNG TIỆN DI CHUYỂN ', 'NGÀY KHỞI HÀNH ', 'GIÁ TOUR', 'COM', null, 'SL'], null, 'A2');
        $hn->fromArray(['HÀ NỘI - THÀNH ĐÔ - CTC', '6N5D', '3U', 'Tháng 10: 17, 24', '19,590,000', '1,000,000', null, '16/01 Đóng đoàn'], null, 'A3');
        $hn->fromArray(['HÀ NỘI - THÀNH ĐÔ - CTC', '6N5D', '3U', 'Tháng 11: 03', '19,590,000', '1,000,000', null, 'Full'], null, 'A4');
        $hn->fromArray(['HÀ NỘI - THÀNH ĐÔ - CTC', '6N5D', '3U', 'Tháng 12: 29 (Tết Dương Lịch)', '18,990,000', '1,000,000'], null, 'A5');
        $hn->getStyle('D5')->getFont()->getColor()->setRGB('FF0000'); // đỏ = ngày lễ ở tab này

        $kt = $s->createSheet()->setTitle('BẮC KINH - THƯỢNG HẢI HCM');
        $kt->setCellValue('A1', 'THƯỢNG HẢI - HÀNG CHÂU - Ô TRẤN (ĐẦU HCM) NOSHOP')->mergeCells('A1:J1');
        $kt->fromArray([null, 'NAM KINH - THƯỢNG HẢI - Ô TRẤN', 'NK T10 NKGPVG', '6N5Đ', 'MU', '[ĐÊM] 31/10/2026', null, '23,590,000', '1,000,000'], null, 'A2');
        $kt->fromArray([null, 'NAM KINH - THƯỢNG HẢI - Ô TRẤN', 'NK T11', '6N5Đ', 'MU', '14/11/2026', null, '23,590,000', '1,000,000', 'full'], null, 'A3');

        return self::luu($s, 'hanvina');
    }

    /** Kiểu VNA: chữ phông đặc biệt (𝐒𝐄𝐎𝐔𝐋), "KH", "2tr5", "DEALINE VISA", "FULL". */
    public static function vna(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('Hàn Quốc - HCM');
        $t->setCellValue('A1', 'VNA TRAVEL KÍNH GỬI QUÝ ĐỐI TÁC');
        $t->fromArray(['MÃ TOUR', 'TUYẾN', "THỜI\n GIAN", 'LINK CT', "HÀNG \nKHÔNG", 'KH', 'GIÁ', 'SỐ CHỖ', 'GIỮ', 'ĐÃ CHỐT ', 'CÒN NHẬN', 'DEALINE VISA', "COM \ntừ"], null, 'A3');
        $t->fromArray(['OSBUS130526/06/TGH', "𝐒𝐄𝐎𝐔𝐋 - 𝐁𝐔𝐒𝐀𝐍\n(ĐI TỐI)", '6N5Đ', 'LINK CT', 'Vietnam Airlines', '13/11', '𝟮𝟬.𝟵𝟵𝟬.𝟬𝟬𝟬', 20, 2, 14, 4, '30/10', '2tr5'], null, 'A4');
        $t->fromArray(['OSBUS050626/06/TGH', "𝐒𝐄𝐎𝐔𝐋 - 𝐁𝐔𝐒𝐀𝐍\n(ĐI TỐI)", '6N5Đ', 'LINK CT', 'Vietnam Airlines', '20.11', '20.990.000', 20, 0, 20, 'FULL', null, '2tr'], null, 'A5');

        return self::luu($s, 'vna');
    }

    /** Kiểu J Travel: THỊ TRƯỜNG + HÀNH TRÌNH, GIÁ BÁN + GIÁ KHUYẾN MÃI. */
    public static function jTravel(): string
    {
        $s = new Spreadsheet;
        $t = $s->getActiveSheet()->setTitle('CỬU TRẠI CÂU');
        $t->setCellValue('B1', 'LỊCH KHỞI HÀNH TOUR 2026 ');
        $t->fromArray(['THỊ TRƯỜNG', 'HÀNH TRÌNH', 'HÃNG BAY ', 'THÁNG', 'NGÀY KHỞI HÀNH', 'GIÁ BÁN', 'COM', 'GIÁ KHUYẾN MÃI ', 'CHƯƠNG TRÌNH CHI TIẾT '], null, 'A2');
        $t->fromArray(['CỬU TRẠI CÂU', "THÀNH ĐÔ – TRÙNG KHÁNH – CỬU TRẠI CÂU\n6N5Đ", 'VJ', 'Tháng 10', '12/10', '25,990,000', '1.000.000', '23,990,000', 'CHƯƠNG TRÌNH CHI TIẾT'], null, 'A3');
        $t->fromArray(['CỬU TRẠI CÂU', "THÀNH ĐÔ – TRÙNG KHÁNH – CỬU TRẠI CÂU\n6N5Đ", 'VJ', 'Tháng 11', '30/12 - 03/01/2027', '27,990,000', '1.000.000', null], null, 'A4');

        return self::luu($s, 'jtravel');
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
