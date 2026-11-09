<?php

namespace App\Filament\Pages;

use App\Services\ChamCong\CauHinhChamCong as CauHinh;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Page\Models\Setting;

/**
 * Toạ độ công ty, bán kính, giờ làm (thứ 2–6, thứ 7 nửa ngày), số phút không
 * tính trễ / về sớm, trễ quá bao nhiêu phút phải ghi lý do. Đứng tại công ty
 * bấm "Dùng vị trí hiện tại" để lấy toạ độ cho chuẩn.
 */
class CauHinhChamCong extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Nhân sự';

    protected static ?string $navigationLabel = 'Cấu hình chấm công';

    protected static ?string $title = 'Cấu hình chấm công';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'cau-hinh-cham-cong';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public const KHOA = ['cham_cong_lat', 'cham_cong_lng', 'cham_cong_ban_kinh', 'cham_cong_vao', 'cham_cong_ra',
        'cham_cong_vao_t7', 'cham_cong_ra_t7', 'cham_cong_phut_tre', 'cham_cong_phut_ly_do'];

    /** Nhãn cho khoá thêm sau (các khoá cũ đã có nhãn từ migration). */
    private const NHAN = ['cham_cong_phut_ly_do' => 'Phải ghi lý do khi đi trễ quá (phút)'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAll:Attendance');
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KHOA)->mapWithKeys(fn ($k) => [$k => CauHinh::lay($k)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Vị trí công ty')
                ->description('529 Huỳnh Tấn Phát, Q.7. Đứng tại công ty, bấm "Dùng vị trí hiện tại" rồi Lưu. Hoặc lấy toạ độ từ Google Maps (bấm chuột phải vào điểm trên bản đồ).')
                ->columns(3)
                ->schema([
                    TextInput::make('cham_cong_lat')->label('Vĩ độ (lat)')->numeric()->minValue(-90)->maxValue(90)->placeholder('10.7…'),
                    TextInput::make('cham_cong_lng')->label('Kinh độ (lng)')->numeric()->minValue(-180)->maxValue(180)->placeholder('106.7…'),
                    TextInput::make('cham_cong_ban_kinh')->label('Bán kính (mét)')->numeric()->minValue(20)->maxValue(5000)->required(),
                    Actions::make([
                        Action::make('viTriHienTai')
                            ->label('Dùng vị trí hiện tại')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->color('gray')
                            ->alpineClickHandler(<<<'JS'
                                if (!navigator.geolocation) { alert('Trình duyệt không hỗ trợ định vị'); return }
                                navigator.geolocation.getCurrentPosition(
                                    (v) => {
                                        $wire.set('data.cham_cong_lat', v.coords.latitude.toFixed(7))
                                        $wire.set('data.cham_cong_lng', v.coords.longitude.toFixed(7))
                                        $tooltip('Đã lấy vị trí (sai số ±' + Math.round(v.coords.accuracy) + ' m) — bấm Lưu', { theme: $store.theme, timeout: 4000 })
                                    },
                                    () => alert('Không lấy được vị trí: hãy cho phép trình duyệt truy cập vị trí (trang phải mở bằng https).'),
                                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
                                )
                                JS),
                    ])->columnSpanFull(),
                ]),
            Section::make('Giờ làm')
                ->description('Chủ nhật nghỉ. Thứ 7 làm nửa ngày (0,5 công). Về sớm hoặc quên chấm ra: ngày đó chỉ tính nửa công (0,5 ngày thường, 0,25 thứ 7) — trừ khi quản lý chấp nhận lý do / duyệt giờ ra bổ sung. Tính theo phút như giờ hiển thị: giờ vào 08:30 thì 08:30 là đúng giờ, 08:31 là trễ 1 phút. Đổi giờ ở đây chỉ áp dụng cho lượt chấm sau khi lưu.')
                ->columns(3)
                ->schema([
                    TimePicker::make('cham_cong_vao')->label('Giờ vào thứ 2 – 6')->seconds(false)->required(),
                    TimePicker::make('cham_cong_ra')->label('Giờ ra thứ 2 – 6')->seconds(false)->required(),
                    TextInput::make('cham_cong_phut_tre')->label('Không tính trễ / về sớm trong (phút)')->numeric()->minValue(0)->maxValue(120)->required()
                        ->helperText('Trễ / về sớm trong số phút này coi như đúng giờ. Để 0: vào trễ 1 phút là tính trễ 1 phút.'),
                    TimePicker::make('cham_cong_vao_t7')->label('Giờ vào thứ 7')->seconds(false)->required(),
                    TimePicker::make('cham_cong_ra_t7')->label('Giờ ra thứ 7')->seconds(false)->required(),
                    TextInput::make('cham_cong_phut_ly_do')->label('Phải ghi lý do khi đi trễ quá (phút)')->numeric()->minValue(0)->maxValue(240)
                        ->placeholder('Để trống = trễ là phải ghi lý do')
                        ->helperText('Trễ ít hơn vẫn tính phút trễ vào bảng công nhưng không phải ghi lý do. Về sớm thì luôn phải ghi lý do.'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('luu')
                ->footer([Actions::make([Action::make('luu')->label('Lưu')->submit('luu')])]),
        ]);
    }

    public function luu(): void
    {
        $d = $this->form->getState();
        foreach (self::KHOA as $i => $k) {
            $v = $d[$k] ?? null;
            if (in_array($k, ['cham_cong_vao', 'cham_cong_ra', 'cham_cong_vao_t7', 'cham_cong_ra_t7'], true) && $v) {
                $v = substr((string) $v, 0, 5);
            }
            Setting::updateOrCreate(['key' => $k], [
                'value' => $v === null || $v === '' ? null : (string) $v,
                'group' => 'cham_cong',
                'type' => 'text',
                'label' => Setting::where('key', $k)->value('label') ?? self::NHAN[$k] ?? $k,
                'sort_order' => 900 + $i,
            ]);
        }
        Notification::make()->title('Đã lưu cấu hình chấm công')->success()->send();
    }
}
