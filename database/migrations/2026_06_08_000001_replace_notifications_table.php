<?php

use App\Models\User;
use App\Notifications\SimpleNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If an existing custom notifications table exists, keep it as old_notifications
        if (Schema::hasTable('notifications')) {
            Schema::rename('notifications', 'old_notifications');
        }

        // Create Laravel-standard notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->uuid('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // If we have old data, migrate it into the new table
        if (Schema::hasTable('old_notifications')) {
            $old = DB::table('old_notifications')->get();

            foreach ($old as $row) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => SimpleNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $row->user_id,
                    'data' => json_encode([
                        'message' => $row->message,
                        'type' => $row->type,
                        'ticket_id' => $row->ticket_id,
                    ]),
                    'read_at' => $row->est_lue ? $row->date_envoi : null,
                    'created_at' => $row->date_envoi ?? now(),
                    'updated_at' => $row->date_envoi ?? now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new notifications table
        Schema::dropIfExists('notifications');

        // If an old_notifications table exists, restore it to notifications
        if (Schema::hasTable('old_notifications')) {
            Schema::rename('old_notifications', 'notifications');
        }
    }
};
