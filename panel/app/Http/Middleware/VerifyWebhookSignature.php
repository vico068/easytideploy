<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $application = $request->route('application');

        if (! $application || ! $application->webhook_secret) {
            return response()->json(['error' => 'Invalid application'], 404);
        }

        // GitHub signature verification
        if ($request->hasHeader('X-Hub-Signature-256')) {
            $signature = $request->header('X-Hub-Signature-256');
            $payload = $request->getContent();
            $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $application->webhook_secret);

            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            return $next($request);
        }

        // GitLab token verification
        if ($request->hasHeader('X-Gitlab-Token')) {
            $token = $request->header('X-Gitlab-Token');

            if (! hash_equals($application->webhook_secret, $token)) {
                return response()->json(['error' => 'Invalid token'], 403);
            }

            return $next($request);
        }

        // Bitbucket uses basic auth or IP whitelisting
        // For simplicity, we'll check a custom header
        if ($request->hasHeader('X-Bitbucket-Token')) {
            $token = $request->header('X-Bitbucket-Token');

            if (! hash_equals($application->webhook_secret, $token)) {
                return response()->json(['error' => 'Invalid token'], 403);
            }

            return $next($request);
        }

        return response()->json(['error' => 'No signature provided'], 403);
    }
}
