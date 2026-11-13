<?php

declare(strict_types=1);

namespace App\Services\Pmo;

use App\Models\SavedAiPrompt;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Saved AI prompt templates. Every query is scoped to Auth::id().
 */
final class SavedPromptService
{
    /**
     * @return Collection<int, SavedAiPrompt>
     */
    public function list(): Collection
    {
        return SavedAiPrompt::query()
            ->where('user_id', $this->ownerId())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): SavedAiPrompt
    {
        return SavedAiPrompt::query()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id);
    }

    /**
     * @param  array{title: string, action_type: string, custom_instruction: string, icon: string, color: string}  $data
     */
    public function create(array $data): SavedAiPrompt
    {
        $ownerId = $this->ownerId();

        return SavedAiPrompt::query()->create([
            ...$data,
            'user_id' => $ownerId,
            'sort_order' => (int) SavedAiPrompt::query()->where('user_id', $ownerId)->max('sort_order') + 1,
        ]);
    }

    /**
     * @param  array{title: string, action_type: string, custom_instruction: string, icon: string, color: string}  $data
     */
    public function update(int $id, array $data): SavedAiPrompt
    {
        $prompt = $this->find($id);
        $prompt->fill($data)->save();

        return $prompt;
    }

    public function delete(int $id): SavedAiPrompt
    {
        $prompt = $this->find($id);
        $prompt->delete();

        return $prompt;
    }

    private function ownerId(): int
    {
        $id = Auth::id();

        if ($id === null) {
            throw new AuthenticationException;
        }

        return (int) $id;
    }
}
