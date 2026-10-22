<?php

namespace App\Filament\Resources\SeasonPages\Schemas;

use App\Models\SeasonPage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SeasonPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên trang (tiêu đề lớn trên trang)')
                    ->helperText('VD: Tour Tết Nguyên Đán 2027 — nên có chữ "tour" và tên dịp, khách hay tìm đúng cụm này')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    // Gợi ý đường dẫn từ tên khi còn trống: "Tour Tết 2027" → tour-tet-2027
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                        if (blank($get('slug')) && filled($state)) {
                            $slug = Str::slug($state);
                            $set('slug', str_starts_with($slug, 'tour-') ? $slug : 'tour-'.$slug);
                        }
                    }),
                TextInput::make('slug')
                    ->label('Đường dẫn')
                    ->prefix('psvtravel.com/')
                    ->helperText('Bắt đầu bằng "tour-", không dấu, VD: tour-tet, tour-he, tour-30-4. Đã đăng thì hạn chế đổi — link cũ trên Google sẽ hỏng.')
                    ->required()
                    ->maxLength(100)
                    ->regex(SeasonPage::MAU_SLUG)
                    ->notIn(SeasonPage::SLUG_CAM)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'regex' => 'Đường dẫn phải bắt đầu bằng "tour-", chỉ gồm chữ thường không dấu, số và dấu gạch ngang.',
                        'not_in' => 'Đường dẫn này trùng trang danh sách tour có sẵn.',
                    ]),
                Select::make('type')
                    ->label('Loại tour')
                    ->options(SeasonPage::LOAI)
                    ->placeholder('Cả trong nước và nước ngoài'),
                DatePicker::make('start_from')
                    ->label('Khởi hành từ ngày')
                    ->helperText('Tour có ngày khởi hành (còn đặt được) trong khoảng này tự hiện trên trang — không cần gắn tay từng tour.')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),
                DatePicker::make('start_to')
                    ->label('Đến ngày')
                    ->helperText('Hết dịp thì sửa khoảng ngày sang năm sau, giữ nguyên đường dẫn.')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_from')
                    ->required(),
                Textarea::make('intro')
                    ->label('Đoạn giới thiệu')
                    ->helperText('2–4 câu dưới tiêu đề: dịp gì, đi đâu, vì sao nên đặt sớm. Viết cho khách đọc, không nhồi từ khoá.')
                    ->rows(4)
                    ->maxLength(1500)
                    ->columnSpanFull(),
                FileUpload::make('cover_image')
                    ->label('Ảnh nền đầu trang')
                    ->image()->acceptedFileTypes(\App\Services\TepTaiLen::ANH)
                    ->directory('season-pages')
                    ->disk('public')
                    ->columnSpanFull(),
                Repeater::make('faqs')
                    ->label('Câu hỏi thường gặp')
                    ->helperText('3–6 câu khách hay hỏi về dịp này (giá, đặt sớm bao lâu, phụ thu lễ…). Hiện cuối trang và gửi cho Google.')
                    ->schema([
                        TextInput::make('q')->label('Câu hỏi')->required()->maxLength(255),
                        Textarea::make('a')->label('Trả lời')->required()->rows(3)->maxLength(1000),
                    ])
                    // Không mở sẵn dòng trống: dòng trống bắt buộc điền làm không lưu được trang
                    ->defaultItems(0)
                    ->maxItems(10)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                    ->columnSpanFull(),
                TextInput::make('seo_title')
                    ->label('Tiêu đề Google (không bắt buộc)')
                    ->helperText('Để trống thì dùng tên trang. Tối đa 60 ký tự cho khỏi bị cắt.')
                    ->maxLength(70),
                Textarea::make('seo_description')
                    ->label('Mô tả Google (không bắt buộc)')
                    ->helperText('Để trống thì tự ghép từ số tour, khoảng ngày và giá thấp nhất.')
                    ->rows(2)
                    ->maxLength(160),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options(['published' => 'Đang hiển thị', 'hidden' => 'Đã ẩn'])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
