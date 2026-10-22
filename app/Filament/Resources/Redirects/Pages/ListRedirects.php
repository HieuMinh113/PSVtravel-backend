<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Redirect;
use App\Services\LinkCu;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    public function getSubheading(): ?string
    {
        return 'Link của website cũ (psvtravel.com.vn) mà Google, khách vẫn còn giữ. Khi có người mở link cũ, hệ thống tự tìm trang mới tương ứng và ghi vào đây: tour cùng tên, điểm đến, bài cẩm nang hoặc danh sách tour. Dòng "Chưa khớp" là link khách thấy trang lỗi — chọn trang thay thế cho dòng có nhiều lượt trước. Nhân viên sửa thì máy không ghi đè.';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Thêm tour / bài mới xong thì link cũ trước đây "đã gỡ" có thể khớp được
            Action::make('khopLaiTatCa')
                ->label('Tự khớp lại tất cả')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Dò lại trang mới cho mọi link do máy khớp (dùng sau khi thêm tour, bài viết). Dòng nhân viên đã sửa giữ nguyên.')
                ->authorize(fn (): bool => auth()->user()?->can('create', Redirect::class) ?? false)
                ->action(function (): void {
                    $doi = 0;
                    Redirect::query()->where('source', 'tu_dong')->chunkById(200, function ($ds) use (&$doi) {
                        foreach ($ds as $r) {
                            $truoc = [$r->status_code, $r->new_path];
                            LinkCu::khopLai($r);
                            $doi += (int) ($truoc !== [$r->status_code, $r->new_path]);
                        }
                    });
                    Notification::make()->success()->title("Đã khớp lại — {$doi} link đổi trang đích")->send();
                }),
            CreateAction::make()->label('Thêm link'),
        ];
    }
}
