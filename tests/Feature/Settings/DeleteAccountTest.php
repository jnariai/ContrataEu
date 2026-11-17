<?php

use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;

it("deletes the user's data and every file under their prefix in the bucket, and nobody else's", function (): void {
    $disk = fakeFilesDisk();
    $user = User::factory()->create();
    $other = User::factory()->create();

    $master = MasterDocument::factory()->for($user)->create();
    $original = StoredFile::factory()->forMasterDocument($master)->create();
    $document = GeneratedDocument::factory()->for(Generation::factory()->for(Application::factory()->for($user)))->create();
    $pdf = StoredFile::factory()->forGeneratedDocument($document, $user)->create();
    $untracked = "users/{$user->id}/stray/leftover.pdf";
    $othersFile = StoredFile::factory()->forMasterDocument(MasterDocument::factory()->for($other)->create())->create();
    foreach ([$original->path, $pdf->path, $untracked, $othersFile->path] as $path) {
        $disk->put($path, '%PDF');
    }

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull()
        ->and(StoredFile::query()->pluck('id')->all())->toBe([$othersFile->id]);
    $disk->assertMissing([$original->path, $pdf->path, $untracked]);
    $disk->assertExists($othersFile->path);
});
