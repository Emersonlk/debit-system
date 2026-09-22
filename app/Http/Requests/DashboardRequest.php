<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida os parâmetros de leitura do dashboard.
 *
 * Antes desta classe o controller lia os quatro parâmetros crus e o
 * DashboardPeriodResolver descartava em silêncio o que não reconhecia — um
 * `periodo` inválido virava "últimos 30 dias" e respondia 200, sem indicar que o
 * pedido fora ignorado. O resolver segue intacto e continua como defesa em
 * profundidade; o que muda é que uma entrada inválida agora para aqui, com 422.
 *
 * O teto do intervalo personalizado existe porque a série de recebimentos tem uma
 * entrada por dia: o tamanho da resposta cresce com o número de dias pedidos,
 * independentemente do volume de dados da empresa, e cada combinação de datas
 * ainda grava a sua própria entrada de cache.
 */
class DashboardRequest extends FormRequest
{
    /**
     * Períodos aceitos, iguais aos do seletor do frontend e aos ramos do
     * DashboardPeriodResolver.
     *
     * @var list<string>
     */
    public const PERIODOS = ['hoje', '7', '30', 'personalizado'];

    /**
     * Intervalo máximo, contado de forma inclusiva (início e fim entram na conta).
     */
    public const INTERVALO_MAXIMO_DIAS = 366;

    /**
     * A autorização continua no controller, via Policy, como nos demais endpoints
     * de leitura. Negar aqui anteciparia o 403 para antes da validação e mudaria a
     * resposta de requisições que hoje são recusadas por permissão.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'periodo' => ['sometimes', Rule::in(self::PERIODOS)],
            'dias' => ['sometimes', 'integer', 'min:1', 'max:365'],
            // As datas só são exigidas no modo personalizado — é o único em que o
            // resolver as utiliza. Quando enviadas fora dele continuam sendo
            // ignoradas pelo resolver, mas o formato é conferido de qualquer forma:
            // aceitar uma data malformada e descartá-la calado é o comportamento de
            // que esta fase está se livrando.
            'data_inicio' => ['required_if:periodo,personalizado', 'date_format:Y-m-d'],
            'data_fim' => ['required_if:periodo,personalizado', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('periodo') !== 'personalizado') {
                return;
            }

            // Sem as duas datas válidas não há intervalo a medir, e as regras acima
            // já terão registrado o erro correspondente.
            if ($validator->errors()->hasAny(['data_inicio', 'data_fim'])) {
                return;
            }

            $inicio = $this->input('data_inicio');
            $fim = $this->input('data_fim');

            if (! is_string($inicio) || ! is_string($fim)) {
                return;
            }

            $dias = (int) CarbonImmutable::createFromFormat('Y-m-d', $inicio)
                ->startOfDay()
                ->diffInDays(CarbonImmutable::createFromFormat('Y-m-d', $fim)->startOfDay()) + 1;

            if ($dias > self::INTERVALO_MAXIMO_DIAS) {
                $validator->errors()->add('data_fim', sprintf(
                    'O período personalizado não pode exceder %d dias (foram solicitados %d).',
                    self::INTERVALO_MAXIMO_DIAS,
                    $dias
                ));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodo.in' => 'O período deve ser um destes: ' . implode(', ', self::PERIODOS) . '.',
            'dias.integer' => 'O parâmetro dias deve ser um número inteiro.',
            'dias.min' => 'O parâmetro dias deve ser no mínimo 1.',
            'dias.max' => 'O parâmetro dias deve ser no máximo 365.',
            'data_inicio.required_if' => 'Informe a data de início para o período personalizado.',
            'data_fim.required_if' => 'Informe a data de fim para o período personalizado.',
            'data_inicio.date_format' => 'A data de início deve estar no formato AAAA-MM-DD.',
            'data_fim.date_format' => 'A data de fim deve estar no formato AAAA-MM-DD.',
            'data_fim.after_or_equal' => 'A data de fim não pode ser anterior à data de início.',
        ];
    }
}
