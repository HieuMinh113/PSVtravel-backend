<?php

namespace App\Filament\Resources\VisaChecklists\Schemas;

use App\Filament\Resources\VisaCases\Schemas\VisaCaseForm;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use Modules\Visa\Models\VisaCountry;

class VisaChecklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên mẫu')
                    ->placeholder('VD: Trung Quốc — Du lịch — Nhân viên')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('country')
                    ->label('Nước')
                    ->required()
                    ->maxLength(100)
                    ->datalist(fn () => VisaChecklist::query()->whereNotNull('country')->distinct()->pluck('country')
                        ->merge(VisaCountry::query()->pluck('name'))->filter()->unique()->sort()->values()->all()),
                Select::make('purpose')
                    ->label('Mục đích')
                    ->options(VisaCase::MUC_DICH)
                    ->placeholder('Chung — mọi mục đích'),
                Select::make('profile')
                    ->label('Đối tượng')
                    ->options(VisaCase::DOI_TUONG)
                    ->placeholder('Chung — mọi đối tượng'),
                Toggle::make('is_active')
                    ->label('Đang dùng')
                    ->default(true)
                    ->inline(false),

                Repeater::make('items')
                    ->label('Danh sách giấy tờ')
                    ->table([
                        TableColumn::make('Nhóm')->width('14rem'),
                        TableColumn::make('Giấy tờ')->markAsRequired(),
                        TableColumn::make('Ghi chú'),
                    ])
                    ->schema([
                        TextInput::make('nhom')
                            ->placeholder('Giấy tờ cá nhân')
                            ->datalist(['Giấy tờ cá nhân', 'Công việc', 'Tài chính', 'Phía người mời', 'Thông tin cần']),
                        TextInput::make('ten')
                            ->required(),
                        TextInput::make('ghi_chu')
                            ->placeholder('VD: sao y công chứng không quá 3 tháng'),
                    ])
                    ->defaultItems(1)
                    ->addActionLabel('Thêm giấy tờ')
                    ->reorderable()
                    ->columnSpanFull(),

                Textarea::make('note')
                    ->label('Lưu ý chung')
                    ->helperText('Hiện ra trong hồ sơ khi chọn mẫu này.')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('attachments')
                    ->label('File mẫu (tờ khai, thư mời mẫu…)')
                    ->multiple()
                    ->disk('rieng')
                    ->directory('mau-visa')
                    ->visibility('private')
                    ->acceptedFileTypes(VisaCaseForm::LOAI_FILE)
                    ->maxSize(10240)
                    ->storeFileNamesIn('attachment_names')
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
            ])
            ->columns(4);
    }
}
