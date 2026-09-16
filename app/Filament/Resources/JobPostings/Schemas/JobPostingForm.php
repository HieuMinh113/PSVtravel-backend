<?php

namespace App\Filament\Resources\JobPostings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobPostingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Vị trí tuyển dụng')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Không dấu, viết thường, ví dụ: nhan-vien-kinh-doanh')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('department')
                    ->label('Phòng ban')
                    ->maxLength(255),
                TextInput::make('location')
                    ->label('Nơi làm việc')
                    ->maxLength(255),
                TextInput::make('employment_type')
                    ->label('Hình thức')
                    ->helperText('Toàn thời gian / Bán thời gian / Thực tập')
                    ->maxLength(255),
                TextInput::make('salary_range')
                    ->label('Mức lương')
                    ->helperText('Thoả thuận / 10-15 triệu')
                    ->maxLength(255),
                TextInput::make('quantity')
                    ->label('Số lượng')
                    ->numeric()
                    ->minValue(1),
                DatePicker::make('deadline')
                    ->label('Hạn nộp')
                    ->helperText('Quá hạn tự ẩn')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                RichEditor::make('description')
                    ->label('Mô tả công việc')
                    ->columnSpanFull(),
                RichEditor::make('requirements')
                    ->label('Yêu cầu ứng viên')
                    ->columnSpanFull(),
                RichEditor::make('benefits')
                    ->label('Quyền lợi')
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang tuyển',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
