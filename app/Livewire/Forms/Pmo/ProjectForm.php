<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Pmo;

use App\Models\Project;
use App\Support\NeoPalette;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProjectForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $icon = '📁';

    public string $color = 'violet';

    public string $status = 'active';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('projects', 'code')->where('user_id', Auth::id())->ignore($this->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', Rule::in(NeoPalette::keys())],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'project name',
            'code' => 'project code',
        ];
    }

    public function fillFrom(Project $project): void
    {
        $this->resetErrorBag();
        $this->id = $project->id;
        $this->name = $project->name;
        $this->code = (string) $project->code;
        $this->description = (string) $project->description;
        $this->icon = $project->icon;
        $this->color = $project->color;
        $this->status = $project->status;
    }

    /**
     * @return array{name: string, code: ?string, description: ?string, icon: string, color: string, status: string}
     */
    public function payload(): array
    {
        $this->code = strtoupper(trim($this->code));
        $this->validate();

        return [
            'name' => trim($this->name),
            'code' => $this->code === '' ? null : $this->code,
            'description' => trim($this->description) === '' ? null : trim($this->description),
            'icon' => trim($this->icon),
            'color' => $this->color,
            'status' => $this->status,
        ];
    }
}
