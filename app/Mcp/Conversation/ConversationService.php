<?php

namespace App\Mcp\Conversation;

use App\Mcp\Auth\AuthenticatedUser;
use App\Mcp\Data\RequestLabRepository;
use App\Mcp\Exceptions\McpException;
use App\Models\McpConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(private readonly RequestLabRepository $repository) {}

    public function list(AuthenticatedUser $user, string $workspaceId): array
    {
        $this->authorizeWorkspace($user, $workspaceId);
        return McpConversation::query()->where('user_id', $user->id)->where('workspace_id', $workspaceId)
            ->whereNull('archived_at')->orderByDesc('last_message_at')->limit(50)->get()->map(fn ($item) => $this->summary($item))->all();
    }

    public function create(AuthenticatedUser $user, array $values): array
    {
        $this->authorizeWorkspace($user, $values['workspace_id']);
        $conversation = McpConversation::create([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'workspace_id' => $values['workspace_id'],
            'collection_id' => $values['collection_id'] ?? null, 'environment_id' => $values['environment_id'] ?? null,
            'request_id' => $values['request_id'] ?? null, 'title' => trim($values['title'] ?? '') ?: 'New chat',
        ]);
        return $this->detail($conversation);
    }

    public function get(AuthenticatedUser $user, string $id): array
    {
        return $this->detail($this->owned($user, $id)->load('messages'));
    }

    public function append(AuthenticatedUser $user, string $id, string $role, string $content, ?array $metadata = null): array
    {
        $conversation = $this->owned($user, $id);
        return DB::transaction(function () use ($conversation, $role, $content, $metadata) {
            $message = $conversation->messages()->create([
                'id' => (string) Str::uuid(), 'role' => $role, 'content' => trim($content), 'metadata' => $metadata,
            ]);
            $conversation->update(['last_message_at' => now()]);
            if ($conversation->title === 'New chat' && $role === 'user') {
                $conversation->update(['title' => Str::limit(preg_replace('/\s+/', ' ', trim($content)), 80, '')]);
            }
            return $this->message($message);
        });
    }

    public function archive(AuthenticatedUser $user, string $id): void
    {
        $this->owned($user, $id)->update(['archived_at' => now()]);
    }

    public function planningContext(AuthenticatedUser $user, string $id, string $workspaceId): string
    {
        $conversation = $this->owned($user, $id);
        if ($conversation->workspace_id !== $workspaceId) {
            throw new McpException('RESOURCE_RELATIONSHIP_INVALID', 'The conversation does not belong to the active workspace.', httpStatus: 422);
        }
        $messages = $conversation->messages()->latest('created_at')->limit(12)->get()->reverse();
        $text = $messages->map(fn ($message) => strtoupper($message->role).': '.preg_replace('/\s+/', ' ', $message->content))->implode("\n");
        return Str::limit($text, 2500, '');
    }

    private function owned(AuthenticatedUser $user, string $id): McpConversation
    {
        $item = McpConversation::query()->whereKey($id)->where('user_id', $user->id)->first();
        if (!$item) throw new McpException('RESOURCE_NOT_FOUND', 'The conversation is unavailable.', httpStatus: 404);
        return $item;
    }

    private function authorizeWorkspace(AuthenticatedUser $user, string $workspaceId): void
    {
        $visible = collect($this->repository->forUser($user)->listWorkspaces())->contains(fn ($row) => ($row['id'] ?? null) === $workspaceId);
        if (!$visible) throw new McpException('WORKSPACE_ACCESS_DENIED', 'The workspace is unavailable.', httpStatus: 403);
    }

    private function summary(McpConversation $item): array
    {
        return ['id' => $item->id, 'workspace_id' => $item->workspace_id, 'title' => $item->title,
            'last_message_at' => optional($item->last_message_at)->toIso8601String(), 'created_at' => $item->created_at->toIso8601String()];
    }

    private function detail(McpConversation $item): array
    {
        return $this->summary($item) + ['collection_id' => $item->collection_id, 'environment_id' => $item->environment_id,
            'request_id' => $item->request_id, 'messages' => $item->relationLoaded('messages') ? $item->messages->map(fn ($m) => $this->message($m))->all() : []];
    }

    private function message($item): array
    {
        return ['id' => $item->id, 'role' => $item->role, 'text' => $item->content, 'metadata' => $item->metadata ?? [], 'created_at' => $item->created_at->toIso8601String()];
    }
}
