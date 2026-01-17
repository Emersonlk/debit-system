<?php

namespace App\Http\Requests;

use App\Enums\PromissoriaStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromissoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $promissoria = $this->route('promissoria');
        return $this->user()->can('update', $promissoria);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => 'sometimes|exists:clientes,id',
            'valor' => 'sometimes|numeric|min:0.01',
            'data_vencimento' => 'sometimes|date',
            'status' => ['sometimes', 'in:' . PromissoriaStatus::valoresString()],
            'observacoes' => 'nullable|string|max:1000',
            'data_pagamento' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.exists' => 'O cliente selecionado não existe.',
            'valor.numeric' => 'O valor deve ser um número.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'data_vencimento.date' => 'A data de vencimento deve ser uma data válida.',
            'status.in' => 'O status deve ser: ' . implode(', ', PromissoriaStatus::valores()) . '.',
        ];
    }
}
