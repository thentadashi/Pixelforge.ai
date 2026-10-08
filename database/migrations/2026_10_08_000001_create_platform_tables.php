<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->index();
            $table->string('organization')->nullable();
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable();
        });
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('organization');
            $table->string('sector');
            $table->text('requirements');
            $table->date('date');
            $table->string('slot', 5);
            $table->string('reservation_key')->nullable()->unique();
            $table->string('status')->default('requested');
            $table->text('notes')->nullable();
            $table->uuid('reference')->unique();
            $table->timestamps();
        });
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->string('title');
            $table->text('scope');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('draft');
            $table->date('valid_until');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('quotation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('status')->default('planning');
            $table->date('target_date')->nullable();
            $table->timestamps();
        });
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status')->default('planned');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('note');
            $table->timestamps();
        });
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('subject');
            $table->text('details');
            $table->string('status')->default('open');
            $table->timestamps();
        });
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime');
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['invoices', 'project_files', 'ticket_replies', 'tickets', 'project_updates', 'revisions', 'milestones', 'projects', 'quotations', 'bookings', 'site_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'organization', 'invitation_token', 'invitation_expires_at']));
    }
};
