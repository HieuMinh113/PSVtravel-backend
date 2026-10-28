<?php

namespace Tests\Feature;

use App\Filament\Pages\QuetVoucher;
use App\Filament\Resources\VoucherBatches\Pages\EditVoucherBatch;
use App\Filament\Resources\VoucherBatches\Pages\ListVoucherBatches;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Jobs\XuatFileVoucher;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Voucher\InVoucher;
use App\Services\Voucher\MaVach128;
use App\Services\Voucher\XuatVoucher;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * Voucher: đợt + mã dùng một lần, khách kiểm tra mã trên website, nhân viên
 * quét và đánh dấu đã dùng, file in PDF (nhà in / A4) và ảnh.
 */
class VoucherTest extends TestCase
{
    use RefreshDatabase;

    private VoucherBatch $dot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        Storage::fake('rieng');
        $this->dot = VoucherBatch::create(['name' => 'Ốc & Du lịch 200K', 'value' => 200000, 'expires_at' => now()->addMonth(),
            'applies_to' => 'Dịch vụ ăn uống', 'conditions' => 'Không quy đổi thành tiền mặt.', 'code_prefix' => 'psv', 'status' => 'active']);
    }

    /** Ảnh mẫu khổ thành phẩm (155:100) hoặc có tràn lề (161:106) */
    private function anh(string $ten, bool $tranLe = false): string
    {
        $a = imagecreatetruecolor($tranLe ? 1610 : 1550, $tranLe ? 1060 : 1000);
        imagefill($a, 0, 0, imagecolorallocate($a, 30, 110, 160));
        ob_start();
        imagejpeg($a, null, 80);
        Storage::disk('public')->put('voucher/'.$ten, (string) ob_get_clean());

        return 'voucher/'.$ten;
    }

    private function nhanVien(string $vaiTro): User
    {
        return User::factory()->create()->assignRole($vaiTro);
    }

    public function test_tao_ma_dung_mot_lan_va_api_kiem_tra(): void
    {
        $dau = $this->dot->taoMa(5, 'Hội chợ 11/2026');
        $this->assertSame(1, $dau);
        $this->assertSame('PSV', $this->dot->fresh()->code_prefix);
        $ma = $this->dot->vouchers()->orderBy('serial')->get();
        $this->assertSame([1, 2, 3, 4, 5], $ma->pluck('serial')->all());
        $this->assertCount(5, $ma->pluck('code')->unique());
        foreach ($ma as $v) {
            $this->assertMatchesRegularExpression('/^PSV-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $v->code);
        }
        $this->assertSame(6, $this->dot->taoMa(2)); // nối tiếp số thứ tự

        $v = $ma[0];
        $d = $this->getJson('/api/v1/vouchers/'.$v->code)->assertOk()->json('data');
        $this->assertSame([$v->code, 'con_dung', 200000, 'Dịch vụ ăn uống'], [$d['ma'], $d['trang_thai'], $d['gia_tri'], $d['ap_dung']]);
        // khách gõ chữ thường, không gạch
        $this->assertSame('con_dung', $this->getJson('/api/v1/vouchers/'.strtolower(str_replace('-', '', $v->code)))->json('data.trang_thai'));
        $this->getJson('/api/v1/vouchers/PSV-AAAA-BBBB')->assertNotFound()->assertJson(['trang_thai' => 'khong_co']);
        // không lộ thông tin nội bộ
        $this->assertArrayNotHasKey('issued_to', $d);

        $sale = $this->nhanVien('sale');
        $this->assertTrue($v->danhDauDaDung($sale, 'Đơn #123'));
        $this->assertFalse($v->fresh()->danhDauDaDung($sale)); // dùng một lần
        $this->assertSame('da_dung', $this->getJson('/api/v1/vouchers/'.$v->code)->json('data.trang_thai'));

        $ma[1]->huy($sale, 'Mất');
        $this->assertSame('da_huy', $ma[1]->fresh()->trangThai());
        $this->dot->update(['status' => 'paused']);
        $this->assertSame('tam_ngung', $ma[2]->fresh()->trangThai());
        $this->assertFalse($ma[2]->fresh()->danhDauDaDung($sale));
        $this->dot->update(['status' => 'active', 'expires_at' => now()->subDay()]);
        $this->assertSame('het_han', $ma[2]->fresh()->trangThai());
        $this->dot->update(['expires_at' => today()]);
        $this->assertSame('con_dung', $ma[2]->fresh()->trangThai()); // dùng được tới hết ngày hết hạn
        $this->dot->delete();
        $this->assertSame('da_huy', $ma[2]->fresh()->trangThai());
    }

    public function test_api_kiem_tra_gioi_han_luot(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/v1/vouchers/PSV-AAAA-BBB'.$i % 10);
        }
        $this->getJson('/api/v1/vouchers/PSV-AAAA-BBBB')->assertStatus(429);
    }

    public function test_quyen_theo_bo_phan(): void
    {
        $this->assertTrue($this->nhanVien('sale')->can('Scan:Voucher'));
        $this->assertFalse($this->nhanVien('sale')->can('update', $this->dot));
        $this->assertTrue($this->nhanVien('marketing')->can('update', $this->dot));
        $this->assertFalse($this->nhanVien('ke_toan')->can('Scan:Voucher'));
        $this->assertTrue($this->nhanVien('admin')->can('Scan:Voucher'));

        $this->actingAs($this->nhanVien('ke_toan'))->get('/admin/quet-voucher')->assertForbidden();
    }

    public function test_nhan_vien_quet_xac_nhan_va_hoan_tac(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->dot->taoMa(2);
        $v = $this->dot->vouchers()->first();
        $sale = $this->nhanVien('sale');
        $this->actingAs($sale);

        // máy quét đọc QR ra link trang kiểm tra trên website
        Livewire::test(QuetVoucher::class)
            ->set('nhap', InVoucher::urlKiemTra($v->code))->call('kiemTra')
            ->assertSee('CÒN DÙNG ĐƯỢC')->assertSee('200.000₫')->assertSet('nhap', $v->code)
            ->set('ghiChu', 'Bàn 5')->call('xacNhan')
            ->assertSee('ĐÃ XÁC NHẬN DÙNG')->assertSee('Hoàn tác');
        $v->refresh();
        $this->assertSame(['used', $sale->id, 'Bàn 5'], [$v->status, $v->used_by, $v->used_note]);

        // người khác quét lại: đã dùng, không hoàn tác được
        $this->actingAs($this->nhanVien('dieu_hanh'));
        Livewire::withQueryParams(['ma' => $v->code])->test(QuetVoucher::class)->assertSee('ĐÃ DÙNG')->assertSee($sale->name)->assertDontSee('Hoàn tác');

        // người quét hoàn tác (quét nhầm)
        $this->actingAs($sale);
        Livewire::test(QuetVoucher::class)->call('kiemTra', strtolower($v->code))->call('hoanTac');
        $this->assertSame('unused', $v->fresh()->status);

        Livewire::test(QuetVoucher::class)->call('kiemTra', 'PSV-AAAA-BBBB')->assertSee('KHÔNG CÓ MÃ NÀY');
    }

    public function test_trang_quan_tri_tao_ma_va_danh_sach(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->nhanVien('marketing'));
        $this->dot->update(['front_image' => $this->anh('truoc.jpg')]);

        Livewire::test(ListVoucherBatches::class)->assertOk()->assertSee('Ốc &amp; Du lịch 200K', false);
        Livewire::test(EditVoucherBatch::class, ['record' => $this->dot->getRouteKey()])->assertOk()
            ->assertSee('Xem thử')
            ->callAction('taoMa', data: ['so_luong' => 3, 'phat_cho' => 'Khách đoàn ABC'])->assertHasNoActionErrors();
        $this->assertSame(3, $this->dot->vouchers()->where('issued_to', 'Khách đoàn ABC')->count());
        $ma = $this->dot->vouchers()->first()->code;
        Livewire::test(ListVouchers::class)->assertOk()->assertSee($ma)
            ->searchTable(strtolower(substr(str_replace('-', '', $ma), 3)))->assertCanSeeTableRecords([$this->dot->vouchers()->first()]);

        // ảnh xem thử vị trí ô mã
        $this->get(route('voucher.xem-thu', ['dot' => $this->dot->id, 'goc' => 'top_left', 'co' => 30]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->nhanVien('customer'))->get(route('voucher.xem-thu', ['dot' => $this->dot->id]))->assertForbidden();
    }

    public function test_file_in_pdf_dung_so_trang_va_kho_giay(): void
    {
        $this->dot->update(['front_image' => $this->anh('truoc.jpg'), 'back_image' => $this->anh('sau.jpg', true), 'show_barcode' => true]);
        $this->dot->taoMa(3);
        $ds = $this->dot->vouchers()->orderBy('serial')->get();
        $in = new InVoucher;
        $trang = fn (string $pdf) => preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf);
        $kho = fn (string $pdf) => preg_match('#/MediaBox\s*\[\s*0\.?0*\s+0\.?0*\s+([\d.]+)\s+([\d.]+)#', $pdf, $m) ? [round($m[1] / 72 * 25.4), round($m[2] / 72 * 25.4)] : null;

        $pdf = $in->pdf($this->dot, $ds, 'tran_le');
        $this->assertSame(6, $trang($pdf)); // mỗi voucher 2 trang
        $this->assertSame([161.0, 106.0], $kho($pdf));
        $this->assertSame([155.0, 100.0], $kho($in->pdf($this->dot, $ds, 'thanh_pham')));
        $a4 = $in->pdf($this->dot, $ds, 'a4');
        $this->assertSame(4, $trang($a4)); // 3 voucher = 2 tờ × 2 mặt
        $this->assertSame([210.0, 297.0], $kho($a4));

        // không có mặt sau: 1 trang / voucher
        $this->dot->update(['back_image' => null]);
        $this->assertSame(3, $trang($in->pdf($this->dot->fresh(), $ds, 'tran_le')));

        // ô mã có mã vạch rộng tối thiểu 36 mm; mã vạch Code 128 đúng chuẩn (bắt đầu B, kết thúc dừng)
        $this->assertSame(36.0, InVoucher::oMa($this->dot)['rong']);
        $w = MaVach128::doRong('PSV2345');
        $this->assertSame([2, 1, 1, 2, 1, 4], array_slice($w, 0, 6));
        $this->assertSame([2, 3, 3, 1, 1, 1, 2], array_slice($w, -7));
        $this->assertSame(11 * 9 + 13, array_sum($w)); // bắt đầu + 7 ký tự + kiểm tra = 9 × 11 mô-đun, dừng 13
    }

    public function test_zip_anh_va_link_tai_chi_nguoi_xuat(): void
    {
        $this->dot->update(['front_image' => $this->anh('truoc.jpg', true), 'back_image' => $this->anh('sau.jpg')]);
        $this->dot->taoMa(3);
        $mk = $this->nhanVien('marketing');
        $ids = $this->dot->vouchers()->pluck('id')->all();

        $kq = XuatVoucher::chay($this->dot, 'anh', $ids, $mk);
        $this->assertFalse($kq['nen']);
        $this->actingAs($this->nhanVien('sale'))->get($kq['url'])->assertForbidden();
        $tai = $this->actingAs($mk)->get($kq['url'])->assertOk();
        $tep = $tai->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tep));
        $ten = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $ten[] = $zip->getNameIndex($i);
        }
        $zip->close();
        $this->assertEqualsCanonicalizing([...$this->dot->vouchers()->pluck('code')->map(fn ($c) => $c.'.jpg')->all(), 'mat-sau.jpg'], $ten);

        // tải một lần
        ob_start();
        $tai->baseResponse->sendContent();
        ob_end_clean();
        $this->actingAs($mk)->get($kq['url'])->assertNotFound();
    }

    public function test_nhieu_ma_tao_file_trong_nen(): void
    {
        Bus::fake();
        $this->dot->update(['front_image' => $this->anh('truoc.jpg')]);
        $this->dot->taoMa(301);
        $kq = XuatVoucher::chay($this->dot, 'a4', $this->dot->vouchers()->pluck('id')->all(), $this->nhanVien('marketing'));
        $this->assertTrue($kq['nen']);
        Bus::assertDispatched(XuatFileVoucher::class, fn ($j) => count($j->ids) === 301 && $j->kieu === 'a4');
    }

    public function test_job_xuat_xong_bao_chuong_kem_link(): void
    {
        $this->dot->update(['front_image' => $this->anh('truoc.jpg')]);
        $this->dot->taoMa(2);
        $mk = $this->nhanVien('marketing');
        (new XuatFileVoucher($this->dot->id, 'thanh_pham', $this->dot->vouchers()->pluck('id')->all(), $mk->id))->handle();
        $tb = $mk->notifications()->first();
        $this->assertSame('File voucher đã sẵn sàng', $tb->data['title']);
        $this->assertCount(1, Storage::disk('rieng')->files(XuatVoucher::THU_MUC));

        // dọn file quá 1 ngày
        $this->travel(2)->days();
        $this->assertSame(1, XuatVoucher::donDep());
    }
}
