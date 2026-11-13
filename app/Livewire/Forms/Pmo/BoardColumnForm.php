<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Pmo;

use App\Models\BoardColumn;
use Livewire\Form;

class BoardColumnForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $icon = '📋';

    public string $color = '#38bdf8';

    public int|string $wip_limit = 0;

    public bool $is_done_column = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'wip_limit' => ['required', 'integer', 'min:0', 'max:999'],
            'is_done_column' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'column name',
            'wip_limit' => 'WIP limit',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'color.regex' => 'Choose a valid hex colour, e.g. #38bdf8.',
        ];
    }

    public function fillFrom(BoardColumn $column): void
    {
        $this->resetErrorBag();
        $this->id = $column->id;
        $this->name = $column->name;
        $this->icon = $column->icon;
        $this->color = $column->color;
        $this->wip_limit = $column->wip_limit;
        $this->is_done_column = $column->is_done_column;
    }

    /**
     * @return array{name: string, icon: string, color: string, wip_limit: int, is_done_column: bool}
     */
    public function payload(): array
    {
        $this->name = trim($this->name);
        $this->icon = trim($this->icon);
        $this->color = strtolower(trim($this->color));
        $this->wip_limit = trim((string) $this->wip_limit) === '' ? 0 : trim((string) $this->wip_limit);

        $this->validate();

        return [
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'wip_limit' => (int) $this->wip_limit,
            'is_done_column' => $this->is_done_column,
        ];
    }
}
