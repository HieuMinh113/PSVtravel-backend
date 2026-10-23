<?php

namespace App\Filament\Resources\VisaDeadlines;

use App\Filament\Resources\VisaDeadlines\Pages\CreateVisaDeadline;
use App\Filament\Resources\VisaDeadlines\Pages\EditVisaDeadline;
use App\Filament\Resources\VisaDeadlines\Pages\ListVisaDeadlines;
use App\Filament\Resources\VisaDeadlines\Schemas\VisaDeadlineForm;
use App\Filament\Resources\VisaDeadlines\Tables\VisaDeadlinesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Visa\Models\VisaDeadline;

/**
 * Hạn nộp hồ sơ visa theo nước (nhân viên visa quản lý). Đợt khởi hành tour
 * nước ngoài quá hạn thì tự ẩn trên website — xem Modules\Tour\Services\HanNopVisa.
 */
class VisaDeadlineResource extends Resource
{
    protected static ?string $model = VisaDeadline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'country';

    protected static ?string $modelLabel = 'hạn nộp visa';

    protected static ?string $pluralModelLabel = 'Hạn nộp visa theo nước';

    protected static ?string $navigationLabel = 'Hạn nộp visa theo nước';

    protected static string|\UnitEnum|null $navigationGroup = 'Visa';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return VisaDeadlineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaDeadlinesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaDeadlines::route('/'),
            'create' => CreateVisaDeadline::route('/create'),
            'edit' => EditVisaDeadline::route('/{record}/edit'),
        ];
    }
}
