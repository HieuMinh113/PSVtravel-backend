<?php

namespace App\Filament\Resources\MaldivesResorts\Schemas;

use App\Filament\Support\NhieuDong;
use App\Services\TepTaiLen;
use App\Services\YouTube;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Resort: nhập một lần, mọi combo của resort này dùng chung (giới thiệu, ảnh,
 * hạng phòng, tiện ích, quy định). Giá nằm ở combo (theo ngày khởi hành).
 */
class MaldivesResortForm
{
    /** Khung tiện ích thường gặp trong file đối tác — resort mới mở ra đã có sẵn */
    private const TIEN_ICH = ['Nhà hàng bữa trưa', 'Nhà hàng bữa tối', 'Đồ uống', 'Miễn phí tour, water sport', 'Giải trí',
        'Sức khoẻ', 'Thể thao - games', 'Trẻ em 4 - 12 tuổi', 'Phục vụ thêm'];

    private const QUY_DINH = ['Chứng từ cần có để nhập cảnh', 'Thời gian nhận phòng – trả phòng', 'Quy định về thanh toán',
        'Chính sách huỷ đặt phòng / vắng mặt', 'Ưu đãi tuần trăng mật', 'Ưu đãi sinh nhật và kỷ niệm ngày cưới',
        'Trẻ em ở Water Villa, giường phụ', 'Quy định sử dụng thuỷ phi cơ và phòng chờ'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Thông tin resort')->columns(2)->schema([
                TextInput::make('name')->label('Tên resort')->required()->maxLength(255)->placeholder('Sun Siyam World'),
                TextInput::make('slug')->label('Đường dẫn')
                    ->helperText('Để trống thì tự tạo theo tên. Chỉ chữ thường không dấu và gạch ngang.')
                    ->maxLength(150)->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Str::slug($state) : null)
                    ->rule('nullable')->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                Select::make('stars')->label('Hạng sao')->options(['3' => '3 sao', '4' => '4 sao', '4.5' => '4,5 sao', '5' => '5 sao'])->default('5')
                    ->formatStateUsing(fn ($state) => $state === null ? null : (string) (fmod((float) $state, 1.0) ? (float) $state : (int) $state)),
                TextInput::make('atoll')->label('Vị trí (đảo san hô)')->placeholder('Noonu Atoll')->maxLength(255),
                TextInput::make('package_name')->label('Gói dịch vụ tại resort')->placeholder('WOW Premium All-Inclusive')->maxLength(255),
                Select::make('status')->label('Trạng thái')->options(['published' => 'Đang hiện', 'hidden' => 'Tạm ẩn'])->default('published')->required()
                    ->helperText('Tạm ẩn thì mọi combo của resort này cũng ẩn khỏi website.'),
                TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
            ]),

            Section::make('Giới thiệu')->schema([
                Textarea::make('intro')->label('Giới thiệu resort')->rows(7)
                    ->helperText('Mỗi đoạn cách nhau một dòng trống. Chỉ ghi thông tin đúng của resort.'),
                NhieuDong::o('highlights', 'Điểm nổi bật (mỗi dòng một ý)', 4),
                TextInput::make('video_url')->label('Link video YouTube')->placeholder('https://www.youtube.com/watch?v=...')
                    ->maxLength(255)->rules([YouTube::quyTac()]),
            ]),

            Section::make('Ảnh')->description('Dùng ảnh resort cấp chính thức (media kit) hoặc ảnh công ty tự chụp — ảnh lấy từ file đối tác có thể vướng bản quyền.')
                ->schema([
                    FileUpload::make('cover_image')->label('Ảnh bìa')->image()->acceptedFileTypes(TepTaiLen::ANH)
                        ->directory('maldives')->disk('public')->maxSize(15360),
                    FileUpload::make('gallery')->label('Bộ ảnh')->multiple()->reorderable()->image()->acceptedFileTypes(TepTaiLen::ANH)
                        ->directory('maldives')->disk('public')->maxSize(15360)->maxFiles(20)->panelLayout('grid'),
                ]),

            Section::make('Hạng phòng')->description('Giá từng hạng phòng nhập ở combo, theo ngày khởi hành.')->schema([
                Repeater::make('roomTypes')->hiddenLabel()->relationship()
                    ->schema([
                        TextInput::make('name')->label('Tên hạng phòng')->required()->maxLength(255)->placeholder('Water Villa with Pool & Slide'),
                        TextInput::make('capacity')->label('Sức chứa')->maxLength(255)->placeholder('3 người lớn hoặc 2 người lớn + 1 trẻ em'),
                        Toggle::make('adults_only')->label('Chỉ nhận người lớn (từ 18 tuổi)')
                            ->helperText('Website ẩn ô trẻ em / em bé khi khách chọn hạng phòng này.')->columnSpanFull(),
                        Textarea::make('description')->label('Mô tả ngắn')->rows(2),
                        FileUpload::make('image')->label('Ảnh phòng')->image()->acceptedFileTypes(TepTaiLen::ANH)
                            ->directory('maldives')->disk('public')->maxSize(15360),
                    ])->columns(2)
                    ->orderColumn('sort_order')->reorderable()->collapsible()
                    ->itemLabel(fn (array $state): string => $state['name'] ?? 'Hạng phòng mới')
                    ->addActionLabel('Thêm hạng phòng'),
            ]),

            Section::make('Tiện ích tại resort')->collapsible()->schema([
                Repeater::make('amenities')->hiddenLabel()
                    ->schema([
                        TextInput::make('title')->label('Nhóm')->required()->maxLength(255),
                        NhieuDong::o('items', 'Mỗi dòng một mục', 5),
                    ])
                    ->default(fn () => array_map(fn ($t) => ['title' => $t, 'items' => []], self::TIEN_ICH))
                    ->helperText('Nhóm để trống nội dung sẽ không hiện ra ngoài web.')
                    ->itemLabel(fn (array $state): string => $state['title'] ?? 'Nhóm mới')
                    ->collapsed()->collapsible()->reorderable()->addActionLabel('Thêm nhóm tiện ích'),
            ]),

            Section::make('Quy định của resort')->collapsible()->schema([
                Repeater::make('policies')->hiddenLabel()
                    ->schema([
                        TextInput::make('title')->label('Mục')->required()->maxLength(255),
                        Textarea::make('content')->label('Nội dung')->rows(5),
                    ])
                    ->default(fn () => array_map(fn ($t) => ['title' => $t, 'content' => ''], self::QUY_DINH))
                    ->itemLabel(fn (array $state): string => $state['title'] ?? 'Mục mới')
                    ->collapsed()->collapsible()->reorderable()->addActionLabel('Thêm mục'),
            ]),
        ]);
    }
}
