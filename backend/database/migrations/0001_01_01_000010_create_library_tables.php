<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Book Categories
        Schema::create('book_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Self-referencing foreign key
        Schema::table('book_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('book_categories')->onDelete('set null');
        });

        // Book Shelves/Locations
        Schema::create('book_shelves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('code');
            $table->string('location')->nullable();
            $table->integer('capacity')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
        });

        // Publishers
        Schema::create('publishers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Authors
        Schema::create('authors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('biography')->nullable();
            $table->string('nationality')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Books
        Schema::create('books', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('category_id')->nullable();
            $table->uuid('shelf_id')->nullable();
            $table->uuid('publisher_id')->nullable();
            $table->string('title');
            $table->string('isbn')->nullable();
            $table->string('edition')->nullable();
            $table->smallInteger('publish_year')->nullable();
            $table->string('language')->default('Indonesia');
            $table->integer('pages')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('total_copies')->default(1);
            $table->integer('available_copies')->default(1);
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('status', ['available', 'unavailable', 'damaged', 'lost'])->default('available');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('book_categories')->onDelete('set null');
            $table->foreign('shelf_id')->references('id')->on('book_shelves')->onDelete('set null');
            $table->foreign('publisher_id')->references('id')->on('publishers')->onDelete('set null');

            $table->index(['tenant_id', 'isbn']);
            $table->index(['tenant_id', 'title']);
        });

        // Book Authors (Many-to-Many)
        Schema::create('book_authors', function (Blueprint $table) {
            $table->id();
            $table->uuid('book_id');
            $table->uuid('author_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('authors')->onDelete('cascade');
            $table->unique(['book_id', 'author_id']);
        });

        // Book Copies (Individual copies)
        Schema::create('book_copies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('book_id');
            $table->string('copy_number'); // Unique identifier for each copy
            $table->string('barcode')->nullable();
            $table->enum('condition', ['good', 'fair', 'poor', 'damaged', 'lost'])->default('good');
            $table->enum('status', ['available', 'borrowed', 'reserved', 'maintenance', 'lost'])->default('available');
            $table->date('acquisition_date')->nullable();
            $table->string('acquisition_source')->nullable(); // purchase, donation, etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->unique(['tenant_id', 'copy_number']);
            $table->index(['tenant_id', 'barcode']);
        });

        // Library Members (can be students, teachers, staff)
        Schema::create('library_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->unique();
            $table->string('member_number');
            $table->enum('member_type', ['student', 'teacher', 'staff', 'external'])->default('student');
            $table->date('registered_at');
            $table->date('expires_at')->nullable();
            $table->integer('max_borrow_limit')->default(3);
            $table->integer('current_borrowed')->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended', 'expired'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['tenant_id', 'member_number']);
        });

        // Book Loans (Peminjaman)
        Schema::create('book_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('library_member_id');
            $table->uuid('book_copy_id');
            $table->date('borrow_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->integer('extension_count')->default(0);
            $table->enum('status', ['borrowed', 'returned', 'overdue', 'lost'])->default('borrowed');
            $table->string('condition_on_borrow')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->boolean('fine_paid')->default(false);
            $table->uuid('issued_by')->nullable();
            $table->uuid('returned_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('library_member_id')->references('id')->on('library_members')->onDelete('cascade');
            $table->foreign('book_copy_id')->references('id')->on('book_copies')->onDelete('cascade');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('returned_to')->references('id')->on('users')->onDelete('set null');

            $table->index(['library_member_id', 'status']);
            $table->index(['tenant_id', 'due_date', 'status']);
        });

        // Book Reservations
        Schema::create('book_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('library_member_id');
            $table->uuid('book_id');
            $table->date('reservation_date');
            $table->date('expiry_date');
            $table->enum('status', ['pending', 'ready', 'fulfilled', 'cancelled', 'expired'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('library_member_id')->references('id')->on('library_members')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');

            $table->index(['book_id', 'status']);
        });

        // Library Settings
        Schema::create('library_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->integer('default_loan_days')->default(7);
            $table->integer('max_loan_days')->default(14);
            $table->integer('max_extensions')->default(2);
            $table->integer('extension_days')->default(7);
            $table->decimal('daily_fine', 10, 2)->default(500.00);
            $table->decimal('max_fine', 10, 2)->default(50000.00);
            $table->decimal('lost_book_multiplier', 5, 2)->default(2.00);
            $table->boolean('allow_reservations')->default(true);
            $table->integer('reservation_expiry_days')->default(3);
            $table->json('operating_hours')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_settings');
        Schema::dropIfExists('book_reservations');
        Schema::dropIfExists('book_loans');
        Schema::dropIfExists('library_members');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('book_authors');
        Schema::dropIfExists('books');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('publishers');
        Schema::dropIfExists('book_shelves');
        Schema::dropIfExists('book_categories');
    }
};
