<?php

namespace App\Support\MediaLibrary;

use App\Support\DocumentFileType;
use App\Support\ImageProcessor;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\RemoteFile;

/**
 * Every file added to the media library passes through here. Raster images
 * are resized and stripped of metadata before they are written to the disk,
 * so the uploaded original is never stored. Other files (documents) are added
 * unchanged, with a MIME type checked against their contents; collections
 * decide which MIME types they accept.
 */
class ImageProcessingFileAdder extends FileAdder
{
    public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
    {
        if ($this->file instanceof RemoteFile) {
            throw new LogicException('Media must be added from a local file so images can be processed before storage.');
        }

        if (! is_file($this->pathToFile)) {
            throw FileDoesNotExist::create($this->pathToFile);
        }

        $processor = app(ImageProcessor::class);
        $mimeType = (string) mime_content_type($this->pathToFile);

        if (! $processor->supports($mimeType)) {
            return $this->addDocument($collectionName, $diskName);
        }

        // Images have a lower limit than media-library.max_file_size, which covers documents.
        if (filesize($this->pathToFile) > (int) config('cms.images.max_upload_kb') * 1024) {
            throw FileIsTooBig::create($this->pathToFile);
        }

        $sourcePath = $this->pathToFile;
        $processedPath = $processor->process($sourcePath, $mimeType);

        // The stored file always carries the extension of its real format.
        $this->pathToFile = $processedPath;
        $this->fileSize = null;
        $this->fileName = pathinfo($this->fileName, PATHINFO_FILENAME).'.'.$processor->extensionFor($mimeType);

        if (! $this->preserveOriginal) {
            @unlink($sourcePath);
        }

        try {
            $media = parent::toMediaCollection($collectionName, $diskName);
        } catch (\Throwable $exception) {
            @unlink($processedPath);

            throw $exception;
        }

        // For an unsaved subject the parent copies the file once the subject
        // is created, so the processed copy must stay until then.
        if ($this->subject->exists && is_file($processedPath)) {
            @unlink($processedPath);
        }

        return $media;
    }

    /**
     * Non-images are stored unchanged. Their MIME type is set from the real
     * contents and extension together: a genuine document gets its proper
     * type (fileinfo reports some DOCX files as plain zip archives), anything
     * else gets a generic type that no collection accepts.
     */
    protected function addDocument(string $collectionName, string $diskName): Media
    {
        $mimeType = app(DocumentFileType::class)
            ->detect($this->pathToFile, pathinfo($this->fileName, PATHINFO_EXTENSION));

        $this->properties['mime_type'] = $mimeType ?? 'application/octet-stream';

        return parent::toMediaCollection($collectionName, $diskName);
    }
}
