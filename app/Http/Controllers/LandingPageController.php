<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\LandingSection;
use App\Models\LandingSetting;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    public function show(string $brandSlug): Response
    {
        $brand = Brand::where('slug', $brandSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $settings = LandingSetting::where('brand_id', $brand->id)->first();

        if (!$settings || !$settings->isPublished()) {
            abort(404);
        }

        $sections = LandingSection::where('brand_id', $brand->id)
            ->whereRaw('(section_bitmask & ?) = ?', [LandingSection::FLAG_VISIBLE, LandingSection::FLAG_VISIBLE])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($section) {
                $data = $section->toArray();
                if ($section->image) {
                    $data['image_url'] = Storage::disk('public')->url($section->image);
                }
                return $data;
            });

        return Inertia::render('Landing/Show', [
            'brand'    => $brand->only('name', 'slug', 'logo', 'phone', 'email', 'address'),
            'settings' => [
                'site_title'   => $settings->site_title,
                'tagline'      => $settings->tagline,
                'logo'         => $settings->logo ? Storage::disk('public')->url($settings->logo) : null,
                'favicon'      => $settings->favicon ? Storage::disk('public')->url($settings->favicon) : null,
                'colors'       => $settings->colors ?? [],
                'fonts'        => $settings->fonts ?? [],
                'social_links' => $settings->social_links ?? [],
                'seo'          => $settings->seo ?? [],
            ],
            'sections' => $sections,
        ]);
    }
}
