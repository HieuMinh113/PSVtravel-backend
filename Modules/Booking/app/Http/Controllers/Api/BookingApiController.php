<?php

namespace Modules\Booking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Booking\Http\Requests\StoreBookingRequest;
use Modules\Booking\Models\Booking;
use Modules\Tour\Models\Tour;

class BookingApiController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Lấy tour từ DB — không tin giá khách gửi lên
        $tour = Tour::query()->published()->findOrFail($data['tour_id']);

        // Nếu có chọn đợt: đợt phải thuộc đúng tour này, còn mở, còn chỗ
        $departure = null;
        if (! empty($data['tour_departure_id'])) {
            $departure = $tour->departures()
                ->where('id', $data['tour_departure_id'])
                ->where('status', 'open')
                ->first();

            if (! $departure) {
                return response()->json([
                    'message' => 'Đợt khởi hành đã đóng hoặc không hợp lệ.',
                ], 422);
            }

            $soKhach = $data['adults'] + ($data['children'] ?? 0);
            if ($departure->seats_left < $soKhach) {
                return response()->json([
                    'message' => "Đợt này chỉ còn {$departure->seats_left} chỗ, không đủ cho {$soKhach} khách.",
                ], 422);
            }
        }

        // Server tự tính tiền, tự đặt trạng thái — khách không quyết được
        $donGiaNguoiLon = $tour->adult_price;
        $donGiaTreEm = $tour->child_price ?? 0;
        $tongTien = $data['adults'] * $donGiaNguoiLon
            + ($data['children'] ?? 0) * $donGiaTreEm;

        $booking = Booking::create([
            'tour_id' => $tour->id,
            'tour_departure_id' => $departure?->id,
            'user_id' => auth('sanctum')->id(),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'adults' => $data['adults'],
            'children' => $data['children'] ?? 0,
            'unit_price_adult' => $donGiaNguoiLon,
            'unit_price_child' => $donGiaTreEm,
            'total_price' => $tongTien,
            'status' => 'pending',       // luôn chờ xử lý — không nhận từ khách
            'payment_status' => 'unpaid',
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Đặt tour thành công! Chúng tôi sẽ liên hệ xác nhận trong thời gian sớm nhất.',
            'data' => [
                'booking_code' => $booking->booking_code,
                'customer_name' => $booking->customer_name,
                'tour_name' => $tour->name,
                'total_price' => $booking->total_price,
                'status' => 'pending',
            ],
        ], 201);
    }
}