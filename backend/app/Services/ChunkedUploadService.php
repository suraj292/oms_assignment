<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadService
{

    private const CHUNKS_DIR = 'chunks';
    
    public function initUpload(string $filename, int $totalChunks, int $fileSize): array
    {
        $uploadId = (string) Str::uuid();
        $uploadPath = $this->getUploadPath($uploadId);

        Storage::makeDirectory($uploadPath);


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
            'chunk_size' => 1048576, // 1MB chunks
        ];
    }

    public function saveChunk(string $uploadId, int $chunkIndex, $chunkData): array
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            throw new \Exception('Upload session not found');
        }

        if ($chunkIndex < 0 || $chunkIndex >= $metadata['total_chunks']) {
            throw new \Exception('Invalid chunk index');
        }

        $chunkPath = $this->getChunkPath($uploadId, $chunkIndex);
        Storage::put($chunkPath, $chunkData);


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

    public function getStatus(string $uploadId): ?array
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
            'is_complete' => $this->isComplete($uploadId, $metadata['total_chunks']),
            'created_at' => $metadata['created_at'],
            'updated_at' => $metadata['updated_at'],
        ];
    }

    public function isComplete(string $uploadId, int $totalChunks): bool
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            return false;
        }

        return count($metadata['received_chunks']) === $totalChunks;
    }

    public function mergeChunks(string $uploadId, string $finalPath): bool
    {
        $metadata = $this->getMetadata($uploadId);

        if (!$metadata) {
            throw new \Exception('Upload session not found');
        }

        if (!$this->isComplete($uploadId, $metadata['total_chunks'])) {
            throw new \Exception('Upload not complete yet');
        }

        $finalFullPath = Storage::path($finalPath);
        $finalDir = dirname($finalFullPath);

        if (!file_exists($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $finalFile = fopen($finalFullPath, 'wb');

        if (!$finalFile) {
            throw new \Exception('Could not create final file');
        }

        // stitch all the chunks together in order
        for ($i = 0; $i < $metadata['total_chunks']; $i++) {
            $chunkPath = $this->getChunkPath($uploadId, $i);
            $chunkFullPath = Storage::path($chunkPath);

            if (!file_exists($chunkFullPath)) {
                fclose($finalFile);
                throw new \Exception("Missing chunk $i");
            }

            $chunkData = file_get_contents($chunkFullPath);
            fwrite($finalFile, $chunkData);
        }

        fclose($finalFile);

        // make sure the final file size matches what we expected
        $finalSize = filesize($finalFullPath);
        if ($finalSize !== $metadata['file_size']) {
            unlink($finalFullPath);
            throw new \Exception('File size mismatch after merge');
        }

        return true;
    }

    public function cleanup(string $uploadId): bool
    {
        $uploadPath = $this->getUploadPath($uploadId);
        return Storage::deleteDirectory($uploadPath);
    }

    // clean up old uploads that have been sitting around too long
    public function cleanupOldUploads(int $hours = 24): int
    {
        $cleaned = 0;
        $directories = Storage::directories(self::CHUNKS_DIR);

        foreach ($directories as $dir) {
            $uploadId = basename($dir);
            $metadata = $this->getMetadata($uploadId);

            if (!$metadata) {
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

    private function getUploadPath(string $uploadId): string
    {
        return self::CHUNKS_DIR . '/' . $uploadId;
    }

    private function getChunkPath(string $uploadId, int $chunkIndex): string
    {
        return $this->getUploadPath($uploadId) . '/chunk_' . $chunkIndex . '.tmp';
    }

    private function getMetadataPath(string $uploadId): string
    {
        return $this->getUploadPath($uploadId) . '/metadata.json';
    }

    private function getMetadata(string $uploadId): ?array
    {
        $metadataPath = $this->getMetadataPath($uploadId);

        if (!Storage::exists($metadataPath)) {
            return null;
        }

        $json = Storage::get($metadataPath);
        return json_decode($json, true);
    }

    private function saveMetadata(string $uploadId, array $metadata): void
    {
        $metadataPath = $this->getMetadataPath($uploadId);
        Storage::put($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }
}
