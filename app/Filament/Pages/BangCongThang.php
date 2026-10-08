<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\BangChamCongCot;
use App\Models\Attendance;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bảng công tháng của mọi nhân viên (quản lý): ngày công (thứ 7 = 0,5),
 * đi trễ / về sớm (lần, phút, trong đó có phép), ngoài công ty, nghi vấn,
 * thiếu chấm ra, chờ duyệt. Xuất Excel: tổng hợp + chi tiết từng ngày.
 */
class BangCongThang extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Chấm công';

    protected static ?string $navigationLabel = 'Bảng công tháng';

    protected static ?string $title = 'Bảng công tháng';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'bang-cong-thang';

    /** @var array<string, mixed> | null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $soLieu = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAll:Attendance');
    }

    public const COT = [
        'cong' => 'Ngày công', 'tre_lan' => 'Đi trễ (lần)', 'tre_phut' => 'Đi trễ (phút)', 'tre_co_phep' => 'Trễ có phép',
        'som_lan' => 'Về sớm (lần)', 'som_phut' => 'Về sớm (phút)', 'ngoai' => 'Ngoài công ty', 'nghi_van' => 'Nghi vấn',
        'thieu_ra' => 'Thiếu chấm ra', 'cho_duyet' => 'Chờ duyệt',
    ];

    /** Nhân viên = tài khoản vào được trang quản trị (vai trò có quyền). */
    public static function nhanVien(): Builder
    {
        return User::query()->where(fn ($q) => $q->whereHas('roles.permissions')->orWhereHas('permissions'));
    }

    public function thang(): string
    {
        $t = $this->tableFilters['thang']['value'] ?? null;

        return is_string($t) && preg_match('/^\d{4}-\d{2}$/', $t) ? $t : now()->format('Y-m');
    }

    /** @return Collection<int, Attendance> */
    public function chamCongThang(): Collection
    {
        $dau = Carbon::createFromFormat('!Y-m', $this->thang());

        return Attendance::whereBetween('work_date', [$dau->toDateString(), $dau->copy()->endOfMonth()->toDateString()])
            ->with('user:id,name')->orderBy('work_date')->get();
    }

    /** @return array<string, int|float> */
    public static function cong(Collection $ds): array
    {
        $daQua = fn (Attendance $a) => $a->work_date->lt(today());

        return [
            'cong' => (float) $ds->sum(fn (Attendance $a) => $a->cong()),
            'tre_lan' => $ds->filter(fn ($a) => $a->late_minutes > 0)->count(),
            'tre_phut' => (int) $ds->sum('late_minutes'),
            'tre_co_phep' => $ds->filter(fn ($a) => $a->late_minutes > 0 && $a->coPhep())->count(),
            'som_lan' => $ds->filter(fn ($a) => $a->early_minutes > 0)->count(),
            'som_phut' => (int) $ds->sum('early_minutes'),
            'ngoai' => $ds->filter(fn ($a) => $a->ngoaiCongTy())->count(),
            'nghi_van' => $ds->filter(fn ($a) => $a->nghiVan())->count(),
            'thieu_ra' => $ds->filter(fn ($a) => $a->in_at && ! $a->out_at && $daQua($a))->count(),
            'cho_duyet' => $ds->where('review_status', 'cho_duyet')->count(),
        ];
    }

    public static function so(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');
    }

    private function soLieuNguoi(int $id): array
    {
        $this->soLieu ??= $this->chamCongThang()->groupBy('user_id')->map(fn ($g) => self::cong($g))->all();

        return $this->soLieu[$id] ?? self::cong(collect());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $cot = [TextColumn::make('name')->label('Nhân viên')->searchable()->weight('bold')];
        foreach (self::COT as $khoa => $nhan) {
            $cot[] = TextColumn::make("so_{$khoa}")
                ->label($nhan)
                ->alignEnd()
                ->state(fn (User $u) => $khoa === 'cong' ? self::so($this->soLieuNguoi($u->id)[$khoa]) : $this->soLieuNguoi($u->id)[$khoa])
                ->color(fn ($state) => in_array($khoa, ['nghi_van', 'ngoai', 'thieu_ra', 'cho_duyet', 'tre_lan', 'som_lan'], true) && (int) $state > 0 ? 'warning' : null);
        }

        return $table
            ->query(fn () => self::nhanVien())
            ->columns($cot)
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('thang')
                    ->label('Tháng')
                    ->options(ThongKeDoanhSo::cacThang())
                    ->default(now()->format('Y-m'))
                    ->query(fn (Builder $query) => $query),
            ])
            ->deferFilters(false)
            ->recordUrl(fn (User $u) => AttendanceResource::getUrl('index', ['filters' => [
                'user_id' => ['value' => $u->id],
                'thang' => ['value' => $this->thang()],
            ]]))
            ->paginated(false);
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

    public function xuatExcel()
    {
        $thang = Carbon::createFromFormat('!Y-m', $this->thang());
        $ds = $this->chamCongThang();
        $theoNguoi = $ds->groupBy('user_id');

        $wb = new Spreadsheet;
        $s1 = $wb->getActiveSheet()->setTitle('Bảng công');
        $s1->setCellValue('A1', 'BẢNG CÔNG THÁNG '.$thang->format('m/Y'));
        $s1->fromArray(['Nhân viên', 'Email', ...array_values(self::COT)], null, 'A3');
        $dong = self::nhanVien()->orderBy('name')->get()->map(fn (User $u) => [
            $u->name, $u->email, ...array_values(self::cong($theoNguoi[$u->id] ?? collect())),
        ])->all();
        $s1->fromArray($dong, null, 'A4');

        $s2 = $wb->createSheet()->setTitle('Chi tiết từng ngày');
        $s2->fromArray(['Ngày', 'Thứ', 'Nhân viên', 'Vào', 'Ra', 'Công', 'Trễ (phút)', 'Sớm (phút)', 'Ghi nhận', 'Lý do', 'Duyệt', 'Ghi chú duyệt'], null, 'A1');
        $thu = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $s2->fromArray($ds->sortBy([['work_date', 'asc']])->map(fn (Attendance $a) => [
            $a->work_date->format('d/m/Y'), $thu[$a->work_date->dayOfWeek], $a->user?->name,
            $a->in_at?->format('H:i'), $a->out_at?->format('H:i'), $a->cong(), $a->late_minutes, $a->early_minutes,
            collect([BangChamCongCot::moTa($a, 'in') ? 'Vào: '.BangChamCongCot::moTa($a, 'in') : null, BangChamCongCot::moTa($a, 'out') ? 'Ra: '.BangChamCongCot::moTa($a, 'out') : null])->filter()->implode(' | '),
            collect([$a->in_reason, $a->out_reason])->filter()->implode(' | '),
            Attendance::DUYET[$a->review_status] ?? '', $a->review_note,
        ])->values()->all(), null, 'A2');

        $s1->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $s1->getStyle('3:3')->getFont()->setBold(true);
        $s1->freezePane('C4');
        $s2->getStyle('1:1')->getFont()->setBold(true);
        $s2->freezePane('A2');
        foreach ([$s1, $s2] as $s) {
            foreach (range('A', $s->getHighestColumn()) as $c) {
                $s->getColumnDimension($c)->setAutoSize(true);
            }
        }

        return response()->streamDownload(fn () => (new Xlsx($wb))->save('php://output'), 'Bang-cong-'.$thang->format('m-Y').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
