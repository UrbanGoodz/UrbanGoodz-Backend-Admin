<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Business Portal's AI Logistics module scopes several shared platform
// tables by business_client_id, but that column was never added to them.
// All additive/nullable — existing rows are simply unscoped (NULL), which
// is correct: they predate per-client attribution.
return new class extends Migration
{
    private array $targets = [
        'ai_copilot_recommendations' => 'business_client_id',
        'ai_action_logs' => 'business_client_id',
        'external_loads' => 'business_client_id',
        'urban_goodz_client_invoices' => 'due_date',
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $column) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($column) {
                if ($column === 'due_date') {
                    $t->date('due_date')->nullable();
                } else {
                    $t->unsignedBigInteger($column)->nullable();
                    $t->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($column, $table) {
                if ($column !== 'due_date') {
                    try { $t->dropIndex([$column]); } catch (\Throwable $e) {}
                }
                $t->dropColumn($column);
            });
        }
    }
};
