<?php

namespace App\Filament\Resources\TeamMembers\Schemas;

use App\Services\TepTaiLen;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TeamMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Họ tên')
                    ->required()
                    ->maxLength(255),
                TextInput::make('position')
                    ->label('Chức vụ')
                    ->placeholder('VD: Giám đốc Vận hành')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email liên hệ')
                    ->email()
                    ->maxLength(255),

                FileUpload::make('photo')
                    ->label('Ảnh')
                    ->image()->acceptedFileTypes(TepTaiLen::ANH)
                    ->avatar()
                    ->imageEditor()
                    ->directory('team')
                    ->disk('public')
                    ->helperText('Nên dùng ảnh chân dung, cắt vuông cho đẹp')
                    ->columnSpanFull(),

                Textarea::make('bio')
                    ->label('Giới thiệu ngắn')
                    ->helperText('Không bắt buộc — vài dòng giới thiệu, hiện ở phần chữ bên cạnh ảnh.')
                    ->rows(4)
                    ->maxLength(600)
                    ->columnSpanFull(),

                Section::make('Trang tác giả (bài cẩm nang)')
                    ->description('Người này đứng tên bài cẩm nang thì có trang giới thiệu riêng trên website (psvtravel.com/tac-gia/…). Google và ChatGPT tin bài có người viết thật, có kinh nghiệm. Chỉ ghi thông tin THẬT mà người này đồng ý công khai — để trống mục nào thì trang ẩn mục đó.')
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('slug')
                            ->label('Đường dẫn trang tác giả')
                            ->prefix('psvtravel.com/tac-gia/')
                            ->helperText('Để trống thì tự lấy theo họ tên.')
                            ->maxLength(120)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::slug($state) : null)
                            ->unique(ignoreRecord: true),
                        Select::make('user_id')
                            ->label('Tài khoản admin của người này')
                            ->relationship('user', 'name', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', '!=', 'customer')))
                            ->searchable()
                            ->preload()
                            ->helperText('Bài người này nhập sẽ tự chọn họ làm tác giả.'),
                        TextInput::make('years_experience')
                            ->label('Số năm kinh nghiệm du lịch')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix('năm'),
                        TextInput::make('expertise')
                            ->label('Chuyên về')
                            ->placeholder('VD: Tuyến Nhật Bản, Hàn Quốc; visa châu Âu')
                            ->maxLength(255),
                        TagsInput::make('countries')
                            ->label('Những nơi đã dẫn tour / đi thực tế')
                            ->placeholder('Gõ tên nước rồi Enter')
                            ->columnSpanFull(),
                        Textarea::make('profile')
                            ->label('Giới thiệu đầy đủ (trang tác giả)')
                            ->helperText('3–6 câu: làm ở PSV từ khi nào, đã dẫn bao nhiêu đoàn, chuyến đáng nhớ. Viết ở ngôi thứ ba, VD "Anh Minh có 8 năm dẫn tour Nhật Bản…".')
                            ->rows(5)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        TagsInput::make('social_links')
                            ->label('Trang mạng xã hội công khai (không bắt buộc)')
                            ->placeholder('Dán link Facebook / LinkedIn / TikTok rồi Enter')
                            ->helperText('Chỉ link trang công khai của chính người này.')
                            ->nestedRecursiveRules(['url:http,https', 'max:255'])
                            ->columnSpanFull(),
                    ]),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự hiển thị')
                    ->helperText('Số nhỏ hiện trước')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
