<template>
  <div class="swagger-import-form">
    <form @submit.prevent="handleImport">
      <div class="form-group">
        <label>{{ $t('Swagger/OpenAPI URL') }} <span class="text-danger">*</span></label>
        <input 
          type="url" 
          class="form-control" 
          v-model="form.swagger_url"
          placeholder="https://petstore.swagger.io/v2/swagger.json"
          required
        />
        <small class="text-muted">
          {{ $t('Enter the URL to your Swagger/OpenAPI specification file') }}
        </small>
      </div>

      <div class="form-group">
        <label>{{ $t('Data Source Name') }}</label>
        <input 
          type="text" 
          class="form-control" 
          v-model="form.name"
          :placeholder="$t('Auto-generated if empty')"
        />
      </div>

      <!-- Preview Section -->
      <div v-if="preview" class="mt-4">
        <h6>{{ $t('Preview') }}</h6>
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">{{ preview.title }}</h6>
            <p class="card-text">{{ preview.description }}</p>
            <p><strong>{{ $t('Base URL') }}:</strong> {{ preview.baseUrl }}</p>
            <p><strong>{{ $t('Endpoints') }}:</strong> {{ preview.endpointCount }}</p>
            
            <div class="endpoints-preview mt-3">
              <h6>{{ $t('Available Endpoints:') }}</h6>
              <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>{{ $t('Method') }}</th>
                      <th>{{ $t('Path') }}</th>
                      <th>{{ $t('Operation ID') }}</th>
                      <th>{{ $t('Summary') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="endpoint in preview.endpoints" :key="endpoint.operationId">
                      <td>
                        <span class="badge" :class="getMethodClass(endpoint.method)">
                          {{ endpoint.method.toUpperCase() }}
                        </span>
                      </td>
                      <td><code>{{ endpoint.path }}</code></td>
                      <td>{{ endpoint.operationId }}</td>
                      <td>{{ endpoint.summary }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions mt-4 text-right">
        <button type="button" class="btn btn-outline-secondary mr-2" @click="$emit('cancel')">
          {{ $t('Cancel') }}
        </button>
        <button 
          type="button" 
          class="btn btn-outline-primary mr-2" 
          @click="loadPreview"
          :disabled="!form.swagger_url || loading"
        >
          <i class="fas fa-eye"></i> 
          {{ loading ? $t('Loading...') : $t('Preview') }}
        </button>
        <button 
          type="submit" 
          class="btn btn-primary"
          :disabled="!form.swagger_url || importing"
        >
          <i class="fas fa-download"></i>
          {{ importing ? $t('Importing...') : $t('Import') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  name: 'SwaggerImportForm',
  data() {
    return {
      form: {
        swagger_url: '',
        name: ''
      },
      preview: null,
      loading: false,
      importing: false
    }
  },
  methods: {
    async loadPreview() {
      if (!this.form.swagger_url) return

      this.loading = true
      try {
        // Fetch swagger spec directly to show preview
        const response = await fetch(this.form.swagger_url)
        const spec = await response.json()
        
        this.preview = this.parseSwaggerSpec(spec)
        
        // Auto-fill name if empty
        if (!this.form.name && spec.info?.title) {
          this.form.name = spec.info.title
        }
      } catch (error) {
        ProcessMaker.alert(this.$t('Failed to load Swagger specification'), 'danger')
        console.error('Error loading swagger spec:', error)
      } finally {
        this.loading = false
      }
    },

    parseSwaggerSpec(spec) {
      const basePath = spec.basePath || ''
      const host = spec.host || ''
      const schemes = spec.schemes || ['http']
      const baseUrl = host ? `${schemes[0]}://${host}${basePath}` : basePath

      const endpoints = []
      
      Object.entries(spec.paths || {}).forEach(([path, methods]) => {
        Object.entries(methods).forEach(([method, details]) => {
          if (typeof details === 'object' && details.operationId) {
            endpoints.push({
              path,
              method,
              operationId: details.operationId,
              summary: details.summary || '',
              description: details.description || ''
            })
          }
        })
      })

      return {
        title: spec.info?.title || 'Unknown API',
        description: spec.info?.description || '',
        baseUrl,
        endpointCount: endpoints.length,
        endpoints: endpoints.slice(0, 10) // Show first 10 for preview
      }
    },

    getMethodClass(method) {
      const classes = {
        get: 'badge-primary',
        post: 'badge-success',
        put: 'badge-warning',
        patch: 'badge-info',
        delete: 'badge-danger'
      }
      return classes[method.toLowerCase()] || 'badge-secondary'
    },

    async handleImport() {
      if (!this.form.swagger_url) return

      this.importing = true
      try {
        this.$emit('import', this.form)
      } catch (error) {
        console.error('Error importing from swagger:', error)
      } finally {
        this.importing = false
      }
    }
  }
}
</script>

<style scoped>
.endpoints-preview {
  font-size: 0.875rem;
}

.form-actions {
  border-top: 1px solid #dee2e6;
  padding-top: 1rem;
}

code {
  font-size: 0.8rem;
}
</style>