<?php

use App\Models\AiCredential;
use App\Models\Application;
use App\Models\GeneratedAnswer;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

dataset('owned resources', [
    'application view' => [fn (User $owner) => Application::factory()->for($owner)->create(), 'view'],
    'application update' => [fn (User $owner) => Application::factory()->for($owner)->create(), 'update'],
    'application delete' => [fn (User $owner) => Application::factory()->for($owner)->create(), 'delete'],
    'application generate' => [fn (User $owner) => Application::factory()->for($owner)->create(), 'generate'],
    'generation view' => [fn (User $owner) => Generation::factory()->for(Application::factory()->for($owner))->create(), 'view'],
    'document view' => [fn (User $owner) => GeneratedDocument::factory()->for(Generation::factory()->for(Application::factory()->for($owner)))->create(), 'view'],
    'document update' => [fn (User $owner) => GeneratedDocument::factory()->for(Generation::factory()->for(Application::factory()->for($owner)))->create(), 'update'],
    'answer update' => [fn (User $owner) => GeneratedAnswer::factory()->for(Generation::factory()->for(Application::factory()->for($owner)))->create(), 'update'],
    'credential update' => [fn (User $owner) => AiCredential::factory()->for($owner)->create(), 'update'],
    'credential delete' => [fn (User $owner) => AiCredential::factory()->for($owner)->create(), 'delete'],
    'file view' => [fn (User $owner) => StoredFile::factory()->for($owner)->create(), 'view'],
]);

it('lets the owner act on their own resource', function (Closure $make, string $ability): void {
    $owner = User::factory()->create();

    expect(Gate::forUser($owner)->inspect($ability, $make($owner))->allowed())->toBeTrue();
})->with('owned resources');

it("hides another user's resource as not found", function (Closure $make, string $ability): void {
    $resource = $make(User::factory()->create());

    $response = Gate::forUser(User::factory()->create())->inspect($ability, $resource);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
})->with('owned resources');
