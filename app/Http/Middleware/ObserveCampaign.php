<?php

namespace App\Http\Middleware;

use App\Support\CampaignAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ObserveCampaign
{
    public function __construct(private CampaignAttribution $campaigns) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->isSuccessful()) {
            $this->campaigns->observe($request);
        }

        return $response;
    }
}
