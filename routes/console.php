<?php

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BirthdayAutomation;
use App\Services\MarketingEmailSender;
use App\Services\StockReservationService;
use App\Services\UserSessionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('demo:resetar {--force : Não pede confirmação}', function () {
    if (! config('loja.demo.enabled')) {
        $this->error('Disponível só com DEMO_MODE=true no .env.');

        return 1;
    }

    if (! $this->option('force') && ! $this->confirm('Apagar todos os dados e gerar a demonstração de novo?')) {
        return 0;
    }

    $this->call('down', ['--retry' => 60]);

    try {
        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);
    } finally {
        $this->call('up');
    }

    return 0;
})->purpose('Restaura a loja de demonstração com os dados fictícios originais');

Artisan::command('usuarios:redefinir-senha {email}', function (string $email, AuditLogger $audit, UserSessionService $sessions) {
    $user = User::where('email', Str::lower(trim($email)))->first();

    if (! $user) {
        $this->error("Nenhum usuário com o e-mail {$email}. Usuários cadastrados:");
        User::orderBy('id')->pluck('email')->each(fn ($e) => $this->line("  {$e}"));

        return 1;
    }

    $password = Str::password(12, symbols: false);
    $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
    $sessions->revokeAllFor($user->id);
    $audit->record('user.password_reset_cli', "Senha de {$user->name} redefinida pelo servidor.", $user);

    $this->info("Senha provisória de {$user->email} (exibida só agora): {$password}");
    $this->line('No primeiro acesso o sistema pedirá uma senha nova.');

    return 0;
})->purpose('Gera uma senha provisória para um usuário (recuperação de acesso pelo servidor)');

Artisan::command('marketing:enviar-emails', function (MarketingEmailSender $sender) {
    $result = $sender->sendBatch();
    $this->info("E-mails: {$result['sent']} enviado(s), {$result['failed']} com falha, {$result['skipped']} sem consentimento.");
})->purpose('Envia o próximo lote de e-mails de marketing, dentro do limite por hora');

Artisan::command('marketing:aniversarios', function (BirthdayAutomation $automation) {
    $expired = $automation->expireStale();
    $created = $automation->run();
    $this->info("{$created} mensagem(ns) de aniversário criada(s); {$expired} mensagem(ns) de WhatsApp vencida(s).");
})->purpose('Gera as mensagens de aniversário do dia e vence as de WhatsApp não enviadas');

Artisan::command('email:testar {destino}', function (string $destino) {
    try {
        Mail::raw('Teste de envio do sistema '.config('loja.store.name').' em '.now()->format('d/m/Y H:i').'.', fn ($m) => $m
            ->to($destino)->subject('Teste de e-mail - '.config('loja.store.name')));
    } catch (Throwable $e) {
        $this->error('Falhou: '.$e->getMessage());

        return 1;
    }

    $this->info("E-mail de teste enviado para {$destino} pelo servidor ".config('mail.mailers.smtp.host').'.');

    return 0;
})->purpose('Envia um e-mail de teste para conferir a configuração de SMTP');

Artisan::command('estoque:liberar-reservas', function (StockReservationService $reservations) {
    $this->info($reservations->releaseExpired().' reserva(s) expirada(s) liberada(s).');
})->purpose('Devolve ao disponível as reservas de estoque vencidas');

/*
| A VPS tem pouca memória, então não há worker de fila permanente. O único cron
| necessário no servidor é "php artisan schedule:run" a cada minuto; as filas
| são processadas aqui, em lotes curtos.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('estoque:liberar-reservas')->everyFiveMinutes();
Schedule::command('marketing:enviar-emails')->everyMinute()->withoutOverlapping(10);
// De hora em hora no horário comercial: se o servidor parar, a próxima execução recupera o dia.
Schedule::command('marketing:aniversarios')->hourly()->between('8:00', '20:00')->withoutOverlapping(30);
Schedule::command('auth:clear-resets')->daily();

// Demonstração: volta aos dados fictícios originais uma vez por dia.
if (config('loja.demo.enabled')) {
    Schedule::command('demo:resetar --force')->dailyAt(config('loja.demo.reset_at'))->withoutOverlapping(60)->runInBackground();
}
Schedule::command('queue:prune-failed --hours=720')->daily();
