<?php

namespace ProcessMaker\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;

class TrustHosts extends Middleware
{
    public function hosts(): array
    {
        // اجازه بده از ENV هم الگو بدهیم، مثلا: APP_TRUSTED_HOSTS="^(.+\.)?bpms\.clickapps\.ir$|^bpms\.clickapps\.ir$"
        $envPattern = env('APP_TRUSTED_HOSTS');

        // الگوی پیش‌فرض مبتنی بر APP_URL (برای سازگاری با قبل)
        $default = $this->allSubdomainsOfApplicationUrl();

        // الگوهای قطعی برای دامنه فعلی
        $hardcoded = [
            '^(.+\.)?bpms\.clickapps\.ir$',
            '^bpms\.clickapps\.ir$',
        ];

        return array_values(array_filter([
            $envPattern,
            ...$hardcoded,
            $default,
        ]));
    }

    public function handle(Request $request, $next)
    {
        // اگر پشت پروکسی هستی، X-Forwarded-Host ممکنه چندتا مقدارِ کاما-سِپَرِیت بده
        if ($request->hasHeader('X-Forwarded-Host')) {
            $forwarded = $request->header('X-Forwarded-Host');
            // آخرین مقدار معمولا هاست اصلی کاربره
            $forwardedHost = trim(last(explode(',', $forwarded)));

            $trustedPatterns = $this->hosts();
            foreach ($trustedPatterns as $pattern) {
                if (@preg_match('/' . str_replace('/', '\/', $pattern) . '/', $forwardedHost)) {
                    if (preg_match('/' . str_replace('/', '\/', $pattern) . '/', $forwardedHost) === 1) {
                        // معتبر است
                        return parent::handle($request, $next);
                    }
                }
            }

            \Log::warning('Rejected request with untrusted X-Forwarded-Host', [
                'forwarded_host' => $forwardedHost,
                'trusted_patterns' => $trustedPatterns,
            ]);
            abort(400, 'Invalid Host Header');
        }

        return parent::handle($request, $next);
    }
}
