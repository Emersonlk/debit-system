<?php

namespace App\Http\Requests;

use App\Rules\CpfValido;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');
        return $this->user()->can('update', $cliente);
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $clienteId = $cliente instanceof \App\Models\Cliente ? $cliente->id : $cliente;

        // Unicidade restrita à empresa do contexto autenticado. O ->ignore() abaixo
        // não permite escapar do tenant: o where() já limita a busca de duplicatas à
        // empresa atual, e o cliente da rota só é resolvido dentro dela (global scope).
        $empresaAtual = app(CurrentCompany::class)->id();

        return [
            'nome'      => 'sometimes|required|string|max:255',
            'email'     => [
                'sometimes',
                'required',
                'email',
                Rule::unique('clientes', 'email')
                    ->where('company_id', $empresaAtual)
                    ->ignore($clienteId),
            ],
            'cpf'       => [
                'sometimes',
                'required',
                Rule::unique('clientes', 'cpf')
                    ->where('company_id', $empresaAtual)
                    ->ignore($clienteId),
                new CpfValido
            ],
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
            'nome.required'   => 'O nome é obrigatório.',
            'email.required'  => 'O e-mail é obrigatório.',
            'email.email'     => 'O e-mail deve ser um endereço válido.',
            'email.unique'    => 'Este e-mail já está cadastrado.',
            'cpf.required'    => 'O CPF é obrigatório.',
            'cpf.unique'      => 'Este CPF já está cadastrado.',
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
