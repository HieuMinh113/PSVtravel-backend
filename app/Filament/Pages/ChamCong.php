<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Attendances\BangChamCongCot;
use App\Models\Attendance;
use App\Models\AttendanceFace;
use App\Services\ChamCong\CauHinhChamCong;
use App\Services\ChamCong\ChamCong as DichVuChamCong;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Url;

/**
 * Trang nhân viên tự chấm công: quét khuôn mặt + lấy vị trí, khớp là chấm vào / ra,
 * ghi lý do khi trễ / về sớm / ngoài công ty, xin bổ sung công, xem lịch sử
 * của mình. Lần đầu: đồng ý + đăng ký khuôn mặt.
 */
class ChamCong extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'Chấm công';

    protected static ?string $navigationLabel = 'Chấm công';

    protected static ?string $title = 'Chấm công';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'cham-cong';

    /** Số lần quét không khớp tối đa trong 10 phút (chặn dò thử khuôn mặt). */
    public const SO_LAN_KHONG_KHOP = 40;

    /** @var array<string, mixed> | null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->vaoDuocQuanTri();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.cham-cong.trang')->viewData(fn () => $this->duLieuTrang()),
            EmbeddedTable::make(),
        ]);
    }

    /** @return array<string, mixed> */
    public function duLieuTrang(): array
    {
        $homNay = Attendance::where('user_id', auth()->id())->whereDate('work_date', today())->first();

        return [
            'homNay' => $homNay,
            'lich' => CauHinhChamCong::lichNgay(today()),
            'daDangKy' => AttendanceFace::where('user_id', auth()->id())->exists(),
            'coToaDo' => CauHinhChamCong::toaDo() !== null,
            'laQuanLy' => (bool) auth()->user()?->can('ViewAll:Attendance'),
            'js' => asset('js/psv/cham-cong.js').'?v='.@filemtime(public_path('js/psv/cham-cong.js')),
            'thuVien' => asset('vendor/face-api/face-api.js'),
            'moHinh' => asset('vendor/face-api/model'),
        ];
    }

    /** Gọi từ trình duyệt (Alpine): đăng ký khuôn mặt lần đầu. */
    public function dangKyKhuonMat($descriptor, $anh, $dongY): array
    {
        if (! $dongY) {
            return ['ok' => false, 'loi' => 'Bạn cần đồng ý cho công ty dùng ảnh khuôn mặt để chấm công.'];
        }
        try {
            app(DichVuChamCong::class)->dangKy(auth()->user(), is_array($descriptor) ? $descriptor : null, is_string($anh) ? $anh : null);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'loi' => $e->getMessage()];
        }

        return ['ok' => true, 'thong_bao' => 'Đã đăng ký khuôn mặt. Từ giờ bấm "Chấm công vào" / "Chấm công ra" rồi đưa mặt vào khung là xong.', 'canh_bao' => []];
    }

    /**
     * Gọi từ trình duyệt (Alpine): chấm vào / ra. Lúc quét, trình duyệt gọi
     * liên tục với chi_khi_khop — chưa khớp thì không vẽ lại trang (đỡ nặng).
     */
    public function chamCong($loai, $duLieu): array
    {
        if (! in_array($loai, ['vao', 'ra'], true) || ! is_array($duLieu)) {
            return ['ok' => false, 'loi' => 'Yêu cầu không hợp lệ.'];
        }
        $quet = ! empty($duLieu['chi_khi_khop']);
        $khoa = 'cham-cong-quet:'.auth()->id();
        if ($quet && RateLimiter::tooManyAttempts($khoa, self::SO_LAN_KHONG_KHOP)) {
            $this->skipRender();

            return ['ok' => false, 'qua_nhieu' => true, 'loi' => 'Khuôn mặt không khớp quá nhiều lần. Đợi '.max(1, (int) ceil(RateLimiter::availableIn($khoa) / 60))
                .' phút rồi quét lại, hoặc bấm "Gửi ảnh cho quản lý duyệt".'];
        }

        $kq = app(DichVuChamCong::class)->cham(auth()->user(), $loai, $duLieu);
        if (! empty($kq['khong_khop'])) {
            RateLimiter::hit($khoa, 600);
            $this->skipRender();
        }

        return $kq;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('boSung')
                ->label('Xin bổ sung công')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->modalHeading('Xin bổ sung công (quên chấm)')
                ->modalDescription('Quản lý duyệt xong mới được tính công.')
                ->schema([
                    DatePicker::make('ngay')->label('Ngày')->native(false)->displayFormat('d/m/Y')
                        ->maxDate(today())->minDate(today()->subDays(31))->default(today())->required(),
                    Select::make('loai')->label('Bổ sung giờ')->options(['vao' => 'Giờ vào', 'ra' => 'Giờ ra'])->default('vao')->required(),
                    TimePicker::make('gio')->label('Giờ thực tế')->seconds(false)->required(),
                    Textarea::make('ly_do')->label('Lý do')->required()->minLength(3)->rows(2),
                ])
                ->modalSubmitActionLabel('Gửi quản lý duyệt')
                ->action(function (array $data, Action $action) {
                    try {
                        app(DichVuChamCong::class)->boSung(auth()->user(), Carbon::parse($data['ngay']), $data['loai'],
                            substr((string) $data['gio'], 0, 5), $data['ly_do']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        $action->halt();
                    }
                    Notification::make()->title('Đã gửi xin bổ sung công — chờ quản lý duyệt')->success()->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Chấm công của tôi')
            ->description(fn () => $this->tomTat())
            ->query(fn () => Attendance::query()->where('user_id', auth()->id()))
            ->columns(BangChamCongCot::cot(false))
            ->defaultSort('work_date', 'desc')
            ->filters([
                SelectFilter::make('thang')
                    ->label('Tháng')
                    ->options(ThongKeDoanhSo::cacThang())
                    ->default(now()->format('Y-m'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereBetween('work_date', [
                            Carbon::createFromFormat('!Y-m', $data['value'])->startOfMonth()->toDateString(),
                            Carbon::createFromFormat('!Y-m', $data['value'])->endOfMonth()->toDateString(),
                        ])
                        : $query),
            ])
            ->deferFilters(false)
            ->paginated([31])
            ->emptyStateHeading('Chưa có ngày chấm công nào trong tháng này');
    }

    private function tomTat(): string
    {
        $ds = $this->getFilteredTableQuery()->get();
        $t = BangCongThang::cong($ds);

        return 'Công: '.BangCongThang::so($t['cong'])
            .' · Đi trễ: '.$t['tre_lan'].' lần ('.$t['tre_phut'].' phút)'
            .' · Về sớm: '.$t['som_lan'].' lần ('.$t['som_phut'].' phút)'
            .($t['cho_duyet'] ? ' · Chờ duyệt: '.$t['cho_duyet'] : '');
    }
}
