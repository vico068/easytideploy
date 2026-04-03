<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerifyWebhookSignature;
use App\Jobs\ProcessDeploymentJob;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware(VerifyWebhookSignature::class);
    }

    public function github(Request $request, Application $application): JsonResponse
    {
        $event = $request->header('X-GitHub-Event');

        if ($event !== 'push') {
            return response()->json(['message' => 'Event ignored'], 200);
        }

        if (! $application->auto_deploy) {
            return response()->json(['message' => 'Auto-deploy disabled'], 200);
        }

        $payload = $request->all();

        // Check if push is to the configured branch
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        if ($branch !== $application->git_branch) {
            return response()->json(['message' => 'Branch mismatch'], 200);
        }

        $commitSha = $payload['after'] ?? null;
        $commitMessage = $payload['head_commit']['message'] ?? null;
        $commitAuthor = $payload['head_commit']['author']['name'] ?? null;

        ProcessDeploymentJob::dispatch($application, $commitSha, 'webhook');

        return response()->json([
            'message' => 'Deployment triggered',
            'commit' => $commitSha,
        ], 202);
    }

    public function gitlab(Request $request, Application $application): JsonResponse
    {
        $event = $request->header('X-Gitlab-Event');

        if ($event !== 'Push Hook') {
            return response()->json(['message' => 'Event ignored'], 200);
        }

        if (! $application->auto_deploy) {
            return response()->json(['message' => 'Auto-deploy disabled'], 200);
        }

        $payload = $request->all();

        // Check if push is to the configured branch
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        if ($branch !== $application->git_branch) {
            return response()->json(['message' => 'Branch mismatch'], 200);
        }

        $commitSha = $payload['after'] ?? null;

        ProcessDeploymentJob::dispatch($application, $commitSha, 'webhook');

        return response()->json([
            'message' => 'Deployment triggered',
            'commit' => $commitSha,
        ], 202);
    }

    public function bitbucket(Request $request, Application $application): JsonResponse
    {
        if (! $application->auto_deploy) {
            return response()->json(['message' => 'Auto-deploy disabled'], 200);
        }

        $payload = $request->all();

        // Bitbucket sends changes array
        $changes = $payload['push']['changes'] ?? [];
        if (empty($changes)) {
            return response()->json(['message' => 'No changes'], 200);
        }

        $change = $changes[0];
        $branch = $change['new']['name'] ?? '';

        if ($branch !== $application->git_branch) {
            return response()->json(['message' => 'Branch mismatch'], 200);
        }

        $commitSha = $change['new']['target']['hash'] ?? null;

        ProcessDeploymentJob::dispatch($application, $commitSha, 'webhook');

        return response()->json([
            'message' => 'Deployment triggered',
            'commit' => $commitSha,
        ], 202);
    }
}
