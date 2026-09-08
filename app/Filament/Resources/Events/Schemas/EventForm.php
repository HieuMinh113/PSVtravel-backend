<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Tên gói / sự kiện')
                    ->required()
                    ->maxLength(255)
                    // Gõ tên xong, nếu slug còn trống thì tự điền slug không dấu
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $get, $set) {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Chỉ chữ thường, số và dấu gạch ngang. Ví dụ: team-building-bai-bien')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages([
                        'regex' => 'Slug chỉ gồm chữ thường, số và dấu gạch ngang (không dấu, không khoảng trắng).',
                    ]),

                CheckboxList::make('audience')
                    ->label('Phù hợp với')
                    ->helperText('Chọn một hoặc nhiều nhóm khách phù hợp với gói này')
                    ->options(\App\Models\Event::DOI_TUONG)
                    ->columns(3)
                    ->columnSpanFull(),

                FileUpload::make('cover_image')
                    ->label('Ảnh bìa')
                    ->image()
                    ->directory('events')
                    ->disk('public')
                    ->columnSpanFull(),
                FileUpload::make('gallery')
                    ->label('Hình ảnh thực tế')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->directory('events')
                    ->disk('public')
                    ->helperText('Ảnh thực tế của chương trình. Để trống thì web tự lấy ảnh từ các ngày trong "Chương trình theo ngày".')
                    ->columnSpanFull(),

                Textarea::make('summary')
                    ->label('Mô tả ngắn')
                    ->helperText('Đoạn ngắn hiện ở thẻ ngoài danh sách')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),

                Textarea::make('includes')
                    ->label('Sẽ có gì (mỗi dòng một mục)')
                    ->helperText('Liệt kê hoạt động / dịch vụ trong gói — mỗi dòng một ý, ví dụ: MC & trò chơi team building')
                    ->rows(6)
                    ->columnSpanFull()
                    // Lưu thành mảng dòng; hiện lại thành văn bản nhiều dòng
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) ($state ?? ''))
                    ->dehydrateStateUsing(fn ($state): array => collect(preg_split('/\r\n|\r|\n/', (string) $state))
                        ->map(fn ($x) => trim($x, " \t\u{00A0}-•▪➢"))
                        ->filter()
                        ->values()
                        ->all()),

                RichEditor::make('description')
                    ->label('Giới thiệu chi tiết')
                    ->columnSpanFull(),

                Repeater::make('itinerary')
                    ->label('Chương trình theo ngày')
                    ->helperText('Mỗi mục là một ngày. Gói 1 buổi (gala…) có thể bỏ trống khối này.')
                    ->addActionLabel('Thêm ngày')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->schema([
                        TextInput::make('title')
                            ->label('Tiêu đề ngày')
                            ->placeholder('VD: Ngày 1 — Di chuyển & trò chơi bãi biển')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(3),
                        FileUpload::make('images')
                            ->label('Ảnh của ngày này')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('events')
                            ->disk('public'),
                    ])
                    ->columnSpanFull(),

                TextInput::make('group_size')
                    ->label('Số người phù hợp')
                    ->placeholder('VD: 20 – 200 khách')
                    ->maxLength(100),
                TextInput::make('duration')
                    ->label('Thời lượng')
                    ->placeholder('VD: 2 ngày 1 đêm')
                    ->maxLength(100),
                TextInput::make('location')
                    ->label('Địa điểm gợi ý')
                    ->placeholder('VD: Vũng Tàu, Đà Lạt...')
                    ->maxLength(255),
                TextInput::make('price_note')
                    ->label('Ghi chú giá')
                    ->placeholder('VD: Liên hệ báo giá / Từ 850.000đ/khách')
                    ->maxLength(255),

                Toggle::make('is_featured')
                    ->label('Gói nổi bật (đưa lên đầu)')
                    ->default(false),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự sắp xếp')
                    ->helperText('Số nhỏ hiện trước')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
