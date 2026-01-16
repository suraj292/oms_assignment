import apiClient from './client'

export interface InitializeUploadResponse {
    success: boolean
    data: {
        upload_id: string
        chunk_size: number
    }
}

export interface UploadChunkResponse {
    success: boolean
    data: {
        success: boolean
        received_chunks: number[]
        total_chunks: number
    }
}

export interface UploadStatusResponse {
    success: boolean
    data: {
        upload_id: string
        filename: string
        total_chunks: number
        received_chunks: number[]
        is_complete: boolean
        created_at: string
        updated_at: string
    }
}

export interface CompleteUploadResponse {
    success: boolean
    data: {
        file_path: string
        url: string
    }
    message: string
}

export const chunkedUploadsAPI = {
    initialize: (filename: string, totalChunks: number, fileSize: number) =>
        apiClient.post<InitializeUploadResponse>('/uploads/init', {
            filename,
            total_chunks: totalChunks,
            file_size: fileSize,
        }),

    uploadChunk: (uploadId: string, chunkIndex: number, chunk: Blob) => {
        const formData = new FormData()
        formData.append('chunk_index', chunkIndex.toString())
        formData.append('chunk', chunk)
        return apiClient.post<UploadChunkResponse>(`/uploads/${uploadId}/chunk`, formData)
    },

    getStatus: (uploadId: string) =>
        apiClient.get<UploadStatusResponse>(`/uploads/${uploadId}/status`),

    complete: (uploadId: string, targetType: string, targetId: number) =>
        apiClient.post<CompleteUploadResponse>(`/uploads/${uploadId}/complete`, {
            target_type: targetType,
            target_id: targetId,
        }),

    cancel: (uploadId: string) =>
        apiClient.delete(`/uploads/${uploadId}`),
}
