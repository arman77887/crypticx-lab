<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'target_id' => $this->target_id,

            'type' => $this->type,
            'fingerprint' => $this->fingerprint,

            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'confidence' => $this->confidence,

            'evidence' => $this->evidence,
            'evidence_data' => $this->evidence_data,

            'remediation' => $this->remediation,
            'status' => $this->status,

            'assessment' => $this->whenLoaded('assessment'),
            'target' => $this->whenLoaded('target'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
