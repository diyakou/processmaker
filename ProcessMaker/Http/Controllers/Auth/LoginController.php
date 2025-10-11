<?php

namespace ProcessMaker\Http\Controllers\Auth;

use App;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use ProcessMaker\Events\Logout;
use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Managers\LoginManager;
use ProcessMaker\Models\Setting;
use ProcessMaker\Models\User;
use ProcessMaker\Package\Auth\Database\Seeds\AuthDefaultSeeder;
use ProcessMaker\Traits\HasControllerAddons;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class LoginController extends Controller
{
    use HasControllerAddons;
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    protected $maxAttempts;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Set middle wares
        $this->middleware('session_block')->only('loginWithIntendedCheck');
        $this->middleware('guest')->except(['logout', 'beforeLogout', 'keepAlive']);
        $this->middleware('saml_request')->only('showLoginForm');

        // Set login attempts
        $loginAttempts = (int) config('password-policies.login_attempts', PHP_INT_MAX);
        if ($loginAttempts === 0) {
            $loginAttempts = PHP_INT_MAX;
        }
        $this->maxAttempts = $loginAttempts;
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm(Request $request)
    {
        $manager = App::make(LoginManager::class);
        $addons = $manager->list();
        // Cheche if the user can by pass
        $showForceLogin = $request->has('showLogin') && $request->get('showLogin') === 'true';
        // Review if we need to redirect the default SSO
        if (config('app.enable_default_sso') && !$showForceLogin) {
            $arrayAddons = $addons->toArray();
            $driver = $this->getDefaultSSO($arrayAddons);
            // If a default SSO was defined we will to redirect
            if (!empty($driver)) {
                // Store the current full URL as the intended URL before redirecting to SSO.
                $intendedUrl = $request->session()->get('url.intended', url()->full());
                $ssoIntendedCookie = cookie(
                    'processmaker_intended',
                    $intendedUrl,
                    10,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    'none'
                );

                // Redirect to SSO and attach the cookie
                return redirect()->route('sso.redirect', ['driver' => $driver])->withCookie($ssoIntendedCookie);
            }
        }
        $block = $manager->getBlock();
        // clear cookie to avoid an issue when logout SLO and then try to login with simple PM login form
        \Cookie::queue(\Cookie::forget(config('session.cookie')));
        // cookie required here because SSO redirect resets the session
        $cookie = cookie(
            'processmaker_intended',
            redirect()->intended()->getTargetUrl(),
            10,
            null,
            null,
            true,
            true,
            false,
            'none'
        );
        $loginView = empty(config('app.login_view')) ? 'auth.login' : config('app.login_view');
        $response = response(view($loginView, compact('addons', 'block')));
        $response->withCookie($cookie);

        // Remove 'password_hash_web' from session
        $request = request();
        $request->session()->forget('password_hash_' . app('auth')->getDefaultDriver());

        return $response;
    }

    protected function getDefaultSSO(array $addons): string
    {
        $addonsData = !empty($addons) ? head($addons)->data : [];
        $defaultSSO = $this->getLoginDefaultSSO();
        $pmLogin = $this->getPmLogin();
        if (!empty($defaultSSO) && !empty($addonsData)) {
            // Get the config selected
            $position = $this->getColumnAttribute($defaultSSO, 'config', 'config');
            // Get the ui defined
            $elements = $this->getColumnAttribute($defaultSSO, 'ui', 'elements');
            $options = $this->getColumnAttribute($defaultSSO, 'ui', 'options');
            // Get the sso drivers configured
            $drivers = !empty($addonsData['drivers']) ? $addonsData['drivers'] : [];
            if (
                is_int($position)
                && $options[$position] !== $pmLogin
                && !empty($elements)
                && !empty($drivers)
            ) {
                // Get the specific element defined with the default SSO
                $element = !empty($elements[$position]->name) ? strtolower($elements[$position]->name) : '';
                if (!empty($element) && array_key_exists($element, $drivers)) {
                    return $element;
                }
            }
        }

        return '';
    }

    protected function getLoginDefaultSSO()
    {
        $defaultSSO = '';
        // Check if the package-auth is installed and has a const SSO_DEFAULT_LOGIN was defined
        if (class_exists(AuthDefaultSeeder::class)) {
            $defaultSSO = Setting::byKey('sso.default.login');
        }

        return $defaultSSO;
    }

    protected function getPmLogin()
    {
        return 'ProcessMaker';
    }

    protected function getColumnAttribute(object $setting, string $attribute, string $key = '')
    {
        $config = $setting->getAttribute($attribute);
        switch ($key) {
            case 'config':
                $result = !is_null($config) ? (int) $config : null;
                break;
            case 'elements':
                $result = !empty($config->elements) ? $config->elements : [];
                break;
            case 'options':
                $result = !empty($config->options) ? $config->options : [];
                break;
            default:
                $result = null;
        }

        return $result;
    }

    public function loginWithIntendedCheck(Request $request)
{
    // --- Context & timing ---
    $t0   = microtime(true);
    $rid  = (string) Str::uuid();
    $ip   = $request->ip();
    $ua   = $request->userAgent();
    $uname= (string) $request->input('username');

    // همه‌ی لاگ‌های این درخواست این context را خواهند داشت
    Log::withContext([
        'rid'      => $rid,
        'ip'       => $ip,
        'ua'       => $ua,
        'username' => $uname,
    ]);

    Log::info('Auth: loginWithIntendedCheck called');

    // --- Intended (از کوکی) ---
    $intended = Cookie::get('processmaker_intended');
    if ($intended) {
        Log::debug('Auth: intended cookie found', ['intended' => $intended]);

        // جلوگیری از ریدایرکت‌های خطرناک به دامنه‌های دیگر
        try {
            $appUrlHost = parse_url(config('app.url'), PHP_URL_HOST);
            $intHost    = parse_url($intended, PHP_URL_HOST);
            if ($intHost && $appUrlHost && strcasecmp($intHost, $appUrlHost) !== 0) {
                Log::warning('Auth: external intended blocked', ['intended' => $intended, 'allowed_host' => $appUrlHost]);
                $intended = false;
            }
        } catch (\Throwable $e) {
            Log::warning('Auth: intended host parse failed', ['error' => $e->getMessage()]);
            $intended = false;
        }

        // بررسی fallback route (مانند favicon.ico)
        if ($intended) {
            try {
                $route = app('router')->getRoutes()->match(app('request')->create($intended));
                if (method_exists($route, 'isFallback') && $route->isFallback) {
                    Log::warning('Auth: intended matched fallback route, ignore', ['intended' => $intended]);
                    $intended = false;
                }
            } catch (\Throwable $e) {
                Log::warning('Auth: intended route match failed, ignore', ['intended' => $intended, 'error' => $e->getMessage()]);
                $intended = false;
            }
        }

        if ($intended) {
            // گرفتن intended از سشن پاکش می‌کند؛ مجدداً برش گردان
            $request->session()->put('url.intended', $intended);
            Log::debug('Auth: intended restored to session', ['intended' => $intended]);
        } else {
            Log::debug('Auth: intended ignored/cleared');
        }
    } else {
        Log::debug('Auth: no intended cookie');
    }

    // --- بارگیری کاربر و بررسی وضعیت ---
    $user = User::where('username', $uname)->first();
    if (!$user) {
        Log::notice('Auth: user not found');
        $this->sendFailedLoginResponse($request); // معمولاً throw می‌کند
        return;
    }
    Log::debug('Auth: user loaded', [
        'user_id' => $user->id ?? null,
        'status'  => $user->status ?? null,
    ]);

    if (($user->status ?? null) === 'INACTIVE') {
        Log::notice('Auth: inactive user blocked', ['user_id' => $user->id ?? null]);
        $this->sendFailedLoginResponse($request);
        return;
    }
    if (($user->status ?? null) === 'BLOCKED') {
        Log::warning('Auth: blocked user login attempt', ['user_id' => $user->id ?? null]);
        $this->throwLockedLoginResponse();
        return;
    }

    // --- افزونه‌ها ---
    try {
        $addons = $this->getPluginAddons('command', []);
        Log::debug('Auth: addons fetched', ['count' => is_countable($addons) ? count($addons) : 0]);

        foreach ($addons as $addon) {
            if (array_key_exists('command', $addon) && isset($addon['command'])) {
                $class = is_object($addon['command']) ? get_class($addon['command']) : gettype($addon['command']);
                Log::debug('Auth: executing addon command', ['command' => $class]);
                try {
                    $addon['command']->execute($request, $uname);
                } catch (\Throwable $e) {
                    Log::error('Auth: addon command failed', ['command' => $class, 'error' => $e->getMessage()]);
                }
            }
        }
    } catch (\Throwable $e) {
        Log::error('Auth: getPluginAddons failed', ['error' => $e->getMessage()]);
    }

    // --- LDAP (در صورت وجود پکیج) ---
    if (class_exists(\ProcessMaker\Package\Auth\Auth\LDAPLogin::class)) {
        Log::info('Auth: attempting LDAP auth', ['user_id' => $user->id ?? null]);
        try {
            // هرگز پسورد را لاگ نکن!
            $redirect = \ProcessMaker\Package\Auth\Auth\LDAPLogin::auth($user, $request->input('password'));
            if ($redirect !== false) {
                // مقصد ریدایرکت را لاگ کن
                $target = method_exists($redirect, 'getTargetUrl') ? $redirect->getTargetUrl() : null;
                Log::info('Auth: LDAP handled, redirecting', [
                    'target'     => $target,
                    'session_id' => $request->session()->getId(),
                ]);
                Log::info('Auth: finished', ['ms' => (int) round((microtime(true) - $t0) * 1000)]);
                return $redirect;
            }
            Log::debug('Auth: LDAP returned false, fallback to local login');
        } catch (\Throwable $e) {
            Log::error('Auth: LDAP auth error', ['error' => $e->getMessage()]);
        }
    } else {
        Log::debug('Auth: LDAP class not present, skipping');
    }

    // --- لاگین محلی ---
    Log::debug('Auth: calling login()');
    try {
        // اگر داخل login() از Auth::attempt استفاده می‌کنی، قبل/بعدش هم لاگ بگذار.
        $resp = $this->login($request, $user);

        // نوع پاسخ را بررسی و لاگ کن
        if ($resp instanceof \Illuminate\Http\RedirectResponse) {
            $target = method_exists($resp, 'getTargetUrl') ? $resp->getTargetUrl() : null;
            Log::info('Auth: login() redirect', [
                'target'      => $target,
                'intended'    => session('url.intended'),
                'session_id'  => $request->session()->getId(),
                'auth_user_id'=> optional(auth()->user())->id,
            ]);
        } else {
            Log::info('Auth: login() non-redirect', [
                'class'       => is_object($resp) ? get_class($resp) : gettype($resp),
                'session_id'  => $request->session()->getId(),
                'auth_user_id'=> optional(auth()->user())->id,
            ]);
        }

        Log::info('Auth: finished', ['ms' => (int) round((microtime(true) - $t0) * 1000)]);
        return $resp;
    } catch (\Throwable $e) {
        Log::error('Auth: login() exception', [
            'error' => $e->getMessage(),
            'trace' => substr($e->getTraceAsString(), 0, 1500),
        ]);
        throw $e;
    }
}


    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    public function keepAlive()
    {
        return response('', 204);
    }

    protected function authenticated(Request $request, $user)
    {
        if (env('LOGOUT_OTHER_DEVICES', false)) {
            Auth::logoutOtherDevices($request->input('password'));
        }
    }

    public function beforeLogout(Request $request)
    {
        if (Auth::check()) {
            //Clear the user permissions
            $request->session()->forget('permissions');

            //Clear the user permissions
            $userId = Auth::user()->id;
            Cache::forget("user_{$userId}_permissions");
            Cache::forget("user_{$userId}_project_assets");

            // Clear the user session
            $this->forgetUserSession();

            // Always destroy 2fa flag
            session()->remove(TwoFactorAuthController::TFA_VALIDATED);
            session()->remove(TwoFactorAuthController::TFA_MESSAGE);
            session()->remove(TwoFactorAuthController::TFA_ERROR);

            // Notify to listeners (package-auth, security logger)
            $eventResult = event(new Logout(Auth::user()));

            // Perform the logout operation
            $this->logout($request);

            // Remove the Laravel cookie
            Cookie::queue(Cookie::forget(Passport::cookie()));

            // process any redirects generated by the logout event listeners
            foreach ($eventResult as $result) {
                if (is_array($result) && array_key_exists('redirectTo', $result)) {
                    return redirect($result['redirectTo']);
                }
            }
        }

        return $this->logout($request);
    }

    private function forgetUserSession()
    {
        $userSession = session()->get('user_session');
        $user = Auth::user();
        $user->sessions()->where('token', $userSession)->update(['is_active' => false]);
        session()->forget('user_session');
    }

    public function loggedOut(Request $request)
    {
        $response = redirect(route('login'));
        if ($request->has('timeout')) {
            $response->with('timeout', true);
        }

        return $response;
    }

    /**
     * Handle a login request to the application.
     * Overrides the original login action.
     *
     * @param  Request $request
     * @param  User $user
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws ValidationException
     */
    public function login(Request $request, User $user)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            // Block the user
            $user->status = 'BLOCKED';
            $user->save();

            // Throw locked error message
            $this->throwLockedLoginResponse();
        }

        if ($this->attemptLogin($request)) {
            if ($request->hasSession()) {
                $request->session()->put('auth.password_confirmed_at', time());
            }

            // Check if the user needs to change the password
            if ($request->filled(['SAMLRequest', 'RelayState']) && $user->force_change_password === 1) {
                // Store the SAMLRequest and RelayState in a cookie
                Cookie::queue(
                    'saml_request',
                    json_encode([
                        'SAMLRequest' => $request->get('SAMLRequest'),
                        'RelayState' => $request->get('RelayState'),
                    ]),
                    10,
                    null,
                    null,
                    true,
                    true,
                    false,
                    'none',
                );

                return redirect()->route('password.change');
            }
            // Cache user permissions for a day to improve performance
            Cache::remember("user_{$user->id}_permissions", 86400, function () use ($user) {
                return $user->permissions()->pluck('name')->toArray();
            });

            $this->setupLanguage($request, $user);

            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Throws locked error message
     *
     * @throws ValidationException
     */
    protected function throwLockedLoginResponse()
    {
        throw ValidationException::withMessages([
            $this->username() => [_('Account locked after too many failed attempts. Contact administrator.')],
        ]);
    }

    public function showLoginFailed(Request $request)
    {
        return view('errors.login-failed');
    }

    private function setupLanguage(Request $request, User $user)
    {
        $language = $request->cookies->get('language');
        if ($language && $language !== 'null') {
            $user->language = json_decode($language)->code;
            $user->save();
        }
    }
}
