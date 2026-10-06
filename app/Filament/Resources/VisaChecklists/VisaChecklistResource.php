<?php

namespace App\Filament\Resources\VisaChecklists;

use App\Filament\Resources\VisaChecklists\Pages\CreateVisaChecklist;
use App\Filament\Resources\VisaChecklists\Pages\EditVisaChecklist;
use App\Filament\Resources\VisaChecklists\Pages\ListVisaChecklists;
use App\Filament\Resources\VisaChecklists\Schemas\VisaChecklistForm;
use App\Filament\Resources\VisaChecklists\Tables\VisaChecklistsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Visa\Models\VisaChecklist;

/**
 * Mẫu checklist giấy tờ theo nước / mục đích / đối tượng — bản số hoá của
 * thư mục "thủ tục visa các nước" trên Drive.
 */
class VisaChecklistResource extends Resource
{
    protected static ?string $model = VisaChecklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'mẫu checklist';

    protected static ?string $pluralModelLabel = 'Mẫu checklist visa';

    protected static ?string $navigationLabel = 'Mẫu checklist';

    protected static string|\UnitEnum|null $navigationGroup = 'Visa';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return VisaChecklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaChecklistsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaChecklists::route('/'),
            'create' => CreateVisaChecklist::route('/create'),
            'edit' => EditVisaChecklist::route('/{record}/edit'),
        ];
    }
}
