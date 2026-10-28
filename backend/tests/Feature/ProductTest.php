<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_add_product_with_picture_and_replace_it(): void
    {
        Storage::fake('local');

        $created = $this->post('/api/products', [
            'name' => 'Pure Barley', 'unit' => 'pack', 'price' => 250,
            'image' => UploadedFile::fake()->create('barley.png', 20, 'image/png'),
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $this->assertNotNull($created['image_url']);
        $this->assertArrayNotHasKey('image_path', $created);
        $first = Product::find($created['id'])->image_path;
        Storage::disk('local')->assertExists($first);
        $this->get("/api/products/{$created['id']}/image")->assertOk();

        // Editing with a new picture replaces the old file.
        $this->post("/api/products/{$created['id']}", [
            'name' => 'Pure Barley', 'unit' => 'pack', 'price' => 260,
            'image' => UploadedFile::fake()->create('barley2.webp', 20, 'image/webp'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('price', '260.00');
        Storage::disk('local')->assertMissing($first);

        $this->getJson('/api/products')->assertOk()->assertJsonPath('0.customers', 0);
    }

    public function test_rejects_files_that_are_not_pictures(): void
    {
        Storage::fake('local');

        $this->post('/api/products', [
            'name' => 'X', 'unit' => 'pack', 'price' => 1,
            'image' => UploadedFile::fake()->create('virus.exe', 20, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('image');
    }
}
