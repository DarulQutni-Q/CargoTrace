<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for CargoTrace (Slide 11 Data Model Minimum:
     * users, shipments, shipment_items, carriers, locations, tracking_events,
     * shipment_exceptions, notifications, audit_logs + Sanctum tokens & scenario_configs).
     */
    public function up(): void
    {
        // 1. users (akun & role: ADMIN, LOGISTICS_OPERATOR, CUSTOMER)
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

        // 2. carriers (operator logistik: SEA, AIR, ROAD)
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 16)->unique();
            $table->enum('service_type', ['SEA', 'AIR', 'ROAD'])->default('SEA');
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 3. locations (origin/destination ports & hubs with coordinates)
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

        // 4. shipments (shipment utama + soft delete support as noted in Slide 11)
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
            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'current_status']);
            $table->index(['carrier_id', 'current_status']);
            $table->index(['origin_location_id', 'destination_location_id']);
        });

        // 5. shipment_items (detail barang per shipment - 1:N hasMany)
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 12, 2)->default(0);
            $table->decimal('volume_m3', 10, 2)->default(0);
            $table->timestamps();
        });

        // 6. tracking_events (riwayat perjalanan historis / append-only timeline - Slide 7)
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

        // 7. shipment_exceptions (gangguan/exception - Slide 8 & Slide 11)
        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->enum('type', [
                'CUSTOMS_HOLD',
                'WEATHER_DELAY',
                'PORT_CONGESTION',
                'DAMAGED',
                'MISROUTED',
                'DOCUMENTATION_ISSUE',
                'CARRIER_DELAY',
                'DAMAGED_CARGO',
                'ROUTE_DEVIATION',
            ])->index();
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
            $table->enum('status', ['OPEN', 'INVESTIGATING', 'RESOLVED'])->default('OPEN')->index();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
        });

        // 8. notifications (pemberitahuan in-app & email trigger - Slide 10 & Slide 11)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        // 9. audit_logs (jejak perubahan penting: aktor, aksi, waktu, entity terkait - Slide 10 & Slide 11)
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

        // 10. personal_access_tokens (Laravel Sanctum API Token Authentication - Slide 12 & Slide 14)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 11. scenario_configs (Configurable group delay threshold: Group A 12h, Group B 18h, Group C 24h - Slide 8 & 23)
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
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('shipment_exceptions');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('carriers');
        Schema::dropIfExists('users');
    }
};
