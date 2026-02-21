<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtrairPromissoriaImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Promissoria::class);
    }

    public function rules(): array
    {
        return [
            'imagem' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240', // max 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'imagem.required' => 'É necessário enviar uma imagem.',
            'imagem.image' => 'O arquivo deve ser uma imagem.',
            'imagem.mimes' => 'A imagem deve ser JPEG, PNG ou WebP.',
            'imagem.max' => 'A imagem não pode ter mais de 10 MB.',
        ];
    }
}
