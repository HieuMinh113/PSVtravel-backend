<?php

namespace App\Filament\Resources\GuideCities;

use App\Filament\Resources\GuideCities\Pages\CreateGuideCity;
use App\Filament\Resources\GuideCities\Pages\EditGuideCity;
use App\Filament\Resources\GuideCities\Pages\ListGuideCities;
use App\Filament\Resources\GuideCities\RelationManagers\SpotsRelationManager;
use App\Services\TepTaiLen;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Guide\Models\GuideCity;
use Modules\Guide\Models\GuideCountry;

/**
 * Bản đồ cẩm nang — Thành phố. Mở một thành phố để thêm các điểm (ăn uống,
 * tham quan…) bên dưới; mỗi điểm gắn một bài cẩm nang.
 */
class GuideCityResource extends Resource
{
    protected static ?string $model = GuideCity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'thành phố trên bản đồ';

    protected static ?string $pluralModelLabel = 'Bản đồ: Thành phố & điểm';

    protected static ?string $navigationLabel = 'Bản đồ: Thành phố & điểm';

    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 22;

    protected static ?string $slug = 'ban-do-thanh-pho';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Thông tin')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('guide_country_id')
                        ->label('Nước')
                        ->relationship('country', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->default(fn () => request()->integer('nuoc') ?: null),
                    TextInput::make('name')
                        ->label('Tên thành phố')
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => blank($get('slug')) && $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')
                        ->label('Đường dẫn')
                        ->helperText('VD: /cam-nang?nuoc=trung-quoc&tp=thuong-hai')
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
                        ->rows(2)
                        ->maxLength(300)
                        ->columnSpanFull(),
                    Select::make('guide_id')
                        ->label('Bài cẩm nang tổng quan')
                        ->helperText('Không bắt buộc. VD: "Cẩm nang Thượng Hải tự túc" — hiện thành nút đọc bài khi khách mở thành phố.')
                        ->relationship('guide', 'title')
                        ->searchable()
                        ->preload(),
                    FileUpload::make('cover_image')
                        ->label('Ảnh đại diện')
                        ->image()->acceptedFileTypes(TepTaiLen::ANH)
                        ->directory('guides/ban-do')
                        ->disk('public'),
                    TextInput::make('sort_order')->label('Thứ tự')->numeric()->default(0),
                ]),
            ViTriBanDo::khoi(
                coZoom: true,
                tam: function ($livewire, Get $get) {
                    $nhan = GuideCountry::duLieuNuoc(GuideCountry::find($get('guide_country_id'))?->code)['nhan'] ?? null;

                    return $nhan ? [$nhan[1], $nhan[0], 4.5] : null;
                },
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('country:id,name')->withCount('spots'))
            ->columns([
                ImageColumn::make('cover_image')->label('Ảnh')->disk('public'),
                TextColumn::make('name')->label('Thành phố')->searchable()->weight('bold'),
                TextColumn::make('country.name')->label('Nước')->badge()->color('info'),
                TextColumn::make('spots_count')->label('Điểm')->numeric(),
                TextColumn::make('guide.title')->label('Bài tổng quan')->placeholder('—')->limit(40),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('sort_order')->label('Thứ tự')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('guide_country_id')->label('Nước')->relationship('country', 'name'),
            ])
            ->recordActions([
                EditAction::make()->label('Sửa & thêm điểm'),
                DeleteAction::make()->modalDescription('Xoá thành phố sẽ xoá luôn các điểm trên bản đồ của thành phố này.'),
            ])
            ->emptyStateHeading('Chưa có thành phố nào')
            ->emptyStateDescription('Thêm nước ở mục "Bản đồ: Quốc gia" trước, rồi thêm thành phố tại đây.');
    }

    public static function getRelations(): array
    {
        return [SpotsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuideCities::route('/'),
            'create' => CreateGuideCity::route('/create'),
            'edit' => EditGuideCity::route('/{record}/edit'),
        ];
    }
}
