<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Pmo;

use App\Models\TeamMember;
use App\Support\NeoPalette;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TeamMemberForm extends Form
{
    public ?int $id = null;

    public string $name = '';

    public string $role = '';

    public string $email = '';

    public string $phone_whatsapp = '';

    public string $icon = '👤';

    public string $color = 'pink';

    public bool $is_me = false;

    public bool $is_active = true;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{6,28}$/'],
            'icon' => ['required', 'string', 'max:10'],
            'color' => ['required', Rule::in(NeoPalette::keys())],
            'is_me' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'phone_whatsapp' => 'WhatsApp number',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone_whatsapp.regex' => 'Enter a valid WhatsApp number, e.g. +60 12-345 6789.',
        ];
    }

    public function fillFrom(TeamMember $member): void
    {
        $this->resetErrorBag();
        $this->id = $member->id;
        $this->name = $member->name;
        $this->role = (string) $member->role;
        $this->email = (string) $member->email;
        $this->phone_whatsapp = (string) $member->phone_whatsapp;
        $this->icon = $member->icon;
        $this->color = $member->color;
        $this->is_me = $member->is_me;
        $this->is_active = $member->is_active;
    }

    /**
     * @return array{name: string, role: ?string, email: ?string, phone_whatsapp: ?string, icon: string, color: string, is_me: bool, is_active: bool}
     */
    public function payload(): array
    {
        $this->validate();

        $nullable = static fn (string $value): ?string => trim($value) === '' ? null : trim($value);

        return [
            'name' => trim($this->name),
            'role' => $nullable($this->role),
            'email' => $nullable(strtolower($this->email)),
            'phone_whatsapp' => $nullable($this->phone_whatsapp),
            'icon' => trim($this->icon),
            'color' => $this->color,
            'is_me' => $this->is_me,
            'is_active' => $this->is_active,
        ];
    }
}
