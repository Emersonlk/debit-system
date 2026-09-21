<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromissoriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Promissoria::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Rule::exists consulta a tabela crua e NÃO passa pelo global scope de
            // Cliente: o filtro por empresa precisa ser explícito, senão seria possível
            // vincular a promissória a um cliente de outra empresa.
            'cliente_id' => [
                'required',
                Rule::exists('clientes', 'id')->where('company_id', app(CurrentCompany::class)->id()),
            ],
            'valor' => 'required|numeric|min:0.01',
            'data_vencimento' => 'required|date|after_or_equal:today',
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
            'cliente_id.required' => 'O cliente é obrigatório.',
            'cliente_id.exists' => 'O cliente selecionado não existe.',
            'valor.required' => 'O valor é obrigatório.',
            'valor.numeric' => 'O valor deve ser um número.',
            'valor.min' => 'O valor deve ser maior que zero.',
            'data_vencimento.required' => 'A data de vencimento é obrigatória.',
            'data_vencimento.date' => 'A data de vencimento deve ser uma data válida.',
            'data_vencimento.after_or_equal' => 'A data de vencimento deve ser hoje ou uma data futura.',
        ];
    }
}
