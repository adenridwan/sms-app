<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', [
            'permissions' => 'permissions',
            'roles' => 'roles',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        $columnNames = config('permission.column_names', [
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'tenant_id',
        ]);

        // Permissions
        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('group')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // Roles
        Schema::create($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
            $table->id();
            $table->uuid($columnNames['team_foreign_key'])->nullable();
            $table->string('name');
            $table->string('guard_name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            $table->foreign($columnNames['team_foreign_key'])->references('id')->on('tenants')->onDelete('cascade');
        });

        // Model Has Permissions
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger($columnNames['permission_pivot_key']);
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);

            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($columnNames['permission_pivot_key'])
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->primary(
                [$columnNames['permission_pivot_key'], $columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });

        // Model Has Roles
        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger($columnNames['role_pivot_key']);
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);

            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($columnNames['role_pivot_key'])
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary(
                [$columnNames['role_pivot_key'], $columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        // Role Has Permissions
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger($columnNames['permission_pivot_key']);
            $table->unsignedBigInteger($columnNames['role_pivot_key']);

            $table->foreign($columnNames['permission_pivot_key'])
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($columnNames['role_pivot_key'])
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$columnNames['permission_pivot_key'], $columnNames['role_pivot_key']], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', [
            'permissions' => 'permissions',
            'roles' => 'roles',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
