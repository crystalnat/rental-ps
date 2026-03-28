<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSection;
use App\Models\LandingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LandingSettingsController extends Controller
{
    private const DEFAULT_SECTIONS = [
        [
            'section_key' => 'hero',
            'title'       => 'Selamat Datang di Bisnis Kami',
            'subtitle'    => 'Solusi terbaik untuk kebutuhan Anda',
            'content'     => 'Kami menyediakan produk dan layanan berkualitas tinggi yang dirancang untuk memberikan pengalaman terbaik bagi pelanggan.',
            'sort_order'  => 0,
            'config'      => ['layout' => 'center', 'overlay_opacity' => 60],
        ],
        [
            'section_key' => 'about',
            'title'       => 'Tentang Kami',
            'subtitle'    => 'Kenali lebih dekat',
            'content'     => 'Kami adalah perusahaan yang berdedikasi untuk memberikan solusi inovatif dan layanan profesional. Dengan pengalaman bertahun-tahun, kami terus berkomitmen untuk menjadi mitra terbaik bagi setiap klien.',
            'sort_order'  => 1,
            'config'      => ['layout' => 'image-right'],
        ],
        [
            'section_key' => 'services',
            'title'       => 'Layanan Kami',
            'subtitle'    => 'Apa yang bisa kami bantu',
            'sort_order'  => 2,
            'items'       => [
                ['title' => 'Konsultasi Bisnis',   'description' => 'Analisis mendalam untuk strategi pertumbuhan bisnis Anda.',   'icon' => 'briefcase'],
                ['title' => 'Desain Profesional',  'description' => 'Branding dan identitas visual yang mengesankan.',              'icon' => 'palette'],
                ['title' => 'Pemasaran Digital',   'description' => 'Strategi pemasaran modern untuk menjangkau lebih banyak pelanggan.', 'icon' => 'megaphone'],
            ],
        ],
        [
            'section_key' => 'gallery',
            'title'       => 'Galeri',
            'subtitle'    => 'Portofolio dan dokumentasi',
            'sort_order'  => 3,
            'items'       => [],
        ],
        [
            'section_key' => 'testimonials',
            'title'       => 'Testimoni Pelanggan',
            'subtitle'    => 'Apa kata mereka',
            'sort_order'  => 4,
            'items'       => [
                ['name' => 'Rina Sari',       'role' => 'Pemilik Cafe',          'content' => 'Pelayanan luar biasa dan hasil sangat memuaskan. Sangat direkomendasikan!', 'rating' => 5],
                ['name' => 'Budi Santoso',    'role' => 'Manager Operasional',   'content' => 'Tim yang responsif dan profesional. Proyek kami selesai tepat waktu.',     'rating' => 5],
                ['name' => 'Maya Putri',      'role' => 'Owner Retail',          'content' => 'Kualitas kerja yang konsisten dan komunikasi yang sangat baik.',           'rating' => 4],
            ],
        ],
        [
            'section_key' => 'contact',
            'title'       => 'Hubungi Kami',
            'subtitle'    => 'Kami siap membantu Anda',
            'sort_order'  => 5,
            'items'       => [],
            'config'      => [
                'address'   => 'Jl. Contoh Alamat No. 123, Jakarta',
                'phone'     => '+62 812 3456 7890',
                'email'     => 'info@contoh.com',
                'whatsapp'  => '6281234567890',
                'map_embed' => '',
            ],
        ],
    ];

    public function index(): Response
    {
        $brandId = Auth::user()->brand_id;

        $settings = LandingSetting::firstOrCreate(
            ['brand_id' => $brandId],
            [
                'site_title' => Auth::user()->brand->name ?? 'Company Profile',
                'tagline'    => 'Solusi Terbaik untuk Bisnis Anda',
                'colors'     => [
                    'primary'    => '#2563eb',
                    'secondary'  => '#1e40af',
                    'accent'     => '#f59e0b',
                    'background' => '#ffffff',
                    'text'       => '#111827',
                ],
                'fonts'       => ['heading' => 'Inter', 'body' => 'Inter'],
                'social_links'=> ['instagram' => '', 'facebook' => '', 'whatsapp' => '', 'tiktok' => ''],
                'seo'         => ['meta_title' => '', 'meta_description' => '', 'og_image' => ''],
            ]
        );

        $sections = LandingSection::where('brand_id', $brandId)
            ->orderBy('sort_order')
            ->get();

        if ($sections->isEmpty()) {
            foreach (self::DEFAULT_SECTIONS as $def) {
                $section = LandingSection::create(array_merge($def, ['brand_id' => $brandId]));
                $section->setFlag(LandingSection::FLAG_VISIBLE, true);
                $section->save();
            }
            $sections = LandingSection::where('brand_id', $brandId)
                ->orderBy('sort_order')
                ->get();
        }

        // Map bitmasks to booleans for the frontend
        $settings->is_published = $settings->isPublished();
        $sections->each(function (LandingSection $s) {
            $s->is_visible = $s->isVisible();
        });

        return Inertia::render('Admin/Landing/Index', [
            'settings' => $settings,
            'sections' => $sections,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $brandId = Auth::user()->brand_id;

        $data = $request->validate([
            'site_title'    => ['nullable', 'string', 'max:255'],
            'tagline'       => ['nullable', 'string', 'max:500'],
            'colors'        => ['nullable', 'array'],
            'fonts'         => ['nullable', 'array'],
            'social_links'  => ['nullable', 'array'],
            'seo'           => ['nullable', 'array'],
            'is_published'  => ['nullable', 'boolean'],
        ]);

        $settings = LandingSetting::where('brand_id', $brandId)->first();

        if ($request->hasFile('logo')) {
            if ($settings && $settings->logo) {
                Storage::disk('public')->delete($settings->logo);
            }
            $data['logo'] = $request->file('logo')->store('landing/logos', 'public');
        }
        if ($request->hasFile('favicon')) {
            if ($settings && $settings->favicon) {
                Storage::disk('public')->delete($settings->favicon);
            }
            $data['favicon'] = $request->file('favicon')->store('landing/favicons', 'public');
        }

        $isPublished = $request->input('is_published');
        unset($data['is_published']);

        $settings = LandingSetting::updateOrCreate(
            ['brand_id' => $brandId],
            $data
        );

        if ($request->has('is_published')) {
            $settings->setFlag(LandingSetting::FLAG_PUBLISHED, (bool)$isPublished);
            $settings->save();
        }

        return back()->with('success', 'Pengaturan landing page berhasil disimpan.');
    }

    public function updateSection(Request $request, LandingSection $section): RedirectResponse
    {
        $brandId = Auth::user()->brand_id;
        if ($section->brand_id !== $brandId) {
            abort(403);
        }

        $data = $request->validate([
            'title'      => ['nullable', 'string', 'max:255'],
            'subtitle'   => ['nullable', 'string', 'max:500'],
            'content'    => ['nullable', 'string', 'max:5000'],
            'items'      => ['nullable', 'array'],
            'config'     => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $isVisible = $request->input('is_visible');
        unset($data['is_visible']);

        if ($request->hasFile('image')) {
            if ($section->image) {
                Storage::disk('public')->delete($section->image);
            }
            $data['image'] = $request->file('image')->store('landing/sections', 'public');
        }

        $section->fill($data);
        if ($request->has('is_visible')) {
            $section->setFlag(LandingSection::FLAG_VISIBLE, (bool)$isVisible);
        }
        $section->save();

        return back()->with('success', "Section \"{$section->section_key}\" berhasil diperbarui.");
    }

    public function uploadSectionImage(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store('landing/gallery', 'public');

        return response()->json([
            'success' => true,
            'url'     => Storage::disk('public')->url($path),
            'path'    => $path,
        ]);
    }
}
