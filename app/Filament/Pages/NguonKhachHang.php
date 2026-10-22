<?php

namespace App\Filament\Pages;

use App\Models\ContactMessage;
use App\Services\NguonKhach;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Modules\Booking\Models\Booking;

/**
 * Khách đến từ kênh nào, clip / chiến dịch nào ra đơn — theo tháng.
 *
 * Tính theo NGÀY KHÁCH GỬI (đơn đặt tour, tin liên hệ / team building), không
 * theo ngày chốt: câu hỏi ở đây là "kênh nào kéo được khách", không phải hoa
 * hồng. Doanh số chỉ cộng đơn đã xác nhận / hoàn thành.
 *
 * Dòng "Trợ lý AI" = khách bấm link PSV trong câu trả lời của ChatGPT,
 * Perplexity, Gemini… — dấu hiệu PSV đang được AI trích dẫn.
 */
class NguonKhachHang extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?string $navigationLabel = 'Nguồn khách';

    protected static ?string $title = 'Nguồn khách theo kênh & chiến dịch';

    protected static ?int $navigationSort = 31;

    protected static ?string $slug = 'nguon-khach';

    #[Url]
    public string $thang = '';

    // Số liệu toàn công ty (doanh số mọi kênh): quản lý đơn và Marketing — nhân
    // viên bán hàng thường chỉ xem đơn của mình nên không mở trang này
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can('giao', Booking::class) || $u?->hasRole('marketing'));
    }

    public function mount(): void
    {
        if (! array_key_exists($this->thang, ThongKeDoanhSo::cacThang())) {
            $this->thang = now()->format('Y-m');
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.thong-ke.nguon-khach')->viewData(fn () => [
                'cacThang' => ThongKeDoanhSo::cacThang(),
                'theoKenh' => $this->theoKenh(),
                'theoChienDich' => $this->theoChienDich(),
            ]),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function khoang(): array
    {
        $dau = Carbon::createFromFormat('Y-m-d', $this->thang.'-01')->startOfDay();

        return [$dau, $dau->copy()->endOfMonth()];
    }

    /** Đơn + tin liên hệ trong tháng, mỗi dòng: kenh, utm, loại, đã chốt, tiền, số khách */
    private function duLieu(): Collection
    {
        [$tu, $den] = $this->khoang();

        $don = Booking::query()->whereBetween('created_at', [$tu, $den])
            ->get(['kenh', 'utm', 'status', 'total_price', 'adults', 'children'])
            ->map(fn (Booking $b) => [
                'kenh' => $b->kenh, 'utm' => $b->utm, 'loai' => 'don',
                'chot' => in_array($b->status, ['confirmed', 'completed'], true),
                'tien' => in_array($b->status, ['confirmed', 'completed'], true) ? (int) $b->total_price : 0,
                'khach' => (int) $b->adults + (int) $b->children,
            ]);

        $tin = ContactMessage::query()->whereBetween('created_at', [$tu, $den])
            ->get(['kenh', 'utm'])
            ->map(fn (ContactMessage $m) => ['kenh' => $m->kenh, 'utm' => $m->utm, 'loai' => 'tin', 'chot' => false, 'tien' => 0, 'khach' => 0]);

        return $don->concat($tin);
    }

    private static function tong(Collection $ds): array
    {
        return [
            'yeu_cau' => $ds->where('loai', 'tin')->count(),
            'don' => $ds->where('loai', 'don')->count(),
            'chot' => $ds->where('chot', true)->count(),
            'khach' => $ds->where('chot', true)->sum('khach'),
            'doanh_so' => $ds->sum('tien'),
        ];
    }

    public function theoKenh(): Collection
    {
        $ds = $this->duLieu();
        $dong = $ds->groupBy(fn ($d) => $d['kenh'] ?? '')
            ->map(fn ($nhom, $kenh) => ['ten' => NguonKhach::nhan($kenh === '' ? null : $kenh), 'ai' => NguonKhach::laAI($kenh), ...self::tong($nhom)])
            ->sortByDesc(fn ($d) => [$d['chot'], $d['don'] + $d['yeu_cau']])
            ->values();

        $ai = $ds->filter(fn ($d) => NguonKhach::laAI($d['kenh']));

        return $dong
            ->when($ai->isNotEmpty(), fn ($c) => $c->push(['ten' => 'Trong đó: trợ lý AI', 'ai' => true, 'phu' => true, ...self::tong($ai)]))
            ->push(['ten' => 'Tổng', 'tong' => true, ...self::tong($ds)]);
    }

    /** Theo chiến dịch · mã clip (chỉ lượt có gắn utm_campaign / utm_content) */
    public function theoChienDich(): Collection
    {
        return $this->duLieu()
            ->filter(fn ($d) => filled($d['utm']['campaign'] ?? null) || filled($d['utm']['content'] ?? null))
            ->groupBy(fn ($d) => NguonKhach::nhan($d['kenh']).'|'.($d['utm']['campaign'] ?? '').'|'.($d['utm']['content'] ?? ''))
            ->map(function ($nhom, $khoa) {
                [$kenh, $chienDich, $clip] = explode('|', $khoa);

                return ['kenh' => $kenh, 'chien_dich' => $chienDich ?: '—', 'clip' => $clip ?: '—', ...self::tong($nhom)];
            })
            ->sortByDesc(fn ($d) => [$d['chot'], $d['don'] + $d['yeu_cau']])
            ->values();
    }
}
