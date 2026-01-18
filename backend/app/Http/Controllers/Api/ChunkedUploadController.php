<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CompleteUploadRequest;
use App\Http\Requests\InitializeUploadRequest;
use App\Http\Requests\UploadChunkRequest;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Product;
use App\Services\ChunkedUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends BaseApiController
{
    protected ChunkedUploadService $uploadService;

    public function __construct(ChunkedUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function initialize(InitializeUploadRequest $request)
    {
        try {
            $result = $this->uploadService->initUpload(
                $request->input('filename'),
                $request->input('total_chunks'),
                $request->input('file_size')
            );

            return $this->successResponse($result, 'Upload session initialized', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initialize upload: ' . $e->getMessage(), 500);
        }
    }

    public function uploadChunk(UploadChunkRequest $request, string $uploadId)
    {
        try {
            $chunkIndex = $request->input('chunk_index');
            $chunkFile = $request->file('chunk');

            $result = $this->uploadService->saveChunk(
                $uploadId,
                $chunkIndex,
                file_get_contents($chunkFile->getRealPath())
            );

            return $this->successResponse($result, 'Chunk uploaded');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload chunk: ' . $e->getMessage(), 500);
        }
    }

    public function getStatus(string $uploadId)
    {
        try {
            $status = $this->uploadService->getStatus($uploadId);

            if (!$status) {
                return $this->errorResponse('Upload session not found', 404);
            }

            return $this->successResponse($status);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get upload status: ' . $e->getMessage(), 500);
        }
    }

    public function complete(CompleteUploadRequest $request, string $uploadId)
    {
        try {
            $status = $this->uploadService->getStatus($uploadId);

            if (!$status) {
                return $this->errorResponse('Upload session not found', 404);
            }

            if (!$status['is_complete']) {
                return $this->errorResponse('Upload is not complete', 400);
            }

            $targetType = $request->input('target_type');
            $targetId = $request->input('target_id');


            $filename = Str::random(40) . '.' . pathinfo($status['filename'], PATHINFO_EXTENSION);
            
            if ($targetType === 'order_document') {
                $finalPath = 'orders/documents/' . $filename;
            } else {
                $finalPath = 'products/documents/' . $filename;
            }

            $this->uploadService->mergeChunks($uploadId, 'public/' . $finalPath);


            $fullPath = Storage::path('public/' . $finalPath);
            $mimeType = 'application/octet-stream';
            
            if (file_exists($fullPath)) {
                $detectedMime = mime_content_type($fullPath);
                if ($detectedMime !== false) {
                    $mimeType = $detectedMime;
                }
            }


            if ($targetType === 'order_document') {
                $order = Order::findOrFail($targetId);
                $document = OrderDocument::create([
                    'order_id' => $order->id,
                    'filename' => $filename,
                    'original_name' => $status['filename'],
                    'file_path' => $finalPath,
                    'file_size' => $status['file_size'],
                    'mime_type' => $mimeType,
                    'uploaded_by' => auth()->id(),
                ]);

                $fileUrl = asset('storage/' . $finalPath);
            } else {
                $product = Product::findOrFail($targetId);
                
                if ($product->document) {
                    Storage::disk('public')->delete($product->document);
                }

                $product->update(['document' => $finalPath]);
                $fileUrl = asset('storage/' . $finalPath);
            }


            $this->uploadService->cleanup($uploadId);

            return $this->successResponse([
                'file_path' => $finalPath,
                'url' => $fileUrl,
            ], 'Upload completed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to complete upload: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(string $uploadId)
    {
        try {
            $this->uploadService->cleanup($uploadId);
            return $this->successResponse(null, 'Upload cancelled');
        } catch (\Exception $e) {
            return $this->errorResponse('Could not cancel upload: ' . $e->getMessage(), 500);
        }
    }
}
