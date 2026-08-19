<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar
                ? (str_starts_with($this->avatar, 'http') ? $this->avatar : asset('storage/'.$this->avatar))
                : null,
            'loyalty_points' => $this->loyalty_points,
            'verified' => (bool) $this->email_verified_at,
            'created_at' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
