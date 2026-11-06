<?php

namespace Tests\Feature;

use App\Domain\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsUsers;
use Tests\TestCase;

/** T-15 evidence: 2 MB PNG ok; 3 MB -> 422; no cover -> cover_key null. Type checked by content. */
class CoversApiTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n";
    private const JPG = "\xFF\xD8\xFF\xE0";

    private array $admin;
    private string $bookId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->as(Role::Admin);
        $this->bookId = $this->postJson('/api/v1/books', [
            'title' => 'Momo', 'author' => 'Michael Ende', 'isbn' => '9788420482811',
            'category_id' => '55555555-5555-4555-8555-555555555555', 'total_copies' => 2,
        ], $this->admin)->json('id');
    }

    private function upload(string $bytes, string $name = 'cover.png'): \Illuminate\Testing\TestResponse
    {
        // Real clients must use POST + _method=PUT: PHP does not parse multipart bodies on PUT.
        return $this->withHeaders($this->admin)->post("/api/v1/books/{$this->bookId}/cover", [
            '_method' => 'PUT',
            'file' => UploadedFile::fake()->createWithContent($name, $bytes),
        ], ['Accept' => 'application/json']);
    }

    private function sized(string $magic, int $bytes): string
    {
        return $magic.str_repeat("\0", $bytes - strlen($magic));
    }

    private function coverPath(string $key): string
    {
        return config('library.covers_dir').DIRECTORY_SEPARATOR.$key;
    }

    public function test_without_cover_the_key_is_null_never_empty_and_get_is_404(): void
    {
        $this->getJson("/api/v1/books/{$this->bookId}", $this->admin)->assertJson(['cover_key' => null]);
        $this->assertNull(DB::table('library.book')->value('cover_key'));
        $this->get("/api/v1/books/{$this->bookId}/cover", $this->admin)->assertNotFound()->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_png_of_exactly_2_mb_is_accepted_and_served_back(): void
    {
        $bytes = $this->sized(self::PNG, 2 * 1024 * 1024);

        $key = $this->upload($bytes)->assertOk()->json('cover_key');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key, 'opaque key, not a path');
        $this->assertSame($key, DB::table('library.book')->value('cover_key'));
        $served = $this->get("/api/v1/books/{$this->bookId}/cover", $this->as(Role::Librarian))->assertOk();
        $this->assertSame('image/png', $served->headers->get('Content-Type'));
        $this->assertSame($bytes, $served->getContent());
    }

    public function test_3_mb_file_is_rejected_with_422_and_nothing_is_stored(): void
    {
        $this->upload($this->sized(self::PNG, 3 * 1024 * 1024))
            ->assertStatus(422)->assertJson(['code' => 'VALIDATION_ERROR'])->assertJsonPath('details.0.field', 'file');
        $this->assertNull(DB::table('library.book')->value('cover_key'));
    }

    public function test_type_is_checked_by_content_not_extension(): void
    {
        $this->upload('GIF89a not really a png', 'fake.png')->assertStatus(422)->assertJsonPath('details.0.field', 'file');
        $this->upload($this->sized(self::JPG, 1000), 'photo.bin')->assertOk();
    }

    public function test_replacing_and_removing_delete_the_old_binary(): void
    {
        $first = $this->upload($this->sized(self::PNG, 100))->json('cover_key');
        $second = $this->upload($this->sized(self::JPG, 100))->json('cover_key');
        $this->assertFileDoesNotExist($this->coverPath($first));
        $this->assertFileExists($this->coverPath($second));

        $this->deleteJson("/api/v1/books/{$this->bookId}/cover", [], $this->admin)->assertNoContent();
        $this->assertNull(DB::table('library.book')->value('cover_key'));
        $this->assertFileDoesNotExist($this->coverPath($second));
        $this->get("/api/v1/books/{$this->bookId}/cover", $this->admin)->assertNotFound();
    }

    public function test_only_admin_changes_covers(): void
    {
        $this->withHeaders($this->as(Role::Librarian))->post("/api/v1/books/{$this->bookId}/cover", [
            '_method' => 'PUT',
            'file' => UploadedFile::fake()->createWithContent('c.png', self::PNG),
        ], ['Accept' => 'application/json'])->assertStatus(403);
        $this->deleteJson("/api/v1/books/{$this->bookId}/cover", [], $this->as(Role::Librarian))->assertStatus(403);
    }
}
