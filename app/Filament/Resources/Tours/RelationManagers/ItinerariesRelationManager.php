<?php

namespace App\Filament\Resources\Tours\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItinerariesRelationManager extends RelationManager
{
    protected static string $relationship = 'itineraries';

    protected static ?string $title = 'Lịch trình từng ngày';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('day_number')
                ->label('Ngày thứ')
                ->numeric()
                ->required(),
            TextInput::make('title')
                ->label('Tiêu đề')
                ->required()
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Nội dung')
                ->rows(6)
                ->helperText('Gõ "Sáng:", "Trưa:", "Chiều:", "Tối:" ở đầu mỗi buổi — ngoài web tự tách thành từng đoạn riêng. Bọc tên điểm tham quan trong dấu sao để in đậm, ví dụ: tham quan *Tòa nhà Quốc Hội*.')
                ->columnSpanFull(),

            FileUpload::make('images')
                ->label('Ảnh của ngày này')
                ->helperText('Kéo thả để sắp xếp. Ảnh hiện đúng thứ tự này ngoài web.')
                ->image()
                ->multiple()
                ->reorderable()
                ->appendFiles()
                ->imageEditor()
                ->directory('tours/lich-trinh')
                ->maxFiles(10)
                ->maxSize(4096)
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->label('Thứ tự')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_number')
                    ->label('Ngày')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->wrap(),
                TextColumn::make('images')
                    ->label('Số ảnh')
                    ->formatStateUsing(fn ($state): string => (string) count((array) $state))
                    ->badge(),
            ])
            ->defaultSort('day_number')
            ->headerActions([
                CreateAction::make()->label('Thêm ngày'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}