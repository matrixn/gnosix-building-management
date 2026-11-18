<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SiteSettings::class)->save(SiteSettings::defaults());
    }
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
        app(SiteSettings::class)->save(['indexable' => false, 'contact_enabled' => false]);
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('class="contact-fields" disabled', false);
        $this->get('/robots.txt')->assertSee('Disallow: /');
    }

    public function test_production_seo_and_private_design_paths(): void
    {
        app(SiteSettings::class)->save(['indexable' => true]);
        $this->get('/robots.txt')->assertSee('Allow: /')->assertSee('https://gnosix.zion3d.ro/sitemap.xml');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://gnosix.zion3d.ro/confidentialitate');
        $this->get('/confidentialitate')->assertOk()->assertSee('Cine primește cererea');
        $this->get('/concepts/index.html')->assertNotFound();
        $this->assertDirectoryDoesNotExist(public_path('concepts'));
    }

    public function test_romanian_is_default_english_has_separate_canonical_and_full_translation(): void
    {
        $this->withHeader('Accept-Language', 'en')->get('/')->assertOk()->assertHeader('Content-Language', 'ro')->assertSee('<html lang="ro">', false)->assertSee('Nume complet');
        $response = $this->get('/en?utm_source=test')->assertOk()->assertHeader('Content-Language', 'en')
            ->assertSee('<html lang="en">', false)->assertSee('Give them a memory.')->assertSee('Full name')->assertSee('Joining the pilot programme')
            ->assertSee('<link rel="canonical" href="https://gnosix.zion3d.ro/en">', false)
            ->assertSee('hreflang="ro" href="https://gnosix.zion3d.ro/ro"', false)->assertSee('hreflang="x-default"', false)
            ->assertSee('gnosix-social-en.png')->assertDontSee('Devino partener pilot')->assertDontSee('Nume complet');
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $json = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('en', $json['@graph'][2]['inLanguage']);
        $this->get('/en/privacy')->assertOk()->assertSee('Who receives your enquiry')->assertSee('phone number')->assertSee('https://gnosix.zion3d.ro/en/privacy');
        $this->get('/')->assertHeader('Content-Language', 'ro');
    }


    public function test_ip_country_negotiates_english_and_preserves_campaign_attribution(): void
    {
        $url = '/?utm_source=outreach&utm_campaign=pilot';
        $this->withServerVariables(['REMOTE_ADDR' => '86.34.0.1'])->get($url)->assertOk()->assertHeader('Content-Language', 'ro');
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get($url)
            ->assertRedirect('/en?utm_source=outreach&utm_campaign=pilot')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->withServerVariables(['REMOTE_ADDR' => '2606:4700:4700::1111'])->get('/')->assertRedirect('/en');
        $this->get('/en?utm_source=outreach&utm_campaign=pilot')->assertOk()->assertHeader('Content-Language', 'en');
        $this->assertDatabaseCount('campaign_links', 2);
        $this->assertDatabaseHas('campaign_links', ['path' => '/en', 'visits' => 1]);
        $this->assertDatabaseHas('campaign_links', ['path' => '/', 'visits' => 1]);
    }

    public function test_explicit_language_may_override_ip_and_romanian_url_is_stable(): void
    {
        app(SiteSettings::class)->save(['indexable' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/ro')->assertOk()->assertHeader('Content-Language', 'ro')
            ->assertSee('<link rel="canonical" href="https://gnosix.zion3d.ro/ro">', false);
        $this->get('/')->assertOk()->assertHeader('Content-Language', 'ro');
        $this->withServerVariables(['REMOTE_ADDR' => '86.34.0.1'])->get('/en?lang=en')->assertOk();
        $this->get('/')->assertRedirect('/en');
        $this->get('/ro')->assertOk();
        $this->get('/')->assertOk()->assertHeader('Content-Language', 'ro');
    }

    public function test_unknown_ip_uses_default_and_untrusted_forwarded_ip_is_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.16'])
            ->withHeaders(['X-Forwarded-For' => '8.8.8.8', 'CF-IPCountry' => 'US'])
            ->get('/')->assertOk()->assertHeader('Content-Language', 'ro');
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/confidentialitate')->assertOk()->assertHeader('Content-Language', 'ro');
    }

    public function test_six_subjects_have_no_empty_choice_and_pilot_is_selected_by_default(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<select id="contact-subject".*?<\/select>/s', $html, $matches);
        $this->assertSame(6, substr_count($matches[0], '<option '));
        $this->assertStringContainsString('value="pilot" selected', $matches[0]);
        $this->assertStringNotContainsString('value=""', $matches[0]);
        $this->assertStringNotContainsString('<dialog', $html);
    }

    public function test_sitemap_has_both_languages_and_reciprocal_alternates(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertCount(4, $xml->url);
        foreach ($xml->url as $url) $this->assertCount(3, $url->children('http://www.w3.org/1999/xhtml')->link);
    }

    public function test_product_experience_is_readable_and_honest_in_both_languages(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Concept interactiv · date fictive')
            ->assertSee('Time Machine')->assertSee('Evidence Engine')->assertSee('Building DNA')
            ->assertSee('Centrală CT-01 instalată')->assertSee('Pompa P-02 înlocuită')->assertSee('4 incidente de apă caldă')
            ->assertSee('Funcționalități planificate.')->assertSee('nu analizează documente reale')
            ->assertSee('AX-204 și AX-240')->assertSee('Istoricul ar urma clădirea');
        $this->get('/en')->assertOk()
            ->assertSee('Interactive concept · fictional data')->assertSee('Explore what we are building.')
            ->assertSee('Boiler CT-01 installed')->assertSee('Pump P-02 replaced')->assertSee('4 hot-water incidents')
            ->assertSee('Planned features.')->assertSee('does not analyse real documents')
            ->assertSee('Claims with provenance.')->assertSee('0 of 3 categories reviewed')
            ->assertDontSee('Centrală CT-01 instalată')->assertDontSee('Ce predai mai departe?');
    }
}
