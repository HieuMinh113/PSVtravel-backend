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
 * Toạ độ công ty, bán kính, giờ làm (thứ 2–6, thứ 7 nửa ngày), số phút được
 * trễ. Đứng tại công ty bấm "Dùng vị trí hiện tại" để lấy toạ độ cho chuẩn.
 */
class CauHinhChamCong extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Chấm công';

    protected static ?string $navigationLabel = 'Cấu hình chấm công';

    protected static ?string $title = 'Cấu hình chấm công';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'cau-hinh-cham-cong';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public const KHOA = ['cham_cong_lat', 'cham_cong_lng', 'cham_cong_ban_kinh', 'cham_cong_vao', 'cham_cong_ra',
        'cham_cong_vao_t7', 'cham_cong_ra_t7', 'cham_cong_phut_tre'];

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
                ->description('Chủ nhật nghỉ. Thứ 7 làm nửa ngày (0,5 công).')
                ->columns(3)
                ->schema([
                    TimePicker::make('cham_cong_vao')->label('Giờ vào thứ 2 – 6')->seconds(false)->required(),
                    TimePicker::make('cham_cong_ra')->label('Giờ ra thứ 2 – 6')->seconds(false)->required(),
                    TextInput::make('cham_cong_phut_tre')->label('Được trễ / về sớm (phút)')->numeric()->minValue(0)->maxValue(120)->required()
                        ->helperText('Trong số phút này không tính trễ / sớm, không phải ghi lý do.'),
                    TimePicker::make('cham_cong_vao_t7')->label('Giờ vào thứ 7')->seconds(false)->required(),
                    TimePicker::make('cham_cong_ra_t7')->label('Giờ ra thứ 7')->seconds(false)->required(),
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
                'label' => Setting::where('key', $k)->value('label') ?? $k,
                'sort_order' => 900 + $i,
            ]);
        }
        Notification::make()->title('Đã lưu cấu hình chấm công')->success()->send();
    }
}
