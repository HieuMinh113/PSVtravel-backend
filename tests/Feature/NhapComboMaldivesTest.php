<?php

namespace Tests\Feature;

use App\Filament\Resources\MaldivesCombos\Pages\EditMaldivesCombo;
use App\Filament\Resources\MaldivesCombos\Pages\ListMaldivesCombos;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesResort;
use Modules\Maldives\Services\NhapCombo\NhapComboTuFile;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;
use ZipArchive;

/**
 * Nhập combo Maldives từ file chương trình của đối tác (Word / PDF): resort +
 * hạng sao, hạng phòng (kể cả "chỉ người lớn"), bảng giá USD hạng phòng × ngày
 * (cột hai ngày → hai ngày, NA = không bán, bỏ ngày đã qua), phụ thu theo nơi
 * đi (khoảng giá → mức thấp nhất), lịch bay, lịch trình, tiện ích, bao gồm /
 * không bao gồm, lưu ý, quy định. Nhập lại → chỉ cập nhật bảng giá.
 *
 * File được dựng ngay trong bài kiểm thử (không dùng file thật của đối tác).
 */
class NhapComboMaldivesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-13 09:00'));
        Storage::fake('public');
    }

    // ------------------------------------------------------------------
    // Dựng file .docx tối giản giống file combo của đối tác
    // ------------------------------------------------------------------

    private function p(string $chu, bool $dam = false, bool $gach = false): string
    {
        return '<w:p>'.($gach ? '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>' : '')
            .'<w:r>'.($dam ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.htmlspecialchars($chu).'</w:t></w:r></w:p>';
    }

    /** Ô có nhiều dòng ("\n") = nhiều đoạn trong ô */
    private function bang(array $hang): string
    {
        $xml = '<w:tbl>';
        foreach ($hang as $o) {
            $xml .= '<w:tr>'.implode('', array_map(fn ($c) => '<w:tc>'.implode('', array_map(fn ($d) => $this->p($d), explode("\n", $c))).'</w:tc>', $o)).'</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    private function anh(): string
    {
        return '<w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill>'
            .'<a:blip r:embed="rIdAnh1"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    private function pngMau(int $rong, int $cao): string
    {
        $im = imagecreatetruecolor($rong, $cao);
        for ($x = 0; $x < $rong; $x += 40) {
            imagefilledrectangle($im, $x, 0, $x + 20, $cao, imagecolorallocate($im, $x % 255, 120, 200));
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** @param  array<int, list<string>>  $themHang  hàng giá thêm vào bảng */
    private function docx(string $giaWater = '2.631', array $themHang = []): string
    {
        $than = implode('', [
            $this->p('SUN SIYAM WORLD RESORT 5*', dam: true),
            $this->p('Thời gian: 05 ngày – 04 đêm', dam: true),
            $this->p('Hàng không: Singapore Airlines – sử dụng Thủy Phi Cơ', dam: true),
            $this->p('Khởi hành: 04/11/2026 & 08/02; 09/02/2027', dam: true),
            $this->p('SIYAM WORLD RESORT 5*', dam: true),
            $this->p('Siyam World Maldives là một trong những resort nghỉ dưỡng 5 sao sang trọng tại Maldives, thuộc Noonu Atoll.'),
            $this->bang([
                ['Lịch bay từ Hà Nội', 'Lịch bay từ Hồ Chí Minh'],
                ['SQ 191 HANSIN 1235 1705', 'SQ 183 SGNSIN 1555 1855'],
                ['SQ 438 SINMLE 2045 2210', 'SQ 438 SINMLE 2045 2210'],
            ]),
            $this->p('LỊCH TRÌNH', dam: true),
            $this->p('01 ĐÊM KHÁCH SẠN + 02 ĐÊM RESORT SIYAM WORLD 5*, WOW ALL INCLUSIVE', dam: true),
            $this->p('NGÀY 01: VIỆT NAM - KHÁCH SẠN Ở THỦ ĐÔ MALE (Bữa tối trên máy bay)', dam: true),
            $this->p('10h00: Quý khách có mặt tại sân bay.'),
            $this->anh(),
            $this->p('NGÀY 02: SIYAM WORLD 5* (Bữa sáng, bữa trưa, bữa tối, Đồ uống)', dam: true),
            $this->p('11h00: Thủy phi cơ đưa khách ra resort.'),
            $this->bang([['GIẢI TRÍ', 'SỨC KHỎE'], ["- Live music and DJ.\n- Karaoke nights.", "- Group yoga.\n- Aqua aerobics."]]),
            $this->p('NGÀY 03: MALE – VIỆT NAM (Bữa sáng)', dam: true),
            $this->p('Trả phòng, bay về Việt Nam.'),
            $this->p('CHI PHÍ KHÔNG BAO GỒM:', dam: true),
            $this->p('Bữa tối ngày 3 tự túc.', gach: true),
            $this->p('GIÁ COMBO DÀNH CHO 1 KHÁCH (USD/KHÁCH)', dam: true),
            $this->p('Khách khởi hành từ TP HCM phụ thu $100 - $120', dam: true),
            $this->bang([
                ['LOẠI PHÒNG', '04/11/2026', "08, 09/02/2027\n(Lịch Tết)", '10/10/2026'],
                ['', 'TWN/DBL', 'TWN/DBL', 'TWN/DBL'],
                ["Water Villa with Pool & Slide\n03 Adults/02 Adults + 01 Child", $giaWater, '2.817', '2.500'],
                ["Ocean Villa (Adult Only)\n03 Adults/02 Adults", '2.846', 'NA', '2.600'],
                ...$themHang,
            ]),
            $this->p('Note:', dam: true),
            $this->p('Giá dành cho trẻ em từ 02 – 12 tuổi: 75% giá người lớn.', gach: true),
            $this->p('LƯU Ý ĐẶC BIỆT', dam: true),
            $this->p('Combo được xây dựng trên cơ sở vé đoàn. Trường hợp không đủ 10 khách/ đoàn thì quý khách sẽ được báo trước 07 ngày.', gach: true),
            $this->p('DỊCH VỤ ĐÃ BAO GỒM:', dam: true),
            $this->p('Vé máy bay khứ hồi Singapore Airlines.', gach: true),
            $this->p('02 đêm Resort Siyam World 5*.', gach: true),
            $this->p('DỊCH VỤ KHÔNG BAO GỒM:', dam: true),
            $this->p('Chi phí cá nhân.', gach: true),
            $this->p('QUY ĐỊNH CỦA RESORT VÀ CÁC THÔNG TIN LIÊN QUAN', dam: true),
            $this->p('1. Thời gian nhận phòng – trả phòng:'),
            $this->p('Nhận phòng 14:00, trả phòng 12:00.', gach: true),
        ]);
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            .' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

        $duong = tempnam(sys_get_temp_dir(), 'mdv').'.docx';
        $zip = new ZipArchive;
        $zip->open($duong, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="png" ContentType="image/png"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document '.$ns.'><w:body>'.$than.'</w:body></w:document>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdAnh1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/media/image1.png', $this->pngMau(900, 560));
        $zip->close();

        return $duong;
    }

    // ------------------------------------------------------------------

    public function test_doc_file_word_ra_resort_hang_phong_bang_gia_lich_trinh(): void
    {
        $kq = app(NhapComboTuFile::class)->doc($this->docx(), 'SIYAM_WORLD_5__-_Bay_SQ.docx');

        $this->assertSame('Sun Siyam World 5* – bay Singapore Airlines, thuỷ phi cơ', $kq['ten']);
        $this->assertSame(['Sun Siyam World', 5.0, 'Noonu Atoll', 'WOW All Inclusive'], [$kq['resort']['ten'], $kq['resort']['sao'], $kq['resort']['dao'], $kq['resort']['goi']]);
        $this->assertSame([5, 4, 'Singapore Airlines', 'Thuỷ phi cơ'], [$kq['so_ngay'], $kq['so_dem'], $kq['hang_bay'], $kq['van_chuyen']]);
        $this->assertSame('01 đêm khách sạn + 02 đêm resort Siyam World 5*, WOW All Inclusive', $kq['luu_tru']);

        // Hạng phòng: sức chứa đổi sang tiếng Việt; "(Adult Only)" → chỉ người lớn
        $this->assertSame([
            ['ten' => 'Water Villa with Pool & Slide', 'suc_chua' => '3 người lớn hoặc 2 người lớn + 1 trẻ em', 'chi_nguoi_lon' => false],
            ['ten' => 'Ocean Villa', 'suc_chua' => '3 người lớn hoặc 2 người lớn', 'chi_nguoi_lon' => true],
        ], $kq['phong']);

        // Cột "08, 09/02/2027" → hai ngày cùng giá; NA = không bán; 10/10/2026 đã qua → bỏ
        $this->assertSame([
            ['ngay' => '2026-11-04', 'nhan' => null, 'gia' => [2631, 2846]],
            ['ngay' => '2027-02-08', 'nhan' => 'Lịch Tết', 'gia' => [2817, null]],
            ['ngay' => '2027-02-09', 'nhan' => 'Lịch Tết', 'gia' => [2817, null]],
        ], $kq['ngay']);

        // Phụ thu ghi khoảng "$100 - $120" → lấy mức thấp nhất + báo xem lại; lịch bay theo từng nơi đi
        $this->assertSame([
            ['city' => 'Hà Nội', 'surcharge_usd' => 0, 'flights' => "SQ 191 HANSIN 1235 1705\nSQ 438 SINMLE 2045 2210"],
            ['city' => 'TP.HCM', 'surcharge_usd' => 100, 'flights' => "SQ 183 SGNSIN 1555 1855\nSQ 438 SINMLE 2045 2210"],
        ], $kq['noi_khoi_hanh']);
        $this->assertSame([75, '2 – 12 tuổi', 10], [$kq['tre_em_phan_tram'], $kq['tuoi_tre_em'], $kq['toi_thieu']]);

        $this->assertSame(['Việt Nam - khách sạn ở thủ đô Male', 'Siyam World 5*', 'Male – Việt Nam'], array_column($kq['lich_trinh'], 'tieu_de'));
        $this->assertSame('Bữa sáng, bữa trưa, bữa tối, đồ uống', $kq['lich_trinh'][1]['bua']);
        $this->assertStringNotContainsString('Live music', $kq['lich_trinh'][1]['noi_dung']); // khung tiện ích tách khỏi lịch trình
        $this->assertSame([['title' => 'Giải trí', 'items' => ['Live music and DJ.', 'Karaoke nights.']], ['title' => 'Sức khỏe', 'items' => ['Group yoga.', 'Aqua aerobics.']]],
            $kq['resort']['tien_ich']);
        $this->assertSame(['Vé máy bay khứ hồi Singapore Airlines.', '02 đêm Resort Siyam World 5*.'], $kq['bao_gom']);
        $this->assertSame(['Chi phí cá nhân.', 'Bữa tối ngày 3 tự túc.'], $kq['khong_bao_gom']);
        $this->assertSame(['Ghi chú bảng giá', 'Lưu ý đặc biệt'], array_column($kq['luu_y'], 'title'));
        $this->assertSame([['title' => 'Thời gian nhận phòng – trả phòng', 'content' => '• Nhận phòng 14:00, trả phòng 12:00.']], $kq['resort']['quy_dinh']);
        $this->assertStringContainsString('Siyam World Maldives là một trong những resort', $kq['resort']['gioi_thieu']);
        $this->assertSame([1], array_column($kq['anh'], 'ngay'));

        $this->assertContains('Phụ thu khởi hành từ TP.HCM trong file là $100 – $120/khách: đã lấy mức thấp nhất $100 — sửa lại nếu cần.', $kq['canh_bao']);
        $this->assertContains('Bỏ qua 1 ngày khởi hành đã qua: 10/10/2026.', $kq['canh_bao']);
    }

    public function test_tao_combo_nhap_roi_nhap_lai_chi_cap_nhat_bang_gia(): void
    {
        $dv = app(NhapComboTuFile::class);
        [$combo, $viec] = $dv->tao($dv->doc($this->docx(), 'combo.docx'));

        $resort = $combo->resort;
        $this->assertSame(['Sun Siyam World', 5.0, 'published'], [$resort->name, $resort->stars, $resort->status]);
        $this->assertSame([false, true], $resort->roomTypes->pluck('adults_only')->all());
        [$water, $ocean] = $resort->roomTypes->pluck('id')->all();
        $this->assertSame(['draft', 75, 10, 'Singapore Airlines'], [$combo->status, $combo->child_percent, $combo->min_group, $combo->airline]);
        $this->assertSame([[$water => 2631, $ocean => 2846], [$water => 2817], [$water => 2817]],
            $combo->dates()->orderBy('start_date')->get()->map(fn ($d) => $d->prices)->all());
        $this->assertSame(3, $combo->days()->count());
        $this->assertSame([], Storage::disk('public')->allFiles()); // mặc định không lấy ảnh trong file
        $this->assertContains('tạo resort "Sun Siyam World"', $viec);

        // Nhân viên sửa nội dung combo; đối tác gửi bảng giá mới (đổi giá + thêm hạng phòng)
        $combo->update(['summary' => 'Mô tả đã sửa tay']);
        $combo->days()->where('day_number', 1)->update(['title' => 'Ngày đầu đã sửa tay']);
        $kq = $dv->doc($this->docx('2.700', [["Lagoon Villa\n03 Adults", '2.721', '2.929', '2.829']]), 'combo-moi.docx');
        $this->assertTrue($dv->comboTrung($kq)->is($combo));

        [$viec, $xemLai] = $dv->capNhat($combo, $kq);
        $combo->refresh();
        $lagoon = $resort->roomTypes()->where('name', 'Lagoon Villa')->value('id');
        $this->assertNotNull($lagoon);
        $this->assertSame([$water => 2700, $ocean => 2846, $lagoon => 2721], $combo->dates()->whereDate('start_date', '2026-11-04')->first()->prices);
        $this->assertSame(['Mô tả đã sửa tay', 'Ngày đầu đã sửa tay', 3, 1], [$combo->summary, $combo->days()->where('day_number', 1)->value('title'), $combo->dates()->count(), MaldivesCombo::count()]);
        $this->assertSame(['thêm 1 hạng phòng cho resort', 'sửa giá 3 ngày khởi hành'], $viec);
        $this->assertSame([], $xemLai);
    }

    public function test_resort_da_co_khong_ghi_de_noi_dung_chi_them_hang_phong(): void
    {
        $resort = MaldivesResort::create(['name' => 'Sun Siyam World', 'stars' => 5, 'intro' => 'Giới thiệu đã viết tay.', 'status' => 'published',
            'amenities' => [['title' => 'Nhóm đã sửa', 'items' => ['Mục cũ']]]]);
        $resort->roomTypes()->create(['name' => 'Water Villa with Pool & Slide', 'capacity' => 'Sức chứa đã sửa tay']);

        $dv = app(NhapComboTuFile::class);
        [$combo, $viec] = $dv->tao($dv->doc($this->docx(), 'combo.docx'));

        $resort->refresh();
        $this->assertTrue($combo->resort->is($resort));
        $this->assertSame(['Giới thiệu đã viết tay.', [['title' => 'Nhóm đã sửa', 'items' => ['Mục cũ']]]], [$resort->intro, $resort->amenities]);
        $this->assertSame(['Sức chứa đã sửa tay', '3 người lớn hoặc 2 người lớn'], $resort->roomTypes()->pluck('capacity')->all());
        $this->assertSame(1, MaldivesResort::count());
        $this->assertContains('thêm 1 hạng phòng', $viec);
    }

    public function test_nut_nhap_tu_file_trong_admin(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create()->assignRole('marketing'));
        $file = fn () => UploadedFile::fake()->createWithContent('SIYAM WORLD 5 - Bay SQ.docx', (string) file_get_contents($this->docx()));

        Livewire::test(ListMaldivesCombos::class)
            ->assertActionVisible('nhapTuFile')
            ->callAction('nhapTuFile', data: ['tep' => $file()])
            ->assertHasNoActionErrors()
            ->assertRedirect(EditMaldivesCombo::getUrl(['record' => MaldivesCombo::firstOrFail()]));
        $this->assertSame(['draft', 3], [MaldivesCombo::firstOrFail()->status, MaldivesCombo::firstOrFail()->dates()->count()]);

        // Tải lại đúng file: chọn "chỉ cập nhật" → không sinh combo mới
        Livewire::test(ListMaldivesCombos::class)
            ->callAction('nhapTuFile', data: ['tep' => $file(), 'che_do' => 'cap_nhat'])
            ->assertHasNoActionErrors();
        $this->assertSame(1, MaldivesCombo::count());

        // Tạo mới + bật lấy ảnh → ảnh của ngày 1 được lưu
        Livewire::test(ListMaldivesCombos::class)
            ->callAction('nhapTuFile', data: ['tep' => $file(), 'che_do' => 'tao_moi', 'lay_anh' => true])
            ->assertHasNoActionErrors();
        $moi = MaldivesCombo::query()->latest('id')->firstOrFail();
        $this->assertSame([2, 1], [MaldivesCombo::count(), count($moi->days()->where('day_number', 1)->value('images') ?? [])]);

        // Sale chỉ xem combo → không thấy nút
        $this->actingAs(User::factory()->create()->assignRole('sale'));
        Livewire::test(ListMaldivesCombos::class)->assertActionHidden('nhapTuFile');
    }

    public function test_doc_bang_gia_file_pdf_theo_toa_do(): void
    {
        if (! (new ExecutableFinder)->find('pdftohtml')) {
            $this->markTestSkipped('Máy chưa cài poppler-utils (pdftohtml).');
        }

        // Bảng giá kiểu file đối tác: tên phòng dòng trên, sức chứa + giá dòng dưới; ô "3.100"
        // nằm lệch một dòng riêng (căn giữa ô gộp); cột hai ngày; "(Adult Only)" dưới tên phòng
        $dong = [
            ['F2', 60, 800, 'SUN SIYAM WORLD RESORT 5*'],
            ['F1', 60, 785, 'Hang khong: Singapore Airlines'],
            ['F2', 60, 740, 'NGAY 01: VIET NAM - MALE'],
            ['F1', 60, 725, '10h00: Quy khach co mat tai san bay.'],
            ['F2', 60, 700, 'GIA COMBO DANH CHO 1 KHACH (USD)'],
            ['F2', 250, 670, '04/11/2026'], ['F2', 350, 670, '30/12/2026'], ['F2', 450, 670, '08, 09/02/2027'],
            ['F2', 60, 662, 'LOAI PHONG'], ['F2', 355, 655, '(Tet DL)'],
            ['F2', 60, 625, 'Water Villa'],
            ['F1', 60, 611, '03 Adults/02 Adults'], ['F2', 260, 611, '2.631'], ['F2', 365, 611, 'NA'], ['F2', 460, 611, '2.817'],
            ['F2', 360, 590, '3.100'],
            ['F2', 60, 585, 'Ocean Villa'],
            ['F2', 260, 578, '2.846'], ['F2', 460, 578, '3.072'],
            ['F1', 60, 571, '(Adult Only)'],
            ['F1', 60, 540, 'Ty gia quy doi theo gia ban ra Vietcombank.'],
        ];
        $noiDung = '';
        foreach ($dong as [$phong, $x, $y, $chu]) {
            $noiDung .= "BT /{$phong} 11 Tf {$x} {$y} Td ({$chu}) Tj ET\n";
        }
        $kq = app(NhapComboTuFile::class)->doc($this->pdf($noiDung), 'SIYAM_WORLD_5__-_Bay_SQ.pdf');

        $this->assertSame([['ten' => 'Water Villa', 'suc_chua' => '3 người lớn hoặc 2 người lớn', 'chi_nguoi_lon' => false],
            ['ten' => 'Ocean Villa', 'suc_chua' => null, 'chi_nguoi_lon' => true]], $kq['phong']);
        $this->assertSame([
            ['ngay' => '2026-11-04', 'nhan' => null, 'gia' => [2631, 2846]],
            ['ngay' => '2026-12-30', 'nhan' => 'Tet Dương lịch', 'gia' => [null, 3100]],
            ['ngay' => '2027-02-08', 'nhan' => null, 'gia' => [2817, 3072]],
            ['ngay' => '2027-02-09', 'nhan' => null, 'gia' => [2817, 3072]],
        ], $kq['ngay']);
        $this->assertSame('Sun Siyam World 5* – bay Singapore Airlines', $kq['ten']);
        $this->assertSame(['Việt Nam - Male'], array_column($kq['lich_trinh'], 'tieu_de'));
        $this->assertStringNotContainsString('Water Villa', $kq['lich_trinh'][0]['noi_dung']); // bảng giá không lẫn vào lịch trình
    }

    /** PDF một trang, phông Helvetica (F1) / Helvetica-Bold (F2), từ lệnh vẽ chữ cho sẵn. */
    private function pdf(string $noiDung): string
    {
        $doiTuong = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($noiDung)." >>\nstream\n{$noiDung}endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pdf = "%PDF-1.4\n";
        $viTri = [];
        foreach ($doiTuong as $i => $o) {
            $viTri[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($doiTuong) + 1)."\n0000000000 65535 f \n".implode('', array_map(fn ($v) => sprintf("%010d 00000 n \n", $v), $viTri))
            .'trailer << /Size '.(count($doiTuong) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        $tep = tempnam(sys_get_temp_dir(), 'mdv').'.pdf';
        file_put_contents($tep, $pdf);

        return $tep;
    }
}
