<?php

namespace App\Filament\Resources\GuideCities\RelationManagers;

use App\Filament\Resources\GuideCities\ViTriBanDo;
use App\Services\TepTaiLen;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Guide\Models\GuideCity;
use Modules\Guide\Models\GuideSpot;

/** Các điểm trên bản đồ của một thành phố — bấm vào trên website thì mở bài cẩm nang. */
class SpotsRelationManager extends RelationManager
{
    protected static string $relationship = 'spots';

    protected static ?string $title = 'Điểm trên bản đồ';

    protected static ?string $modelLabel = 'điểm';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label('Tên điểm')
                ->placeholder('VD: Bến Thượng Hải')
                ->required()
                ->maxLength(120),
            Select::make('type')
                ->label('Loại')
                ->options(GuideSpot::LOAI)
                ->default('tham_quan')
                ->required(),
            Select::make('guide_id')
                ->label('Bài cẩm nang mở ra khi khách bấm')
                ->relationship('guide', 'title')
                ->searchable()
                ->preload()
                ->helperText('Chưa có bài thì viết ở mục Cẩm nang trước. Để trống: điểm vẫn hiện, chỉ có mô tả ngắn.')
                ->columnSpanFull(),
            Textarea::make('summary')
                ->label('Mô tả ngắn')
                ->placeholder('VD: Đẹp nhất lúc lên đèn 19h–22h, đi tàu điện tuyến 2 ga Nam Kinh Đông.')
                ->rows(2)
                ->maxLength(300)
                ->columnSpanFull(),
            FileUpload::make('image')
                ->label('Ảnh (không bắt buộc)')
                ->helperText('Để trống thì dùng ảnh bìa của bài cẩm nang.')
                ->image()->acceptedFileTypes(TepTaiLen::ANH)
                ->directory('guides/ban-do')
                ->disk('public'),
            Select::make('status')
                ->label('Trạng thái')
                ->options(['published' => 'Đang hiển thị', 'hidden' => 'Đã ẩn'])
                ->default('published')
                ->required(),
            ViTriBanDo::khoi(
                tam: fn ($livewire) => [$livewire->getOwnerRecord()->lat, $livewire->getOwnerRecord()->lng, $livewire->getOwnerRecord()->zoom + 1],
                diemKhac: fn ($livewire) => $livewire->getOwnerRecord()->spots()
                    ->get(['name', 'lat', 'lng'])
                    ->map(fn ($d) => ['ten' => $d->name, 'lat' => $d->lat, 'lng' => $d->lng])
                    ->all(),
            ),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('guide:id,title,status'))
            ->columns([
                TextColumn::make('name')->label('Điểm')->searchable()->weight('bold'),
                TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => GuideSpot::LOAI[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'an_uong' => 'warning',
                        'mua_sam' => 'danger',
                        'thien_nhien' => 'success',
                        default => 'info',
                    }),
                TextColumn::make('guide.title')
                    ->label('Bài cẩm nang')
                    ->placeholder('Chưa gắn bài')
                    ->limit(45)
                    ->description(fn (GuideSpot $record) => $record->guide && $record->guide->status !== 'published' ? 'Bài chưa đăng — khách chưa đọc được' : null),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('type')->label('Loại')->options(GuideSpot::LOAI),
            ])
            ->headerActions([
                CreateAction::make()->label('Thêm điểm')->modalWidth(Width::FourExtraLarge),
            ])
            ->recordActions([
                EditAction::make()->modalWidth(Width::FourExtraLarge),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Chưa có điểm nào')
            ->emptyStateDescription('Bấm "Thêm điểm", đặt ghim lên bản đồ và chọn bài cẩm nang mở ra khi khách bấm vào.');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof GuideCity && auth()->user()?->can('viewAny', GuideSpot::class);
    }
}
