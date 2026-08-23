<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Schemas\Components\Utilities\Get;
use Modules\Category\Models\Category;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Nhóm')
                    ->options([
                        'domestic' => 'Trong nước',
                        'abroad' => 'Nước ngoài',
                    ])
                    ->default('domestic')
                    ->live()
                    ->required(),
                TextInput::make('name')
                    ->label('Tên danh mục')
                    ->required()
                    ->maxLength(255)
                    // Chặn trùng tên TRONG CÙNG MỘT NHÓM.
                    //
                    // Khác nhóm thì cho phép: "Trung Quốc" ở nhóm Nước ngoài và
                    // một danh mục cùng tên ở nhóm Trong nước là hai thứ khác
                    // nhau, người nhập tour vẫn phân biệt được nhờ nhóm.
                    //
                    // Viết thành luật tường minh thay vì ->unique() vì Category
                    // dùng xoá mềm: ->unique() so cả với danh mục đã nằm trong
                    // thùng rác, chặn lưu mà người dùng không hiểu vì sao — đúng
                    // hiện tượng "chặn lưu nhưng không hiện thông báo".
                    ->rules([
                        fn (Get $get, ?Category $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            if (blank($value)) {
                                return;
                            }

                            $daCo = Category::query()
                                ->where('type', $get('type'))
                                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($daCo) {
                                $nhom = $get('type') === 'abroad' ? 'Nước ngoài' : 'Trong nước';
                                $fail('Nhóm "'.$nhom.'" đã có danh mục tên này rồi. Đổi tên khác hoặc chọn nhóm khác.');
                            }
                        },
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Tự điền theo tên danh mục. Chỉ gồm chữ thường không dấu, số và dấu gạch ngang.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state))
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages([
                        'regex' => 'Đường dẫn chỉ được gồm chữ thường không dấu, số và dấu gạch ngang. VD: mien-trung',
                    ]),
                FileUpload::make('image')
                    ->label('Ảnh đại diện')
                    ->image()
                    ->directory('categories')
                    ->disk('public'),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Textarea::make('description')
                    ->label('Mô tả')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}