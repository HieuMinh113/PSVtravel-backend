<?php

namespace Tests\Feature;

use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Tour\Models\Tour;
use Modules\Tour\Services\NhapTour\DocPdf;
use Modules\Tour\Services\NhapTour\NhapTourTuFile;
use Modules\Tour\Services\NhapTour\PhanTichChuongTrinh;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;
use ZipArchive;

/**
 * Nhập tour từ file chương trình tour của đối tác (Word / PDF): đọc tên, giá
 * (người lớn / trẻ em / em bé), ngày khởi hành (bỏ ngày đã qua), lịch trình
 * kèm bữa ăn + ảnh từng ngày, các phần bao gồm / không bao gồm / huỷ / lưu ý;
 * tạo tour NHÁP hoặc cập nhật giá + ngày cho tour cùng tên.
 *
 * File Word được dựng ngay trong bài kiểm thử (không dùng file thật của đối tác).
 */
class NhapTourTuFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-13 09:00'));
        Storage::fake('public');
    }

    // ------------------------------------------------------------------
    // Dựng file .docx tối giản giống chương trình tour của đối tác
    // ------------------------------------------------------------------

    private function p(string $chu, bool $dam = false, bool $gach = false): string
    {
        return '<w:p>'.($gach ? '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>' : '')
            .'<w:r>'.($dam ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.htmlspecialchars($chu).'</w:t></w:r></w:p>';
    }

    /** Ô "✓" = dấu tích Wingdings như file thật */
    private function bang(array $hang): string
    {
        $xml = '<w:tbl>';
        foreach ($hang as $o) {
            $xml .= '<w:tr>'.implode('', array_map(fn ($c) => '<w:tc>'.($c === '✓'
                ? '<w:p><w:r><w:sym w:font="Wingdings 2" w:char="F050"/></w:r></w:p>'
                : $this->p($c)).'</w:tc>', $o)).'</w:tr>';
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

    private function docx(): string
    {
        $than = implode('', [
            $this->p('THƯỢNG HẢI – HÀNG CHÂU', dam: true),
            $this->p('Thời gian: 3 ngày 2 đêm', dam: true),
            $this->p('Tour NO SHOPPING, không ghé điểm mua sắm', gach: true),
            $this->p('Thưởng ngoạn Tây Hồ bằng thuyền', gach: true),
            $this->bang([['NGÀY 1: TP.HCM – THƯỢNG HẢI', 'Sáng', 'Trưa', 'Tối'], ['', '', '', 'MB']]),
            $this->p('21h00: Quý khách tập trung tại sân bay Tân Sơn Nhất.'),
            $this->bang([['NGÀY 2: THƯỢNG HẢI – HÀNG CHÂU', 'Sáng', 'Trưa', 'Tối'], ['', '✓', '✓', '✓']]),
            $this->p('Sáng: Tham quan Tây Hồ, xem ѕhoᴡ nhạc nước.'),
            $this->anh(),
            $this->p('NGÀY 3: HÀNG CHÂU – TP.HCM (ĂN S, T)', dam: true),
            $this->p('Kết thúc chương trình, chia tay và hẹn gặp lại.'),
            $this->p('BẢNG GIÁ TOUR', dam: true),
            $this->bang([
                ['NGÀY KHỞI HÀNH', 'GIÁ NGƯỜI LỚN', 'TRẺ EM (2-11 TUỔI)', 'EM BÉ (DƯỚI 2 TUỔI)'],
                ['05/09/2026', '15.990.000', '14.490.000', '4.890.000'],
                ['Tháng 11: 07, 14', '15.990.000', '14.490.000', '4.890.000'],
                ['Tháng 12: 05', '16.990.000', '15.290.000', '5.190.000'],
            ]),
            $this->p('GIÁ TOUR BAO GỒM:', dam: true),
            $this->p('Vé máy bay khứ hồi', gach: true),
            $this->p('Khách sạn 4 sao', gach: true),
            $this->p('GIÁ TOUR KHÔNG BAO GỒM:', dam: true),
            $this->p('Chi phí cá nhân', gach: true),
            $this->p('ĐIỀU KIỆN HỦY TOUR:', dam: true),
            $this->p('- Hủy trước 30 ngày: phí 10% giá tour.'),
            $this->p('QUY ĐỊNH VISA:', dam: true),
            $this->p('- Nộp hồ sơ visa trước ngày khởi hành ít nhất 20 ngày.'),
        ]);
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            .' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

        $duong = tempnam(sys_get_temp_dir(), 'tour').'.docx';
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

    public function test_doc_file_word_ra_ten_gia_ngay_lich_trinh(): void
    {
        $kq = app(NhapTourTuFile::class)->doc($this->docx(), 'THUONG-HAI-HANG-CHAU-3N2D.docx');

        $this->assertSame('Thượng Hải – Hàng Châu 3N2Đ', $kq['ten']);
        $this->assertSame([3, 2], [$kq['so_ngay'], $kq['so_dem']]);
        $this->assertSame(['abroad', 'Trung Quốc', 'Hồ Chí Minh', 'No Shopping'], [$kq['loai'], $kq['quoc_gia'], $kq['khoi_hanh_tu'], $kq['nhan']]);
        $this->assertSame([15_990_000, 14_490_000, 4_890_000], [$kq['gia_nguoi_lon'], $kq['gia_tre_em'], $kq['gia_em_be']]);

        // Ngày đã qua (05/09) bị bỏ, báo lại trong cảnh báo
        $this->assertSame(['2026-11-07', '2026-11-14', '2026-12-05'], array_column($kq['dot'], 'ngay'));
        $this->assertSame(16_990_000, $kq['dot'][2]['gia']['nguoi_lon']);
        $this->assertSame(['05/09/2026'], $kq['dot_bo_qua']);
        $this->assertTrue(collect($kq['canh_bao'])->contains(fn ($c) => str_contains($c, 'đã qua')));

        $this->assertSame(['TP. HCM – Thượng Hải', 'Thượng Hải – Hàng Châu', 'Hàng Châu – TP. HCM'], array_column($kq['lich_trinh'], 'tieu_de'));
        $this->assertSame(['Tối: trên máy bay', 'Ăn sáng, trưa, tối', 'Ăn sáng, trưa'], array_column($kq['lich_trinh'], 'bua'));
        $this->assertStringContainsString('xem show nhạc nước', $kq['lich_trinh'][1]['noi_dung']); // chữ "giả" ѕhoᴡ → show
        $this->assertStringNotContainsString('NGÀY KHỞI HÀNH', $kq['lich_trinh'][2]['noi_dung']);
        $this->assertSame([2], array_column($kq['anh'], 'ngay')); // ảnh nằm trong ngày 2

        $this->assertSame(['Tour NO SHOPPING, không ghé điểm mua sắm', 'Thưởng ngoạn Tây Hồ bằng thuyền'], $kq['noi_bat']);
        $this->assertSame(['Vé máy bay khứ hồi', 'Khách sạn 4 sao'], $kq['bao_gom']);
        $this->assertSame(['Chi phí cá nhân'], $kq['khong_bao_gom']);
        $this->assertStringContainsString('Hủy trước 30 ngày', (string) $kq['huy']);
        $this->assertSame(20, $kq['han_visa_ngay']);
    }

    public function test_chuan_hoa_chu_gia_va_ky_hieu(): void
    {
        $this->assertSame('xem show ở Tây Hồ', PhanTichChuongTrinh::chuan("xem\u{00A0}ѕhoᴡ  ở Tây\u{200B} Hồ"));
        $this->assertSame('• Tây Hồ', PhanTichChuongTrinh::chuan("\u{F0FC} Tây Hồ"));
    }

    public function test_tao_tour_nhap_kem_ngay_khoi_hanh_lich_trinh_anh(): void
    {
        $dm = Category::create(['type' => 'abroad', 'name' => 'Trung Quốc', 'slug' => 'trung-quoc', 'status' => 'published']);
        $dv = app(NhapTourTuFile::class);
        [$tour, $viec] = $dv->tao($dv->doc($this->docx(), 'tour.docx'));
        $tour->refresh();

        $this->assertSame('draft', $tour->status); // chưa hiện ngoài web
        $this->assertSame('thuong-hai-hang-chau-3n2d', $tour->slug);
        $this->assertSame([15_990_000, 14_490_000, 4_890_000], [$tour->adult_price, $tour->child_price, $tour->infant_price]);
        $this->assertSame(20, $tour->visa_deadline_days);
        $this->assertSame([$dm->id], $tour->categories->pluck('id')->all());
        $this->assertSame(['Vé máy bay khứ hồi', 'Khách sạn 4 sao'], $tour->included);

        // Ngày khởi hành: 24 chỗ, giá riêng chỉ khi khác giá tour
        $dot = $tour->departures;
        $this->assertSame(['2026-11-07', '2026-11-14', '2026-12-05'], $dot->map(fn ($d) => $d->start_date->toDateString())->all());
        $this->assertSame([24, 24, 'open'], [$dot[0]->seats_total, $dot[0]->seats_left, $dot[0]->status]);
        $this->assertSame([null, null, 16_990_000], $dot->pluck('price_override')->all());

        // Lịch trình + ảnh: ảnh của ngày 2 lưu thành JPEG, ảnh bìa là bản chép riêng
        $ngay = $tour->itineraries;
        $this->assertSame('Thượng Hải – Hàng Châu (Ăn sáng, trưa, tối)', $ngay[1]->title);
        $this->assertCount(1, $ngay[1]->images);
        Storage::disk('public')->assertExists([$ngay[1]->images[0], $tour->cover_image]);
        $this->assertNotSame($ngay[1]->images[0], $tour->cover_image);
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('public')->get($tour->cover_image))['mime']);
        $this->assertContains('3 ngày khởi hành ('.NhapTourTuFile::SO_CHO.' chỗ mỗi ngày)', $viec);

        // Tên trùng → tour mới có đường dẫn khác
        [$tour2] = $dv->tao($dv->doc($this->docx(), 'tour.docx'));
        $this->assertSame('thuong-hai-hang-chau-3n2d-2', $tour2->slug);
    }

    public function test_tour_cung_ten_chi_cap_nhat_gia_va_ngay_khoi_hanh(): void
    {
        $tour = Tour::create(['slug' => 'thuong-hai-hang-chau-3n2d', 'name' => 'Thượng Hải – Hàng Châu 3N2Đ', 'type' => 'abroad', 'status' => 'published',
            'adult_price' => 13_000_000, 'old_price' => 15_000_000, 'duration_days' => 3, 'duration_nights' => 2, 'description' => 'Mô tả đã sửa tay']);
        $tour->departures()->create(['start_date' => '2026-11-07', 'seats_total' => 30, 'seats_left' => 12, 'price_override' => 18_000_000]);

        $dv = app(NhapTourTuFile::class);
        $kq = $dv->doc($this->docx(), 'tour.docx');
        $this->assertSame($tour->id, $dv->tourTrung($kq)?->id);

        $viec = $dv->capNhat($tour, $kq);
        $tour->refresh();

        $this->assertSame(['cập nhật giá tour', 'thêm 2 ngày khởi hành', 'sửa giá 1 ngày khởi hành'], $viec);
        $this->assertSame([15_990_000, 14_490_000, 4_890_000, null], [$tour->adult_price, $tour->child_price, $tour->infant_price, $tour->old_price]);
        $this->assertSame(['published', 'Mô tả đã sửa tay'], [$tour->status, $tour->description]); // nội dung giữ nguyên
        $cu = $tour->departures->firstWhere(fn ($d) => $d->start_date->toDateString() === '2026-11-07');
        $this->assertSame([null, 30, 12], [$cu->price_override, $cu->seats_total, $cu->seats_left]); // số chỗ giữ nguyên
        $this->assertCount(3, $tour->departures);
        $this->assertSame(0, $tour->itineraries()->count());
    }

    public function test_nut_nhap_tu_file_trong_admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Tour", 'web'));
        }
        $this->actingAs($nv);
        $file = fn () => UploadedFile::fake()->createWithContent('THUONG HAI 3N2D.docx', (string) file_get_contents($this->docx()));

        Livewire::test(ListTours::class)
            ->assertActionVisible('nhapTuFile')
            ->callAction('nhapTuFile', data: ['tep' => $file()])
            ->assertHasNoActionErrors()
            ->assertRedirect(EditTour::getUrl(['record' => Tour::firstOrFail()]));

        $tour = Tour::firstOrFail();
        $this->assertSame(['Thượng Hải – Hàng Châu 3N2Đ', 'draft', 3, 3], [$tour->name, $tour->status, $tour->departures()->count(), $tour->itineraries()->count()]);

        // Tải lại đúng file đó: hỏi cập nhật hay tạo mới — chọn cập nhật thì không sinh tour mới
        Livewire::test(ListTours::class)
            ->callAction('nhapTuFile', data: ['tep' => $file(), 'che_do' => 'cap_nhat'])
            ->assertHasNoActionErrors();
        $this->assertSame(1, Tour::count());

        Livewire::test(ListTours::class)
            ->callAction('nhapTuFile', data: ['tep' => $file(), 'che_do' => 'tao_moi'])
            ->assertHasNoActionErrors();
        $this->assertSame(2, Tour::count());

        // Không có quyền tạo tour → không thấy nút
        $xem = User::factory()->create();
        $xem->givePermissionTo(Permission::findOrCreate('ViewAny:Tour', 'web'));
        $this->actingAs($xem);
        Livewire::test(ListTours::class)->assertActionHidden('nhapTuFile');
    }

    public function test_file_khong_doc_duoc_bao_loi_ro_rang(): void
    {
        $dv = app(NhapTourTuFile::class);
        $tep = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($tep, 'không phải file word');

        $this->expectExceptionMessage('File không phải Word .docx hợp lệ');
        $dv->doc($tep, 'chuong-trinh.docx');
    }

    public function test_doc_file_pdf(): void
    {
        if (! (new ExecutableFinder)->find('pdftohtml')) {
            $this->markTestSkipped('Máy chưa cài poppler-utils (pdftohtml).');
        }

        // PDF tối giản: tiêu đề, 2 ngày (dòng ngày 1 bị ngắt xuống dòng), bảng giá 3 cột
        $dong = [
            ['F2', 60, 800, 'HA NOI - SAPA'],
            ['F2', 60, 770, 'NGAY 1: HA NOI - SAPA'],
            ['F1', 60, 752, 'Sang: Xe don doan tai Ha Noi, di chuyen'],
            ['F1', 60, 738, 'len Sapa qua cao toc.'],
            ['F2', 60, 710, 'NGAY 2: SAPA - HA NOI'],
            ['F1', 60, 692, 'Sang: Tham quan ban Cat Cat.'],
            ['F2', 60, 650, 'BANG GIA TOUR'],
            ['F2', 60, 630, 'KHOI HANH'], ['F2', 250, 630, 'NGUOI LON'], ['F2', 400, 630, 'TRE EM'],
            ['F1', 60, 612, '15/11/2026'], ['F1', 250, 612, '5.990.000'], ['F1', 400, 612, '4.490.000'],
            ['F1', 60, 594, '22/11/2026'], ['F1', 250, 594, '6.290.000'], ['F1', 400, 594, '4.690.000'],
        ];
        $noiDung = '';
        foreach ($dong as [$phong, $x, $y, $chu]) {
            $noiDung .= "BT /{$phong} 11 Tf {$x} {$y} Td ({$chu}) Tj ET\n";
        }
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
        $tep = tempnam(sys_get_temp_dir(), 'tour').'.pdf';
        file_put_contents($tep, $pdf);

        $this->assertNotEmpty((new DocPdf)->doc($tep));
        $kq = app(NhapTourTuFile::class)->doc($tep, 'ha-noi-sapa-2n1d.pdf');

        $this->assertSame('Ha Noi – Sapa 2N1Đ', $kq['ten']);
        $this->assertSame(['2026-11-15', '2026-11-22'], array_column($kq['dot'], 'ngay'));
        $this->assertSame([5_990_000, 4_490_000], [$kq['gia_nguoi_lon'], $kq['gia_tre_em']]);
        $this->assertCount(2, $kq['lich_trinh']);
        $this->assertStringContainsString('di chuyen len Sapa qua cao toc.', $kq['lich_trinh'][0]['noi_dung']); // dòng bị ngắt được nối lại
        $this->assertStringNotContainsString('KHOI HANH', $kq['lich_trinh'][1]['noi_dung']);
    }
}
