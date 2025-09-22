<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'name' => $this->name,
            'role' => $this->role,
            'capabilities_json' => $this->capabilities_json,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'specializations' => AgentSpecializationResource::collection($this->whenLoaded('specializations')),
        ];
    }
}
