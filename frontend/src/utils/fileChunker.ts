/**
 * Split a file into chunks
 */
export function chunkFile(file: File, chunkSize: number = 1048576): Blob[] {
    const chunks: Blob[] = []
    let offset = 0

    while (offset < file.size) {
        const chunk = file.slice(offset, offset + chunkSize)
        chunks.push(chunk)
        offset += chunkSize
    }

    return chunks
}

/**
 * Calculate total number of chunks needed
 */
export function calculateTotalChunks(fileSize: number, chunkSize: number = 1048576): number {
    return Math.ceil(fileSize / chunkSize)
}

/**
 * Format file size for display
 */
export function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes'

    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))

    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}
