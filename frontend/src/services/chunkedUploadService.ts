import { chunkedUploadsAPI } from '@/api/chunkedUploads'
import { chunkFile } from '@/utils/fileChunker'

export type UploadStatus = 'idle' | 'initializing' | 'uploading' | 'paused' | 'completing' | 'completed' | 'error' | 'cancelled'

export interface UploadProgress {
    uploadedChunks: number
    totalChunks: number
    percentage: number
    uploadedBytes: number
    totalBytes: number
}

export class ChunkedUploadManager {
    private file: File
    private uploadId: string | null = null
    private chunks: Blob[] = []
    private uploadedChunks: Set<number> = new Set()
    private chunkSize: number
    private status: UploadStatus = 'idle'
    private targetType: string
    private targetId: number

    // Callbacks
    public onProgress?: (progress: UploadProgress) => void
    public onStatusChange?: (status: UploadStatus) => void
    public onComplete?: (fileUrl: string) => void
    public onError?: (error: Error) => void

    constructor(
        file: File,
        targetType: string,
        targetId: number,
        chunkSize: number = 1048576 // 1MB default
    ) {
        this.file = file
        this.targetType = targetType
        this.targetId = targetId
        this.chunkSize = chunkSize
        this.chunks = chunkFile(file, chunkSize)
    }

    /**
     * Start the upload process
     */
    async start(): Promise<void> {
        try {
            this.setStatus('initializing')

            // Initialize upload session
            const initResponse = await chunkedUploadsAPI.initialize(
                this.file.name,
                this.chunks.length,
                this.file.size
            )

            this.uploadId = initResponse.data.data.upload_id

            // Check if there are already uploaded chunks (resume)
            await this.checkExistingChunks()

            // Start uploading
            this.setStatus('uploading')
            await this.uploadRemainingChunks()

            // Complete upload
            await this.completeUpload()

        } catch (error) {
            this.setStatus('error')
            this.onError?.(error as Error)
        }
    }

    /**
     * Check for existing chunks (for resume)
     */
    private async checkExistingChunks(): Promise<void> {
        if (!this.uploadId) return

        const statusResponse = await chunkedUploadsAPI.getStatus(this.uploadId)
        const receivedChunks = statusResponse.data.data.received_chunks

        receivedChunks.forEach(index => {
            this.uploadedChunks.add(index)
        })

        this.updateProgress()
    }

    /**
     * Upload all remaining chunks
     */
    private async uploadRemainingChunks(): Promise<void> {
        for (let i = 0; i < this.chunks.length; i++) {
            if (this.status === 'paused' || this.status === 'cancelled') {
                return
            }

            if (this.uploadedChunks.has(i)) {
                continue // Skip already uploaded chunks
            }

            await this.uploadChunkWithRetry(i)
        }
    }

    /**
     * Upload a single chunk with retry logic
     */
    private async uploadChunkWithRetry(index: number, maxRetries: number = 3): Promise<void> {
        if (!this.uploadId) throw new Error('Upload not initialized')

        const chunk = this.chunks[index]
        if (!chunk) throw new Error(`Chunk ${index} not found`)

        for (let attempt = 0; attempt < maxRetries; attempt++) {
            try {
                await chunkedUploadsAPI.uploadChunk(this.uploadId, index, chunk)

                this.uploadedChunks.add(index)
                this.updateProgress()
                return

            } catch (error) {
                if (attempt === maxRetries - 1) {
                    throw new Error(`Failed to upload chunk ${index} after ${maxRetries} attempts`)
                }

                // Exponential backoff
                await this.sleep(1000 * Math.pow(2, attempt))
            }
        }
    }

    /**
     * Complete the upload and merge chunks
     */
    private async completeUpload(): Promise<void> {
        if (!this.uploadId) throw new Error('Upload not initialized')

        this.setStatus('completing')

        const completeResponse = await chunkedUploadsAPI.complete(
            this.uploadId,
            this.targetType,
            this.targetId
        )

        this.setStatus('completed')
        this.onComplete?.(completeResponse.data.data.url)
    }

    /**
     * Pause the upload
     */
    pause(): void {
        if (this.status === 'uploading') {
            this.setStatus('paused')
        }
    }

    /**
     * Resume the upload
     */
    async resume(): Promise<void> {
        if (this.status === 'paused') {
            this.setStatus('uploading')
            await this.uploadRemainingChunks()
            await this.completeUpload()
        }
    }

    /**
     * Cancel the upload
     */
    async cancel(): Promise<void> {
        this.setStatus('cancelled')

        if (this.uploadId) {
            try {
                await chunkedUploadsAPI.cancel(this.uploadId)
            } catch (error) {
                console.error('Failed to cancel upload:', error)
            }
        }
    }

    /**
     * Get current progress
     */
    getProgress(): UploadProgress {
        const uploadedChunks = this.uploadedChunks.size
        const totalChunks = this.chunks.length
        const percentage = totalChunks > 0 ? Math.round((uploadedChunks / totalChunks) * 100) : 0
        const uploadedBytes = uploadedChunks * this.chunkSize
        const totalBytes = this.file.size

        return {
            uploadedChunks,
            totalChunks,
            percentage,
            uploadedBytes: Math.min(uploadedBytes, totalBytes),
            totalBytes,
        }
    }

    /**
     * Get current status
     */
    getStatus(): UploadStatus {
        return this.status
    }

    /**
     * Update progress and notify callback
     */
    private updateProgress(): void {
        this.onProgress?.(this.getProgress())
    }

    /**
     * Set status and notify callback
     */
    private setStatus(status: UploadStatus): void {
        this.status = status
        this.onStatusChange?.(status)
    }

    /**
     * Sleep utility
     */
    private sleep(ms: number): Promise<void> {
        return new Promise(resolve => setTimeout(resolve, ms))
    }
}
