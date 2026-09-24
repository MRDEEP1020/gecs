<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module "Organisation" v2 (2026-09-22) — annule l'extension de `services`
// du tour précédent (parent_id/type/soft deletes), remplacée par une vraie
// table dédiée `organization_units` (voir cette migration). Aucune perte de
// donnée : les 14 services réels restent des lignes `services` intactes,
// seules ces 2 colonnes disparaissent.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('type');
            // SQLite (tests) n'auto-supprime pas l'index en même temps que la
            // colonne (contrairement à MySQL) — le retirer explicitement
            // d'abord, sinon "no such column: parent_id" lors de la
            // reconstruction de table SQLite (voir memory
            // utilisateurs_acces_maquette_2026_09_21, même piège déjà rencontré).
            $table->dropIndex(['parent_id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('services')->nullOnDelete();
            $table->string('type')->default('service')->after('parent_id');
            $table->softDeletes();
            $table->index('parent_id');
        });
    }
};
