<?php

namespace Tests\Feature;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use App\Mail\UngTuyenMail;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Database\Seeders\QuyenHoSoUngTuyenSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ứng viên gửi CV / portfolio (PDF) từ trang chi tiết tin tuyển dụng → admin
 * thấy hồ sơ (chuông + menu), mở CV, cập nhật trạng thái.
 */
class UngTuyenTest extends TestCase
{
    use RefreshDatabase;

    private JobPosting $tin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('rieng');
        Mail::fake();
        $this->tin = JobPosting::create(['title' => 'Nhân viên kinh doanh tour', 'slug' => 'nhan-vien-kinh-doanh', 'status' => 'published']);
    }

    /** File thật trên đĩa — để kiểm tra theo NỘI DUNG file (finfo), không chỉ theo đuôi. */
    private function tep(string $ten, string $noiDung): UploadedFile
    {
        $duong = tempnam(sys_get_temp_dir(), 'ut');
        file_put_contents($duong, $noiDung);

        return new UploadedFile($duong, $ten, null, null, true);
    }

    private function pdf(string $ten = 'CV Nguyen Van A.pdf'): UploadedFile
    {
        return $this->tep($ten, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private function hoSo(array $them = []): array
    {
        return array_merge([
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0907 123 456',
            'email' => 'ungvien@example.com',
            'message' => 'Em có 2 năm bán tour outbound.',
            'cv' => $this->pdf(),
            'dong_y' => '1',
            'website' => '',
        ], $them);
    }

    private function quanTri(): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $u = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update', 'Delete', 'DeleteAny'] as $q) {
            $u->givePermissionTo(Permission::findOrCreate("{$q}:JobApplication", 'web'));
        }

        return $u;
    }

    public function test_ung_vien_gui_cv_va_portfolio_admin_nhan_chuong_ung_vien_nhan_thu(): void
    {
        $admin = $this->quanTri();

        $this->post('/api/v1/jobs/nhan-vien-kinh-doanh/apply', $this->hoSo([
            'portfolio' => $this->pdf('portfolio.pdf'),
            'portfolio_url' => 'https://drive.google.com/file/d/abc/view',
        ]), ['Accept' => 'application/json'])->assertCreated()->assertJsonStructure(['message']);

        $hs = JobApplication::sole();
        $this->assertSame($this->tin->id, $hs->job_posting_id);
        $this->assertSame('Nhân viên kinh doanh tour', $hs->job_title);
        $this->assertSame('moi', $hs->status);
        $this->assertSame('CV Nguyen Van A.pdf', $hs->cv_name);
        Storage::disk('rieng')->assertExists([$hs->cv_path, $hs->portfolio_path]);
        $this->assertStringStartsWith('ung-tuyen/', $hs->cv_path);

        // Chuông cho người xem được hồ sơ; thư xác nhận cho ứng viên
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('Nguyễn Văn A', $admin->notifications()->first()->data['title']);
        Mail::assertQueued(UngTuyenMail::class, fn ($m) => $m->hasTo('ungvien@example.com'));

        // Bấm gửi lần nữa ngay sau đó: không tạo trùng, không lưu file thừa
        $this->post('/api/v1/jobs/nhan-vien-kinh-doanh/apply', $this->hoSo(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(1, JobApplication::count());
        $this->assertCount(2, Storage::disk('rieng')->allFiles('ung-tuyen'));
    }

    public function test_chi_nhan_pdf_that_va_du_thong_tin(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // gửi nhiều lần liền trong một bài thử
        $gui = fn (array $them) => $this->post('/api/v1/jobs/nhan-vien-kinh-doanh/apply', $this->hoSo($them), ['Accept' => 'application/json']);

        // File chữ đổi tên thành .pdf → bị từ chối (kiểm tra theo nội dung)
        $gui(['cv' => $this->tep('cv.pdf', 'day khong phai pdf')])->assertStatus(422)->assertJsonValidationErrors('cv');
        $gui(['cv' => $this->tep('cv.docx', "PK\x03\x04 word")])->assertStatus(422)->assertJsonValidationErrors('cv');
        $gui(['cv' => null])->assertStatus(422)->assertJsonValidationErrors('cv');
        $gui(['portfolio' => $this->tep('anh.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))])
            ->assertStatus(422)->assertJsonValidationErrors('portfolio');
        $gui(['portfolio_url' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors('portfolio_url');
        $gui(['email' => ''])->assertStatus(422)->assertJsonValidationErrors('email');
        $gui(['dong_y' => null])->assertStatus(422)->assertJsonValidationErrors('dong_y');
        $gui(['website' => 'http://spam'])->assertStatus(422); // ô bẫy bot

        // CV quá 5MB
        $gui(['cv' => UploadedFile::fake()->create('to.pdf', 5 * 1024 + 1, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('cv');

        // Tin đã hết hạn / chưa đăng → không nhận
        $this->tin->update(['deadline' => now()->subDay()]);
        $gui([])->assertNotFound();

        $this->assertSame(0, JobApplication::count());
        $this->assertSame([], Storage::disk('rieng')->allFiles());
    }

    public function test_admin_xem_mo_cv_cap_nhat_va_xoa_ho_so(): void
    {
        $this->post('/api/v1/jobs/nhan-vien-kinh-doanh/apply', $this->hoSo(['portfolio' => $this->pdf('pf.pdf')]), ['Accept' => 'application/json'])->assertCreated();
        $hs = JobApplication::sole();
        $admin = $this->quanTri();
        $this->actingAs($admin);

        $this->get('/admin')->assertOk()->assertSee('Hồ sơ ứng tuyển');
        $this->get('/admin/ho-so-ung-tuyen')->assertOk()->assertSee('Nguyễn Văn A');
        $this->get('/admin/ho-so-ung-tuyen/'.$hs->id)->assertOk()->assertSee('CV Nguyen Van A.pdf')->assertSee('Em có 2 năm bán tour outbound.');

        // Mở CV ngay trên trình duyệt, tên tải về dễ đọc
        $r = $this->get($hs->linkTep('cv'))->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('inline; filename="CV - Nguyen Van A - Nhan vien kinh doanh tour.pdf"', $r->headers->get('Content-Disposition'));
        $this->get($hs->linkTep('portfolio'))->assertOk();
        $this->get('/admin/ung-tuyen/'.$hs->id.'/khac')->assertNotFound();

        Livewire::test(ViewJobApplication::class, ['record' => $hs->getRouteKey()])
            ->callAction('xuLy', data: ['status' => 'phong_van', 'admin_note' => 'Hẹn phỏng vấn 9h thứ Sáu'])
            ->assertHasNoFormErrors();
        $hs->refresh();
        $this->assertSame('phong_van', $hs->status);
        $this->assertSame($admin->id, $hs->handled_by);
        $this->assertNotNull($hs->handled_at);

        // Xoá hồ sơ → xoá luôn CV, portfolio
        Livewire::test(ListJobApplications::class)->callAction(TestAction::make('delete')->table($hs));
        $this->assertSame(0, JobApplication::count());
        $this->assertSame([], Storage::disk('rieng')->allFiles());
    }

    public function test_khong_co_quyen_thi_khong_thay_ho_so_khong_mo_duoc_cv(): void
    {
        $this->post('/api/v1/jobs/nhan-vien-kinh-doanh/apply', $this->hoSo(), ['Accept' => 'application/json'])->assertCreated();
        $hs = JobApplication::sole();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Nhân viên sửa được tin tuyển dụng nhưng KHÔNG được xem CV
        Role::findOrCreate('staff', 'web')->givePermissionTo(Permission::findOrCreate('ViewAny:JobPosting', 'web'));
        $this->actingAs(User::factory()->create()->assignRole('staff'));
        $this->get('/admin/ho-so-ung-tuyen')->assertForbidden();
        $this->get('/admin/ho-so-ung-tuyen/'.$hs->id)->assertForbidden();
        $this->get('/admin/ung-tuyen/'.$hs->id.'/cv')->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Hồ sơ ứng tuyển');

        // File không nằm trong thư mục công khai
        $this->assertNotSame(200, $this->get('/storage/'.$hs->cv_path)->status());
    }

    public function test_migrate_cap_quyen_cho_super_admin_va_admin(): void
    {
        $sieu = Role::findOrCreate('super_admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $sale = Role::findOrCreate('sale', 'web');

        (new QuyenHoSoUngTuyenSeeder)->run();

        $this->assertTrue($sieu->fresh()->hasPermissionTo('ViewAny:JobApplication'));
        $this->assertTrue($admin->fresh()->hasPermissionTo('Update:JobApplication'));
        $this->assertFalse($sale->fresh()->hasPermissionTo('ViewAny:JobApplication'));
    }
}
