<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('severity', 10);
            $table->string('status', 20)->index();
            $table->text('description')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
        });

        // Exception-linked timeline events so the journey shows the
        // disruption inline, distinct from normal events.
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->foreignId('exception_id')->nullable()->after('created_by')
                ->constrained('shipment_exceptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exception_id');
        });

        Schema::dropIfExists('shipment_exceptions');
    }
};
