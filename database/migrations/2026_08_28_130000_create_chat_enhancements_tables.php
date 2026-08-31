<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. conversations table
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type')->default('direct'); // direct | group
                $table->string('name')->nullable();
                $table->string('avatar')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('last_message_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->index('last_message_at');
                $table->index('type');
            });
        }

        // 2. conversation_participants table
        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->uuid('conversation_id');
                $table->uuid('user_id');
                $table->string('role')->default('member'); // member | admin | owner
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('left_at')->nullable();
                $table->uuid('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->boolean('is_muted')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
                $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index('user_id');
                $table->index('conversation_id');
            });
        }

        // 3. Update portal_messages table with conversation_id and reply_to_message_id
        if (Schema::hasTable('portal_messages')) {
            Schema::table('portal_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('portal_messages', 'conversation_id')) {
                    $table->uuid('conversation_id')->nullable()->after('id');
                    $table->index('conversation_id');
                }
                if (! Schema::hasColumn('portal_messages', 'reply_to_message_id')) {
                    $table->uuid('reply_to_message_id')->nullable()->after('recipient_user_id');
                    $table->index('reply_to_message_id');
                }
            });
        }

        // 4. portal_message_reactions table
        if (! Schema::hasTable('portal_message_reactions')) {
            Schema::create('portal_message_reactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('message_id');
                $table->uuid('user_id');
                $table->string('reaction', 32); // 👍, 🙏, ❤️, etc.
                $table->timestamps();

                $table->unique(['message_id', 'user_id', 'reaction']);
                $table->foreign('message_id')->references('id')->on('portal_messages')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index('message_id');
            });
        }

        // 5. portal_message_attachments table
        if (! Schema::hasTable('portal_message_attachments')) {
            Schema::create('portal_message_attachments', function (Blueprint $table) {
                $table->id();
                $table->uuid('message_id');
                $table->string('disk')->default('public');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('file_size');
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->timestamps();

                $table->foreign('message_id')->references('id')->on('portal_messages')->cascadeOnDelete();
                $table->index('message_id');
            });
        }

        // 6. user_presences table
        if (! Schema::hasTable('user_presences')) {
            Schema::create('user_presences', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id')->unique();
                $table->string('status')->default('offline'); // online | busy | offline
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->string('device')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_presences');
        Schema::dropIfExists('portal_message_attachments');
        Schema::dropIfExists('portal_message_reactions');
        if (Schema::hasTable('portal_messages')) {
            Schema::table('portal_messages', function (Blueprint $table) {
                if (Schema::hasColumn('portal_messages', 'reply_to_message_id')) {
                    $table->dropColumn('reply_to_message_id');
                }
                if (Schema::hasColumn('portal_messages', 'conversation_id')) {
                    $table->dropColumn('conversation_id');
                }
            });
        }
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
