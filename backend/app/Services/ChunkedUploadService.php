<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadService
{
    private const CHUNK_STORAGE_PATH = 'chunks';
    private const METADATA_FILE = 'metadata.json';
    private const CHUNK_PREFIX = 'chunk_';
    private const CHUNK_EXTENSION = '.tmp';

    /**
     * Initialize a new chunked upload session
     */
    public function initializeUpload(string $filename, int $totalChunks, int $fileSize): array
    {
        $uploadId = (string) Str::uuid();
        $uploadPath = $this->getUploadPath($uploadId);

        // Create upload directory
        Storage::makeDirectory($uploadPath);

        // Create metadata
        $metadata = [
            'upload_id' => $uploadId,
            'filename' => $filename,
            'total_chunks' => $totalChunks,
            'file_size' => $fileSize,
            'received_chunks' => [],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->saveMetadata($uploadId, $metadata);

        return [
            'upload_id' => $uploadId,
            'chunk_size' => 1048576, // 1MB
        ];
    }

    /**
     * Store a chunk
     */
    public function storeChunk(string $uploadId, int $chunkIndex, $chunkData): array
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            throw new \Exception('Upload session not found');
        }

        // Validate chunk index
        if ($chunkIndex < 0 || $chunkIndex >= $metadata['total_chunks']) {
            throw new \Exception('Invalid chunk index');
        }

        // Store chunk
        $chunkPath = $this->getChunkPath($uploadId, $chunkIndex);
        Storage::put($chunkPath, $chunkData);

        // Update metadata
        if (!in_array($chunkIndex, $metadata['received_chunks'])) {
            $metadata['received_chunks'][] = $chunkIndex;
            sort($metadata['received_chunks']);
        }
        $metadata['updated_at'] = now()->toIso8601String();

        $this->saveMetadata($uploadId, $metadata);

        return [
            'success' => true,
            'received_chunks' => $metadata['received_chunks'],
            'total_chunks' => $metadata['total_chunks'],
        ];
    }

    /**
     * Get upload status
     */
    public function getUploadStatus(string $uploadId): ?array
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            return null;
        }

        return [
            'upload_id' => $metadata['upload_id'],
            'filename' => $metadata['filename'],
            'total_chunks' => $metadata['total_chunks'],
            'file_size' => $metadata['file_size'],
            'received_chunks' => $metadata['received_chunks'],
            'is_complete' => $this->isUploadComplete($uploadId, $metadata['total_chunks']),
            'created_at' => $metadata['created_at'],
            'updated_at' => $metadata['updated_at'],
        ];
    }

    /**
     * Check if all chunks have been received
     */
    public function isUploadComplete(string $uploadId, int $totalChunks): bool
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            return false;
        }

        return count($metadata['received_chunks']) === $totalChunks;
    }

    /**
     * Merge all chunks into final file
     */
    public function mergeChunks(string $uploadId, string $finalPath): bool
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            throw new \Exception('Upload session not found');
        }

        if (!$this->isUploadComplete($uploadId, $metadata['total_chunks'])) {
            throw new \Exception('Upload is not complete');
        }

        // Create final file
        $finalFullPath = Storage::path($finalPath);
        $finalDir = dirname($finalFullPath);

        if (!file_exists($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $finalFile = fopen($finalFullPath, 'wb');

        if (!$finalFile) {
            throw new \Exception('Failed to create final file');
        }

        // Merge chunks in order
        for ($i = 0; $i < $metadata['total_chunks']; $i++) {
            $chunkPath = $this->getChunkPath($uploadId, $i);
            $chunkFullPath = Storage::path($chunkPath);

            if (!file_exists($chunkFullPath)) {
                fclose($finalFile);
                throw new \Exception("Chunk $i is missing");
            }

            $chunkData = file_get_contents($chunkFullPath);
            fwrite($finalFile, $chunkData);
        }

        fclose($finalFile);

        // Verify file size
        $finalSize = filesize($finalFullPath);
        if ($finalSize !== $metadata['file_size']) {
            unlink($finalFullPath);
            throw new \Exception('Merged file size mismatch');
        }

        return true;
    }

    /**
     * Cleanup upload (remove all chunks and metadata)
     */
    public function cleanupUpload(string $uploadId): bool
    {
        $uploadPath = $this->getUploadPath($uploadId);
        return Storage::deleteDirectory($uploadPath);
    }

    /**
     * Cleanup stale uploads (older than specified hours)
     */
    public function cleanupStaleUploads(int $hours = 24): int
    {
        $cleaned = 0;
        $directories = Storage::directories(self::CHUNK_STORAGE_PATH);

        foreach ($directories as $dir) {
            $uploadId = basename($dir);
            $metadata = $this->getMetadata($uploadId);

            if (!$metadata) {
                // No metadata, delete directory
                Storage::deleteDirectory($dir);
                $cleaned++;
                continue;
            }

            $createdAt = new \DateTime($metadata['created_at']);
            $now = new \DateTime();
            $diff = $now->diff($createdAt);
            $hoursDiff = ($diff->days * 24) + $diff->h;

            if ($hoursDiff >= $hours) {
                Storage::deleteDirectory($dir);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Get upload directory path
     */
    private function getUploadPath(string $uploadId): string
    {
        return self::CHUNK_STORAGE_PATH . '/' . $uploadId;
    }

    /**
     * Get chunk file path
     */
    private function getChunkPath(string $uploadId, int $chunkIndex): string
    {
        return $this->getUploadPath($uploadId) . '/' . self::CHUNK_PREFIX . $chunkIndex . self::CHUNK_EXTENSION;
    }

    /**
     * Get metadata file path
     */
    private function getMetadataPath(string $uploadId): string
    {
        return $this->getUploadPath($uploadId) . '/' . self::METADATA_FILE;
    }

    /**
     * Get metadata
     */
    private function getMetadata(string $uploadId): ?array
    {
        $metadataPath = $this->getMetadataPath($uploadId);

        if (!Storage::exists($metadataPath)) {
            return null;
        }

        $json = Storage::get($metadataPath);
        return json_decode($json, true);
    }

    /**
     * Save metadata
     */
    private function saveMetadata(string $uploadId, array $metadata): void
    {
        $metadataPath = $this->getMetadataPath($uploadId);
        Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }
}
