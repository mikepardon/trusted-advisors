<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Sound Assets</h2>
    </div>

    <p class="page-desc">
      Upload sound files to S3/MinIO. Sounds without an uploaded file use the local default.
    </p>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="sounds-list">
      <div v-for="sound in sounds" :key="sound.id" class="sound-row">
        <div class="sound-info">
          <span class="sound-label">{{ sound.label }}</span>
          <span class="sound-key">{{ sound.key }}</span>
          <span class="sound-category" :class="'cat-' + sound.category">{{ sound.category }}</span>
        </div>

        <div class="sound-actions">
          <span v-if="sound.url" class="sound-status uploaded">Uploaded</span>
          <span v-else class="sound-status default">Default</span>

          <button v-if="sound.url" class="btn-sm" @click="preview(sound)" title="Play">
            &#9654;
          </button>

          <label class="btn-sm upload-label">
            Upload
            <input
              type="file"
              accept="audio/*"
              class="hidden-input"
              @change="uploadFile(sound, $event)"
            />
          </label>
        </div>

        <div v-if="uploading === sound.key" class="upload-progress">Uploading...</div>
        <div v-if="uploadError === sound.key" class="upload-error">Upload failed. Check S3 config.</div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminSounds',
  data() {
    return {
      sounds: [],
      loading: true,
      uploading: null,
      uploadError: null,
      previewAudio: null,
    };
  },
  async mounted() {
    await this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const res = await axios.get('/api/admin/sound-assets');
        this.sounds = res.data;
      } catch {
        this.sounds = [];
      }
      this.loading = false;
    },
    preview(sound) {
      if (this.previewAudio) {
        this.previewAudio.pause();
      }
      this.previewAudio = new Audio(sound.url);
      this.previewAudio.play().catch(() => {});
    },
    async uploadFile(sound, event) {
      const file = event.target.files[0];
      if (!file) return;

      this.uploading = sound.key;
      this.uploadError = null;

      const formData = new FormData();
      formData.append('file', file);

      try {
        await axios.post(`/api/admin/sound-assets/${sound.key}/upload`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        await this.fetch();
      } catch {
        this.uploadError = sound.key;
      }
      this.uploading = null;
      event.target.value = '';
    },
  },
};
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
}

.page-desc {
  color: var(--text-secondary);
  font-size: 0.9rem;
  font-style: italic;
  margin-bottom: 20px;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

.sounds-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sound-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  flex-wrap: wrap;
  gap: 8px;
}

.sound-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sound-label {
  color: var(--text-bright);
  font-weight: 600;
  font-size: 0.95rem;
}

.sound-key {
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-family: monospace;
}

.sound-category {
  font-size: 0.7rem;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.cat-ui {
  background: rgba(67, 160, 212, 0.15);
  color: #5cb8e0;
  border: 1px solid rgba(67, 160, 212, 0.3);
}

.cat-actions {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  border: 1px solid rgba(212, 168, 67, 0.3);
}

.sound-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sound-status {
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 4px;
}

.sound-status.uploaded {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.sound-status.default {
  background: rgba(100, 80, 60, 0.3);
  color: var(--text-secondary);
}

.btn-sm {
  padding: 5px 12px;
  font-size: 0.8rem;
  cursor: pointer;
}

.upload-label {
  position: relative;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  color: var(--accent-gold);
  border: 2px solid var(--border-gold);
  padding: 5px 12px;
  font-size: 0.8rem;
  font-family: 'Cinzel', serif;
  border-radius: 4px;
  transition: all 0.2s;
  letter-spacing: 1px;
}

.upload-label:hover {
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  box-shadow: 0 0 15px rgba(212, 168, 67, 0.25);
  border-color: var(--accent-gold);
}

.hidden-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
}

.upload-progress {
  width: 100%;
  font-size: 0.8rem;
  color: var(--accent-gold);
  font-style: italic;
}

.upload-error {
  width: 100%;
  font-size: 0.8rem;
  color: var(--accent-red);
}
</style>
