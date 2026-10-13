<?php

namespace App\Filament\Resources\JobApplications\Pages;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJobApplication extends ViewRecord
{
    protected static string $resource = JobApplicationResource::class;

    protected function getHeaderActions(): array
    {
        /** @var JobApplication $hs */
        $hs = $this->getRecord();

        return [
            Action::make('cv')
                ->label('Mở CV')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url($hs->linkTep('cv'), shouldOpenInNewTab: true),
            Action::make('portfolio')
                ->label('Mở portfolio')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->visible((bool) $hs->portfolio_path)
                ->url($hs->linkTep('portfolio'), shouldOpenInNewTab: true),
            Action::make('goi')
                ->label('Gọi')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->url('tel:'.preg_replace('/[^0-9+]/', '', $hs->phone)),
            Action::make('email')
                ->label('Gửi email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->url('mailto:'.$hs->email.'?subject='.rawurlencode('PSV Travel – Hồ sơ ứng tuyển '.$hs->job_title)),
            JobApplicationResource::hanhDongXuLy(),
            DeleteAction::make()
                ->modalDescription('Xoá hồ sơ sẽ xoá luôn CV và portfolio của ứng viên, không khôi phục được.'),
        ];
    }
}
