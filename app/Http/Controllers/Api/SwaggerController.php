<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SwaggerSource;
use App\Models\SwaggerEndpoint;
use App\Models\SwaggerField;
use Illuminate\Http\Request;

class SwaggerController extends Controller
{
    public function index()
    {
        return SwaggerSource::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'url' => 'required|url',
            'meta' => 'nullable|array',
        ]);
        return SwaggerSource::create($data);
    }

    public function update(Request $request, $id)
    {
        $source = SwaggerSource::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string',
            'url' => 'sometimes|url',
            'meta' => 'nullable|array',
        ]);
        $source->update($data);
        return $source;
    }

    public function destroy($id)
    {
        $source = SwaggerSource::findOrFail($id);
        $source->delete();
        return response()->json(['success' => true]);
    }

    public function endpoints($sourceId)
    {
        return SwaggerEndpoint::where('swagger_source_id', $sourceId)->get();
    }

    public function endpoint($id)
    {
        return SwaggerEndpoint::with('fields')->findOrFail($id);
    }

    public function updateEndpoint(Request $request, $id)
    {
        $endpoint = SwaggerEndpoint::findOrFail($id);
        $data = $request->validate([
            'summary' => 'sometimes|string',
            'description' => 'nullable|string',
            'request_body' => 'nullable|array',
            'response_body' => 'nullable|array',
        ]);
        $endpoint->update($data);
        return $endpoint;
    }

    public function destroyEndpoint($id)
    {
        $endpoint = SwaggerEndpoint::findOrFail($id);
        $endpoint->delete();
        return response()->json(['success' => true]);
    }

    public function fields($endpointId)
    {
        return SwaggerField::where('endpoint_id', $endpointId)->get();
    }

    public function updateField(Request $request, $id)
    {
        $field = SwaggerField::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'required' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'example' => 'nullable|string',
        ]);
        $field->update($data);
        return $field;
    }

    public function destroyField($id)
    {
        $field = SwaggerField::findOrFail($id);
        $field->delete();
        return response()->json(['success' => true]);
    }
}
