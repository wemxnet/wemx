<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_guest_tickets')->default(false);
            $table->boolean('allow_guest_members')->default(false);
            $table->boolean('allow_invites')->default(true);
            $table->text('prefill_template')->nullable();
            $table->text('auto_response')->nullable();
            $table->string('notify_email')->nullable();
            $table->unsignedInteger('auto_close_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->unique();
            $table->foreignId('department_id')->constrained('ticket_departments')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->string('last_reply_from')->default('client');
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamps();

            $table->index('department_id');
            $table->index('user_id');
            $table->index('order_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('priority');
            $table->index('last_reply_from');
            $table->index('last_replied_at');
            $table->index('locked_at');
            $table->index('guest_email');
            $table->index(['status', 'last_reply_from', 'priority', 'last_replied_at'], 'tickets_staff_queue_index');
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('comment');
            $table->string('event_type')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->boolean('from_admin')->default(false);
            $table->longText('body')->nullable();
            $table->json('meta')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index('type');
        });

        Schema::create('ticket_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('role')->default('member');
            $table->boolean('is_subscribed')->default(true);
            $table->string('access_token', 64)->unique();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'email']);
            $table->index('user_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_members');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_departments');
    }
};
