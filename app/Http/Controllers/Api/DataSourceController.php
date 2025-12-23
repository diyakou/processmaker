<?php

namespace App\Http\Controllers\Api;

use App\Models\DataSource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use ProcessMaker\Http\Controllers\Controller;
use Illuminate\Validation\Rule;

class DataSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DataSource::query();

        if ($request->has('filter')) {
            $filter = $request->filter;
            $query->where(function($q) use ($filter) {
                $q->where('name', 'LIKE', "%{$filter}%")
                  ->orWhere('description', 'LIKE', "%{$filter}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $dataSources = $query->orderBy($request->order_by ?? 'name')
                            ->paginate($request->per_page ?? 15);

        return response()->json($dataSources);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:data_sources',
            'description' => 'nullable|string',
            'type' => 'required|in:rest,soap',
            'authtype' => 'required|in:NONE,BASIC,OAUTH2_BEARER,OAUTH2_PASSWORD',
            'endpoints' => 'required|array',
            'endpoints.*.url' => 'required|url',
            'endpoints.*.method' => 'required|in:GET,POST,PUT,DELETE,PATCH',
            'credentials' => 'nullable|array',
            'verify_certificate' => 'boolean',
            'debug_mode' => 'boolean'
        ]);

        $dataSource = DataSource::create($validated);
        
        return response()->json($dataSource, 201);
    }

    public function show(DataSource $dataSource): JsonResponse
    {
        return response()->json($dataSource);
    }

    public function update(Request $request, DataSource $dataSource): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('data_sources')->ignore($dataSource)],
            'description' => 'nullable|string',
            'type' => 'required|in:rest,soap',
            'authtype' => 'required|in:NONE,BASIC,OAUTH2_BEARER,OAUTH2_PASSWORD',
            'endpoints' => 'required|array',
            'endpoints.*.url' => 'required|url',
            'endpoints.*.method' => 'required|in:GET,POST,PUT,DELETE,PATCH',
            'credentials' => 'nullable|array',
            'verify_certificate' => 'boolean',
            'debug_mode' => 'boolean'
        ]);

        $dataSource->update($validated);
        
        return response()->json($dataSource);
    }

    public function destroy(DataSource $dataSource): JsonResponse
    {
        $dataSource->delete();
        
        return response()->json(['message' => 'Data source deleted successfully']);
    }

    public function test(Request $request, DataSource $dataSource): JsonResponse
    {
        $endpoint = $request->get('endpoint');
        $testData = $request->get('data', []);

        try {
            $result = $dataSource->testConnection($endpoint);
            return response()->json([
                'status' => 'success',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function execute(Request $request, DataSource $dataSource): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'data' => 'array',
            'config' => 'array'
        ]);

        try {
            $result = $dataSource->execute(
                $validated['endpoint'], 
                $validated['data'] ?? [], 
                $validated['config'] ?? []
            );

            return response()->json([
                'status' => 'success',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function createFromSwagger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'swagger_url' => 'required|url',
            'name' => 'nullable|string|max:255'
        ]);

        try {
            $dataSource = DataSource::createFromSwagger(
                $validated['swagger_url'],
                $validated['name'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'data_source' => $dataSource,
                'message' => 'Data source created successfully from Swagger spec'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function endpoints(DataSource $dataSource): JsonResponse
    {
        return response()->json([
            'endpoints' => $dataSource->getEndpointsList(),
            'details' => $dataSource->endpoints
        ]);
    }
}