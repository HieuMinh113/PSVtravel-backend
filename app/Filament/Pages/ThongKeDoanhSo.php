<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Modules\Booking\Models\Booking;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Thống kê đơn chốt theo tháng để tính hoa hồng: mỗi nhân viên phụ trách bao
 * nhiêu đơn, khách đang cọc hay đã trả đủ, tour đã hoàn thành chưa.
 *
 * - Đơn tính vào tháng XÁC NHẬN (chốt) đơn, cho người PHỤ TRÁCH đơn.
 * - Tiền chỉ tính khoản kế toán đã duyệt.
 * - Đơn huỷ không tính doanh số, đếm riêng.
 * - Quản lý / kế toán xem của mọi người; nhân viên chỉ xem của mình.
 */
class ThongKeDoanhSo extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?string $navigationLabel = 'Thống kê doanh số';

    protected static ?string $title = 'Thống kê doanh số theo tháng';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'thong-ke-doanh-so';

    /** @var array<string, mixed> | null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:Booking');
    }

    public static function xemTatCa(): bool
    {
        return (bool) auth()->user()?->can('giao', Booking::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.thong-ke.tong-hop')
                ->viewData(fn () => ['dong' => $this->tongHop(), 'thang' => $this->thangDangXem(), 'tatCa' => self::xemTatCa()]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Chi tiết đơn đã chốt')
            ->query(fn () => Booking::query()
                ->whereNotNull('confirmed_at')
                ->when(! self::xemTatCa(), fn (Builder $query) => $query->where('assigned_to', auth()->id()))
                ->with(['tour:id,name', 'departure:id,start_date', 'nguoiPhuTrach:id,name']))
            ->columns([
                TextColumn::make('booking_code')->label('Mã đơn')->searchable()
                    ->url(fn (Booking $r) => BookingResource::getUrl('view', ['record' => $r])),
                TextColumn::make('confirmed_at')->label('Ngày chốt')->date('d/m/Y')->sortable(),
                TextColumn::make('nguoiPhuTrach.name')->label('Phụ trách')->placeholder('—')
                    ->visible(fn () => self::xemTatCa()),
                TextColumn::make('customer_name')->label('Khách hàng')->searchable()
                    ->description(fn (Booking $r) => $r->customer_phone),
                TextColumn::make('tour.name')->label('Tour')->wrap()->limit(50)
                    ->description(fn (Booking $r) => $r->departure?->start_date ? 'Đi '.$r->departure->start_date->format('d/m/Y') : null),
                TextColumn::make('so_khach')->label('Khách')->state(fn (Booking $r) => $r->adults + $r->children)->alignCenter(),
                TextColumn::make('total_price')->label('Doanh số')->money('VND')->sortable(),
                TextColumn::make('paid_total')->label('Đã thu')->money('VND')
                    ->description(fn (Booking $r) => $r->status !== 'cancelled' && $r->total_price > $r->paid_total
                        ? 'Còn '.number_format($r->total_price - $r->paid_total, 0, ',', '.').'đ' : null),
                TextColumn::make('tinh_trang_tien')->label('Tiền')->badge()
                    ->state(fn (Booking $r) => Booking::TIEN[$r->tinhTrangTien()])
                    ->color(fn (Booking $r) => match ($r->tinhTrangTien()) {
                        'da_thu_du' => 'success', 'du_coc' => 'info', 'dang_coc' => 'warning', default => 'gray',
                    }),
                TextColumn::make('status')->label('Tour')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'completed' => 'Hoàn thành', 'cancelled' => 'Đã huỷ', default => 'Chưa đi / đang đi',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success', 'cancelled' => 'danger', default => 'info',
                    }),
            ])
            ->defaultSort('confirmed_at', 'desc')
            ->filters([
                SelectFilter::make('thang')
                    ->label('Tháng chốt đơn')
                    ->options(self::cacThang())
                    ->default(now()->format('Y-m'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->chotTrongThang($data['value']) : $query),
                SelectFilter::make('assigned_to')
                    ->label('Người phụ trách')
                    ->relationship('nguoiPhuTrach', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => self::xemTatCa()),
                SelectFilter::make('tien')
                    ->label('Tình trạng tiền')
                    ->options(Booking::TIEN)
                    ->query(fn (Builder $query, array $data) => self::locTien($query, $data['value'] ?? null)),
                SelectFilter::make('status')
                    ->label('Tình trạng tour')
                    ->options(['confirmed' => 'Chưa đi / đang đi', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã huỷ']),
            ], layout: FiltersLayout::AboveContent)
            // Chọn là lọc ngay (không cần bấm "Áp dụng"), để bảng tổng hợp và
            // file Excel luôn khớp với những gì đang chọn trên màn hình
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('xuatExcel')
                ->label('Xuất Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->xuatExcel()),
        ];
    }

    /** @return array<string, string> 24 tháng gần nhất + tháng sau */
    public static function cacThang(): array
    {
        $ds = [];
        for ($i = -1; $i < 24; $i++) {
            $t = now()->startOfMonth()->subMonths($i);
            $ds[$t->format('Y-m')] = 'Tháng '.$t->format('m/Y');
        }

        return $ds;
    }

    public static function locTien(Builder $q, ?string $loai): Builder
    {
        return match ($loai) {
            'chua_coc' => $q->where('paid_total', '<=', 0),
            'da_thu_du' => $q->where('paid_total', '>', 0)->whereColumn('paid_total', '>=', 'total_price'),
            'du_coc' => $q->where('paid_total', '>', 0)->whereColumn('paid_total', '<', 'total_price')
                ->whereNotNull('deposit_amount')->whereColumn('paid_total', '>=', 'deposit_amount'),
            'dang_coc' => $q->where('paid_total', '>', 0)->whereColumn('paid_total', '<', 'total_price')
                ->where(fn ($w) => $w->whereNull('deposit_amount')->orWhereColumn('paid_total', '<', 'deposit_amount')),
            default => $q,
        };
    }

    /** "Tháng 10/2026 · Phụ trách: Thu Trang · Tiền: Đang cọc" — ghi lên đầu file Excel. */
    public function moTaBoLoc(): string
    {
        $giaTri = fn (string $k) => $this->tableFilters[$k]['value'] ?? null;
        $phuTrach = $giaTri('assigned_to') ? User::whereKey($giaTri('assigned_to'))->value('name') : null;

        return collect([
            $this->thangDangXem() ? 'Tháng '.$this->thangDangXem() : 'Mọi tháng',
            $phuTrach ? 'Phụ trách: '.$phuTrach : (self::xemTatCa() ? null : 'Phụ trách: '.auth()->user()?->name),
            $giaTri('tien') ? 'Tiền: '.(Booking::TIEN[$giaTri('tien')] ?? $giaTri('tien')) : null,
            $giaTri('status') ? 'Tour: '.(self::TOUR[$giaTri('status')] ?? $giaTri('status')) : null,
            filled($this->tableSearch ?? null) ? 'Tìm: '.$this->tableSearch : null,
        ])->filter()->implode(' · ');
    }

    public const TOUR = ['confirmed' => 'Chưa đi / đang đi', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã huỷ'];

    public function thangDangXem(): ?string
    {
        $t = $this->tableFilters['thang']['value'] ?? null;

        return $t ? Carbon::createFromFormat('!Y-m', $t)->format('m/Y') : null;
    }

    /**
     * Một dòng mỗi người phụ trách (theo bộ lọc đang chọn) + dòng tổng.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tongHop(): Collection
    {
        $ds = $this->getFilteredTableQuery()->with('nguoiPhuTrach:id,name')->get();

        $dong = $ds->groupBy(fn (Booking $b) => $b->assigned_to ?? 0)
            ->map(fn (Collection $g) => self::cong($g, $g->first()->nguoiPhuTrach?->name ?? '(Chưa ai phụ trách)'))
            ->sortByDesc('doanh_so')
            ->values();

        return $dong->count() > 1 ? $dong->push(self::cong($ds, 'TỔNG CỘNG', true)) : $dong;
    }

    /** @param  Collection<int, Booking>  $g */
    private static function cong(Collection $g, string $ten, bool $tong = false): array
    {
        $con = $g->where('status', '!=', 'cancelled');
        $tien = $con->countBy(fn (Booking $b) => $b->tinhTrangTien());

        return [
            'ten' => $ten,
            'tong' => $tong,
            'so_don' => $con->count(),
            'so_khach' => $con->sum(fn (Booking $b) => $b->adults + $b->children),
            'doanh_so' => (int) $con->sum('total_price'),
            'da_thu' => (int) $con->sum('paid_total'),
            'con_no' => (int) $con->sum(fn (Booking $b) => max(0, $b->total_price - $b->paid_total)),
            'chua_coc' => $tien['chua_coc'] ?? 0,
            'dang_coc' => ($tien['dang_coc'] ?? 0) + ($tien['du_coc'] ?? 0),
            'da_thu_du' => $tien['da_thu_du'] ?? 0,
            'hoan_thanh' => $con->where('status', 'completed')->count(),
            'huy' => $g->where('status', 'cancelled')->count(),
        ];
    }

    public const COT_TONG_HOP = [
        'ten' => 'Nhân viên phụ trách', 'so_don' => 'Đơn chốt', 'so_khach' => 'Số khách',
        'doanh_so' => 'Doanh số', 'da_thu' => 'Đã thu', 'con_no' => 'Còn nợ',
        'chua_coc' => 'Chưa cọc', 'dang_coc' => 'Đang cọc', 'da_thu_du' => 'Đã thu đủ',
        'hoan_thanh' => 'Tour hoàn thành', 'huy' => 'Đơn huỷ',
    ];

    public function xuatExcel()
    {
        $ds = $this->getFilteredSortedTableQuery()
            ->with(['tour:id,name', 'departure:id,start_date', 'nguoiPhuTrach:id,name', 'nguoiTao:id,name', 'nguoiXacNhan:id,name'])
            ->get();

        $wb = new Spreadsheet;
        $s1 = $wb->getActiveSheet()->setTitle('Tổng hợp');
        $s1->setCellValue('A1', 'THỐNG KÊ DOANH SỐ — '.$this->moTaBoLoc());
        $s1->fromArray(array_values(self::COT_TONG_HOP), null, 'A3');
        $s1->fromArray($this->tongHop()->map(fn ($d) => array_map(fn ($k) => $d[$k], array_keys(self::COT_TONG_HOP)))->all(), null, 'A4');

        $s2 = $wb->createSheet()->setTitle('Chi tiết đơn');
        $s2->fromArray(['Mã đơn', 'Ngày chốt', 'Phụ trách', 'Người tạo', 'Người xác nhận', 'Khách hàng', 'Điện thoại',
            'Tour', 'Ngày đi', 'Số khách', 'Doanh số', 'Tiền cọc', 'Đã thu', 'Còn nợ', 'Tình trạng tiền', 'Tình trạng tour'], null, 'A1');
        $s2->fromArray($ds->map(fn (Booking $b) => [
            $b->booking_code, $b->confirmed_at?->format('d/m/Y'), $b->nguoiPhuTrach?->name, $b->nguoiTao?->name ?? 'Khách đặt web',
            $b->nguoiXacNhan?->name, $b->customer_name, $b->customer_phone, $b->tour?->name, $b->departure?->start_date?->format('d/m/Y'),
            $b->adults + $b->children, (int) $b->total_price, $b->deposit_amount, (int) $b->paid_total,
            $b->status === 'cancelled' ? 0 : max(0, $b->total_price - $b->paid_total),
            Booking::TIEN[$b->tinhTrangTien()],
            match ($b->status) {
                'completed' => 'Hoàn thành', 'cancelled' => 'Đã huỷ', default => 'Chưa đi / đang đi'
            },
        ])->all(), null, 'A2');
        $s2->getStyle('G:G')->getNumberFormat()->setFormatCode('@');

        $s1->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $s1->getStyle('3:3')->getFont()->setBold(true);
        $s1->freezePane('A4');
        $s2->getStyle('1:1')->getFont()->setBold(true);
        $s2->freezePane('A2');
        foreach ([$s1, $s2] as $s) {
            foreach (range('A', $s->getHighestColumn()) as $c) {
                $s->getColumnDimension($c)->setAutoSize(true);
            }
        }
        foreach (['D', 'E', 'F'] as $c) {
            $s1->getStyle($c.':'.$c)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (['K', 'L', 'M', 'N'] as $c) {
            $s2->getStyle($c.':'.$c)->getNumberFormat()->setFormatCode('#,##0');
        }

        $phuTrach = ($id = $this->tableFilters['assigned_to']['value'] ?? null) ? User::whereKey($id)->value('name') : null;
        $ten = 'Thong-ke-doanh-so-'.str_replace('/', '-', $this->thangDangXem() ?? 'tat-ca')
            .($phuTrach ? '-'.Str::slug($phuTrach) : '').'.xlsx';

        return response()->streamDownload(fn () => (new Xlsx($wb))->save('php://output'), $ten, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
