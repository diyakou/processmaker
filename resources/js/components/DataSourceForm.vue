<template>
  <div class="data-source-form">
    <form @submit.prevent="handleSubmit">
      <!-- Basic Information -->
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>{{ $t('Name') }} <span class="text-danger">*</span></label>
            <input 
              type="text" 
              class="form-control" 
              v-model="form.name" 
              :class="{ 'is-invalid': errors.name }"
              required 
            />
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>{{ $t('Type') }}</label>
            <select class="form-control" v-model="form.type" :disabled="isEditing">
              <option value="rest">REST API</option>
              <option value="soap">SOAP</option>
            </select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>{{ $t('Description') }}</label>
        <textarea 
          class="form-control" 
          v-model="form.description" 
          rows="3"
          :class="{ 'is-invalid': errors.description }"
        ></textarea>
        <div v-if="errors.description" class="invalid-feedback">{{ errors.description[0] }}</div>
      </div>

      <!-- Authentication -->
      <h5 class="mt-4">{{ $t('Authentication') }}</h5>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>{{ $t('Auth Type') }}</label>
            <select class="form-control" v-model="form.authtype">
              <option value="NONE">{{ $t('None') }}</option>
              <option value="BASIC">{{ $t('Basic Auth') }}</option>
              <option value="OAUTH2_BEARER">{{ $t('Bearer Token') }}</option>
              <option value="OAUTH2_PASSWORD">{{ $t('OAuth2 Password') }}</option>
            </select>
          </div>
        </div>
        <div class="col-md-6" v-if="form.authtype !== 'NONE'">
          <div class="form-group">
            <label>{{ $t('Credentials') }}</label>
            <div v-if="form.authtype === 'BASIC'" class="row">
              <div class="col-6">
                <input 
                  type="text" 
                  class="form-control" 
                  placeholder="Username"
                  v-model="form.credentials.username"
                />
              </div>
              <div class="col-6">
                <input 
                  type="password" 
                  class="form-control" 
                  placeholder="Password"
                  v-model="form.credentials.password"
                />
              </div>
            </div>
            <input 
              v-else-if="form.authtype === 'OAUTH2_BEARER'"
              type="text" 
              class="form-control" 
              placeholder="Bearer Token"
              v-model="form.credentials.token"
            />
          </div>
        </div>
      </div>

      <!-- SSL Options -->
      <div class="row">
        <div class="col-md-6">
          <div class="form-check">
            <input 
              class="form-check-input" 
              type="checkbox" 
              id="verify_certificate"
              v-model="form.verify_certificate"
            />
            <label class="form-check-label" for="verify_certificate">
              {{ $t('Verify SSL Certificate') }}
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check">
            <input 
              class="form-check-input" 
              type="checkbox" 
              id="debug_mode"
              v-model="form.debug_mode"
            />
            <label class="form-check-label" for="debug_mode">
              {{ $t('Debug Mode') }}
            </label>
          </div>
        </div>
      </div>

      <!-- Endpoints -->
      <h5 class="mt-4">{{ $t('Endpoints') }}</h5>
      <div class="endpoints-section">
        <div 
          v-for="(endpoint, key) in form.endpoints" 
          :key="key" 
          class="endpoint-item card mb-3"
        >
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ key }}</h6>
            <button 
              type="button" 
              class="btn btn-sm btn-outline-danger"
              @click="removeEndpoint(key)"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-8">
                <label>{{ $t('URL') }}</label>
                <input 
                  type="url" 
                  class="form-control" 
                  v-model="endpoint.url"
                  placeholder="https://api.example.com/users"
                  required
                />
              </div>
              <div class="col-md-4">
                <label>{{ $t('Method') }}</label>
                <select class="form-control" v-model="endpoint.method">
                  <option value="GET">GET</option>
                  <option value="POST">POST</option>
                  <option value="PUT">PUT</option>
                  <option value="PATCH">PATCH</option>
                  <option value="DELETE">DELETE</option>
                </select>
              </div>
            </div>

            <!-- Headers -->
            <div class="mt-3">
              <label>{{ $t('Headers') }}</label>
              <div 
                v-for="(header, index) in endpoint.headers" 
                :key="`header-${index}`"
                class="row mb-2"
              >
                <div class="col-5">
                  <input 
                    type="text" 
                    class="form-control" 
                    placeholder="Header Name"
                    v-model="header.key"
                  />
                </div>
                <div class="col-5">
                  <input 
                    type="text" 
                    class="form-control" 
                    placeholder="Header Value"
                    v-model="header.value"
                  />
                </div>
                <div class="col-2">
                  <button 
                    type="button" 
                    class="btn btn-sm btn-outline-danger"
                    @click="removeHeader(endpoint, index)"
                  >
                    <i class="fas fa-minus"></i>
                  </button>
                </div>
              </div>
              <button 
                type="button" 
                class="btn btn-sm btn-outline-primary"
                @click="addHeader(endpoint)"
              >
                <i class="fas fa-plus"></i> {{ $t('Add Header') }}
              </button>
            </div>

            <!-- Request Body -->
            <div class="mt-3" v-if="['POST', 'PUT', 'PATCH'].includes(endpoint.method)">
              <label>{{ $t('Request Body') }}</label>
              <div class="row mb-2">
                <div class="col-md-3">
                  <select class="form-control" v-model="endpoint.body_type">
                    <option value="json">JSON</option>
                    <option value="form-data">Form Data</option>
                    <option value="raw">Raw</option>
                  </select>
                </div>
              </div>
              <textarea 
                class="form-control" 
                v-model="endpoint.body"
                rows="4"
                placeholder='{"key": "{{variable}}", "value": "example"}'
              ></textarea>
              <small class="text-muted">
                {{ $t('Use mustache syntax for variables: {{variableName}}') }}
              </small>
            </div>
          </div>
        </div>

        <!-- Add Endpoint Button -->
        <button 
          type="button" 
          class="btn btn-outline-primary"
          @click="addEndpoint"
        >
          <i class="fas fa-plus"></i> {{ $t('Add Endpoint') }}
        </button>
      </div>

      <!-- Submit Buttons -->
      <div class="form-actions mt-4 text-right">
        <button type="button" class="btn btn-outline-secondary mr-2" @click="$emit('cancel')">
          {{ $t('Cancel') }}
        </button>
        <button type="submit" class="btn btn-primary">
          {{ isEditing ? $t('Update') : $t('Create') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  name: 'DataSourceForm',
  props: {
    dataSource: {
      type: Object,
      default: null
    },
    isEditing: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      form: {
        name: '',
        description: '',
        type: 'rest',
        authtype: 'NONE',
        credentials: {},
        verify_certificate: true,
        debug_mode: false,
        endpoints: {}
      },
      errors: {},
      newEndpointName: ''
    }
  },
  watch: {
    dataSource: {
      immediate: true,
      handler(newValue) {
        if (newValue) {
          this.form = {
            ...this.form,
            ...newValue,
            credentials: newValue.credentials || {},
            endpoints: newValue.endpoints || {}
          }
        } else {
          this.resetForm()
        }
      }
    }
  },
  methods: {
    resetForm() {
      this.form = {
        name: '',
        description: '',
        type: 'rest',
        authtype: 'NONE',
        credentials: {},
        verify_certificate: true,
        debug_mode: false,
        endpoints: {}
      }
      this.errors = {}
    },

    handleSubmit() {
      this.errors = {}
      
      // Basic validation
      if (!this.form.name) {
        this.errors.name = ['Name is required']
        return
      }

      if (Object.keys(this.form.endpoints).length === 0) {
        ProcessMaker.alert(this.$t('At least one endpoint is required'), 'warning')
        return
      }

      // Validate endpoints
      for (const [key, endpoint] of Object.entries(this.form.endpoints)) {
        if (!endpoint.url) {
          ProcessMaker.alert(this.$t('URL is required for endpoint: ') + key, 'warning')
          return
        }
      }

      this.$emit('save', this.form)
    },

    addEndpoint() {
      const name = prompt(this.$t('Enter endpoint name:'))
      if (name && !this.form.endpoints[name]) {
        this.$set(this.form.endpoints, name, {
          url: '',
          method: 'GET',
          headers: [],
          body: '',
          body_type: 'json'
        })
      }
    },

    removeEndpoint(key) {
      this.$delete(this.form.endpoints, key)
    },

    addHeader(endpoint) {
      endpoint.headers.push({ key: '', value: '' })
    },

    removeHeader(endpoint, index) {
      endpoint.headers.splice(index, 1)
    }
  }
}
</script>

<style scoped>
.endpoint-item {
  border-left: 4px solid #007bff;
}

.form-actions {
  border-top: 1px solid #dee2e6;
  padding-top: 1rem;
}
</style>