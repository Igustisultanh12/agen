<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\CodingAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\AI\AiGatewayService;
use App\Services\AI\FccService;
use App\Services\AI\ModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        protected AiGatewayService $aiGateway,
        protected ModelService $modelService,
        protected FccService $fccService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Conversation::with(['model.provider', 'agent', 'project'])
            ->where('user_id', $user->id)
            ->where('status', 'active');

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }

        $conversations = $query->latest('updated_at')->paginate(25);

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'model_id' => 'nullable|exists:ai_models,id',
            'agent_id' => 'nullable|exists:coding_agents,id',
            'title' => 'nullable|string|max:200',
        ]);

        if (!empty($validated['project_id'])) {
            $project = Project::findOrFail($validated['project_id']);
            if ($project->user_id !== $user->id && !$project->members()->where('user_id', $user->id)->exists()) {
                return response()->json(['error' => 'Unauthorized project access'], 403);
            }
        }

        // Default model
        $modelId = $validated['model_id'] ?? null;
        if (!$modelId) {
            $defaultModel = AiModel::where('is_default', true)->where('status', 'active')->first()
                ?? AiModel::where('status', 'active')->first();
            $modelId = $defaultModel?->id;
        }

        // Default agent
        $agentId = $validated['agent_id'] ?? null;
        if (!$agentId) {
            $defaultAgent = CodingAgent::where('is_default', true)->where('is_active', true)->first()
                ?? CodingAgent::where('is_active', true)->first();
            $agentId = $defaultAgent?->id;
        }

        $model = $modelId ? AiModel::find($modelId) : null;

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'project_id' => $validated['project_id'] ?? null,
            'model_id' => $modelId,
            'provider_id' => $model?->provider_id,
            'agent_id' => $agentId,
            'title' => $validated['title'] ?? 'New Chat',
            'status' => 'active',
        ]);

        return response()->json($conversation->load(['model.provider', 'agent', 'project']), 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversationAccess($request->user(), $conversation);

        $conversation->load(['model.provider', 'agent', 'project']);
        $messages = $conversation->messages()->orderBy('id')->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversationAccess($request->user(), $conversation);

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'model_id' => 'nullable|exists:ai_models,id',
            'agent_id' => 'nullable|exists:coding_agents,id',
        ]);

        if (isset($validated['model_id'])) {
            $model = AiModel::find($validated['model_id']);
            $conversation->model_id = $model->id;
            $conversation->provider_id = $model->provider_id;
        }

        if (isset($validated['agent_id'])) {
            $conversation->agent_id = $validated['agent_id'];
        }

        if (isset($validated['title'])) {
            $conversation->title = $validated['title'];
        }

        $conversation->save();

        return response()->json($conversation->load(['model.provider', 'agent', 'project']));
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversationAccess($request->user(), $conversation);

        $conversation->update(['status' => 'archived']);

        return response()->json(['message' => 'Conversation archived successfully']);
    }

    /**
     * Non-streaming chat message endpoint.
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversationAccess($request->user(), $conversation);

        $validated = $request->validate([
            'prompt' => 'required|string|max:100000',
            'selected_file_ids' => 'nullable|array',
            'selected_file_ids.*' => 'exists:project_files,id',
            'current_file_path' => 'nullable|string',
            'current_file_content' => 'nullable|string',
        ]);

        try {
            $result = $this->aiGateway->sendMessage(
                $request->user(),
                $conversation,
                $validated['prompt'],
                $validated['selected_file_ids'] ?? [],
                $validated['current_file_path'] ?? null,
                $validated['current_file_content'] ?? null,
                $request->header('Idempotency-Key')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'AI Request Failed',
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        }
    }

    /**
     * Realtime streaming chat message endpoint using Server-Sent Events (SSE).
     */
    public function stream(Request $request, Conversation $conversation): StreamedResponse|JsonResponse
    {
        $this->authorizeConversationAccess($request->user(), $conversation);

        $validated = $request->validate([
            'prompt' => 'required|string|max:100000',
            'selected_file_ids' => 'nullable|array',
            'selected_file_ids.*' => 'exists:project_files,id',
            'current_file_path' => 'nullable|string',
            'current_file_content' => 'nullable|string',
        ]);

        try {
            return $this->aiGateway->createStreamResponse(
                $request->user(),
                $conversation,
                $validated['prompt'],
                $validated['selected_file_ids'] ?? [],
                $validated['current_file_path'] ?? null,
                $validated['current_file_content'] ?? null
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Streaming Failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * User Stop generation action (Requirement 17, 60).
     */
    public function stop(): JsonResponse
    {
        $stopped = $this->fccService->stopGeneration();

        return response()->json([
            'status' => $stopped ? 'stopped' : 'failed_or_idle',
            'message' => $stopped ? 'Generation stopped.' : 'No active generation or already stopped.',
        ]);
    }

    /**
     * Fetch model and agent selectors for chat workspace.
     */
    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $groupedModels = $this->modelService->getGroupedModelsForUser($user);
        $agents = $this->modelService->getAgentsForUser($user);

        return response()->json([
            'providers' => $groupedModels,
            'agents' => $agents,
        ]);
    }

    protected function authorizeConversationAccess($user, Conversation $conversation): void
    {
        if ($conversation->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized conversation access');
        }
    }
}
