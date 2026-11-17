<?php

use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;

beforeEach(function (): void {
    fakeFilesDisk();
});

it('redirects the owner to a 5 minute signed URL that downloads the file under its original name', function (): void {
    $this->freezeTime();
    $document = MasterDocument::factory()->create();
    $file = StoredFile::factory()->forMasterDocument($document)->create(['original_name' => 'Currículo.pdf']);

    $response = $this->actingAs($document->user)->get(route('files.show', $file));

    $location = $response->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($location)->toStartWith('https://r2.test/'.$file->path.'?')
        ->and((int) $query['expires'])->toBe(now()->addMinutes(5)->getTimestamp())
        ->and($query['ResponseContentDisposition'])->toContain('attachment')
        ->and($query['ResponseContentDisposition'])->toContain("filename*=utf-8''Curr%C3%ADculo.pdf");
});

it("answers 404 for another user's file", function (): void {
    $file = StoredFile::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('files.show', $file))->assertNotFound();
});

it('sends guests to the login page', function (): void {
    $file = StoredFile::factory()->create();

    $this->get(route('files.show', $file))->assertRedirect(route('login'));
});

it('answers 404 for a malformed file id without querying', function (): void {
    $this->actingAs(User::factory()->create())->get('/files/not-a-file-id')->assertNotFound();
});
