<?php

namespace Modules\Visa\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Visa\Models\VisaCase;

/**
 * Hồ sơ visa khách xem ở trang Tài khoản. Chỉ những gì khách cần: tiến độ,
 * lịch hẹn, giấy tờ còn thiếu. KHÔNG có ghi chú nội bộ, chi phí, người phụ
 * trách, file đã gửi.
 *
 * @mixin VisaCase
 */
class HoSoVisaKhachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        [$co, $can] = $this->tienDoGiayTo();

        return [
            'code' => $this->code,
            'country' => $this->country,
            'purpose' => VisaCase::MUC_DICH[$this->purpose] ?? null,
            'status' => $this->status,
            'status_label' => VisaCase::TRANG_THAI[$this->status] ?? $this->status,
            'created_at' => $this->created_at?->toDateString(),
            'travel_date' => $this->travel_date?->toDateString(),
            'appointment_at' => $this->appointment_at?->format('Y-m-d H:i'),
            'result_expected_on' => $this->result_expected_on?->toDateString(),
            'visa_expiry' => $this->visa_expiry?->toDateString(),
            'documents_received' => $co,
            'documents_total' => $can,
            'missing_documents' => $this->giayConThieu(),
        ];
    }
}
