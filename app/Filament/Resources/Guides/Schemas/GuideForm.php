<?php

namespace App\Filament\Resources\Guides\Schemas;

use App\Models\TeamMember;
use App\Services\KiemTraSeoBai;
use App\Services\TepTaiLen;
use App\Services\YouTube;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;

class GuideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Gợi ý SEO tính trên dữ liệu đã lưu (lưu xong tải lại trang để cập nhật)
                Section::make('Gợi ý SEO')
                    ->icon('heroicon-o-magnifying-glass')
                    ->description('Tính theo dữ liệu đã lưu — sửa xong bấm Lưu rồi tải lại trang để xem lại.')
                    ->collapsible()
                    ->columnSpanFull()
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        View::make('filament.tour-goi-y-seo')
                            ->viewData(fn (?Guide $record): array => [
                                'goiY' => $record ? KiemTraSeoBai::goiY($record) : [],
                                'tieuDe' => $record ? KiemTraSeoBai::tieuDeGoogle($record) : '',
                                'dat' => 'Bài đủ các mục SEO cơ bản.',
                            ]),
                    ]),

                TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required()
                    ->maxLength(255)
                    // Gõ tiêu đề xong tự điền đường dẫn (chỉ khi viết bài mới —
                    // đổi đường dẫn bài đã đăng là đổi link khách đang dùng)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText(fn (string $operation, ?Guide $record): string => 'Tự điền theo tiêu đề. Chỉ gồm chữ thường không dấu, số và dấu gạch ngang (kinh-nghiem-du-lich-osaka).'
                        .($operation === 'edit' && $record?->status === 'published' ? ' Bài đang hiện trên web: đổi đường dẫn thì link cũ tự chuyển sang link mới (301).' : ''))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    // Trước đây ô này nhận mọi thứ — có bài lưu nguyên tiêu đề làm
                    // đường dẫn (/cam-nang/Tempura Nhật Bản: Nguồn Gốc…)
                    ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state))
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages([
                        'regex' => 'Đường dẫn chỉ được gồm chữ thường không dấu, số và dấu gạch ngang. VD: kinh-nghiem-du-lich-osaka',
                    ]),

                // Tiêu đề hiện trên Google (thẻ <title>). Google cắt cụt tiêu đề
                // dài hơn ~70 ký tự thành "…", công cụ audit SEO báo lỗi "Long
                // title element". Để trống thì website tự dùng tiêu đề bài viết và tự rút
                // gọn nếu quá dài.
                TextInput::make('seo_title')
                    ->label('Tiêu đề SEO (hiện trên Google)')
                    ->maxLength(70)
                    ->placeholder(fn (Get $get): string => (string) $get('title'))
                    ->helperText('Không bắt buộc. Nên 50–60 ký tự, có từ khoá chính (VD: "Tour Đà Nẵng – Hội An 3N2Đ giá tốt"). Để trống = dùng tiêu đề bài viết.')
                    ->live(debounce: 500)
                    ->hint(fn (?string $state): string => mb_strlen((string) $state).'/70 ký tự'),

                Select::make('category')
                    ->label('Chuyên mục')
                    ->options([
                        'kinh-nghiem' => 'Kinh nghiệm du lịch',
                        'am-thuc' => 'Ẩm thực',
                        'thu-tuc' => 'Thủ tục giấy tờ',
                        'diem-den' => 'Điểm đến',
                        'khac' => 'Khác',
                    ])
                    ->searchable(),
                Select::make('author_id')
                    ->label('Người nhập bài (tài khoản admin)')
                    ->relationship('author', 'name')
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->preload(),
                // Tác giả hiện trên bài + trang giới thiệu tác giả (Google tin bài
                // có người viết thật, có kinh nghiệm hơn bài không rõ ai viết)
                Select::make('team_member_id')
                    ->label('Tác giả hiện trên bài')
                    ->relationship('tacGia', 'name', fn ($query) => $query->where('status', 'published'))
                    ->default(fn () => TeamMember::query()->dangHienThi()->where('user_id', auth()->id())->value('id'))
                    ->searchable()
                    ->preload()
                    ->helperText('Chọn người trong Giới thiệu công ty → Đội ngũ. Bài sẽ có khung "Về tác giả" và link tới trang giới thiệu người đó. Để trống thì chỉ ghi tên người nhập bài.'),

                Select::make('tour_id')
                    ->label('Gắn tour để đặt')
                    ->helperText('Chọn tour liên quan — bài viết sẽ hiện ô đặt tour bên phải. Để trống nếu không gắn.')
                    ->relationship('tour', 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),

                FileUpload::make('cover_image')
                    ->label('Ảnh bìa')
                    ->image()->acceptedFileTypes(TepTaiLen::ANH)
                    ->directory('guides')
                    ->disk('public')
                    ->columnSpanFull(),

                // Video minh hoạ — ô riêng, KHÔNG dán iframe vào nội dung bài (bộ
                // lọc an toàn của website sẽ loại iframe trong nội dung).
                TextInput::make('video_url')
                    ->label('Link video YouTube')
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->helperText('Video minh hoạ cho bài (VD: clip review chuyến đi). Hiện ngay dưới đoạn mở bài. Để trống nếu không có.')
                    ->maxLength(255)
                    ->rules([YouTube::quyTac()])
                    ->columnSpanFull(),

                Textarea::make('excerpt')
                    ->label('Tóm tắt')
                    ->helperText('Hiện ở danh sách bài viết và làm mô tả dưới tiêu đề trên Google — nên 120–160 ký tự, có từ khoá chính.')
                    ->live(debounce: 500)
                    ->hint(fn (?string $state): string => mb_strlen((string) $state).' ký tự')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                RichEditor::make('content')
                    ->label('Nội dung bài viết')
                    // Bật tải ảnh: nút ảnh trên thanh công cụ lưu vào disk public
                    // (storage/app/public) để ảnh hiện được ra ngoài website.
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('guides/noi-dung')
                    ->fileAttachmentsVisibility('public')
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đã đăng',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('draft')
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label('Thời điểm đăng')
                    ->helperText('Để trống thì đăng ngay khi chuyển sang Đã đăng')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false),

                TextInput::make('view_count')
                    ->label('Lượt xem')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
