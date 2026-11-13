<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Pmo;

use App\Models\SavedAiPrompt;
use App\Support\NeoPalette;
use Illuminate\Validation\Rule;
use Livewire\Form;

class SavedAiPromptForm extends Form
{
    public ?int $id = null;

    public string $title = '';

    public string $action_type = 'standup';

    public string $custom_instruction = '';

    public string $icon = '✨';

    public string $color = 'pink';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'action_type' => ['required', Rule::in(array_keys(SavedAiPrompt::ACTIONS))],
            'custom_instruction' => ['required', 'string', 'max:4000'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', Rule::in(NeoPalette::keys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => 'template title',
            'action_type' => 'AI action',
            'custom_instruction' => 'instruction',
        ];
    }

    public function fillFrom(SavedAiPrompt $prompt): void
    {
        $this->resetErrorBag();
        $this->id = $prompt->id;
        $this->title = $prompt->title;
        $this->action_type = $prompt->action_type;
        $this->custom_instruction = $prompt->custom_instruction;
        $this->icon = $prompt->icon;
        $this->color = $prompt->color;
    }

    /**
     * @return array{title: string, action_type: string, custom_instruction: string, icon: string, color: string}
     */
    public function payload(): array
    {
        $this->title = trim($this->title);
        $this->custom_instruction = trim($this->custom_instruction);
        $this->icon = trim($this->icon);

        $this->validate();

        return [
            'title' => $this->title,
            'action_type' => $this->action_type,
            'custom_instruction' => $this->custom_instruction,
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }
}
