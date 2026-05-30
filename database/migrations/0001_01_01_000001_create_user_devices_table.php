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
        $userForeignKey = config('device-sessions.column_names.user_foreign_key', 'user_id');
        $primaryKeyType = config('device-sessions.keys.primary_key_type', 'uuid');
        $userKeyType = config('device-sessions.keys.user_key_type', 'uuid');

        Schema::create($tableNames['devices'], function (Blueprint $table) use ($primaryKeyType, $userKeyType, $userForeignKey, $tableNames) {
            $this->addPrimaryKey($table, 'id', $primaryKeyType);
            $this->addKeyColumn($table, $userForeignKey, $userKeyType);

            $table->string('type');
            $table->string('os_family')->nullable();
            $table->string('name');
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->foreign($userForeignKey)
                ->references('id')
                ->on($tableNames['users'])
                ->cascadeOnDelete();

            $table->index([$userForeignKey, 'type'], 'user_devices_user_type_index');
            $table->index($userForeignKey, 'user_devices_user_index');
            $table->index('os_family', 'user_devices_os_family_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('device-sessions.table_names.devices', 'user_devices'));
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
