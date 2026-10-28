<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class CustomerExportTest extends TestCase
{
    use RefreshDatabase;

    /** Downloads the export and returns the sheet's XML. */
    private function sheet(string $query = ''): string
    {
        $response = $this->get('/api/stores/export'.$query)->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('customers-'.today()->toDateString().'.xlsx', $response->headers->get('Content-Disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', '_rels/.rels'] as $part) {
            $this->assertNotFalse($zip->getFromName($part), "Missing {$part}");
        }
        $zip->close();
        $this->assertNotFalse(simplexml_load_string($xml), 'Sheet is not valid XML');

        return $xml;
    }

    public function test_admin_exports_customers_with_the_current_filters(): void
    {
        $this->signIn();
        Store::create(['name' => 'Aling Nena Store', 'category' => 'small_sari_sari', 'status' => 'active', 'contact_person' => 'Nena', 'phone' => '0917 111 2222']);
        Store::create(['name' => 'Mang Jose Grocery', 'category' => 'mini_grocery', 'status' => 'prospect']);

        $all = $this->sheet();
        $this->assertStringContainsString('Store name', $all);
        $this->assertStringContainsString('Aling Nena Store', $all);
        $this->assertStringContainsString('Mang Jose Grocery', $all);
        $this->assertStringContainsString('Small Sari-Sari', $all);
        $this->assertStringContainsString('No orders yet', $all);

        $filtered = $this->sheet('?status=prospect');
        $this->assertStringContainsString('Mang Jose Grocery', $filtered);
        $this->assertStringNotContainsString('Aling Nena Store', $filtered);
    }

    public function test_formulas_and_markup_in_customer_fields_stay_plain_text(): void
    {
        $this->signIn();
        Store::create(['name' => '=HYPERLINK("http://evil.test","Click")', 'category' => 'small_sari_sari', 'status' => 'active', 'notes' => '<b>& "quotes"']);

        $xml = $this->sheet();
        $this->assertStringContainsString('t="inlineStr"', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $this->assertStringContainsString('=HYPERLINK(&quot;http://evil.test&quot;,&quot;Click&quot;)', $xml);
        $this->assertStringContainsString('&lt;b&gt;&amp; &quot;quotes&quot;', $xml);
    }

    public function test_only_the_super_admin_can_export(): void
    {
        $this->getJson('/api/stores/export')->assertUnauthorized();
        $this->signIn(User::REPRESENTATIVE);
        $this->getJson('/api/stores/export')->assertForbidden();
    }
}
