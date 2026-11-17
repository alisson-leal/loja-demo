<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Situação atual do consentimento, por canal. O histórico fica em customer_consents.
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('email_opt_in')->default(false)->after('email');
            $table->timestamp('email_opt_in_at')->nullable()->after('email_opt_in');
            $table->boolean('whatsapp_opt_in')->default(false)->after('whatsapp');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
        });

        Schema::create('customer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->boolean('granted');
            $table->string('source', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('channel', 20);
            $table->string('segment_name', 80)->nullable();
            $table->json('filters');
            $table->string('subject', 150)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Caixa de saída: uma linha por mensagem a um cliente, de campanha ou de automação.
        Schema::create('marketing_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('automation', 40)->nullable();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('destination', 190);
            $table->string('subject', 150)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->string('dedupe_key', 120)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 250)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['channel', 'status']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('marketing_automations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('days_before')->default(0);
            $table->string('email_subject', 150);
            $table->text('email_body');
            $table->text('whatsapp_body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        DB::table('marketing_automations')->insert([
            [
                'key' => 'aniversario_cliente',
                'enabled' => false,
                'days_before' => 0,
                'email_subject' => 'Feliz aniversário, {primeiro_nome}!',
                'email_body' => "Olá, {primeiro_nome}!\n\nA equipe da {loja} deseja a você um feliz aniversário, cheio de alegria e carinho.\n\nPasse na loja para comemorar com a gente!",
                'whatsapp_body' => "Olá, {primeiro_nome}! 🎉\nA equipe da {loja} deseja a você um feliz aniversário!\nPasse na loja para comemorar com a gente.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'aniversario_filho',
                'enabled' => false,
                'days_before' => 7,
                'email_subject' => 'O aniversário de {filho} está chegando!',
                'email_body' => "Olá, {primeiro_nome}!\n\nO aniversário de {filho} está chegando: {idade} anos no dia {data_aniversario}.\n\nVenha escolher uma roupa especial para a festa na {loja}!",
                'whatsapp_body' => "Olá, {primeiro_nome}! 🎈\nO aniversário de {filho} está chegando ({idade} anos em {data_aniversario}).\nVenha escolher uma roupa especial para a festa na {loja}!",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_automations');
        Schema::dropIfExists('marketing_messages');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('customer_consents');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email_opt_in', 'email_opt_in_at', 'whatsapp_opt_in', 'whatsapp_opt_in_at']);
        });
    }
};
