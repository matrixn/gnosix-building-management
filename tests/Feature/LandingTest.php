<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingTest extends TestCase
{
    public function test_atlas_is_the_laravel_homepage(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Clădirile au')
            ->assertSee('Dă-le o memorie.')
            ->assertSee('https://gnosix.zion3d.ro/', false)
            ->assertDontSee('Colecția GNOSIX')
            ->assertDontSee('Descarcă rezumatul')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_development_cannot_be_indexed_and_disabled_form_does_not_collect_data(): void
    {
        config(['gnosix.indexable' => false, 'gnosix.pilot_enabled' => false]);
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('id="pilot-form"', false);
        $this->get('/robots.txt')->assertSee('Disallow: /');
    }

    public function test_production_seo_and_private_design_paths(): void
    {
        config(['gnosix.indexable' => true]);
        $this->get('/robots.txt')->assertSee('Allow: /')->assertSee('https://gnosix.zion3d.ro/sitemap.xml');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://gnosix.zion3d.ro/confidentialitate');
        $this->get('/confidentialitate')->assertOk()->assertSee('Cine primește cererea');
        $this->get('/concepts/index.html')->assertNotFound();
        $this->assertDirectoryDoesNotExist(public_path('concepts'));
    }
}
