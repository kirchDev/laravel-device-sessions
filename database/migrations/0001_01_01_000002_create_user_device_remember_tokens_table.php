<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('device-sessions.table_names');
        $primaryKeyType = config('device-sessions.keys.primary_key_type', 'uuid');

        Schema::create($tableNames['remember_tokens'], function (Blueprint $table) use ($primaryKeyType, $tableNames) {
            $this->addPrimaryKey($table, 'id', $primaryKeyType);
            $this->addKeyColumn($table, 'user_device_id', $primaryKeyType);

            $table->string('token_hash')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_device_id')
                ->references('id')
                ->on($tableNames['devices'])
                ->cascadeOnDelete();

            $table->index('user_device_id', 'remember_tokens_device_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('device-sessions.table_names.remember_tokens', 'user_device_remember_tokens'));
    }

    private function addPrimaryKey(Blueprint $table, string $column, string $type): void
    {
        match ($type) {
            'uuid' => $table->uuid($column)->primary(),
            'ulid' => $table->ulid($column)->primary(),
            default => $table->id($column),
        };
    }

    private function addKeyColumn(Blueprint $table, string $column, string $type, bool $nullable = false): void
    {
        $definition = match ($type) {
            'uuid' => $table->uuid($column),
            'ulid' => $table->ulid($column),
            default => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }
    }
};
