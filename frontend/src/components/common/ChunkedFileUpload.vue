<template>
  <div class="chunked-file-upload">
    <!-- Resumable Upload Prompt -->
    <div v-if="resumableUpload && status === 'idle'" class="resumable-upload-prompt">
      <div class="prompt-content">
        <div class="prompt-icon">⏸️</div>
        <div class="prompt-text">
          <h4>Resume Previous Upload?</h4>
          <p>{{ resumableUpload.fileName }} ({{ Math.round(resumableUpload.uploadedChunks.length / resumableUpload.totalChunks * 100) }}% uploaded)</p>
          <p class="prompt-hint">You'll need to select the same file to continue</p>
        </div>
      </div>
      <div class="prompt-actions">
        <button @click="resumePreviousUpload" class="btn btn-primary">
          Resume Upload
        </button>
        <button @click="discardPreviousUpload" class="btn btn-secondary">
          Discard & Upload New
        </button>
      </div>
    </div>

    <!-- Completed Upload Display -->
    <div v-else-if="status === 'completed' && completedFileUrl" class="upload-completed">
      <div class="completed-icon">✓</div>
      <div class="completed-info">
        <div class="completed-filename">{{ selectedFile?.name }}</div>
        <div class="completed-meta">
          <span class="completed-size">{{ formatFileSize(selectedFile?.size || 0) }}</span>
          <span class="completed-status">Upload complete</span>
        </div>
      </div>
      <a :href="completedFileUrl" download class="btn btn-primary">
        Download
      </a>
    </div>

    <!-- Upload Idle State -->
    <div v-else-if="status === 'idle'" class="upload-idle">
      <input
        ref="fileInput"
        type="file"
        @change="handleFileSelect"
        :accept="accept"
        class="file-input"
      />
      <button @click="triggerFileSelect" class="btn btn-primary">
        {{ buttonText }}
      </button>
      <p v-if="maxFileSize" class="file-size-hint">
        Max file size: {{ formatFileSize(maxFileSize) }}
      </p>
    </div>

    <!-- Upload Progress -->
    <div v-else class="upload-progress-container">
      <div class="upload-info">
        <div class="file-name">{{ selectedFile?.name }}</div>
        <div class="file-size">{{ formatFileSize(selectedFile?.size || 0) }}</div>
      </div>

      <div class="progress-bar-container">
        <div class="progress-bar">
          <div class="progress-fill" :style="{ width: progress.percentage + '%' }"></div>
        </div>
        <div class="progress-text">
          {{ progress.percentage }}% ({{ progress.uploadedChunks }} / {{ progress.totalChunks }} chunks)
        </div>
      </div>

      <div class="upload-status">
        <span :class="['status-badge', statusClass]">{{ statusText }}</span>
      </div>

      <div v-if="error" class="error-message">
        {{ error }}
      </div>

      <div class="upload-actions">
        <button
          v-if="status === 'uploading'"
          @click="pauseUpload"
          class="btn btn-secondary"
        >
          Pause
        </button>
        <button
          v-if="status === 'paused'"
          @click="resumeUpload"
          class="btn btn-primary"
        >
          Resume
        </button>
        <button
          v-if="status !== 'completed' && status !== 'cancelled'"
          @click="cancelUpload"
          class="btn btn-danger"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ChunkedUploadManager, type UploadStatus, type UploadProgress, type UploadState } from '@/services/chunkedUploadService'
import { formatFileSize } from '@/utils/fileChunker'

interface Props {
  targetType: 'order_document' | 'product_document'
  targetId: number
  maxFileSize?: number
  chunkSize?: number
  accept?: string
  buttonText?: string
}

const props = withDefaults(defineProps<Props>(), {
  maxFileSize: 5368709120, // 5GB
  chunkSize: 1048576, // 1MB
  accept: '*/*',
  buttonText: 'Select File',
})

const emit = defineEmits<{
  'upload-complete': [url: string]
  'upload-error': [error: Error]
  'upload-cancelled': []
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const uploadManager = ref<ChunkedUploadManager | null>(null)
const status = ref<UploadStatus>('idle')
const progress = ref<UploadProgress>({
  uploadedChunks: 0,
  totalChunks: 0,
  percentage: 0,
  uploadedBytes: 0,
  totalBytes: 0,
})
const error = ref<string>('')
const resumableUpload = ref<UploadState | null>(null)
const completedFileUrl = ref<string>('')

const statusClass = computed(() => {
  return {
    'status-idle': status.value === 'idle',
    'status-uploading': status.value === 'uploading' || status.value === 'initializing',
    'status-paused': status.value === 'paused',
    'status-completing': status.value === 'completing',
    'status-completed': status.value === 'completed',
    'status-error': status.value === 'error',
    'status-cancelled': status.value === 'cancelled',
  }
})

const statusText = computed(() => {
  const texts: Record<UploadStatus, string> = {
    idle: 'Ready',
    initializing: 'Initializing...',
    uploading: 'Uploading...',
    paused: 'Paused',
    completing: 'Finalizing...',
    completed: 'Completed',
    error: 'Error',
    cancelled: 'Cancelled',
  }
  return texts[status.value]
})

function triggerFileSelect() {
  fileInput.value?.click()
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (!file) return

  // Validate file size
  if (props.maxFileSize && file.size > props.maxFileSize) {
    error.value = `File size exceeds maximum allowed (${formatFileSize(props.maxFileSize)})`
    return
  }

  selectedFile.value = file
  error.value = ''
  startUpload(file)
}

function startUpload(file: File) {
  uploadManager.value = new ChunkedUploadManager(
    file,
    props.targetType,
    props.targetId,
    props.chunkSize
  )

  uploadManager.value.onProgress = (prog) => {
    progress.value = prog
  }

  uploadManager.value.onStatusChange = (stat) => {
    status.value = stat
  }

  uploadManager.value.onComplete = (url) => {
    completedFileUrl.value = url
    emit('upload-complete', url)
  }

  uploadManager.value.onError = (err) => {
    error.value = err.message
    emit('upload-error', err)
  }

  // Check if this is a resumed upload (status will be 'paused' from restored state)
  const currentStatus = uploadManager.value.getStatus()
  
  if (currentStatus === 'paused' || currentStatus === 'cancelled') {
    // Don't auto-start, wait for user to click Resume
    // The status is already set from restoreState()
  } else {
    // New upload - start immediately
    uploadManager.value.start()
  }
}

function pauseUpload() {
  uploadManager.value?.pause()
}

function resumeUpload() {
  uploadManager.value?.resume()
}

function cancelUpload() {
  uploadManager.value?.cancel()
  emit('upload-cancelled')
}

function reset() {
  selectedFile.value = null
  uploadManager.value = null
  status.value = 'idle'
  progress.value = {
    uploadedChunks: 0,
    totalChunks: 0,
    percentage: 0,
    uploadedBytes: 0,
    totalBytes: 0,
  }
  error.value = ''
  resumableUpload.value = null
  
  // Check for new resumable uploads after reset
  checkForResumableUpload()
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

// Check for resumable uploads on mount
function checkForResumableUpload() {
  // Check all possible resumable uploads in localStorage
  const keys = Object.keys(localStorage)
  const uploadKey = keys.find(key => 
    key.startsWith(`chunked_upload_${props.targetType}_${props.targetId}_`)
  )
  
  if (uploadKey) {
    const fileName = uploadKey.replace(`chunked_upload_${props.targetType}_${props.targetId}_`, '')
    const uploadInfo = ChunkedUploadManager.getResumableUploadInfo(fileName, props.targetType, props.targetId)
    
    if (uploadInfo && (uploadInfo.status === 'paused' || uploadInfo.status === 'cancelled')) {
      resumableUpload.value = uploadInfo
    }
  }
}

// Resume previous upload
async function resumePreviousUpload() {
  if (!resumableUpload.value) return
  
  // Trigger file selection - user must select the same file
  fileInput.value?.click()
  
  // Store resumable upload info temporarily
  const tempResumableUpload = resumableUpload.value
  resumableUpload.value = null
  
  // Wait for file selection
  const handleResumeFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0]
    
    if (!file) {
      // User cancelled - restore resume prompt
      resumableUpload.value = tempResumableUpload
      return
    }
    
    // Validate file matches
    if (file.name !== tempResumableUpload.fileName || file.size !== tempResumableUpload.fileSize) {
      error.value = `Please select the same file: ${tempResumableUpload.fileName} (${formatFileSize(tempResumableUpload.fileSize)})`
      resumableUpload.value = tempResumableUpload
      return
    }
    
    // File matches - start upload (will auto-restore state)
    selectedFile.value = file
    error.value = ''
    startUpload(file)
    
    // Remove this one-time listener
    fileInput.value?.removeEventListener('change', handleResumeFileSelect)
  }
  
  // Add one-time listener for file selection
  fileInput.value?.addEventListener('change', handleResumeFileSelect, { once: true })
}

// Discard previous upload
function discardPreviousUpload() {
  if (!resumableUpload.value) return
  
  // Clear the saved state
  const storageKey = `chunked_upload_${props.targetType}_${props.targetId}_${resumableUpload.value.fileName}`
  localStorage.removeItem(storageKey)
  
  resumableUpload.value = null
}

onMounted(() => {
  checkForResumableUpload()
})
</script>

<style scoped>
.chunked-file-upload {
  padding: 1.5rem;
  border: 2px dashed var(--color-gray-300);
  border-radius: var(--radius-lg);
  background: var(--color-gray-50);
}

.upload-idle {
  text-align: center;
}

.file-input {
  display: none;
}

.file-size-hint {
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: var(--color-gray-600);
}

.upload-progress-container {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.upload-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.file-name {
  font-weight: 500;
  color: var(--color-gray-900);
}

.file-size {
  font-size: 0.875rem;
  color: var(--color-gray-600);
}

.progress-bar-container {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.progress-bar {
  height: 1.5rem;
  background: var(--color-gray-200);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--color-primary);
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 0.875rem;
  color: var(--color-gray-700);
  text-align: center;
}

.upload-status {
  text-align: center;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 500;
}

.status-uploading,
.status-initializing,
.status-completing {
  background: #dbeafe;
  color: #1e40af;
}

.status-paused {
  background: #fef3c7;
  color: #92400e;
}

.status-completed {
  background: #d1fae5;
  color: #065f46;
}

.status-error {
  background: #fee2e2;
  color: #991b1b;
}

.status-cancelled {
  background: var(--color-gray-100);
  color: var(--color-gray-600);
}

/* Resumable Upload Prompt */
.resumable-upload-prompt {
  border: 2px dashed var(--color-primary);
  border-radius: var(--radius-lg);
  padding: 2rem;
  background: rgba(99, 102, 241, 0.05);
}

.prompt-content {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.prompt-icon {
  font-size: 3rem;
}

.prompt-text h4 {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-gray-900);
}

.prompt-text p {
  margin: 0;
  color: var(--color-gray-600);
  font-size: 0.9375rem;
}

.prompt-hint {
  font-size: 0.8125rem !important;
  color: var(--color-gray-500) !important;
  font-style: italic;
  margin-top: 0.25rem !important;
}

.prompt-actions {
  display: flex;
  gap: 1rem;
}

/* Completed Upload Display */
.upload-completed {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  background: #f0fdf4;
  border: 2px solid #86efac;
  border-radius: var(--radius-lg);
}

.completed-icon {
  font-size: 2.5rem;
  color: var(--color-success);
  flex-shrink: 0;
}

.completed-info {
  flex: 1;
  min-width: 0;
}

.completed-filename {
  font-weight: 600;
  font-size: 1rem;
  color: var(--color-gray-900);
  margin-bottom: 0.25rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.completed-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  color: var(--color-gray-600);
}

.completed-size::after {
  content: '•';
  margin-left: 1rem;
}

.completed-status {
  color: var(--color-success);
  font-weight: 500;
}

.error-message {
  padding: 0.75rem;
  background: #fee2e2;
  border: 1px solid #fecaca;
  border-radius: var(--radius-md);
  color: #991b1b;
  font-size: 0.875rem;
}

.upload-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
}
</style>
