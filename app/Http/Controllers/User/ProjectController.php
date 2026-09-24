<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Services\Project\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = Project::where('user_id', $user->id)
            ->where('is_archived', false)
            ->withCount(['files', 'conversations'])
            ->latest('updated_at')
            ->get();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Enforce max projects quota if user group restricts
        if ($user->userGroup && $user->userGroup->max_projects > 0) {
            $currentProjects = Project::where('user_id', $user->id)->where('is_archived', false)->count();
            if ($currentProjects >= $user->userGroup->max_projects) {
                return response()->json([
                    'error' => 'Project Limit Reached',
                    'message' => "Your plan allows a maximum of {$user->userGroup->max_projects} projects.",
                ], 403);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
        ]);

        $uuid = (string) Str::uuid();

        $project = Project::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'workspace_path' => "workspaces/{$uuid}",
            'settings' => $validated['settings'] ?? [],
        ]);

        $this->workspaceService->ensureProjectDirectory($project);

        AuditLog::log('create_project', 'Project', (string) $project->id, ['name' => $project->name], $user);

        return response()->json($project, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $project->loadCount(['files', 'conversations']);
        $files = $this->workspaceService->listFiles($project);

        return response()->json([
            'project' => $project,
            'files' => $files,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
        ]);

        $project->update($validated);

        AuditLog::log('update_project', 'Project', (string) $project->id, ['name' => $project->name], $request->user());

        return response()->json($project);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request->user(), $project);

        $project->update(['is_archived' => true]);

        AuditLog::log('archive_project', 'Project', (string) $project->id, ['name' => $project->name], $request->user());

        return response()->json(['message' => 'Project archived successfully']);
    }

    protected function authorizeProjectAccess($user, Project $project): void
    {
        if ($project->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized project access');
        }
    }
}
