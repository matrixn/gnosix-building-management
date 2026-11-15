<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

class OrganizationContext
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            $context->resolve($request->user('web'));
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
