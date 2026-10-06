<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;

class JobPostingController extends Controller
{
    // GET /api/v1/jobs
    public function index()
    {
        $items = JobPosting::query()->dangHienThi()->get();

        return response()->json([
            'data' => $items->map(fn ($j) => $this->the($j))->values(),
        ]);
    }

    // GET /api/v1/jobs/{slug}
    public function show(string $slug)
    {
        $j = JobPosting::query()->dangHienThi()->where('slug', $slug)->first();

        if (! $j) {
            return response()->json(['message' => 'Không tìm thấy tin tuyển dụng.'], 404);
        }

        return response()->json(['data' => array_merge($this->the($j), [
            'description' => $j->description,
            'requirements' => $j->requirements,
            'benefits' => $j->benefits,
        ])]);
    }

    // GET /api/v1/jobs-slugs
    public function slugs()
    {
        return response()->json(JobPosting::query()->dangHienThi()->pluck('slug'));
    }

    private function the(JobPosting $j): array
    {
        return [
            'id' => $j->id,
            'title' => $j->title,
            'slug' => $j->slug,
            'department' => $j->department,
            'location' => $j->location,
            'employment_type' => $j->employment_type,
            'salary_range' => $j->salary_range,
            'quantity' => $j->quantity,
            'deadline' => $j->deadline?->format('Y-m-d'),
        ];
    }
}
