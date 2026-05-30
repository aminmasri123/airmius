<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->string('category', 40)->default('other');
            $table->string('status', 30)->default('active');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('billing_interval', 30)->default('monthly');
            $table->string('payment_method', 40)->nullable();
            $table->date('next_due_on')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('notice_until_on')->nullable();
            $table->unsignedSmallInteger('cancellation_period_days')->nullable();
            $table->boolean('auto_renews')->default(true);
            $table->string('contract_number')->nullable();
            $table->string('account_reference')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website')->nullable();
            $table->string('document_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('next_due_on');
            $table->index('notice_until_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_contracts');
    }
};
