<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for CargoTrace (9 core tables + indexes + foreign keys).
     */
    public function up(): void
    {
        // 1. users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['ADMIN', 'LOGISTICS_OPERATOR', 'CUSTOMER'])
                ->default('CUSTOMER')
                ->index();
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. carriers
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 16)->unique();
            $table->enum('service_type', ['SEA', 'AIR', 'ROAD'])->default('SEA');
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 3. locations (Global ports + 38 Indonesian Provinces / Kabupaten / Kota hubs)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 16)->unique();
            $table->string('city')->index();
            $table->string('province')->nullable()->index();
            $table->string('country')->index();
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->timestamps();
        });

        // 4. shipments
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 32)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('current_status', [
                'CREATED',
                'BOOKED',
                'PICKED_UP',
                'IN_TRANSIT',
                'DELIVERED',
            ])->default('CREATED')->index();
            $table->string('cargo_type');
            $table->decimal('weight_kg', 12, 2);
            $table->decimal('volume_m3', 10, 2);
            $table->timestamp('expected_departure_at')->nullable();
            $table->timestamp('expected_arrival_at')->index();
            $table->timestamp('estimated_arrival_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'current_status']);
            $table->index(['carrier_id', 'current_status']);
        });

        // 5. shipment_items
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 12, 2)->default(0);
            $table->decimal('volume_m3', 10, 2)->default(0);
            $table->timestamps();
        });

        // 6. tracking_events (Append-only chronological checkpoints & lifecycle transitions)
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('event_type', 32)->index(); // CREATED, BOOKED, PICKED_UP, IN_TRANSIT, DELIVERED, CHECKPOINT, EXCEPTION, INFO
            $table->timestamp('occurred_at')->index();
            $table->string('location_name');
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
        });

        // 7. exceptions (Operational incident management: OPEN -> INVESTIGATING -> RESOLVED)
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->enum('type', [
                'WEATHER_DELAY',
                'CUSTOMS_HOLD',
                'CARRIER_DELAY',
                'DAMAGED_CARGO',
                'ROUTE_DEVIATION',
                'PORT_CONGESTION',
            ])->index();
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
            $table->enum('status', ['OPEN', 'INVESTIGATING', 'RESOLVED'])->default('OPEN')->index();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
        });

        // 8. notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        // 9. audit_logs (Immutable audit trail of state deltas)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['entity_type', 'entity_id']);
        });

        // 10. scenario_configs (Live deterministic delay rule configuration)
        Schema::create('scenario_configs', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 24)->default('DEFAULT');
            $table->string('route_corridor')->default('Southeast Asia → Europe');
            $table->unsignedInteger('low_threshold_hours')->default(6);
            $table->unsignedInteger('high_threshold_hours')->default(24);
            $table->string('primary_exception', 48)->default('CUSTOMS_HOLD');
            $table->string('kpi_focus')->default('On-Time Delivery & Exception Rate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_configs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('exceptions');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('carriers');
        Schema::dropIfExists('users');
    }
};
