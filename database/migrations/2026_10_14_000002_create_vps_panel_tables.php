<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Websites
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('primary_domain')->unique();
            $table->string('system_user')->unique();
            $table->string('php_version', 10)->default('8.3');
            $table->string('document_root');
            $table->string('status', 20)->default('active'); // active, suspended, provisioning, failed
            $table->boolean('ssl_enabled')->default(false);
            $table->unsignedBigInteger('disk_usage_mb')->default(0);
            $table->unsignedBigInteger('bandwidth_usage_mb')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // 2. Domains & Subdomains
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->string('domain_name')->unique();
            $table->boolean('is_subdomain')->default(false);
            $table->string('type', 20)->default('alias'); // primary, alias, redirect
            $table->string('redirect_url')->nullable();
            $table->unsignedSmallInteger('redirect_status')->nullable(); // 301, 302
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamps();

            $table->index(['website_id', 'type']);
        });

        // 3. DNS Zones
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->string('domain_name')->unique();
            $table->string('soa_email')->default('hostmaster');
            $table->unsignedInteger('default_ttl')->default(3600);
            $table->string('status', 20)->default('active'); // active, disabled
            $table->timestamps();
        });

        // 4. DNS Records
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained('dns_zones')->cascadeOnDelete();
            $table->string('name'); // e.g. @, www, mail
            $table->string('type', 10); // A, AAAA, CNAME, MX, TXT, NS, SRV, CAA
            $table->text('content');
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedSmallInteger('priority')->nullable(); // for MX, SRV
            $table->timestamps();

            $table->index(['dns_zone_id', 'type']);
            $table->index(['dns_zone_id', 'name']);
        });

        // 5. SSL Certificates
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->string('domain_name');
            $table->string('provider', 30)->default('letsencrypt'); // letsencrypt, custom, self-signed
            $table->string('certificate_path')->nullable();
            $table->string('private_key_path')->nullable();
            $table->string('fullchain_path')->nullable();
            $table->string('issuer')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->string('status', 20)->default('active'); // active, expired, pending, failed
            $table->timestamp('last_renewed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['domain_name', 'status']);
        });

        // 6. Databases
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->string('name')->unique();
            $table->string('charset', 32)->default('utf8mb4');
            $table->string('collation', 64)->default('utf8mb4_unicode_ci');
            $table->decimal('size_mb', 10, 2)->default(0);
            $table->timestamps();
        });

        // 7. Database Users
        Schema::create('database_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('host', 64)->default('localhost');
            $table->timestamps();
        });

        // 8. Database User Assignments (pivot)
        Schema::create('database_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_id')->constrained('databases')->cascadeOnDelete();
            $table->foreignId('database_user_id')->constrained('database_users')->cascadeOnDelete();
            $table->string('privileges')->default('ALL PRIVILEGES');
            $table->timestamps();

            $table->unique(['database_id', 'database_user_id']);
        });

        // 9. Email Domains
        Schema::create('email_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->string('status', 20)->default('active'); // active, disabled
            $table->string('dkim_selector')->default('mail');
            $table->text('dkim_private_key')->nullable();
            $table->text('dkim_public_key')->nullable();
            $table->timestamps();
        });

        // 10. Mailboxes
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_domain_id')->constrained('email_domains')->cascadeOnDelete();
            $table->string('username'); // local-part (e.g. info)
            $table->string('email')->unique(); // info@domain.com
            $table->string('password_hash');
            $table->unsignedInteger('quota_mb')->default(1024); // 0 = unlimited
            $table->decimal('used_mb', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['email_domain_id', 'username']);
        });

        // 11. Mail Aliases & Forwarding
        Schema::create('mail_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_domain_id')->constrained('email_domains')->cascadeOnDelete();
            $table->string('source_email');
            $table->string('destination_email');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('source_email');
        });

        // 12. Backup Repositories
        Schema::create('backup_repositories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('local'); // local, s3, sftp
            $table->string('path_or_uri');
            $table->text('credentials_json')->nullable(); // encrypted credentials for S3/SFTP
            $table->boolean('is_initialized')->default(false);
            $table->timestamps();
        });

        // 13. Backup Jobs
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_repository_id')->constrained('backup_repositories')->cascadeOnDelete();
            $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
            $table->string('name');
            $table->string('frequency', 20)->default('daily'); // daily, weekly, monthly, manual
            $table->boolean('include_files')->default(true);
            $table->boolean('include_databases')->default(true);
            $table->unsignedSmallInteger('retention_daily')->default(7);
            $table->unsignedSmallInteger('retention_weekly')->default(4);
            $table->unsignedSmallInteger('retention_monthly')->default(6);
            $table->string('status', 20)->default('active'); // active, paused
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        // 14. Backup Snapshots
        Schema::create('backup_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_job_id')->constrained('backup_jobs')->cascadeOnDelete();
            $table->string('snapshot_id'); // Restic snapshot hash
            $table->string('tree_id')->nullable();
            $table->json('paths')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 20)->default('completed'); // completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['backup_job_id', 'snapshot_id']);
        });

        // 15. Server Operations & Audit Logs
        Schema::create('server_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // CreateWebsite, DeleteWebsite, IssueSslCertificate, etc.
            $table->string('target_type')->nullable(); // Website, Database, etc.
            $table->string('target_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->json('payload')->nullable(); // Redacted input parameters
            $table->json('result')->nullable(); // Structured result from agent
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['action', 'status']);
            $table->index('created_at');
        });

        // 16. Server Settings
        Schema::create('server_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, integer, boolean, json
            $table->string('group', 30)->default('general'); // general, network, email, backup, security
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();

            $table->index('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_settings');
        Schema::dropIfExists('server_operations');
        Schema::dropIfExists('backup_snapshots');
        Schema::dropIfExists('backup_jobs');
        Schema::dropIfExists('backup_repositories');
        Schema::dropIfExists('mail_aliases');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('email_domains');
        Schema::dropIfExists('database_user_assignments');
        Schema::dropIfExists('database_users');
        Schema::dropIfExists('databases');
        Schema::dropIfExists('ssl_certificates');
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('websites');
    }
};
