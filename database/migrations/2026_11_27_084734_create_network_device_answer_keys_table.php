<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('network_device_answer_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->unsignedInteger('revision');
            $table->timestamps();
        });

        DB::table('network_device_answer_keys')->insert([
            'school_id' => null,
            'answers' => json_encode([
                'patch_panel' => 'Am Patchpanel enden die fest verlegten Leitungen von den Netzwerkdosen. Über Patchkabel verbindet man die benötigten Anschlüsse mit dem Switch. Das Patchpanel selbst leitet keine Daten aktiv weiter.',
                'switch' => 'Ein Switch verbindet die Geräte im lokalen Netzwerk. Er lernt, über welche Anschlüsse die Geräte erreichbar sind, und leitet Ethernet-Daten anhand der Ziel-MAC-Adresse passend weiter.',
                'router' => 'Ein Router verbindet verschiedene IP-Netzwerke. In diesem Aufbau leitet er Daten zwischen dem lokalen Netzwerk und dem Internet weiter.',
                'data_server' => 'Der Datenserver stellt Dateien und andere Daten zentral im Netzwerk bereit. Berechtigte Benutzer können von ihren Arbeitsplätzen darauf zugreifen.',
                'access_point' => 'Der WLAN-Access-Point stellt ein Funknetz bereit. Er verbindet WLAN-Geräte wie die Laptops mit dem kabelgebundenen lokalen Netzwerk.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'revision' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_device_answer_keys');
    }
};
