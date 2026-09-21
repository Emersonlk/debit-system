<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportarPromissoriaImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Promissoria::class);
    }

    public function rules(): array
    {
        return [
            'imagem' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            // Só aceita cliente da própria empresa (Rule::exists ignora o global scope).
            'cliente_id' => [
                'nullable',
                Rule::exists('clientes', 'id')->where('company_id', app(CurrentCompany::class)->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'imagem.required' => 'É necessário enviar uma imagem.',
            'imagem.image' => 'O arquivo deve ser uma imagem.',
            'imagem.mimes' => 'A imagem deve ser JPEG, PNG ou WebP.',
            'imagem.max' => 'A imagem não pode ter mais de 10 MB.',
            'cliente_id.exists' => 'O cliente selecionado não existe.',
        ];
    }
}
