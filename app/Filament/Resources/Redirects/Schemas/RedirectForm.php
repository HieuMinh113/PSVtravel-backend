<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Models\Redirect;
use App\Services\LinkCu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('old_path')
                    ->label('Link cũ')
                    ->helperText('Dán cả link cũng được (VD: https://psvtravel.com.vn/tour_nuoc_ngoai/bangkok-pattaya-5n4d) — chỉ giữ phần đường dẫn.')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    // Chuẩn hoá ngay khi rời ô để kiểm tra trùng đúng với dạng sẽ lưu
                    ->afterStateUpdated(fn (?string $state, Set $set) => filled($state) && ($p = LinkCu::chuanHoa($state)) ? $set('old_path', $p) : null)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : (LinkCu::chuanHoa($state) ?? $state))
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if (LinkCu::chuanHoa((string) $value) === null) {
                                $fail('Đường dẫn chỉ gồm chữ không dấu, số, dấu gạch ngang, gạch dưới, dấu chấm và dấu /.');
                            }
                        },
                    ])
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Link này đã có trong danh sách — tìm và sửa dòng đó.']),
                Select::make('status_code')
                    ->label('Xử lý')
                    ->options(Redirect::MA)
                    ->default(301)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live()
                    ->helperText('301: chuyển sang trang mới, Google chuyển thứ hạng cũ sang. 410: trang đã gỡ hẳn, Google sớm bỏ khỏi kết quả tìm kiếm. "Chưa khớp": khách thấy trang lỗi 404.'),
                TextInput::make('new_path')
                    ->label('Chuyển tới')
                    ->prefix('psvtravel.com')
                    ->placeholder('/tour-nuoc-ngoai/ten-tour')
                    ->helperText('Mở trang mới trên website, chép phần sau psvtravel.com dán vào đây.')
                    ->maxLength(500)
                    ->visible(fn (Get $get): bool => (int) $get('status_code') === 301)
                    ->required(fn (Get $get): bool => (int) $get('status_code') === 301)
                    // Dán cả link https://psvtravel.com/... → chỉ giữ đường dẫn
                    ->dehydrateStateUsing(function (?string $state, Get $get): ?string {
                        if ((int) $get('status_code') !== 301 || blank($state)) {
                            return null;
                        }
                        $state = trim($state);
                        if (preg_match('#^https?://#i', $state)) {
                            $u = parse_url($state);
                            $state = ($u['path'] ?? '/').(isset($u['query']) ? '?'.$u['query'] : '');
                        }

                        return '/'.ltrim($state, '/');
                    })
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $v = trim((string) $value);
                            if (preg_match('#^https?://#i', $v)) {
                                $host = (string) parse_url($v, PHP_URL_HOST);
                                $web = (string) parse_url((string) config('app.frontend_url'), PHP_URL_HOST);
                                if (preg_replace('/^www\./', '', $host) !== preg_replace('/^www\./', '', $web)) {
                                    $fail("Chỉ chuyển tới trang trong website ({$web}).");

                                    return;
                                }
                                $v = (string) parse_url($v, PHP_URL_PATH);
                            }
                            if (preg_match('/\s/', $v)) {
                                $fail('Đường dẫn không được có dấu cách.');
                            } elseif (LinkCu::chuanHoa('/'.ltrim($v, '/')) === LinkCu::chuanHoa((string) $get('old_path'))) {
                                $fail('Trang mới trùng link cũ — khách sẽ bị chuyển vòng mãi.');
                            }
                        },
                    ]),
                Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
