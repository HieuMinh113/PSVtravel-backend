<?php

namespace App\Filament\Pages;

use App\Services\NoiDungTeamBuilding;
use App\Services\TepTaiLen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Nội dung trang giới thiệu Team Building trên website (/team-building):
 * ảnh / video đầu trang, đoạn giới thiệu, số liệu, loại hình chương trình,
 * khoảnh khắc thật, logo khách hàng (bật / tắt từng logo). Gói, đánh giá và
 * hỏi đáp vẫn lấy từ mục Gói team building, Đánh giá gói, Câu hỏi thường gặp.
 *
 * Ô để trống → website dùng chữ / số mặc định đã công bố; khối không có dữ
 * liệu thì tự ẩn. Lưu xong khoảng 1 phút website cập nhật.
 */
class TrangTeamBuilding extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung website';

    protected static ?string $navigationLabel = 'Trang team building';

    protected static ?string $title = 'Trang team building';

    protected static ?int $navigationSort = 49;

    protected static ?string $slug = 'trang-team-building';

    /** @var array<string, mixed> */
    public ?array $data = [];

    // Người sửa được gói team building + Marketing
    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->hasAnyRole(['super_admin', 'admin', 'marketing']) || $u?->can('Update:Event'));
    }

    public function mount(): void
    {
        $d = NoiDungTeamBuilding::lay();
        $this->form->fill([
            'hero_anh' => $d['hero']['anh'] ?? null,
            'hero_video_url' => $d['hero']['video_url'] ?? null,
            'hero_tieu_de' => $d['hero']['tieu_de'] ?? null,
            'hero_mo_ta' => $d['hero']['mo_ta'] ?? null,
            'gt_anh' => $d['gioi_thieu']['anh'] ?? null,
            'gt_tieu_de' => $d['gioi_thieu']['tieu_de'] ?? null,
            'gt_noi_dung' => $d['gioi_thieu']['noi_dung'] ?? null,
            'so_lieu' => $d['so_lieu'] ?? [],
            'loai_hinh' => $d['loai_hinh'] ?? [],
            'khoanh_khac' => $d['khoanh_khac'] ?? [],
            'khach_hang' => $d['khach_hang'] ?? [],
        ]);
    }

    private static function anh(string $ten, string $nhan): FileUpload
    {
        return FileUpload::make($ten)
            ->label($nhan)
            ->image()
            ->acceptedFileTypes(TepTaiLen::ANH)
            ->disk('public')
            ->directory(NoiDungTeamBuilding::THU_MUC)
            ->maxSize(15360);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Đầu trang')
                ->description('Phần đầu tiên khách nhìn thấy. Nên dùng ảnh ngang thật của một đoàn đông người, rộng từ 1600px.')
                ->columns(2)
                ->schema([
                    self::anh('hero_anh', 'Ảnh đầu trang')
                        ->helperText('Để trống: dùng ảnh bìa của gói team building đầu tiên.')
                        ->columnSpanFull(),
                    TextInput::make('hero_tieu_de')->label('Tiêu đề')->maxLength(90)
                        ->placeholder('Team Building & Du lịch công ty trọn gói'),
                    TextInput::make('hero_video_url')->label('Video giới thiệu / recap (link YouTube)')
                        ->placeholder('https://www.youtube.com/watch?v=...')
                        ->url()
                        ->helperText('Có video thì đầu trang có nút "Xem video" và trang có khối video riêng.'),
                    Textarea::make('hero_mo_ta')->label('Mô tả ngắn')->rows(2)->maxLength(300)
                        ->placeholder('Kịch bản riêng, ê-kíp trọn gói, báo giá minh bạch trong 24 giờ — cho đoàn từ vài chục đến vài trăm khách.')
                        ->columnSpanFull(),
                ]),
            Section::make('Vì sao doanh nghiệp chọn PSV')
                ->description('Giống phần "Câu chuyện" ở trang Về chúng tôi: một ảnh lớn và vài đoạn giới thiệu.')
                ->columns(2)
                ->schema([
                    self::anh('gt_anh', 'Ảnh')->helperText('Để trống: dùng ảnh trong thư viện khoảnh khắc hoặc ảnh gói.'),
                    TextInput::make('gt_tieu_de')->label('Tiêu đề')->maxLength(90)
                        ->placeholder('Một đầu mối cho cả chuyến đi của công ty bạn'),
                    Textarea::make('gt_noi_dung')->label('Nội dung')->rows(6)->maxLength(2000)
                        ->helperText('Xuống dòng để tách đoạn. Chỉ ghi điều PSV làm thật. Để trống: dùng đoạn giới thiệu mặc định.')
                        ->columnSpanFull(),
                ]),
            Section::make('Số liệu')
                ->description('Dải số nổi bật dưới đầu trang. Để trống: dùng số đã công bố (năm hoạt động, 10.000+ lượt khách/năm, giấy phép lữ hành quốc tế, nhận tổ chức toàn quốc).')
                ->schema([
                    Repeater::make('so_lieu')->hiddenLabel()
                        ->schema([
                            TextInput::make('so')->label('Con số')->required()->maxLength(20)->placeholder('120+'),
                            TextInput::make('nhan')->label('Ý nghĩa')->required()->maxLength(60)->placeholder('đoàn doanh nghiệp mỗi năm'),
                        ])
                        ->columns(2)->maxItems(4)->reorderable()->defaultItems(0)
                        ->addActionLabel('Thêm số liệu'),
                ]),
            Section::make('Loại hình chương trình')
                ->description('Các loại chương trình PSV nhận tổ chức, mỗi loại một ảnh. Để trống: website hiện 6 loại mặc định có biểu tượng.')
                ->schema([
                    Repeater::make('loai_hinh')->hiddenLabel()
                        ->schema([
                            TextInput::make('ten')->label('Tên')->required()->maxLength(60)->placeholder('Team building bãi biển'),
                            self::anh('anh', 'Ảnh'),
                            Textarea::make('mo_ta')->label('Mô tả ngắn')->rows(2)->maxLength(240)->columnSpanFull(),
                        ])
                        ->columns(2)->maxItems(9)->reorderable()->collapsible()->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['ten'] ?? null)
                        ->addActionLabel('Thêm loại hình'),
                ]),
            Section::make('Khoảnh khắc thật')
                ->description('Ảnh các đoàn PSV đã tổ chức — kéo thả để đổi thứ tự. Chưa có ảnh thì khối này ẩn.')
                ->schema([
                    self::anh('khoanh_khac', 'Ảnh')->hiddenLabel()
                        ->multiple()->reorderable()->maxFiles(30)->panelLayout('grid')->appendFiles(),
                ]),
            Section::make('Khách hàng đã đồng hành')
                ->description('Chỉ thêm logo khi doanh nghiệp đồng ý cho PSV giới thiệu. Tắt "Hiện trên web" để ẩn mà không xoá. Chưa có logo nào bật thì khối này ẩn.')
                ->schema([
                    Repeater::make('khach_hang')->hiddenLabel()
                        ->schema([
                            TextInput::make('ten')->label('Tên doanh nghiệp')->required()->maxLength(120),
                            self::anh('logo', 'Logo')->required()->helperText('Nền trong suốt (PNG) hoặc nền trắng.'),
                            Toggle::make('hien')->label('Hiện trên web')->default(true),
                        ])
                        ->columns(3)->reorderable()->collapsible()->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => ($state['ten'] ?? null).(empty($state['hien']) ? ' (đang ẩn)' : ''))
                        ->addActionLabel('Thêm khách hàng'),
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('xemTrang')
                ->label('Xem trang trên website')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(rtrim((string) config('app.frontend_url'), '/').'/team-building')
                ->openUrlInNewTab(),
        ];
    }

    public function luu(): void
    {
        $d = $this->form->getState();
        $ds = fn ($v) => array_values(is_array($v) ? $v : []);

        NoiDungTeamBuilding::luu([
            'hero' => [
                'anh' => $d['hero_anh'] ?? null,
                'video_url' => $d['hero_video_url'] ?? null,
                'tieu_de' => $d['hero_tieu_de'] ?? null,
                'mo_ta' => $d['hero_mo_ta'] ?? null,
            ],
            'gioi_thieu' => [
                'anh' => $d['gt_anh'] ?? null,
                'tieu_de' => $d['gt_tieu_de'] ?? null,
                'noi_dung' => $d['gt_noi_dung'] ?? null,
            ],
            'so_lieu' => $ds($d['so_lieu'] ?? []),
            'loai_hinh' => $ds($d['loai_hinh'] ?? []),
            'khoanh_khac' => $ds($d['khoanh_khac'] ?? []),
            'khach_hang' => array_map(fn ($k) => [...$k, 'hien' => (bool) ($k['hien'] ?? false)], $ds($d['khach_hang'] ?? [])),
        ]);

        Notification::make()->title('Đã lưu trang Team Building')->body('Khoảng 1 phút sau website cập nhật.')->success()->send();
    }
}
