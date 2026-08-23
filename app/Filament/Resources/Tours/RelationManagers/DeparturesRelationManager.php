<?php

namespace App\Filament\Resources\Tours\RelationManagers;
use Modules\Tour\Models\TourDeparture;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeparturesRelationManager extends RelationManager
{
    protected static string $relationship = 'departures';

    protected static ?string $title = 'Lịch khởi hành';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('start_date')
                ->label('Ngày khởi hành')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->minDate(now()->startOfDay())
                ->maxDate(now()->addYears(3))
                ->helperText('Chỉ chọn được từ hôm nay trở đi. Mỗi tour chỉ có một đợt cho một ngày.')
                ->required()
                // Bảng tour_departures có ràng buộc duy nhất (tour_id, start_date).
                // Trước đây form không kiểm tra nên chọn trùng ngày là để cơ sở dữ
                // liệu ném lỗi ra — người dùng nhận nguyên trang 500 thay vì một
                // câu thông báo. Kiểm ở đây để báo ngay tại ô nhập.
                ->rules([
                    function (?TourDeparture $record): \Closure {
                        $tourId = $this->getOwnerRecord()->getKey();

                        return function (string $attribute, $value, \Closure $fail) use ($tourId, $record) {
                            if (! $value) {
                                return;
                            }

                            $daCo = TourDeparture::query()
                                ->where('tour_id', $tourId)
                                ->whereDate('start_date', $value)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($daCo) {
                                $fail('Tour này đã có đợt khởi hành ngày '
                                    .\Illuminate\Support\Carbon::parse($value)->format('d/m/Y')
                                    .'. Chọn ngày khác hoặc sửa đợt đã có.');
                            }
                        };
                    },
                ]),
            TextInput::make('price_override')
                ->label('Giá riêng đợt này')
                ->helperText('Để trống nếu dùng giá mặc định của tour')
                ->numeric()
                ->suffix('₫'),
            TextInput::make('seats_total')
                ->label('Tổng số chỗ')
                ->numeric()
                ->default(0)
                ->minValue(1)
                ->live(onBlur: true)
                ->required(),
            TextInput::make('seats_left')
                ->label('Số chỗ còn')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->rules([
                    fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ((int) $value > (int) $get('seats_total')) {
                            $fail('Số chỗ còn không được lớn hơn tổng số chỗ.');
                        }
                    },
                ])
                ->required(),
            Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'open' => 'Còn nhận',
                    'closed' => 'Đã đóng',
                    'full' => 'Hết chỗ',
                ])
                ->default('open')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')
                    ->label('Ngày khởi hành')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('price_override')
                    ->label('Giá riêng')
                    ->money('VND')
                    ->placeholder('—'),
                TextColumn::make('seats_total')->label('Tổng chỗ'),
                TextColumn::make('seats_left')->label('Còn'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'closed' => 'Đã đóng',
                        'full' => 'Hết chỗ',
                        default => 'Còn nhận',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'closed' => 'gray',
                        'full' => 'danger',
                        default => 'success',
                    }),
            ])
            ->defaultSort('start_date')
            ->headerActions([
                CreateAction::make()->label('Thêm đợt khởi hành'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}