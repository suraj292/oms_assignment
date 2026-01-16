<template>
  <div class="chunked-file-upload">
    <div v-if="status === 'idle'" class="upload-idle">
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
        <button
          v-if="status === 'completed' || status === 'cancelled'"
          @click="reset"
          class="btn btn-secondary"
        >
          Upload Another
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ChunkedUploadManager, type UploadStatus, type UploadProgress } from '@/services/chunkedUploadService'
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
    emit('upload-complete', url)
  }

  uploadManager.value.onError = (err) => {
    error.value = err.message
    emit('upload-error', err)
  }

  uploadManager.value.start()
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
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}
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
  background: var(--color-gray-200);
  color: var(--color-gray-700);
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
