<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalDocumentsTest extends TestCase
{
    #[DataProvider('documents')]
    public function test_public_policy_and_api_expose_the_same_current_document(string $type): void
    {
        config(['company.name' => 'PROTECT YOUR ASSETS TODAY LTD', 'company.number' => '15969857']);
        $api = $this->getJson('/api/v1/legal/'.$type)->assertOk()->assertJsonPath('data.version', '2026-10-26');
        $page = $this->get('/legal/'.$type)->assertOk()->assertSee('PROTECT YOUR ASSETS TODAY LTD')->assertSee('15969857');
        $fullPage = $this->get('/api/v1/legal/'.$type.'/page', ['Accept' => 'text/html'])
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('PROTECT YOUR ASSETS TODAY LTD')->assertSee('15969857');

        foreach ($api->json('data.sections') as $section) {
            $page->assertSee($section['title']);
            $fullPage->assertSee($section['title']);
            foreach ($section['paragraphs'] as $paragraph) {
                $page->assertSee($paragraph);
                $fullPage->assertSee($paragraph);
            }
        }
    }

    public function test_full_policy_navigation_stays_under_the_working_api_prefix(): void
    {
        $page = $this->get('/api/v1/legal/terms/page')->assertOk();

        foreach (['terms', 'privacy', 'refunds', 'security'] as $document) {
            $page->assertSeeHtml('href="/api/v1/legal/'.$document.'/page"');
        }
    }

    public function test_unknown_full_policy_page_returns_404(): void
    {
        $this->get('/api/v1/legal/nonexistent/page')->assertNotFound();
    }

    public static function documents(): array
    {
        return [['terms'], ['privacy'], ['refunds'], ['security']];
    }

    public function test_terms_disclose_virtual_goods_currencies_delivery_and_governing_law(): void
    {
        $this->get('/legal/terms')->assertOk()->assertSee('EUR')->assertSee('GBP')->assertSee('USD')
            ->assertSee('law of England and Wales')->assertSee('Consumer Rights Act 2015')
            ->assertSee('No card number is requested')->assertSee('normally within seconds')
            ->assertSee('Unused currency')->assertSee('cancellation');
    }

    public function test_privacy_names_the_uk_framework_and_ico(): void
    {
        $this->get('/legal/privacy')->assertOk()->assertSee('UK GDPR')->assertSee('Data Protection Act 2018')
            ->assertSee('Data (Use and Access) Act 2025')->assertSee('https://ico.org.uk/make-a-complaint/');
    }

    public function test_unknown_policy_is_not_available(): void
    {
        $this->getJson('/api/v1/legal/nonexistent')->assertNotFound();
    }

    public function test_company_details_are_escaped_in_public_html(): void
    {
        config(['company.name' => '<script>alert(1)</script>']);
        $this->get('/legal/terms')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/api/v1/legal/terms/page')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
