<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An app (or a group of apps sharing their users) sending notifications.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // Public identifier given to the SDKs and the API ("app_id").
            $table->uuid('app_id')->unique();
            $table->string('name');
            $table->string('icon_path')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('editor');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        // Server-side keys of the REST API (sending, subscribers...).
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // First characters shown in the interface, the rest only hashed.
            $table->string('prefix', 16);
            $table->string('key_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // APNs, FCM and web push credentials of a project (secrets encrypted).
        Schema::create('push_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            // Non secret details, shown in the interface (key id, bundle id...).
            $table->json('details')->nullable();
            $table->text('secret')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'provider']);
        });

        // Invitations may give access to a project right away.
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('role')->constrained()->cascadeOnDelete();
            $table->string('project_role', 16)->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('project_role');
        });
        Schema::dropIfExists('push_credentials');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
