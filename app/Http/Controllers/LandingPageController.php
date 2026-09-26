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
    public function home(): Response
    {
        $slug = config('app.landing_brand');

        $brand = $slug
            ? Brand::where('slug', $slug)->where('is_active', true)->first()
            : Brand::where('is_active', true)->orderBy('id')->first();

        return Inertia::render('Landing/Home', [
            'brand' => [
                'name'    => $brand?->name ?? config('app.name'),
                'phone'   => $brand?->phone,
                'address' => $brand?->address,
            ],
            'whatsappUrl' => $this->buildWhatsappUrl(config('app.landing_whatsapp') ?: $brand?->phone),
            'loginUrl'    => route('login'),
        ]);
    }

    private function buildWhatsappUrl(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // wa.me butuh kode negara tanpa tanda plus; nomor lokal diawali 0 diganti 62
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode('Halo, saya mau booking PS. Untuk jam berapa yang masih kosong?');
    }

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
