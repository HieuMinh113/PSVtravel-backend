<?php

namespace Modules\Visa\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisaCountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'flag_image' => $this->anh($this->flag_image),
            'visa_type' => $this->visa_type,
            'price' => $this->price,
            'processing_time' => $this->processing_time,
            'success_rate' => $this->success_rate,
            'required_documents' => $this->required_documents ?? [],
            'description' => $this->description,
        ];
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}