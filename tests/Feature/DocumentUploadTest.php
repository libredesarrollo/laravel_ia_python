<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('document uploads into the knowledge base', function () {
    beforeEach(function () {
        Storage::fake();
    });

    it('stores an uploaded document and registers a pending record', function () {
        $file = UploadedFile::fake()->create(
            'política_interna.pdf',
            32,
            'application/pdf',
        );

        $originalDisk = Storage::disk('local');

        $this->post(route('admin.documents.store'), ['document' => $file])
            ->assertRedirect(route('admin.documents.index'));

        $document = Document::query()->sole();

        expect($document->title)->toBe('política_interna.pdf')
            ->and($document->mime_type)->toBe('application/pdf')
            ->and($document->status)->toBe(DocumentStatus::Pending);

        $originalDisk->assertExists($document->file_path);
    });

    it('uploads only permitted formats', function () {
        $badFile = UploadedFile::fake()->create('exploit.exe', 4);

        $this->post(route('admin.documents.store'), ['document' => $badFile])
            ->assertSessionHasErrors('document');
    });

    it('rejects the upload when no file is provided', function () {
        $this->post(route('admin.documents.store'), [])
            ->assertSessionHasErrors('document');
    });
});
