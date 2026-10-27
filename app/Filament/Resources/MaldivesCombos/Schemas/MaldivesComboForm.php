<?php

namespace App\Filament\Resources\MaldivesCombos\Schemas;

use App\Filament\Support\NhieuDong;
use App\Services\TepTaiLen;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesComboDate;
use Modules\Maldives\Models\MaldivesRoomType;

/**
 * Combo Maldives — nhập theo đúng thứ tự file chương trình của đối tác: thông
 * tin chung → nơi khởi hành & lịch bay → ngày + bảng giá → lịch trình → bao
 * gồm / không bao gồm → lưu ý. Thông tin resort (tiện ích, quy định) lấy từ
 * mục Resort Maldives.
 */
class MaldivesComboForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Thông tin combo')->columns(2)->schema([
                Select::make('resort_id')->label('Resort')
                    ->relationship('resort', 'name')->searchable()->preload()->required()
                    // Đổi resort thì bảng giá đổi theo hạng phòng của resort đó
                    ->live()
                    ->helperText('Chưa có resort thì thêm ở mục Resort Maldives trước (kèm các hạng phòng).'),
                TextInput::make('name')->label('Tên combo')->required()->maxLength(255)
                    ->placeholder('Sun Siyam World 5* – bay Singapore Airlines, thuỷ phi cơ')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')->label('Đường dẫn (slug)')->required()->maxLength(150)->unique(ignoreRecord: true)
                    ->prefix('psvtravel.com/maldives/')
                    ->helperText(fn (string $operation, ?MaldivesCombo $record): string => 'Tự điền theo tên. Chỉ chữ thường không dấu và gạch ngang.'
                        .($operation === 'edit' && $record?->status === 'published' ? ' Combo đang bán: đổi đường dẫn thì link cũ tự chuyển sang link mới (301).' : ''))
                    ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state))
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages(['regex' => 'Đường dẫn chỉ gồm chữ thường không dấu, số và dấu gạch ngang.']),
                TextInput::make('seo_title')->label('Tiêu đề SEO (hiện trên Google)')->maxLength(70)
                    ->placeholder(fn (Get $get): string => (string) $get('name'))
                    ->live(debounce: 500)->hint(fn (?string $state): string => mb_strlen((string) $state).'/70 ký tự'),
                Textarea::make('summary')->label('Mô tả ngắn (hiện dưới tiêu đề trên Google)')->rows(2)->maxLength(500)
                    ->live(debounce: 500)->hint(fn (?string $state): string => mb_strlen((string) $state).' ký tự')
                    ->helperText('Nên 120–160 ký tự.')->columnSpanFull(),
                Grid::make(4)->columnSpanFull()->schema([
                    TextInput::make('duration_days')->label('Số ngày')->numeric()->minValue(1)->maxValue(30),
                    TextInput::make('duration_nights')->label('Số đêm')->numeric()->minValue(0)->maxValue(30),
                    TextInput::make('airline')->label('Hãng bay')->placeholder('Singapore Airlines')->maxLength(255),
                    TextInput::make('transfer')->label('Di chuyển ra resort')->placeholder('Thuỷ phi cơ')->maxLength(255),
                ]),
                TextInput::make('stay_summary')->label('Lưu trú')->placeholder('01 đêm khách sạn + 02 đêm resort, WOW All Inclusive')
                    ->maxLength(255)->columnSpanFull(),
                NhieuDong::o('highlights', 'Điểm nổi bật (mỗi dòng một ý)', 4)->columnSpanFull(),
                FileUpload::make('cover_image')->label('Ảnh bìa riêng của combo')->helperText('Để trống thì dùng ảnh bìa của resort.')
                    ->image()->acceptedFileTypes(TepTaiLen::ANH)->directory('maldives')->disk('public')->maxSize(15360),
                Grid::make(1)->schema([
                    Select::make('status')->label('Trạng thái')->options(MaldivesCombo::TRANG_THAI)->default('draft')->required(),
                    Toggle::make('is_featured')->label('Đưa lên đầu trang Maldives'),
                    TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
                ]),
            ]),

            Section::make('Nơi khởi hành & lịch bay')
                ->description('Phụ thu tính cho MỖI khách (người lớn và trẻ em), cộng vào giá trong bảng giá. Để trống phụ thu = website ghi "liên hệ".')
                ->schema([
                    Repeater::make('departure_cities')->hiddenLabel()
                        ->schema([
                            TextInput::make('city')->label('Khởi hành từ')->required()->maxLength(100)
                                ->datalist(['Hà Nội', 'TP.HCM', 'Đà Nẵng']),
                            TextInput::make('surcharge_usd')->label('Phụ thu (USD / khách)')->numeric()->minValue(0)->prefix('$')
                                ->helperText('0 = không phụ thu.'),
                            Textarea::make('flights')->label('Lịch bay (mỗi dòng một chặng)')->rows(4)
                                ->placeholder("SQ 191 HANSIN 1235 1705\nSQ 438 SINMLE 2045 2210")->columnSpanFull(),
                        ])->columns(2)
                        ->default([
                            ['city' => 'Hà Nội', 'surcharge_usd' => 0, 'flights' => ''],
                            ['city' => 'TP.HCM', 'surcharge_usd' => null, 'flights' => ''],
                        ])
                        ->itemLabel(fn (array $state): string => ($state['city'] ?? '') ?: 'Nơi khởi hành')
                        ->collapsible()->reorderable()->addActionLabel('Thêm nơi khởi hành'),
                ]),

            Section::make('Ngày khởi hành & bảng giá')
                ->description('Giá USD cho 1 người lớn ở phòng 2–3 người, theo từng hạng phòng — chép đúng bảng giá của đối tác. Ô để trống = ngày đó không bán hạng phòng đó. Bấm biểu tượng nhân bản để chép nhanh một ngày.')
                ->schema([
                    Repeater::make('dates')->hiddenLabel()->relationship()
                        ->schema(fn (Get $get): array => [
                            Grid::make(3)->schema([
                                DatePicker::make('start_date')->label('Ngày khởi hành')->required()->native(false)->displayFormat('d/m/Y'),
                                TextInput::make('label')->label('Ghi chú ngày')->placeholder('Lịch Tết')->maxLength(100),
                                Select::make('status')->label('Tình trạng')->options(MaldivesComboDate::TRANG_THAI)->default('open')->required(),
                            ]),
                            ...self::oGia($get('resort_id')),
                        ])
                        ->itemLabel(function (array $state): string {
                            $ngay = filled($state['start_date'] ?? null) ? Carbon::parse($state['start_date'])->format('d/m/Y') : 'Ngày mới';

                            return $ngay.(filled($state['label'] ?? null) ? ' — '.$state['label'] : '');
                        })
                        ->collapsible()->cloneable()->addActionLabel('Thêm ngày khởi hành'),
                    Grid::make(4)->schema([
                        TextInput::make('child_percent')->label('Trẻ em tính (% giá người lớn)')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(75),
                        TextInput::make('child_age')->label('Tuổi trẻ em')->default('2 – 12 tuổi')->maxLength(100),
                        TextInput::make('infant_note')->label('Em bé dưới 2 tuổi')->default('Liên hệ')->maxLength(255),
                        TextInput::make('min_group')->label('Số khách tối thiểu / đoàn')->numeric()->minValue(1)
                            ->helperText('Không đủ khách: báo trước 7 ngày, chuyển ngày gần nhất…'),
                    ]),
                ]),

            Section::make('Lịch trình')->schema([
                Repeater::make('days')->hiddenLabel()->relationship()
                    ->schema([
                        TextInput::make('title')->label('Tiêu đề ngày')->required()->maxLength(255)->placeholder('Việt Nam – khách sạn ở thủ đô Male'),
                        TextInput::make('meals')->label('Bữa ăn')->maxLength(255)->placeholder('Bữa sáng, bữa trưa, bữa tối, đồ uống'),
                        Textarea::make('description')->label('Nội dung')->rows(6)->columnSpanFull()
                            ->helperText('Mỗi mốc giờ một dòng (VD "10h00: Quý khách có mặt tại sân bay…").'),
                        FileUpload::make('images')->label('Ảnh trong ngày')->multiple()->reorderable()->image()->acceptedFileTypes(TepTaiLen::ANH)
                            ->directory('maldives')->disk('public')->maxSize(15360)->maxFiles(6)->columnSpanFull(),
                    ])->columns(2)
                    ->orderColumn('day_number')->reorderable()->collapsible()
                    ->itemLabel(fn (array $state): string => $state['title'] ?? 'Ngày mới')
                    ->addActionLabel('Thêm ngày'),
            ]),

            Section::make('Giá bao gồm / không bao gồm')->columns(2)->schema([
                NhieuDong::o('included', 'Dịch vụ đã bao gồm (mỗi dòng một mục)', 10),
                NhieuDong::o('excluded', 'Không bao gồm (mỗi dòng một mục)', 10),
            ]),

            Section::make('Lưu ý')->collapsible()->schema([
                Repeater::make('notes')->hiddenLabel()
                    ->schema([
                        TextInput::make('title')->label('Mục')->required()->maxLength(255),
                        Textarea::make('content')->label('Nội dung')->rows(4),
                    ])
                    ->default([['title' => 'Lưu ý đặc biệt', 'content' => '']])
                    ->itemLabel(fn (array $state): string => $state['title'] ?? 'Mục mới')
                    ->collapsible()->reorderable()->addActionLabel('Thêm mục'),
            ]),
        ]);
    }

    /** Một ô giá cho mỗi hạng phòng của resort đang chọn */
    private static function oGia(mixed $resortId): array
    {
        $phong = $resortId ? MaldivesRoomType::query()->where('resort_id', $resortId)->orderBy('sort_order')->orderBy('id')->get() : collect();
        if ($phong->isEmpty()) {
            return [Text::make($resortId ? 'Resort này chưa có hạng phòng — thêm ở mục Resort Maldives rồi mở lại trang này.' : 'Chọn resort ở trên để hiện ô giá theo hạng phòng.')];
        }

        return [Grid::make(3)->schema($phong->map(fn (MaldivesRoomType $p) => TextInput::make("prices.{$p->id}")
            ->label($p->name)->helperText($p->capacity)->numeric()->minValue(0)->prefix('$')->placeholder('Không bán'))->all())];
    }
}
