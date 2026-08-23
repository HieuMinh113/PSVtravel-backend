<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

/**
 * Thêm nút "Quay lại danh sách" lên đầu trang tạo/sửa.
 *
 * Filament chỉ có dải breadcrumb chữ nhỏ ở góc trên, người dùng quen với giao
 * diện tiếng Việt thường không nhận ra đó là đường quay về. Nút rõ ràng giúp
 * thoát khỏi form mà không phải bấm nút Back của trình duyệt (dễ làm mất
 * dữ liệu đang nhập dở).
 */
trait CoNutQuayLaiDanhSach
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('quayLai')
                ->label('Quay lại danh sách')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($this->getResource()::getUrl('index')),
        ];
    }
}
