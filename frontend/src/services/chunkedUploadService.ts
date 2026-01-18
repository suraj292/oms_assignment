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

export interface UploadState {
    uploadId: string
    fileName: string
    fileSize: number
    fileType: string
    totalChunks: number
    uploadedChunks: number[]
    targetType: string
    targetId: number
    chunkSize: number
    status: UploadStatus
    timestamp: number
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
    private storageKey: string

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
        this.storageKey = `chunked_upload_${targetType}_${targetId}_${file.name}`

        // Try to restore previous upload state
        this.restoreState()
    }

    /**
     * Start the upload process
     */
    async start(): Promise<void> {
        try {
            // If we have an uploadId from restored state, skip initialization
            if (!this.uploadId) {
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
            } else {
                // Resuming from saved state - just check existing chunks
                await this.checkExistingChunks()
            }

            // Start uploading
            this.setStatus('uploading')
            await this.uploadRemainingChunks()

            // Only complete if not paused or cancelled
            if (this.status !== 'paused' && this.status !== 'cancelled') {
                await this.completeUpload()
            }

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
        this.saveState() // Save state after checking existing chunks
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
            this.saveState() // Save state after each chunk
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
        this.clearState() // Clear state after successful completion
        this.onComplete?.(completeResponse.data.data.url)
    }

    /**
     * Pause the upload
     */
    pause(): void {
        if (this.status === 'uploading') {
            this.setStatus('paused')
            this.saveState() // Save state when paused
        }
    }

    /**
     * Resume the upload
     */
    async resume(): Promise<void> {
        if (this.status === 'paused') {
            this.setStatus('uploading')
            await this.uploadRemainingChunks()

            // Only complete if not paused or cancelled again
            if (this.status !== 'paused' && this.status !== 'cancelled') {
                await this.completeUpload()
            }
        }
    }

    /**
     * Cancel the upload
     */
    async cancel(): Promise<void> {
        this.setStatus('cancelled')
        this.saveState() // Save cancelled state so user can resume later

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

    /**
     * Save upload state to localStorage
     */
    private saveState(): void {
        if (!this.uploadId) return

        const state: UploadState = {
            uploadId: this.uploadId,
            fileName: this.file.name,
            fileSize: this.file.size,
            fileType: this.file.type,
            totalChunks: this.chunks.length,
            uploadedChunks: Array.from(this.uploadedChunks),
            targetType: this.targetType,
            targetId: this.targetId,
            chunkSize: this.chunkSize,
            status: this.status,
            timestamp: Date.now()
        }

        try {
            localStorage.setItem(this.storageKey, JSON.stringify(state))
        } catch (error) {
            console.error('Failed to save upload state:', error)
        }
    }

    /**
     * Restore upload state from localStorage
     */
    private restoreState(): void {
        try {
            const savedState = localStorage.getItem(this.storageKey)
            if (!savedState) return

            const state: UploadState = JSON.parse(savedState)

            // Only restore if file matches and upload was in progress
            if (
                state.fileName === this.file.name &&
                state.fileSize === this.file.size &&
                (state.status === 'paused' || state.status === 'cancelled' || state.status === 'uploading')
            ) {
                this.uploadId = state.uploadId
                this.uploadedChunks = new Set(state.uploadedChunks)
                this.setStatus(state.status === 'uploading' ? 'paused' : state.status)
                this.updateProgress()
            }
        } catch (error) {
            console.error('Failed to restore upload state:', error)
        }
    }

    /**
     * Clear upload state from localStorage
     */
    private clearState(): void {
        try {
            localStorage.removeItem(this.storageKey)
        } catch (error) {
            console.error('Failed to clear upload state:', error)
        }
    }

    /**
     * Check if there's a resumable upload for this file
     */
    static hasResumableUpload(fileName: string, targetType: string, targetId: number): boolean {
        const storageKey = `chunked_upload_${targetType}_${targetId}_${fileName}`
        const savedState = localStorage.getItem(storageKey)

        if (!savedState) return false

        try {
            const state: UploadState = JSON.parse(savedState)
            return state.status === 'paused' || state.status === 'cancelled'
        } catch {
            return false
        }
    }

    /**
     * Get resumable upload info
     */
    static getResumableUploadInfo(fileName: string, targetType: string, targetId: number): UploadState | null {
        const storageKey = `chunked_upload_${targetType}_${targetId}_${fileName}`
        const savedState = localStorage.getItem(storageKey)

        if (!savedState) return null

        try {
            return JSON.parse(savedState)
        } catch {
            return null
        }
    }
}
