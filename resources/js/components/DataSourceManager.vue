<template>
  <div class="data-source-manager">
    <div class="mb-3">
      <div class="row">
        <div class="col-md-8">
          <h4>{{ $t('Data Sources') }}</h4>
        </div>
        <div class="col-md-4 text-right">
          <button class="btn btn-secondary mr-2" @click="showCreateModal">
            <i class="fas fa-plus"></i> {{ $t('Create Data Source') }}
          </button>
          <button class="btn btn-outline-secondary" @click="showSwaggerModal">
            <i class="fas fa-file-code"></i> {{ $t('From Swagger') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Data Sources Table -->
    <div class="card">
      <div class="card-body">
        <vuetable 
          ref="vuetable"
          :api-mode="true" 
          :api-url="apiUrl"
          :fields="fields"
          :css="css"
          :append-params="appendParams"
          pagination-path="meta"
          data-path="data"
          @vuetable:pagination-data="onPaginationData"
        >
          <template slot="actions" slot-scope="props">
            <div class="actions">
              <button class="btn btn-sm btn-outline-primary" @click="editDataSource(props.rowData)">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-success ml-1" @click="testConnection(props.rowData)">
                <i class="fas fa-plug"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger ml-1" @click="deleteDataSource(props.rowData)">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </template>
        </vuetable>
        
        <vuetable-pagination
          ref="pagination"
          @vuetable-pagination:change-page="onChangePage"
        ></vuetable-pagination>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <b-modal v-model="showModal" :title="modalTitle" size="lg" hide-footer>
      <data-source-form
        :data-source="selectedDataSource"
        :is-editing="isEditing"
        @save="saveDataSource"
        @cancel="showModal = false"
      />
    </b-modal>

    <!-- Swagger Import Modal -->
    <b-modal v-model="showSwaggerImport" :title="$t('Import from Swagger')" hide-footer>
      <swagger-import-form
        @import="importFromSwagger"
        @cancel="showSwaggerImport = false"
      />
    </b-modal>
  </div>
</template>

<script>
import DataSourceForm from './DataSourceForm.vue'
import SwaggerImportForm from './SwaggerImportForm.vue'

export default {
  name: 'DataSourceManager',
  components: {
    DataSourceForm,
    SwaggerImportForm
  },
  data() {
    return {
      apiUrl: '/api/1.1/data_sources',
      showModal: false,
      showSwaggerImport: false,
      selectedDataSource: null,
      isEditing: false,
      fields: [
        {
          name: 'name',
          title: this.$t('Name'),
          sortField: 'name'
        },
        {
          name: 'description',
          title: this.$t('Description')
        },
        {
          name: 'type',
          title: this.$t('Type'),
          callback: 'formatType'
        },
        {
          name: 'status',
          title: this.$t('Status'),
          callback: 'formatStatus'
        },
        {
          name: 'created_at',
          title: this.$t('Created'),
          callback: 'formatDate'
        },
        {
          name: '__slot:actions',
          title: this.$t('Actions'),
          titleClass: 'text-center',
          dataClass: 'text-center'
        }
      ],
      css: {
        tableClass: 'table table-hover',
        loadingClass: 'loading',
        ascendingIcon: 'fas fa-sort-up',
        descendingIcon: 'fas fa-sort-down'
      },
      appendParams: {
        per_page: 15
      }
    }
  },
  computed: {
    modalTitle() {
      return this.isEditing ? this.$t('Edit Data Source') : this.$t('Create Data Source')
    }
  },
  methods: {
    showCreateModal() {
      this.selectedDataSource = null
      this.isEditing = false
      this.showModal = true
    },
    
    showSwaggerModal() {
      this.showSwaggerImport = true
    },

    editDataSource(dataSource) {
      this.selectedDataSource = { ...dataSource }
      this.isEditing = true
      this.showModal = true
    },

    async saveDataSource(dataSourceData) {
      try {
        const url = this.isEditing 
          ? `/api/1.1/data_sources/${this.selectedDataSource.id}`
          : '/api/1.1/data_sources'
        
        const method = this.isEditing ? 'PUT' : 'POST'
        
        await ProcessMaker.apiClient.request({
          method,
          url,
          data: dataSourceData
        })

        ProcessMaker.alert(
          this.isEditing 
            ? this.$t('Data Source updated successfully')
            : this.$t('Data Source created successfully'),
          'success'
        )

        this.showModal = false
        this.$refs.vuetable.refresh()
      } catch (error) {
        ProcessMaker.alert(this.$t('Error saving data source'), 'danger')
        console.error('Error saving data source:', error)
      }
    },

    async deleteDataSource(dataSource) {
      const confirmed = await this.confirmDelete(dataSource.name)
      if (!confirmed) return

      try {
        await ProcessMaker.apiClient.delete(`/api/1.1/data_sources/${dataSource.id}`)
        ProcessMaker.alert(this.$t('Data Source deleted successfully'), 'success')
        this.$refs.vuetable.refresh()
      } catch (error) {
        ProcessMaker.alert(this.$t('Error deleting data source'), 'danger')
        console.error('Error deleting data source:', error)
      }
    },

    async testConnection(dataSource) {
      try {
        const response = await ProcessMaker.apiClient.post(`/api/1.1/data_sources/${dataSource.id}/test`)
        
        if (response.data.status === 'success') {
          ProcessMaker.alert(this.$t('Connection test successful'), 'success')
        } else {
          ProcessMaker.alert(this.$t('Connection test failed: ') + response.data.message, 'warning')
        }
      } catch (error) {
        ProcessMaker.alert(this.$t('Connection test failed'), 'danger')
        console.error('Error testing connection:', error)
      }
    },

    async importFromSwagger(swaggerData) {
      try {
        const response = await ProcessMaker.apiClient.post('/api/1.1/data_sources/from-swagger', swaggerData)
        
        ProcessMaker.alert(this.$t('Data Source imported from Swagger successfully'), 'success')
        this.showSwaggerImport = false
        this.$refs.vuetable.refresh()
      } catch (error) {
        ProcessMaker.alert(this.$t('Error importing from Swagger'), 'danger')
        console.error('Error importing from Swagger:', error)
      }
    },

    confirmDelete(name) {
      return new Promise((resolve) => {
        ProcessMaker.confirmModal(
          this.$t('Confirm Delete'),
          this.$t('Are you sure you want to delete the data source "{{name}}"?', { name }),
          '',
          () => resolve(true),
          () => resolve(false)
        )
      })
    },

    formatType(value) {
      return value.toUpperCase()
    },

    formatStatus(value) {
      const statusClass = value === 'ACTIVE' ? 'success' : 'secondary'
      return `<span class="badge badge-${statusClass}">${value}</span>`
    },

    formatDate(value) {
      return new Date(value).toLocaleDateString()
    },

    onPaginationData(paginationData) {
      this.$refs.pagination.setPaginationData(paginationData)
    },

    onChangePage(page) {
      this.$refs.vuetable.changePage(page)
    }
  }
}
</script>

<style scoped>
.actions {
  white-space: nowrap;
}
</style>