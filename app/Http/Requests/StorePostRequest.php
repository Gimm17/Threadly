<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:500'],
            'hook' => ['nullable', 'string', 'max:150'],
            'content_pillar_id' => ['nullable', 'exists:content_pillars,id'],
            'content_idea_id' => ['nullable', 'exists:content_ideas,id'],
            'status' => ['required', 'in:draft,scheduled,cancelled'],
            'scheduled_at' => ['required_if:status,scheduled', 'nullable', 'date', 'after:now'],
            'publish_mode' => ['required', 'in:manual,auto'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'media' => ['nullable', 'array', 'max:4'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,gif,mp4,mov', 'max:51200'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.max' => 'Isi post maksimal 500 karakter (batas Threads).',
            'hook.max' => 'Hook maksimal 150 karakter.',
            'scheduled_at.required_if' => 'Waktu posting wajib diisi jika status Scheduled.',
            'scheduled_at.after' => 'Waktu posting harus di masa depan.',
            'media.max' => 'Maksimal 4 media per post.',
        ];
    }
}
