<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderDocumentResource;
use App\Models\Order;
use App\Models\OrderDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderDocumentController extends BaseApiController
{
    /**
     * Upload document to order
     */
    public function store(Request $request, Order $order)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        try {
            $file = $request->file('file');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('orders/documents', $filename, 'public');

            $document = OrderDocument::create([
                'order_id' => $order->id,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);

            return $this->successResponse(
                new OrderDocumentResource($document),
                'Document uploaded successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete order document
     */
    public function destroy(Order $order, OrderDocument $document)
    {

        if ($document->order_id !== $order->id) {
            return $this->errorResponse('Document not found', 404);
        }

        try {

            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $document->delete();

            return $this->successResponse(null, 'Document deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete document: ' . $e->getMessage(), 500);
        }
    }
}
