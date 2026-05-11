<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content_pillar_id' => ['nullable', 'exists:content_pillars,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,in_progress'],
            'target_date' => ['nullable', 'date'],
        ];
    }
}
