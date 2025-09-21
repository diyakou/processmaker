<?php

namespace ProcessMaker\ScriptRunners;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ProcessMaker\Exception\ConfigurationException;
use ProcessMaker\GenerateAccessToken;
use ProcessMaker\Jobs\ErrorHandling;
use ProcessMaker\Models\EnvironmentVariable;
use ProcessMaker\Models\Script;
use ProcessMaker\Models\User;
use stdClass;
use Throwable;

class ScriptMicroserviceRunner
{
    private string $tokenId = '';

    private string $language;

    public function __construct(protected Script $script)
    {
        $this->language = strtolower($script->language ?? $script->scriptExecutor->language);
    }

    public function getAccessToken()
    {
        if (Cache::has('keycloak.access_token')) {
            Log::debug('ScriptMS@getAccessToken cache hit');
            return Cache::get('keycloak.access_token');
        }

        $kcBase = config('script-runner-microservice.keycloak.base_url') ?? '';
        Log::debug('ScriptMS@getAccessToken requesting token', [
            'keycloak_base_url' => $kcBase,
            'client_id' => config('script-runner-microservice.keycloak.client_id'),
            // client_secret را لاگ نمی‌کنیم
            'username' => config('script-runner-microservice.keycloak.username'),
        ]);

        if (empty($kcBase)) {
            Log::error('ScriptMS@getAccessToken missing keycloak.base_url');
            return null;
        }

        try {
            $response = Http::asForm()->post($kcBase, [
                'grant_type' => 'password',
                'client_id' => config('script-runner-microservice.keycloak.client_id'),
                'client_secret' => config('script-runner-microservice.keycloak.client_secret'),
                'username' => config('script-runner-microservice.keycloak.username'),
                'password' => config('script-runner-microservice.keycloak.password'),
            ]);
        } catch (Throwable $e) {
            Log::error('ScriptMS@getAccessToken http error', ['ex' => $e->getMessage()]);
            return null;
        }

        Log::debug('ScriptMS@getAccessToken response', [
            'status' => $response->status(),
            'ok' => $response->successful(),
            'body_len' => strlen($response->body() ?? ''),
        ]);

        if ($response->successful()) {
            Cache::put(
                'keycloak.access_token',
                $response->json()['access_token'],
                $response->json()['expires_in'] - 60
            );
        }

        return Cache::get('keycloak.access_token');
    }

    public function getScriptRunner()
    {
        $base = config('script-runner-microservice.base_url');
        Log::debug('ScriptMS@getScriptRunner', [
            'base_url' => $base,
            'language' => $this->language,
        ]);

        if (empty($base)) {
            throw new ConfigurationException('SCRIPT_MICROSERVICE_BASE_URL is empty; set it in .env');
        }

        $token = $this->getAccessToken();
        if (!$token) {
            Log::warning('ScriptMS@getScriptRunner missing access token; proceeding without Bearer (may fail)');
        }

        $response = Cache::remember(
            'script-runner-microservice.script-languages',
            now()->addDay(),
            function () use ($base, $token) {
                $req = Http::timeout(15);
                if ($token) {
                    $req = $req->withToken($token);
                }
                $url = rtrim($base, '/') . '/scripts';
                Log::debug('ScriptMS@getScriptRunner HTTP GET', ['url' => $url]);
                return $req->get($url)->collect();
            }
        );

        return $response->filter(fn ($item) => $item['language'] == $this->language)->first();
    }

    public function run($code, array $data, array $config, $timeout, $user, $sync, $metadata)
    {
        $base = config('script-runner-microservice.base_url');
        $callback = config('script-runner-microservice.callback');
        Log::debug('ScriptMS@run start', [
            'language' => $this->language,
            'sync' => $sync,
            'timeout' => $timeout,
            'base_url' => $base,
            'callback' => $callback,
            'docker_host_url' => config('app.docker_host_url'),
            'api_host' => config('app.docker_host_url') ? (config('app.docker_host_url') . '/api/1.0') : null,
        ]);

        if (empty($base)) {
            throw new ConfigurationException('SCRIPT_MICROSERVICE_BASE_URL is empty; set it in .env');
        }

        $scriptRunner = $this->getScriptRunner();
        if (!$scriptRunner) {
            throw new ConfigurationException('No script executor for language: ' . $this->language);
        }

        $metadata = array_merge($this->getMetadata($user), $metadata);
        $environmentVariables = $this->getEnvironmentVariables($user);

        $payload = [
            'version' => config('script-runner-microservice.version') ?? $this->getProcessMakerVersion(),
            'language' => $scriptRunner['language'],
            'metadata'=> $metadata,
            'data' => !empty($data) ? $this->sanitizeCss($data) : new stdClass(),
            'config' => !empty($config) ? $config : new stdClass(),
            'script' => base64_encode(str_replace("'", '&#39;', $code)),
            'secrets' => $environmentVariables,
            'callback' => $callback,
            'callback_secure' => true,
            'callback_token' => $environmentVariables['API_TOKEN'] ?? null,
            'debug' => true,
            'timeout' => $timeout,
            'sync' => $sync,
        ];

        // لاگ پِی‌لود بدون محتویات حساس
        Log::debug('ScriptMS@run payload overview', [
            'url' => rtrim($base, '/') . '/requests/create',
            'has_api_token' => array_key_exists('API_TOKEN', $environmentVariables),
            'callback_set' => !empty($callback),
            'payload_keys' => array_keys($payload),
        ]);

        // حداکثر تایم‌اوت کلاینت (۱ روز) – تایم‌اوت واقعی را میکروسرویس هندل می‌کند
        $clientTimeout = 86400;

        $token = $this->getAccessToken();
        $req = Http::timeout($clientTimeout);
        if ($token) {
            $req = $req->withToken($token);
        }

        $url = rtrim($base, '/') . '/requests/create';
        Log::debug('ScriptMS@run HTTP POST', ['url' => $url]);

        try {
            $response = $req->post($url, $payload);
        } catch (Throwable $e) {
            Log::error('ScriptMS@run HTTP exception', [
                'url' => $url,
                'ex' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::debug('ScriptMS@run response', [
            'status' => $response->status(),
            'ok' => $response->successful(),
            'len' => strlen($response->body() ?? ''),
        ]);

        $response->throw();

        $result = $response->json();

        if ($sync) {
            ErrorHandling::convertResponseToException($result);
        }

        return $result;
    }

    private function getEnvironmentVariables(User $user)
    {
        $variablesParameter = [];
        EnvironmentVariable::chunk(50, function (Collection $variables) use (&$variablesParameter) {
            foreach ($variables as $variable) {
                $variablesParameter[str_replace(' ', '_', $variable->name)] = $variable->value;
            }
        });

        $variablesParameter['HOST_URL'] = config('app.docker_host_url');

        $token = null;
        if ($user) {
            $accessToken = Cache::remember('script-runner-' . $user->id, now()->addWeek(), function () use ($user) {
                $user->removeOldRunScriptTokens();
                $token = new GenerateAccessToken($user);
                return $token->getToken();
            });
            $variablesParameter['API_TOKEN'] = $accessToken;
            $variablesParameter['API_HOST'] = config('app.docker_host_url') . '/api/1.0';
            $variablesParameter['APP_URL'] = config('app.docker_host_url');
            $variablesParameter['API_SSL_VERIFY'] = (config('app.api_ssl_verify') ? '1' : '0');
        }

        Log::debug('ScriptMS@getEnvironmentVariables', [
            'HOST_URL' => $variablesParameter['HOST_URL'] ?? null,
            'has_API_TOKEN' => array_key_exists('API_TOKEN', $variablesParameter),
            'API_HOST' => $variablesParameter['API_HOST'] ?? null,
            'APP_URL' => $variablesParameter['APP_URL'] ?? null,
        ]);

        return $variablesParameter;
    }

    public function setTokenId($tokenId)
    {
        $this->tokenId = $tokenId;
    }

    public function getProcessMakerVersion()
    {
        return Cache::remember('script-runner-microservice.processmaker-version', now()->addDay(), function () {
            $composer_json_path = json_decode(file_get_contents(base_path() . '/composer.json'));
            return $composer_json_path->version;
        });
    }

    public function getMetadata($user)
    {
        return [
            'script_id' => $this->script->id,
            'instance' => config('app.url'),
            'user_id' => $user->id,
            'user_email' => $user->email,
        ];
    }

    public function sanitizeCss($data)
    {
        if ($this->language !== 'javascript-ssr') {
            return $data;
        }
        if (array_key_exists('css', $data)) {
            $data['css'] = false;
        }
        return $data;
    }
}
