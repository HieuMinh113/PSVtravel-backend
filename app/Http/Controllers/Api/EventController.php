<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventListResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // GET /api/v1/events — danh sách gói sự kiện / team building đang hiển thị
    public function index(Request $request)
    {
        $events = Event::query()
            ->dangHienThi()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 30), 60));

        return EventListResource::collection($events);
    }

    // GET /api/v1/events/{slug}
    public function show(string $slug)
    {
        $event = Event::query()
            ->dangHienThi()
            ->where('slug', $slug)
            ->firstOrFail();

        return new EventDetailResource($event);
    }

    // GET /api/v1/events-slugs — cho generateStaticParams của Next
    public function slugs()
    {
        return response()->json(
            Event::query()->dangHienThi()->pluck('slug')
        );
    }
}
