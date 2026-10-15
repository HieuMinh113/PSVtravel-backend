<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\Tours\TourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = TourResource::class;

    /**
     * Tạo xong mở luôn trang SỬA tour vừa tạo (không về danh sách, không sang
     * trang xem): lịch khởi hành, lịch trình từng ngày, thư viện ảnh chỉ thêm
     * được khi tour đã có — nhân viên làm tiếp ngay tại đó.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::canEdit($this->getRecord())
            ? static::getResource()::getUrl('edit', ['record' => $this->getRecord()])
            : parent::getRedirectUrl();
    }
}
