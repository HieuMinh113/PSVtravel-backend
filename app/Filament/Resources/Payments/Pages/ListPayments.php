<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Models\Payment;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTabs(): array
    {
        $choDuyet = Payment::where('status', 'pending')->whereNotNull('received_by')->count();

        return [
            // Khoản "pending" do cổng thanh toán tạo (khách đang thanh toán dở,
            // không ai nhập) không phải việc của kế toán
            'cho_duyet' => Tab::make('Chờ duyệt')
                ->badge($choDuyet ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNotNull('received_by')),
            'da_duyet' => Tab::make('Đã duyệt')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'success')),
            'tu_choi' => Tab::make('Từ chối / thất bại')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed')),
            'tat_ca' => Tab::make('Tất cả'),
        ];
    }
}
