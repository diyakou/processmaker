<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use ProcessMaker\Traits\SerializeToIso8601;
use ProcessMaker\WebServices\WebServiceRequestFactory;

class DataSource extends Model
{
    use HasFactory, SerializeToIso8601;

    protected $fillable = [
        'name',
        'description',
        'type',
        'authtype',
        'endpoints',
        'credentials',
        'status',
        'verify_certificate',
        'debug_mode'
    ];

    protected $casts = [
        'endpoints' => 'array',
        'credentials' => 'array',
        'verify_certificate' => 'boolean',
        'debug_mode' => 'boolean'
    ];

    protected $attributes = [
        'type' => 'rest',
        'authtype' => 'NONE',
        'status' => 'ACTIVE',
        'verify_certificate' => true,
        'debug_mode' => false
    ];

    public function execute($endpointName, $data = [], $config = [])
    {
        $factory = new WebServiceRequestFactory();
        $webService = $factory->create($this->type, $this);
        
        $serviceConfig = array_merge([
            'dataSource' => $this->id,
            'endpoint' => $endpointName,
            'dataMapping' => [],
            'outboundConfig' => []
        ], $config);

        return $webService->execute($data, $serviceConfig);
    }

    public function testConnection($endpointName = null)
    {
        try {
            if ($endpointName && isset($this->endpoints[$endpointName])) {
                return $this->execute($endpointName, []);
            }
            
            // Test first endpoint if no specific endpoint provided
            $firstEndpoint = array_key_first($this->endpoints);
            if ($firstEndpoint) {
                return $this->execute($firstEndpoint, []);
            }
            
            return ['status' => 'error', 'message' => 'No endpoints defined'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getEndpointsList()
    {
        return array_keys($this->endpoints ?? []);
    }

    public static function createFromSwagger($swaggerUrl, $name = null)
    {
        // Implementation for creating data source from Swagger/OpenAPI spec
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($swaggerUrl);
            $spec = json_decode($response->getBody(), true);
            
            $endpoints = [];
            $basePath = $spec['basePath'] ?? '';
            $host = $spec['host'] ?? '';
            $schemes = $spec['schemes'] ?? ['http'];
            
            foreach ($spec['paths'] ?? [] as $path => $methods) {
                foreach ($methods as $method => $details) {
                    $operationId = $details['operationId'] ?? $method . '_' . str_replace(['/', '{', '}'], ['_', '', ''], $path);
                    
                    $endpoints[$operationId] = [
                        'url' => $schemes[0] . '://' . $host . $basePath . $path,
                        'method' => strtoupper($method),
                        'summary' => $details['summary'] ?? '',
                        'parameters' => self::parseSwaggerParameters($details['parameters'] ?? []),
                        'headers' => [],
                        'body' => '',
                        'body_type' => 'json'
                    ];
                }
            }
            
            return self::create([
                'name' => $name ?? ($spec['info']['title'] ?? 'Swagger API'),
                'description' => $spec['info']['description'] ?? 'Auto-generated from Swagger spec',
                'type' => 'rest',
                'endpoints' => $endpoints
            ]);
            
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse Swagger spec: ' . $e->getMessage());
        }
    }

    private static function parseSwaggerParameters($parameters)
    {
        $parsed = [];
        foreach ($parameters as $param) {
            $parsed[] = [
                'name' => $param['name'],
                'in' => $param['in'], // query, path, header, body
                'required' => $param['required'] ?? false,
                'type' => $param['type'] ?? 'string',
                'description' => $param['description'] ?? ''
            ];
        }
        return $parsed;
    }
}