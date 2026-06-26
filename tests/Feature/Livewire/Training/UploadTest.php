<?php

use App\Enums\DocumentStatus;
use App\Models\Agent;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->user = User::factory()->create();
    $this->organization = $this->user->currentOrganization;
    $this->actingAs($this->user);

    $this->agent = Agent::factory()->for($this->organization)->create();
});

it('accepts each supported format', function (string $filename, string $extension) {
    Livewire::test('pages::training.upload', ['agent' => $this->agent])
        ->set('file', UploadedFile::fake()->create($filename, 50))
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::where('agent_id', $this->agent->id)->where('format', $extension)->exists())->toBeTrue();
})->with([
    ['manual.pdf', 'pdf'],
    ['policy.docx', 'docx'],
    ['notes.txt', 'txt'],
    ['readme.md', 'md'],
]);

it('rejects unsupported types', function () {
    Livewire::test('pages::training.upload', ['agent' => $this->agent])
        ->set('file', UploadedFile::fake()->create('malware.exe', 50))
        ->call('save')
        ->assertHasErrors(['file']);

    expect(Document::count())->toBe(0);
});

it('creates a document scoped to the agent and organization', function () {
    Livewire::test('pages::training.upload', ['agent' => $this->agent])
        ->set('file', UploadedFile::fake()->create('manual.pdf', 50))
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::sole();

    expect($document->agent_id)->toBe($this->agent->id)
        ->and($document->organization_id)->toBe($this->organization->id)
        ->and($document->status)->toBe(DocumentStatus::Uploaded)
        ->and($document->name)->toBe('manual.pdf');
});

it('stores the raw material', function () {
    Livewire::test('pages::training.upload', ['agent' => $this->agent])
        ->set('file', UploadedFile::fake()->create('manual.pdf', 50))
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::sole();

    Storage::disk($document->disk)->assertExists($document->path);
    expect($document->size)->toBeGreaterThan(0);
});

it('does not show documents from another organization', function () {
    $other = Organization::factory()->create();
    $foreignDoc = Document::factory()->for($other)->create();

    expect(Document::find($foreignDoc->id))->toBeNull()
        ->and(Document::count())->toBe(0);
});
