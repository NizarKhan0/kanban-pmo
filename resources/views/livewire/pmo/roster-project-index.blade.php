@php
    use App\Models\Project;
    use App\Models\TeamMember;
    use App\Support\NeoPalette;

    $kpis = $this->kpis;
    $field = 'w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-[#8b5cf6] focus:outline-none focus:ring-2 focus:ring-[#8b5cf6]/30 dark:border-white/10 dark:bg-[#07040f] dark:text-white dark:placeholder-zinc-500';
    $label = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400';
    $error = 'mt-1.5 text-xs font-medium text-rose-600 dark:text-rose-400';
    $panel = 'rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#0d0722]';
    $btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#ff2e9a] to-[#8b5cf6] px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-[#ff2e9a]/20 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#ff2e9a]/50 disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-100 dark:border-white/15 dark:text-zinc-200 dark:hover:bg-white/5';
    $iconBtn = 'inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition';
    $badge = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset';
@endphp

<div class="mx-auto w-full max-w-7xl space-y-8">

    {{-- ═══════════════ Header ═══════════════ --}}
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#ff2e9a]">PMO Command Center</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Projects &amp; Team Roster</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Manage your project portfolio and the people in charge of delivering it.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="createMember" class="{{ $btnGhost }}">
                <span aria-hidden="true">👤</span> Add Team Member
            </button>
            <button type="button" wire:click="createProject" class="{{ $btnPrimary }}">
                <span aria-hidden="true">＋</span> New Project
            </button>
        </div>
    </header>

    {{-- ═══════════════ KPI Cards ═══════════════ --}}
    <section class="grid gap-4 sm:grid-cols-3" aria-label="Key metrics">
        @foreach ([
            ['label' => 'Total Projects', 'value' => $kpis['total_projects'], 'hint' => $kpis['active_projects'].' active', 'icon' => '📁', 'accent' => 'from-[#8b5cf6]'],
            ['label' => 'Active PICs', 'value' => $kpis['active_pics'], 'hint' => $kpis['total_pics'].' on roster', 'icon' => '🧑‍💼', 'accent' => 'from-[#ff2e9a]'],
            ['label' => 'Overloaded PICs', 'value' => $kpis['overloaded_pics'], 'hint' => $kpis['threshold'].'+ open tasks', 'icon' => '🔥', 'accent' => $kpis['overloaded_pics'] > 0 ? 'from-rose-500' : 'from-emerald-500'],
        ] as $card)
            <div class="{{ $panel }} relative overflow-hidden p-5">
                <div class="pointer-events-none absolute -right-8 -top-8 size-28 rounded-full bg-gradient-to-br {{ $card['accent'] }} to-transparent opacity-20 blur-xl"></div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <span class="text-xl" aria-hidden="true">{{ $card['icon'] }}</span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-8 xl:grid-cols-5">

        {{-- ═══════════════ Team Roster & PICs ═══════════════ --}}
        <section class="space-y-4 xl:col-span-2" aria-labelledby="roster-heading">
            <div class="flex items-center justify-between">
                <div>
                    <h2 id="roster-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">Team Roster &amp; PICs</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Active Workload per person in charge</p>
                </div>
                <button type="button" wire:click="createMember" class="{{ $iconBtn }} text-[#8b5cf6] hover:bg-[#8b5cf6]/10">＋ Add</button>
            </div>

            <div class="{{ $panel }} divide-y divide-zinc-100 dark:divide-white/5">
                @forelse ($this->members as $member)
                    @php
                        $stats = $this->workload[$member->id];
                        $overloaded = $member->is_active && $stats['open'] >= $kpis['threshold'];
                    @endphp
                    <article wire:key="member-{{ $member->id }}" @class(['p-4 transition hover:bg-zinc-50 dark:hover:bg-white/[0.02]', 'opacity-60' => ! $member->is_active])>
                        <div class="flex items-start gap-3">
                            <div class="relative shrink-0">
                                <div class="flex size-11 items-center justify-center rounded-xl text-sm font-bold text-white {{ NeoPalette::swatch($member->color) }}">
                                    {{ $member->initials() }}
                                </div>
                                <span class="absolute -bottom-1 -right-1 text-sm" aria-hidden="true">{{ $member->icon }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <h3 class="truncate font-semibold text-zinc-900 dark:text-white">{{ $member->name }}</h3>
                                    @if ($member->is_me)
                                        <span class="{{ $badge }} bg-emerald-500/15 text-emerald-700 ring-emerald-500/40 dark:text-emerald-300">Me</span>
                                    @endif
                                    @unless ($member->is_active)
                                        <span class="{{ $badge }} bg-zinc-500/15 text-zinc-600 ring-zinc-500/30 dark:text-zinc-400">Inactive</span>
                                    @endunless
                                    @if ($overloaded)
                                        <span class="{{ $badge }} bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300">Overloaded</span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $member->role ?: 'No role assigned' }}</p>

                                <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    @if ($member->email)
                                        <a href="mailto:{{ $member->email }}" class="truncate text-zinc-600 hover:text-[#8b5cf6] dark:text-zinc-300">✉️ {{ $member->email }}</a>
                                    @endif
                                    @if ($member->whatsappUrl())
                                        <a href="{{ $member->whatsappUrl() }}" target="_blank" rel="noopener" class="text-zinc-600 hover:text-emerald-500 dark:text-zinc-300">💬 {{ $member->phone_whatsapp }}</a>
                                    @endif
                                </div>

                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    <span class="{{ $badge }} bg-[#8b5cf6]/10 text-violet-700 ring-[#8b5cf6]/30 dark:text-violet-300">Active {{ $stats['open'] }}</span>
                                    <span class="{{ $badge }} bg-emerald-500/10 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300">Completed {{ $stats['done'] }}</span>
                                    <span @class([$badge, 'bg-rose-500/15 text-rose-700 ring-rose-500/40 dark:text-rose-300' => $stats['overdue'] > 0, 'bg-zinc-500/10 text-zinc-500 ring-zinc-500/20 dark:text-zinc-400' => $stats['overdue'] === 0])>Overdue {{ $stats['overdue'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap justify-end gap-1">
                            @unless ($member->is_me)
                                <button type="button" wire:click="setAsMe({{ $member->id }})" wire:loading.attr="disabled" class="{{ $iconBtn }} text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400">✔ Set as Me</button>
                            @endunless
                            <button type="button" wire:click="editMember({{ $member->id }})" class="{{ $iconBtn }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5">✏️ Edit</button>
                            <button type="button" wire:click="confirmDeleteMember({{ $member->id }})" class="{{ $iconBtn }} text-rose-600 hover:bg-rose-500/10 dark:text-rose-400">🗑 Delete</button>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center">
                        <p class="text-3xl" aria-hidden="true">👥</p>
                        <p class="mt-2 font-semibold text-zinc-900 dark:text-white">No team members yet</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Add your first PIC to start assigning work.</p>
                        <button type="button" wire:click="createMember" class="{{ $btnPrimary }} mt-4">Add Team Member</button>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ═══════════════ Project Portfolio ═══════════════ --}}
        <section class="space-y-4 xl:col-span-3" aria-labelledby="portfolio-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="portfolio-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">Project Portfolio</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">All projects tracked by this workspace</p>
                </div>
                <nav class="flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1 dark:border-white/10 dark:bg-[#07040f]" aria-label="Filter by status">
                    @foreach (['all' => 'All'] + $statuses as $key => $text)
                        <button
                            type="button"
                            wire:key="status-tab-{{ $key }}"
                            wire:click="setProjectStatus('{{ $key }}')"
                            @class([
                                'rounded-lg px-2.5 py-1 text-xs font-semibold transition',
                                'bg-white text-zinc-900 shadow-sm dark:bg-[#8b5cf6]/25 dark:text-white' => $projectStatus === $key,
                                'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $projectStatus !== $key,
                            ])
                        >
                            {{ $text }} <span class="tabular-nums opacity-60">{{ $this->projectCounts[$key] }}</span>
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @forelse ($this->projects as $project)
                    <article wire:key="project-{{ $project->id }}" class="{{ $panel }} group relative flex flex-col overflow-hidden p-5 transition hover:border-[#8b5cf6]/40">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ NeoPalette::bar($project->color) }}"></div>

                        <div class="flex items-start gap-3">
                            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl text-xl ring-1 ring-inset {{ NeoPalette::badge($project->color) }}" aria-hidden="true">
                                {{ $project->icon }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate font-semibold text-zinc-900 dark:text-white">{{ $project->name }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    @if ($project->code)
                                        <span class="font-mono text-[11px] font-semibold text-zinc-500 dark:text-zinc-400">{{ $project->code }}</span>
                                    @endif
                                    <span class="{{ $badge }} {{ $project->statusBadge() }}">{{ $project->statusLabel() }}</span>
                                </div>
                            </div>
                        </div>

                        <p class="mt-3 line-clamp-2 flex-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $project->description ?: 'No description provided.' }}
                        </p>

                        <div class="mt-4 flex justify-end gap-1 border-t border-zinc-100 pt-3 dark:border-white/5">
                            <button type="button" wire:click="editProject({{ $project->id }})" class="{{ $iconBtn }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5">✏️ Edit</button>
                            <button type="button" wire:click="confirmDeleteProject({{ $project->id }})" class="{{ $iconBtn }} text-rose-600 hover:bg-rose-500/10 dark:text-rose-400">🗑 Delete</button>
                        </div>
                    </article>
                @empty
                    <div class="{{ $panel }} p-10 text-center sm:col-span-2">
                        <p class="text-3xl" aria-hidden="true">📁</p>
                        <p class="mt-2 font-semibold text-zinc-900 dark:text-white">
                            {{ $projectStatus === 'all' ? 'No projects yet' : 'No '.strtolower($statuses[$projectStatus] ?? '').' projects' }}
                        </p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Create a project to organise tasks on the Kanban board.</p>
                        <button type="button" wire:click="createProject" class="{{ $btnPrimary }} mt-4">New Project</button>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- ═══════════════ Project Modal (Create / Edit) ═══════════════ --}}
    <x-pmo.modal
        show="showProjectModal"
        :title="$projectForm->id ? 'Edit Project' : 'New Project'"
        :subtitle="$projectForm->id ? 'Update the project details below.' : 'Add a project to your portfolio.'"
    >
        <form wire:submit="saveProject" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="project-name" class="{{ $label }}">Project Name</label>
                    <input id="project-name" type="text" wire:model="projectForm.name" class="{{ $field }}" placeholder="e.g. eMyHaji 2.0" maxlength="255">
                    @error('projectForm.name') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="project-code" class="{{ $label }}">Code</label>
                    <input id="project-code" type="text" wire:model="projectForm.code" class="{{ $field }} font-mono uppercase" placeholder="EMH2" maxlength="20">
                    @error('projectForm.code') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="project-description" class="{{ $label }}">Description</label>
                <textarea id="project-description" wire:model="projectForm.description" rows="3" class="{{ $field }}" placeholder="Short summary of scope and objectives"></textarea>
                @error('projectForm.description') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="project-status" class="{{ $label }}">Status</label>
                <select id="project-status" wire:model="projectForm.status" class="{{ $field }}">
                    @foreach ($statuses as $key => $text)
                        <option value="{{ $key }}">{{ $text }}</option>
                    @endforeach
                </select>
                @error('projectForm.status') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="{{ $label }}">Icon</span>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach (Project::ICONS as $icon)
                        <button type="button" wire:key="p-icon-{{ $loop->index }}" x-on:click="$wire.set('projectForm.icon', @js($icon), false)"
                            class="flex size-9 items-center justify-center rounded-lg text-lg ring-1 transition"
                            x-bind:class="$wire.projectForm.icon === @js($icon) ? 'ring-2 ring-[#ff2e9a] bg-[#ff2e9a]/10' : 'ring-zinc-200 hover:bg-zinc-100 dark:ring-white/10 dark:hover:bg-white/5'">{{ $icon }}</button>
                    @endforeach
                    <input type="text" wire:model="projectForm.icon" class="{{ $field }} !w-16 text-center" maxlength="10" aria-label="Custom icon">
                </div>
                @error('projectForm.icon') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="{{ $label }}">Colour</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (NeoPalette::COLORS as $key => $text)
                        <button type="button" wire:key="p-color-{{ $key }}" x-on:click="$wire.set('projectForm.color', @js($key), false)" title="{{ $text }}" aria-label="{{ $text }}"
                            class="size-8 rounded-full transition {{ NeoPalette::swatch($key) }}"
                            x-bind:class="$wire.projectForm.color === @js($key) ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-[#0d0722]' : 'opacity-70 hover:opacity-100'"></button>
                    @endforeach
                </div>
                @error('projectForm.color') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled" wire:target="saveProject">
                    <span wire:loading.remove wire:target="saveProject">{{ $projectForm->id ? 'Save Changes' : 'Create Project' }}</span>
                    <span wire:loading wire:target="saveProject">Saving…</span>
                </button>
            </div>
        </form>
    </x-pmo.modal>

    {{-- ═══════════════ Team Member Modal (Create / Edit) ═══════════════ --}}
    <x-pmo.modal
        show="showMemberModal"
        :title="$memberForm->id ? 'Edit Team Member' : 'Add Team Member'"
        :subtitle="$memberForm->id ? 'Update this PIC\'s profile and contact channels.' : 'Register a new PIC for task assignment and notifications.'"
    >
        <form wire:submit="saveMember" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="member-name" class="{{ $label }}">Full Name</label>
                    <input id="member-name" type="text" wire:model="memberForm.name" class="{{ $field }}" placeholder="e.g. Ikhwan A." maxlength="255">
                    @error('memberForm.name') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="member-role" class="{{ $label }}">Role</label>
                    <input id="member-role" type="text" wire:model="memberForm.role" class="{{ $field }}" placeholder="e.g. PMO Officer" maxlength="255">
                    @error('memberForm.role') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="member-email" class="{{ $label }}">Email (Gmail)</label>
                    <input id="member-email" type="email" wire:model="memberForm.email" class="{{ $field }}" placeholder="name@gmail.com" maxlength="255">
                    @error('memberForm.email') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="member-phone" class="{{ $label }}">WhatsApp Number</label>
                    <input id="member-phone" type="tel" wire:model="memberForm.phone_whatsapp" class="{{ $field }}" placeholder="+60 12-345 6789" maxlength="30">
                    @error('memberForm.phone_whatsapp') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <span class="{{ $label }}">Icon</span>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach (TeamMember::ICONS as $icon)
                        <button type="button" wire:key="m-icon-{{ $loop->index }}" x-on:click="$wire.set('memberForm.icon', @js($icon), false)"
                            class="flex size-9 items-center justify-center rounded-lg text-lg ring-1 transition"
                            x-bind:class="$wire.memberForm.icon === @js($icon) ? 'ring-2 ring-[#ff2e9a] bg-[#ff2e9a]/10' : 'ring-zinc-200 hover:bg-zinc-100 dark:ring-white/10 dark:hover:bg-white/5'">{{ $icon }}</button>
                    @endforeach
                    <input type="text" wire:model="memberForm.icon" class="{{ $field }} !w-16 text-center" maxlength="10" aria-label="Custom icon">
                </div>
                @error('memberForm.icon') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="{{ $label }}">Colour</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (NeoPalette::COLORS as $key => $text)
                        <button type="button" wire:key="m-color-{{ $key }}" x-on:click="$wire.set('memberForm.color', @js($key), false)" title="{{ $text }}" aria-label="{{ $text }}"
                            class="size-8 rounded-full transition {{ NeoPalette::swatch($key) }}"
                            x-bind:class="$wire.memberForm.color === @js($key) ? 'ring-2 ring-offset-2 ring-zinc-900 dark:ring-white dark:ring-offset-[#0d0722]' : 'opacity-70 hover:opacity-100'"></button>
                    @endforeach
                </div>
                @error('memberForm.color') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                    <input type="checkbox" wire:model="memberForm.is_me" class="mt-0.5 size-4 rounded accent-[#ff2e9a]">
                    <span>
                        <span class="block text-sm font-semibold text-zinc-900 dark:text-white">This is me</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">Links your account to this PIC.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                    <input type="checkbox" wire:model="memberForm.is_active" class="mt-0.5 size-4 rounded accent-[#8b5cf6]">
                    <span>
                        <span class="block text-sm font-semibold text-zinc-900 dark:text-white">Active</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">Available for new task assignments.</span>
                    </span>
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled" wire:target="saveMember">
                    <span wire:loading.remove wire:target="saveMember">{{ $memberForm->id ? 'Save Changes' : 'Add Member' }}</span>
                    <span wire:loading wire:target="saveMember">Saving…</span>
                </button>
            </div>
        </form>
    </x-pmo.modal>

    {{-- ═══════════════ Delete Confirmation Modal ═══════════════ --}}
    <x-pmo.modal
        show="showDeleteModal"
        :title="$deleteType === 'member' ? 'Confirm Removal' : 'Confirm Deletion'"
        max-width="max-w-md"
    >
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
                <span class="text-xl" aria-hidden="true">⚠️</span>
                <div class="text-sm text-zinc-700 dark:text-zinc-200">
                    @if ($deleteType === 'member')
                        You are about to remove <strong class="text-zinc-900 dark:text-white">{{ $deleteName }}</strong> from the team roster.
                    @else
                        You are about to delete the project <strong class="text-zinc-900 dark:text-white">{{ $deleteName }}</strong>.
                    @endif
                    This action cannot be undone.
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Active Tasks</dt>
                    <dd @class(['mt-1 text-2xl font-bold tabular-nums', 'text-rose-600 dark:text-rose-400' => $deleteOpenTasks > 0, 'text-zinc-900 dark:text-white' => $deleteOpenTasks === 0])>{{ $deleteOpenTasks }}</dd>
                </div>
                <div class="rounded-xl border border-zinc-200 p-3 dark:border-white/10">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total Task Cards</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $deleteTotalTasks }}</dd>
                </div>
            </dl>

            <div class="flex items-start gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-300">
                <span aria-hidden="true">🛡️</span>
                <p>
                    @if ($deleteType === 'member')
                        Task cards will <strong>not</strong> be deleted. They will become unassigned and keep "{{ $deleteName }}" as the historical PIC name until reassigned.
                    @else
                        Task cards will <strong>not</strong> be deleted. They will be detached from this project and keep "{{ $deleteName }}" as the historical project name.
                    @endif
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" wire:click="closeModals" class="{{ $btnGhost }}">Cancel</button>
                <button type="button" wire:click="destroy" wire:loading.attr="disabled" wire:target="destroy"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-500 disabled:opacity-60">
                    <span wire:loading.remove wire:target="destroy">{{ $deleteType === 'member' ? 'Remove Member' : 'Delete Project' }}</span>
                    <span wire:loading wire:target="destroy">Deleting…</span>
                </button>
            </div>
        </div>
    </x-pmo.modal>
</div>
