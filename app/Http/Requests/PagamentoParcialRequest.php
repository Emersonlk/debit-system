<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagamentoParcialRequest extends FormRequest
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
            'valor_pago' => 'required|numeric|min:0.01',
            'data_pagamento' => 'required|date|before_or_equal:today',
            'observacoes' => 'nullable|string|max:1000',
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
            'valor_pago.required' => 'O valor pago é obrigatório.',
            'valor_pago.numeric' => 'O valor pago deve ser um número.',
            'valor_pago.min' => 'O valor pago deve ser maior que zero.',
            'data_pagamento.required' => 'A data de pagamento é obrigatória.',
            'data_pagamento.date' => 'A data de pagamento deve ser uma data válida.',
            'data_pagamento.before_or_equal' => 'A data de pagamento não pode ser uma data futura.',
        ];
    }
}
