<?php

namespace App\Notifications;

use App\Models\Promissoria;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromissoriaVencida extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Promissoria $promissoria
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $dataVencimento = $this->promissoria->data_vencimento->copy()->startOfDay();
        $hoje = now()->startOfDay();
        
        // Calcula dias que passaram desde o vencimento (número inteiro)
        $diasVencida = (int) $dataVencimento->diffInDays($hoje);
        
        // Formata o texto dos dias
        if ($diasVencida === 0) {
            $diasTexto = 'hoje';
        } elseif ($diasVencida === 1) {
            $diasTexto = 'há 1 dia';
        } else {
            $diasTexto = "há {$diasVencida} dias";
        }

        return (new MailMessage)
            ->subject('⚠️ Promissória Vencida - Ação Urgente Necessária')
            ->greeting('Olá!')
            ->line("**ATENÇÃO URGENTE:** A promissória do cliente **{$this->promissoria->cliente->nome}** **já passou do vencimento**.")
            ->line("**Valor:** R$ " . number_format($this->promissoria->valor, 2, ',', '.'))
            ->line("**Data de Vencimento:** {$this->promissoria->data_vencimento->format('d/m/Y')} (vencida {$diasTexto})")
            ->line("**Status:** VENCIDA")
            ->line("**Dias em Atraso:** {$diasVencida} dia(s)")
            ->line("**Cliente:** {$this->promissoria->cliente->nome}")
            ->line("**Email do Cliente:** {$this->promissoria->cliente->email}")
            ->line("**Telefone do Cliente:** {$this->promissoria->cliente->telefone}")
            ->when($this->promissoria->observacoes, function ($mail) {
                return $mail->line("**Observações:** {$this->promissoria->observacoes}");
            })
            ->line('**URGENTE:** Entre em contato com o cliente imediatamente para cobrar o pagamento vencido.')
            ->salutation('Atenciosamente, Sistema de Gestão de Promissórias');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'promissoria_id' => $this->promissoria->id,
            'cliente_nome' => $this->promissoria->cliente->nome,
            'valor' => $this->promissoria->valor,
            'data_vencimento' => $this->promissoria->data_vencimento->toDateString(),
            'dias_vencida' => (int) $this->promissoria->data_vencimento->copy()->startOfDay()->diffInDays(now()->startOfDay()),
        ];
    }
}
