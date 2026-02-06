<?php

namespace App\Http\Requests;

use App\Rules\CpfValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Cliente::class);
    }

    public function rules(): array
    {
        return [
            'nome'      => 'required|string|max:255',
            'email'     => 'required|email|unique:clientes,email',
            'cpf'       => ['required', 'unique:clientes,cpf', new CpfValido],
            'telefone'  => 'nullable|string|max:20',
            'endereco' => 'nullable|array',
            'endereco.rua' => 'nullable|string|max:255',
            'endereco.numero' => 'nullable|string|max:20',
            'endereco.bairro' => 'nullable|string|max:120',
            'endereco.cidade' => 'nullable|string|max:120',
            'endereco.estado' => 'nullable|string|size:2',
            'endereco.complemento' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está cadastrado.',
            'cpf.unique'   => 'Este CPF já está cadastrado.',
            'cpf.size'     => 'O CPF deve conter 11 números.'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status_code' => 422,
            'message' => 'Erro de validação',
            'errors' => $validator->errors()
        ], 422));
    }
}
