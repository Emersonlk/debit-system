<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PromissoriaImageExtractorService
{
    private const PROVIDER_GROQ = 'groq';
    private const PROVIDER_OPENAI = 'openai';

    private const ENDPOINTS = [
        self::PROVIDER_GROQ => 'https://api.groq.com/openai/v1/chat/completions',
        self::PROVIDER_OPENAI => 'https://api.openai.com/v1/chat/completions',
    ];

    private const MODELS = [
        self::PROVIDER_GROQ => 'meta-llama/llama-4-scout-17b-16e-instruct',
        self::PROVIDER_OPENAI => 'gpt-4o-mini',
    ];

    public function __construct(
        private ?string $provider = null,
        private ?string $apiKey = null
    ) {
        $this->provider = $provider ?? config('services.promissoria_image.provider', self::PROVIDER_GROQ);
        $this->apiKey = $apiKey ?? $this->getApiKeyForProvider();
    }

    private function getApiKeyForProvider(): ?string
    {
        return match ($this->provider) {
            self::PROVIDER_GROQ => config('services.groq.api_key'),
            self::PROVIDER_OPENAI => config('services.openai.api_key'),
            default => config('services.groq.api_key') ?: config('services.openai.api_key'),
        };
    }

    /**
     * Extrai nome do cliente, data de vencimento e valor de uma imagem de nota promissória.
     *
     * @return array{nome_cliente: string, data_vencimento: string|null, valor: float|null, cpf: string|null, confianca: string}
     */
    public function extrair(UploadedFile $imagem): array
    {
        if (!$this->apiKey) {
            throw new RuntimeException(
                'Configure GROQ_API_KEY (gratuito) ou OPENAI_API_KEY no .env para usar extração de imagens. ' .
                'Defina PROMISSORIA_IMAGE_PROVIDER=groq ou openai conforme o provedor escolhido.'
            );
        }

        $imageContent = $imagem->get();
        $base64 = base64_encode($imageContent);
        $mimeType = $imagem->getMimeType() ?: 'image/jpeg';

        // Groq limita imagem base64 a 4MB; OpenAI aceita mais
        $base64SizeMb = strlen($base64) / 1024 / 1024;
        if ($base64SizeMb > 4 && ($this->provider === self::PROVIDER_GROQ || !$this->provider)) {
            throw new RuntimeException(
                'Imagem muito grande para o Groq (máx. ~3MB). Reduza o tamanho da foto ou use uma imagem menor.'
            );
        }

        $prompt = <<<'PROMPT'
Analise esta imagem de uma NOTA PROMISSÓRIA brasileira preenchida à mão ou impressa.

Extraia os seguintes dados e retorne APENAS um JSON válido, sem markdown, sem explicações:
{
  "nome_cliente": "nome completo do beneficiário (quem deve receber o pagamento) - campo 'pagar ____ por esta única via' ou similar",
  "data_vencimento": "data no formato AAAA-MM-DD (ano-mês-dia) - campo Vencimento no topo direito",
  "valor": número decimal com ponto (ex: 1500.50) - valor em R$ no topo, sem símbolo ou vírgulas,
  "cpf": "CPF ou CNPJ se visível, ou null",
  "confianca": "alta|media|baixa - sua confiança na extração"
}

Se não conseguir ler algum campo, use null. Para valor, se não extrair corretamente, use null.
O nome_cliente deve ser o beneficiário (para quem se paga), não o emitente.
Retorne SOMENTE o JSON, sem texto antes ou depois.
PROMPT;

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post(self::ENDPOINTS[$this->provider] ?? self::ENDPOINTS[self::PROVIDER_GROQ], [
                'model' => self::MODELS[$this->provider] ?? self::MODELS[self::PROVIDER_GROQ],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}",
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 500,
                'temperature' => 0.1,
            ]);

        if (!$response->successful()) {
            $bodyRaw = $this->sanitizarUtf8($response->body());
            $body = json_decode($bodyRaw, true) ?: [];
            $error = $body['error']['message'] ?? $body['error']['code'] ?? $body['message'] ?? $bodyRaw;
            throw new RuntimeException('Erro ao processar imagem com IA: ' . $error);
        }

        $bodyRaw = $this->sanitizarUtf8($response->body());
        $data = json_decode($bodyRaw, true) ?? [];
        $text = $data['choices'][0]['message']['content'] ?? '';
        $text = $this->sanitizarUtf8($text);
        $text = trim(preg_replace('/^```json\s*|\s*```$/m', '', $text));

        $data = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_UTF8) {
            $text = htmlspecialchars_decode(htmlspecialchars($text, ENT_SUBSTITUTE | ENT_IGNORE, 'UTF-8'), ENT_QUOTES);
            json_decode('[]');
            $data = json_decode($text, true);
        }
        if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
            throw new RuntimeException('Não foi possível interpretar os dados extraídos da imagem. Tente com uma foto mais nítida.');
        }

        return [
            'nome_cliente' => $this->sanitizarUtf8((string) ($data['nome_cliente'] ?? '')),
            'data_vencimento' => $this->normalizarData($data['data_vencimento'] ?? null),
            'valor' => $this->normalizarValor($data['valor'] ?? null),
            'cpf' => isset($data['cpf']) ? $this->sanitizarUtf8((string) $data['cpf']) : null,
            'confianca' => $this->sanitizarUtf8((string) ($data['confianca'] ?? 'media')),
        ];
    }

    private function sanitizarUtf8(string $str): string
    {
        if ($str === '') {
            return '';
        }
        $result = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
        if ($result !== false) {
            return $result;
        }
        $result = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        if ($result !== false) {
            return $result;
        }
        return htmlspecialchars_decode(htmlspecialchars($str, ENT_SUBSTITUTE | ENT_IGNORE, 'UTF-8'), ENT_QUOTES);
    }

    private function normalizarData(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $valor = (string) $valor;
        // Aceitar formato dd/mm/aaaa e converter para aaaa-mm-dd
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor)) {
            return $valor;
        }
        return null;
    }

    private function normalizarValor(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $valor = preg_replace('/[^\d,.-]/', '', (string) $valor);
        $valor = str_replace(',', '.', $valor);
        return is_numeric($valor) ? (float) $valor : null;
    }
}
