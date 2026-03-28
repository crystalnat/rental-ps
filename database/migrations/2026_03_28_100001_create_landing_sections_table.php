<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('section_key'); // hero, about, services, gallery, testimonials, contact, footer
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('content')->nullable(); // Rich text / description
            $table->json('items')->nullable(); // Array of cards, features, testimonials, etc.
            $table->json('config')->nullable(); // Colors, layout variant, visibility toggle
            $table->string('image')->nullable(); // Background/section image path
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'section_key']);
        });

        Schema::create('landing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('site_title')->nullable();
            $table->string('tagline')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->json('colors')->nullable(); // { primary, secondary, accent, background, text }
            $table->json('fonts')->nullable(); // { heading, body }
            $table->json('social_links')->nullable(); // { instagram, facebook, whatsapp, tiktok }
            $table->json('seo')->nullable(); // { meta_title, meta_description, og_image }
            $table->string('custom_domain')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_settings');
        Schema::dropIfExists('landing_sections');
    }
};
