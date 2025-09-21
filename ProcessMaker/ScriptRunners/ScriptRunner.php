<?php

namespace ProcessMaker\ScriptRunners;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;   // 👈 اضافه شد
use ProcessMaker\Enums\ScriptExecutorType;
use ProcessMaker\Exception\ScriptLanguageNotSupported;
use ProcessMaker\Models\Script;
use ProcessMaker\Models\ScriptExecutor;

class ScriptRunner
{
    /**
     * Concrete script runner
     *
     * @var Base
     */
    private $runner;

    public function __construct(protected Script $script)
    {
        $this->runner = $this->getScriptRunner($this->script->scriptExecutor);
    }

    public function run($code, array $data, array $config, $timeout, $user, $sync, $metadata)
    {
        return $this->runner->run($code, $data, $config, $timeout, $user, $sync, $metadata);
    }

    private function getScriptRunner(ScriptExecutor $executor): Base|ScriptMicroserviceRunner|MockRunner
    {
        // 👇 اینجا لاگ بگیر که بفهمی وضعیت چیه
        Log::debug('ScriptRunner@getScriptRunner', [
            'executor_id'   => $executor->id,
            'executor_type' => $executor->type,
            'executor_lang' => $executor->language,
            'microservice_enabled' => config('script-runner-microservice.enabled'),
            'microservice_base_url' => config('script-runner-microservice.base_url'),
        ]);

        if (!config('script-runner-microservice.enabled') || $executor->type === ScriptExecutorType::Custom) {
            $language = strtolower($executor->language);
            $runner = config("script-runners.{$language}.runner");
            if (!$runner) {
                throw new ScriptLanguageNotSupported($language);
            } else {
                $class = "ProcessMaker\\ScriptRunners\\{$runner}";

                return app()->make($class, ['scriptExecutor' => $executor]);
            }
        } else {
            return new ScriptMicroserviceRunner($this->script);
        }
    }

    public function setTokenId($tokenId)
    {
        $this->runner->setTokenId($tokenId);
    }
}
