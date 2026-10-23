<?php

namespace App\Filament\Resources\GuideCountries;

use App\Filament\Resources\GuideCities\GuideCityResource;
use App\Filament\Resources\GuideCountries\Pages\CreateGuideCountry;
use App\Filament\Resources\GuideCountries\Pages\EditGuideCountry;
use App\Filament\Resources\GuideCountries\Pages\ListGuideCountries;
use App\Services\TepTaiLen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Guide\Models\GuideCountry;

/**
 * Bản đồ cẩm nang — Quốc gia. Nước nào có ít nhất một thành phố đang hiện
 * thì được tô màu trên quả địa cầu ở trang Cẩm nang.
 */
class GuideCountryResource extends Resource
{
    protected static ?string $model = GuideCountry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'quốc gia trên bản đồ';

    protected static ?string $pluralModelLabel = 'Bản đồ: Quốc gia';

    protected static ?string $navigationLabel = 'Bản đồ: Quốc gia';

    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung website';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'ban-do-quoc-gia';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('code')
                ->label('Nước')
                ->options(fn () => GuideCountry::luaChonNuoc())
                ->searchable()
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Chọn theo bản đồ thế giới — hình dạng nước để tô màu trên quả địa cầu lấy từ đây.')
                ->live()
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                    $ten = GuideCountry::duLieuNuoc($state)['ten'] ?? null;
                    if ($ten && blank($get('name'))) {
                        $set('name', $ten);
                        $set('slug', Str::slug($ten));
                    }
                }),
            TextInput::make('name')
                ->label('Tên hiển thị')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => blank($get('slug')) && $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')
                ->label('Đường dẫn')
                ->helperText('Hiện trên địa chỉ trang, VD: /cam-nang?nuoc=trung-quoc')
                ->required()
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->maxLength(100),
            self::oMau(),
            Select::make('status')
                ->label('Trạng thái')
                ->options(['published' => 'Đang hiển thị', 'hidden' => 'Đã ẩn'])
                ->default('published')
                ->helperText('Nước chỉ hiện ngoài website khi có ít nhất 1 thành phố đang hiển thị (thêm ở nút "Thành phố").')
                ->required(),
            Textarea::make('intro')
                ->label('Giới thiệu ngắn')
                ->helperText('Một hai câu hiện ở khung bên cạnh bản đồ khi khách chọn nước này.')
                ->rows(2)
                ->maxLength(300)
                ->columnSpanFull(),
            FileUpload::make('cover_image')
                ->label('Ảnh đại diện')
                ->image()->acceptedFileTypes(TepTaiLen::ANH)
                ->directory('guides/ban-do')
                ->disk('public'),
            TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
        ]);
    }

    /** Ô chọn màu tô nước trên quả địa cầu — dùng cho form nước và nút "Đổi màu" hàng loạt */
    public static function oMau(): ColorPicker
    {
        return ColorPicker::make('color')
            ->label('Màu tô trên bản đồ')
            ->hex()
            ->default(GuideCountry::MAU_MAC_DINH)
            ->placeholder(GuideCountry::MAU_MAC_DINH)
            ->regex(GuideCountry::MAU_HOP_LE)
            ->validationMessages(['regex' => 'Chọn màu trong bảng màu hoặc gõ mã dạng #RRGGBB (VD: #E11D48).'])
            ->helperText('Màu tô nước này trên quả địa cầu ở trang Cẩm nang (viền tự đậm hơn). Để trống = xanh lá PSV. Nên chọn màu tươi, không quá nhạt.');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'cities', 'spots',
                'cities as so_thanh_pho_hien' => fn ($q) => $q->where('status', 'published'),
            ]))
            ->columns([
                TextColumn::make('name')->label('Nước')->searchable()->weight('bold'),
                ColorColumn::make('color')
                    ->label('Màu')
                    ->state(fn (GuideCountry $record): string => $record->mau())
                    ->copyable()
                    ->copyMessage('Đã chép mã màu'),
                TextColumn::make('code')->label('Mã')->badge()->color('gray'),
                TextColumn::make('cities_count')->label('Thành phố')->numeric(),
                TextColumn::make('spots_count')->label('Điểm')->numeric(),
                // Website chỉ hiện nước có thành phố đang hiển thị (bấm vào nước trống
                // thì khách chẳng thấy gì) — báo rõ để khỏi tưởng bị lỗi
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state, GuideCountry $record): string => match (true) {
                        $state !== 'published' => 'Đã ẩn',
                        ! $record->so_thanh_pho_hien => 'Chưa lên web — cần thêm thành phố',
                        default => 'Đang hiển thị',
                    })
                    ->color(fn (string $state, GuideCountry $record): string => match (true) {
                        $state !== 'published' => 'gray',
                        ! $record->so_thanh_pho_hien => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (string $state, GuideCountry $record): ?string => $state === 'published' && ! $record->so_thanh_pho_hien
                        ? 'Nước chỉ hiện trên quả địa cầu ngoài web khi có ít nhất 1 thành phố đang hiển thị. Bấm "Thành phố" để thêm.'
                        : null),
                TextColumn::make('sort_order')->label('Thứ tự')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                Action::make('thanhPho')
                    ->label('Thành phố')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->color('gray')
                    ->url(fn (GuideCountry $record) => GuideCityResource::getUrl('index', ['filters' => ['guide_country_id' => ['value' => $record->id]]])),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Xoá nước sẽ xoá luôn các thành phố và điểm của nước này trên bản đồ (bài cẩm nang vẫn giữ nguyên).'),
            ])
            // Chọn nhiều nước → đổi cùng một màu (VD: đổi hết sang màu cam)
            ->toolbarActions([
                BulkAction::make('doiMau')
                    ->label('Đổi màu')
                    ->icon(Heroicon::OutlinedSwatch)
                    ->authorize(fn (): bool => auth()->user()?->can('Update:GuideCountry') ?? false)
                    ->modalHeading('Đổi màu các nước đã chọn')
                    ->modalWidth('md')
                    ->schema([self::oMau()->required()])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(fn (GuideCountry $n) => $n->update(['color' => strtoupper($data['color'])]));
                        Notification::make()->success()->title('Đã đổi màu '.$records->count().' nước')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Chưa có nước nào trên bản đồ')
            ->emptyStateDescription('Thêm nước (VD: Trung Quốc), rồi thêm thành phố (Thượng Hải) và các điểm trong thành phố.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuideCountries::route('/'),
            'create' => CreateGuideCountry::route('/create'),
            'edit' => EditGuideCountry::route('/{record}/edit'),
        ];
    }
}
