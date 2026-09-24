<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Project;
use App\Services\AI\AiGatewayService;
use App\Services\AI\ModelService;
use App\Services\AI\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function __construct(
        protected AiGatewayService $aiGateway,
        protected ModelService $modelService,
        protected TokenUsageService $tokenUsageService
    ) {}

    /**
     * External API: list accessible models.
     */
    public function models(Request $request): JsonResponse
    {
        $user = $request->user();
        $grouped = $this->modelService->getGroupedModelsForUser($user);

        return response()->json([
            'object' => 'list',
            'providers' => $grouped,
        ]);
    }

    /**
     * External API: Send a chat completion.
     */
    public function chat(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'model' => 'nullable|string', // model slug or provider_model_id
            'model_id' => 'nullable|exists:ai_models,id',
            'prompt' => 'required|string|max:100000',
            'conversation_uuid' => 'nullable|uuid',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        // Resolve model
        $model = null;
        if (!empty($validated['model_id'])) {
            $model = AiModel::find($validated['model_id']);
        } elseif (!empty($validated['model'])) {
            $model = AiModel::where('slug', $validated['model'])
                ->orWhere('provider_model_id', $validated['model'])
                ->first();
        }

        if (!$model) {
            $model = AiModel::where('is_default', true)->where('status', 'active')->first()
                ?? AiModel::where('status', 'active')->first();
        }

        if (!$model) {
            return response()->json(['error' => 'No active AI models found on platform.'], 503);
        }

        // Find or create conversation
        if (!empty($validated['conversation_uuid'])) {
            $conversation = Conversation::where('uuid', $validated['conversation_uuid'])
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'project_id' => $validated['project_id'] ?? null,
                'model_id' => $model->id,
                'provider_id' => $model->provider_id,
                'title' => 'API Request',
            ]);
        }

        try {
            $result = $this->aiGateway->sendMessage(
                $user,
                $conversation,
                $validated['prompt'],
                [],
                null,
                null,
                $request->header('Idempotency-Key')
            );

            return response()->json([
                'id' => $result['internal_request_id'],
                'conversation_id' => $conversation->uuid,
                'message' => [
                    'role' => 'assistant',
                    'content' => $result['message']->content,
                ],
                'model' => $result['model'],
                'usage' => $result['usage'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'AI Generation Failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * External API: Get user usage & cost (Requirement 39).
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->tokenUsageService->getUserUsageSummary($user);

        return response()->json([
            'tokens' => [
                'input' => $summary['month']['input_tokens'] ?? $summary['today']['input_tokens'],
                'output' => $summary['month']['output_tokens'] ?? $summary['today']['output_tokens'],
                'total' => $summary['month']['total_tokens'],
            ],
            'estimated_cost' => $summary['month']['estimated_cost'],
            'currency' => 'USD',
            'quota' => $summary['quota'],
        ]);
    }

    /**
     * External API: List user projects.
     */
    public function projects(Request $request): JsonResponse
    {
        $user = $request->user();
        $projects = Project::where('user_id', $user->id)
            ->where('is_archived', false)
            ->withCount(['files', 'conversations'])
            ->get();

        return response()->json(['projects' => $projects]);
    }
}
