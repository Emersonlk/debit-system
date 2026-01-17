<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestarEmail extends Command
{
    protected $signature = 'testar:email {email?}';
    protected $description = 'Testa o envio de email diretamente';

    public function handle(): int
    {
        $emailDestino = $this->argument('email') ?? 'test@example.com';
        
        $this->info("Configuração de email:");
        $this->line("Mailer: " . config('mail.default'));
        $this->line("Host: " . config('mail.mailers.smtp.host'));
        $this->line("Port: " . config('mail.mailers.smtp.port'));
        $this->line("Queue: " . config('queue.default'));
        $this->newLine();

        try {
            Mail::raw('Teste de email do Debit System', function ($message) use ($emailDestino) {
                $message->to($emailDestino)
                    ->subject('Teste de Email - MailHog');
            });

            $this->info("✅ Email de teste enviado para: {$emailDestino}");
            $this->info("Verifique no MailHog: http://localhost:8025");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao enviar email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
