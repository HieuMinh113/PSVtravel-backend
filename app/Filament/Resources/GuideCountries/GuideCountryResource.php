<?php

namespace App\Filament\Resources\GuideCountries;

use App\Filament\Resources\GuideCities\GuideCityResource;
use App\Filament\Resources\GuideCountries\Pages\CreateGuideCountry;
use App\Filament\Resources\GuideCountries\Pages\EditGuideCountry;
use App\Filament\Resources\GuideCountries\Pages\ListGuideCountries;
use App\Services\TepTaiLen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 21;

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
            Select::make('status')
                ->label('Trạng thái')
                ->options(['published' => 'Đang hiển thị', 'hidden' => 'Đã ẩn'])
                ->default('published')
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['cities', 'spots']))
            ->columns([
                TextColumn::make('name')->label('Nước')->searchable()->weight('bold'),
                TextColumn::make('code')->label('Mã')->badge()->color('gray'),
                TextColumn::make('cities_count')->label('Thành phố')->numeric(),
                TextColumn::make('spots_count')->label('Điểm')->numeric(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
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
