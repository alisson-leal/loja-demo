<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada automação escolhe os canais: e-mail sai sozinho, WhatsApp vai para a fila manual.
        Schema::table('marketing_automations', function (Blueprint $table) {
            $table->boolean('send_whatsapp')->default(true)->after('days_before');
            $table->boolean('send_email')->default(true)->after('send_whatsapp');
        });

        $now = now();

        DB::table('marketing_automations')->insert([
            'key' => 'agradecimento_compra',
            'enabled' => true,
            'days_before' => 0,
            'send_whatsapp' => true,
            'send_email' => false,
            'email_subject' => 'Obrigado pela sua compra, {primeiro_nome}!',
            'email_body' => "Olá, {primeiro_nome}!\n\nMuito obrigado pela sua compra na {loja}!\n\nFicamos felizes por fazer parte desse momento e agradecemos pela confiança.\n\nConte conosco sempre que precisar!",
            'whatsapp_body' => "Olá, {primeiro_nome}! ❤️\n\nMuito obrigado pela sua compra na {loja}!\n\nFicamos felizes por fazer parte desse momento e agradecemos pela confiança.\n\nConte conosco sempre que precisar!",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('marketing_automations')->where('key', 'agradecimento_compra')->delete();
        Schema::table('marketing_automations', fn (Blueprint $table) => $table->dropColumn(['send_whatsapp', 'send_email']));
    }
};
