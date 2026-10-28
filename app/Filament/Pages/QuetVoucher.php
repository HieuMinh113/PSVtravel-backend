<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VoucherBatches\Actions\VoucherActions;
use App\Models\User;
use App\Models\Voucher;
use BackedEnum;
use Database\Seeders\QuyenVoucherSeeder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;

/**
 * Nhân viên quét voucher của khách: máy quét cầm tay (gõ mã + Enter vào ô),
 * camera điện thoại / laptop (QR hoặc mã vạch), hoặc gõ tay. Còn dùng được thì
 * bấm "Xác nhận đã dùng" — hệ thống không trừ tiền, chỉ ghi mã đã dùng (ai,
 * lúc nào, ghi chú). Quét nhầm: hoàn tác trong 30 phút.
 *
 * Link từ trang kiểm tra voucher trên website: /admin/quet-voucher?ma=PSV-…
 */
class QuetVoucher extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'Voucher';

    protected static ?string $navigationLabel = 'Quét voucher';

    protected static ?string $title = 'Quét voucher';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'quet-voucher';

    /** Số lần kiểm tra tối đa mỗi phút / nhân viên (chặn dò mã) */
    public const SO_LAN_MOI_PHUT = 60;

    #[Url(as: 'ma', except: '')]
    public string $nhap = '';

    public ?int $voucherId = null;

    public bool $khongThay = false;

    public string $ghiChu = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(QuyenVoucherSeeder::QUET);
    }

    public function mount(): void
    {
        if (trim($this->nhap) !== '') {
            $this->kiemTra();
        }
    }

    public function kiemTra(?string $ma = null): void
    {
        $ma = trim((string) ($ma ?? $this->nhap));
        if ($ma === '') {
            return;
        }
        if (! RateLimiter::attempt('quet-voucher:'.auth()->id(), self::SO_LAN_MOI_PHUT, fn () => true)) {
            Notification::make()->warning()->title('Quét quá nhanh — chờ một phút rồi thử lại')->send();

            return;
        }
        $v = Voucher::tim($ma);
        $this->voucherId = $v?->id;
        $this->khongThay = ! $v;
        $this->nhap = $v?->code ?? mb_substr($ma, 0, 60);
        $this->ghiChu = '';
        $this->dispatch('voucher-da-kiem-tra', ketQua: $v?->trangThai() ?? 'khong_co');
    }

    public function xacNhan(): void
    {
        abort_unless(self::canAccess(), 403);
        $v = $this->voucher();
        if (! $v) {
            return;
        }
        /** @var User $u */
        $u = auth()->user();
        if ($v->danhDauDaDung($u, $this->ghiChu)) {
            Notification::make()->success()->title('Đã xác nhận '.$v->code.' — '.VoucherActions::tien($v->batch?->value))->send();
        } else {
            Notification::make()->danger()->title('Không xác nhận được')->body('Mã vừa được người khác dùng hoặc không còn hiệu lực — xem kết quả bên dưới.')->send();
        }
        $this->dispatch('voucher-da-kiem-tra', ketQua: $v->fresh()?->trangThai());
    }

    public function hoanTac(): void
    {
        $v = $this->voucher();
        if (! $v || $v->status !== 'used' || ! VoucherActions::duocBoDanhDau($v)) {
            return;
        }
        /** @var User $u */
        $u = auth()->user();
        $v->boDanhDau($u);
        Notification::make()->success()->title($v->code.' đã trả về chưa dùng')->send();
    }

    public function quetMoi(): void
    {
        $this->reset(['nhap', 'voucherId', 'khongThay', 'ghiChu']);
    }

    private function voucher(): ?Voucher
    {
        return $this->voucherId ? Voucher::query()->with(['batch', 'nguoiQuet'])->find($this->voucherId) : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.voucher.quet')->viewData(fn () => $this->duLieu()),
        ]);
    }

    /** @return array<string, mixed> */
    private function duLieu(): array
    {
        $v = $this->voucher();

        return [
            'v' => $v,
            'trangThai' => $v?->trangThai(),
            'khongThay' => $this->khongThay,
            'hoanTacDuoc' => $v && $v->status === 'used' && VoucherActions::duocBoDanhDau($v),
            'ganDay' => Voucher::query()->with('batch')->where('used_by', auth()->id())->latest('used_at')->limit(8)->get(),
            'js' => asset('js/psv/quet-voucher.js').'?v='.@filemtime(public_path('js/psv/quet-voucher.js')),
            'thuVien' => asset('vendor/html5-qrcode/html5-qrcode.min.js'),
        ];
    }
}
