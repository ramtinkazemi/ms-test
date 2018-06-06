<?php

namespace App\Http\Middleware;

use Closure;

class DisableSession
{
    /**
     * The URIs that should be disabled
     *
     * @var array
     */
    protected $except = [
        'api/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                config()->set('session.driver', 'array');
            }
        }
        return $next($request);
    }

}
