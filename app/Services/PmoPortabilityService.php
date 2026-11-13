<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Pmo\PortabilityException;
use App\Models\ActivityLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\WorkspaceBackup;
use App\Support\NeoPalette;
use Database\Seeders\ActivityLogSeeder;
use Database\Seeders\BoardColumnSeeder;
use Database\Seeders\PmoRosterProjectSeeder;
use Database\Seeders\TaskSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data portability for one workspace (Auth::id()): JSON export/import compatible
 * with the original single-file Kanban_PMO_Neo.html, CSV export, internal
 * snapshots, restore and reset-to-sample. API keys are never exported.
 *
 * Import, restore and reset REPLACE the board (projects, PICs, columns, tasks,
 * subtasks, activity). An automatic safety snapshot is taken first, so every
 * replacement can be undone. AI templates, automation logs and snapshots are kept.
 *
 * @phpstan-type NormalisedCard array{title: string, project: string, pic: string, prio: string, due: ?string, subtasks: list<array{title: string, done: bool}>, tags: list<string>, notes: ?string, icon: string, color: string, created: ?Carbon, updated: ?Carbon, completed: ?Carbon}
 * @phpstan-type NormalisedColumn array{name: string, color: string, wip: int, icon: string, is_done: bool, cards: list<NormalisedCard>}
 * @phpstan-type NormalisedPerson array{name: string, role: ?string, email: ?string, phone: ?string, icon: string, color: string, is_me: bool, active: bool}
 * @phpstan-type NormalisedProject array{name: string, code: ?string, description: ?string, icon: string, color: string, status: string}
 * @phpstan-type NormalisedActivity array{who: string, what: string, task: string, meta: ?string, at: Carbon}
 * @phpstan-type Normalised array{me: ?string, people: list<NormalisedPerson>, projects: list<NormalisedProject>, columns: list<NormalisedColumn>, activity: list<NormalisedActivity>}
 */
final class PmoPortabilityService
{
    public const MAX_UPLOAD_KB = 5120;

    private const MAX_COLUMNS = 50;

    private const MAX_CARDS = 5000;

    private const MAX_SUBTASKS = 50;

    private const MAX_ACTIVITY = 500;

    /** Original HTML app: a column is "done" when its name starts with one of these. */
    private const DONE_NAME = '/^(done|siap|selesai|complete)/i';

    /** Original HTML activity verbs → Laravel action types. */
    private const VERBS = [
        'created' => ActivityLog::CREATED,
        'updated' => ActivityLog::UPDATED,
        'moved' => ActivityLog::MOVED,
        'deleted' => ActivityLog::DELETED,
        'col_added' => ActivityLog::COL_ADDED,
        'colAdded' => ActivityLog::COL_ADDED,
        'pic_added' => ActivityLog::PIC_ADDED,
        'picAdded' => ActivityLog::PIC_ADDED,
        'picRenamed' => ActivityLog::UPDATED,
        'picDeleted' => ActivityLog::DELETED,
        'note' => ActivityLog::NOTE,
    ];

    // ──────────────────────────────────────────────────────────────────
    // Export
    // ──────────────────────────────────────────────────────────────────

    /**
     * Whole board in the Kanban_PMO_Neo JSON format (a compatible superset).
     *
     * @return array<string, mixed>
     */
    public function exportToJson(string $theme = 'dark'): array
    {
        $owner = $this->ownerId();

        $members = TeamMember::query()->where('user_id', $owner)->orderBy('sort_order')->orderBy('name')->get();
        $projects = Project::query()->where('user_id', $owner)->orderBy('sort_order')->orderBy('name')->get();
        $columns = BoardColumn::query()
            ->where('user_id', $owner)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['tasks' => fn ($q) => $q->where('user_id', $owner)->orderBy('sort_order')->orderBy('id')->with(['subtasks', 'project', 'teamMember'])])
            ->get();
        $activity = ActivityLog::query()->where('user_id', $owner)->latest('created_at')->latest('id')->limit(self::MAX_ACTIVITY)->get();

        return [
            'v' => 1,
            'format' => 'kanban-pmo-laravel',
            'schema' => 2,
            'app' => 'Kanban PMO',
            'exported_at' => Carbon::now()->toIso8601String(),
            'lang' => 'en',
            'theme' => in_array($theme, ['dark', 'light'], true) ? $theme : 'dark',
            'me' => $members->firstWhere('is_me', true)->name ?? Auth::user()?->name,
            // Provider and model only: API keys and endpoints are never exported.
            'ai' => ['prov' => (string) config('pmo.ai.provider', 'claude'), 'model' => (string) config('pmo.ai.model', ''), 'ep' => ''],
            'people' => $members->map(fn (TeamMember $m): array => [
                'name' => $m->name,
                'role' => (string) $m->role,
                'email' => $m->email,
                'phone' => $m->phone_whatsapp,
                'icon' => $m->icon,
                'color' => $m->color,
                'is_me' => $m->is_me,
                'active' => $m->is_active,
            ])->values()->all(),
            'projects' => $projects->map(fn (Project $p): array => [
                'name' => $p->name,
                'code' => $p->code,
                'description' => $p->description,
                'icon' => $p->icon,
                'color' => $p->color,
                'status' => $p->status,
            ])->values()->all(),
            'columns' => $columns->map(fn (BoardColumn $c): array => [
                'id' => 'c'.$c->id,
                'name' => $c->name,
                'color' => $c->color,
                'wip' => $c->wip_limit,
                'icon' => $c->icon,
                'is_done' => $c->is_done_column,
                'cards' => $c->tasks->map(fn (Task $t): array => $this->exportCard($t))->values()->all(),
            ])->values()->all(),
            'activity' => $activity->map(fn (ActivityLog $a): array => [
                'who' => $a->actor_name,
                'what' => $a->action_type,
                'task' => $a->task_title,
                'meta' => $a->meta_details,
                'ts' => (int) ($a->created_at?->getTimestampMs() ?? 0),
            ])->values()->all(),
            'filters' => ['project' => '', 'pic' => '', 'prio' => '', 'due' => '', 'sort' => 'manual', 'q' => ''],
            'view' => 'board',
        ];
    }

    public function exportJsonString(string $theme = 'dark'): string
    {
        return $this->encode($this->exportToJson($theme));
    }

    /**
     * CSV of every card, UTF-8 with BOM (opens correctly in Excel).
     */
    public function exportToCsv(): string
    {
        $owner = $this->ownerId();
        $rows = [['Column', 'Title', 'Project', 'Assignee', 'Priority', 'Due Date', 'Subtasks Done', 'Subtasks Total', 'Tags', 'Notes']];

        $columns = BoardColumn::query()
            ->where('user_id', $owner)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['tasks' => fn ($q) => $q->where('user_id', $owner)->orderBy('sort_order')->orderBy('id')->with(['subtasks', 'project', 'teamMember'])])
            ->get();

        foreach ($columns as $column) {
            foreach ($column->tasks as $task) {
                [$done, $total] = $task->subtaskCounts();
                $rows[] = [
                    $this->csvText($column->name),
                    $this->csvText($task->title),
                    $this->csvText((string) $task->projectLabel()),
                    $this->csvText((string) $task->picLabel()),
                    Task::PRIORITIES[$task->priority] ?? $task->priority,
                    (string) $task->due_date?->toDateString(),
                    (string) $done,
                    (string) $total,
                    $this->csvText(implode('|', $task->tags ?? [])),
                    $this->csvText((string) preg_replace('/\R/u', ' ', (string) $task->notes)),
                ];
            }
        }

        $csv = implode("\r\n", array_map(
            fn (array $row): string => implode(',', array_map(fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"', $row)),
            $rows,
        ));

        return "\u{FEFF}".$csv;
    }

    public function exportFilename(string $extension): string
    {
        return 'Kanban_PMO_Neo_'.Carbon::now((string) config('pmo.timezone'))->format('Y-m-d').'.'.$extension;
    }

    // ──────────────────────────────────────────────────────────────────
    // Import
    // ──────────────────────────────────────────────────────────────────

    /**
     * Replace the board with a JSON payload (original HTML or Laravel format).
     *
     * @param  array<mixed>  $jsonPayload
     * @return array{columns: int, tasks: int, people: int, projects: int, activity: int, theme: ?string}
     *
     * @throws PortabilityException
     */
    public function importFromJson(array $jsonPayload): array
    {
        $data = $this->normalise($jsonPayload);
        $owner = $this->ownerId();

        DB::transaction(function () use ($data, $owner): void {
            $this->wipe($owner);
            $this->insert($owner, $data);
        });

        return [
            'columns' => count($data['columns']),
            'tasks' => array_sum(array_map(fn (array $c): int => count($c['cards']), $data['columns'])),
            'people' => count($data['people']),
            'projects' => count($data['projects']),
            'activity' => count($data['activity']),
            'theme' => in_array($jsonPayload['theme'] ?? null, ['dark', 'light'], true) ? (string) $jsonPayload['theme'] : null,
        ];
    }

    /**
     * Decode an uploaded file's contents.
     *
     * @return array<mixed>
     *
     * @throws PortabilityException
     */
    public function decode(string $contents): array
    {
        if (strlen($contents) > self::MAX_UPLOAD_KB * 1024) {
            throw new PortabilityException('The file is larger than 5 MB.');
        }

        $contents = (string) preg_replace('/^\x{FEFF}/u', '', $contents);
        $decoded = json_decode($contents, true, 64);

        if (! is_array($decoded)) {
            throw new PortabilityException('The file is not valid JSON.');
        }

        return $decoded;
    }

    // ──────────────────────────────────────────────────────────────────
    // Snapshots (WorkspaceBackup CRUD + restore)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, WorkspaceBackup>
     */
    public function snapshots(): Collection
    {
        return WorkspaceBackup::query()
            ->where('user_id', $this->ownerId())
            ->select(['id', 'user_id', 'filename', 'file_size_kb', 'columns_count', 'tasks_count', 'notes', 'created_at', 'updated_at'])
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    public function findSnapshot(int $id): WorkspaceBackup
    {
        return WorkspaceBackup::query()
            ->where('user_id', $this->ownerId())
            ->findOrFail($id);
    }

    /**
     * Create a snapshot of the current board, or of a given (already validated) payload.
     *
     * @param  array<mixed>|null  $payload
     */
    public function createSnapshot(?string $notes = null, ?array $payload = null, ?string $filename = null): WorkspaceBackup
    {
        $payload ??= $this->exportToJson();
        $json = $this->encode($payload);
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];

        return WorkspaceBackup::query()->create([
            'user_id' => $this->ownerId(),
            'filename' => Str::limit($filename ?? $this->snapshotFilename(), 250, ''),
            'file_size_kb' => round(strlen($json) / 1024, 2),
            'columns_count' => count($columns),
            // Count only cards that import would keep (titled cards).
            'tasks_count' => array_sum(array_map(
                fn ($c): int => is_array($c) && is_array($c['cards'] ?? null)
                    ? count(array_filter($c['cards'], fn ($k): bool => is_array($k) && $this->str($k['title'] ?? '', 255) !== ''))
                    : 0,
                $columns,
            )),
            'payload_json' => $json,
            'notes' => $notes === null || trim($notes) === '' ? null : Str::limit(trim($notes), 250, ''),
        ]);
    }

    /**
     * Validate an uploaded JSON file and store it as a snapshot (does not touch the board).
     *
     * @throws PortabilityException
     */
    public function storeUpload(string $contents, string $originalName, ?string $notes = null): WorkspaceBackup
    {
        $payload = $this->decode($contents);
        $this->normalise($payload); // validates; throws on unusable files

        $name = Str::limit(trim(basename($originalName)) ?: 'upload.json', 200, '');

        return $this->createSnapshot($notes ?? 'Imported from '.$name, $payload, $name);
    }

    public function updateSnapshotNotes(int $id, ?string $notes): WorkspaceBackup
    {
        $snapshot = $this->findSnapshot($id);
        $snapshot->update(['notes' => $notes === null || trim($notes) === '' ? null : Str::limit(trim($notes), 250, '')]);

        return $snapshot;
    }

    public function deleteSnapshot(int $id): WorkspaceBackup
    {
        $snapshot = $this->findSnapshot($id);
        $snapshot->delete();

        return $snapshot;
    }

    public function snapshotJson(int $id): string
    {
        return $this->findSnapshot($id)->payload_json;
    }

    /**
     * Restore the board from a snapshot. A safety snapshot of the current board is taken first.
     *
     * @return array{columns: int, tasks: int, people: int, projects: int, activity: int, theme: ?string, safety_id: int}
     *
     * @throws PortabilityException
     */
    public function restoreSnapshot(int $id): array
    {
        $snapshot = $this->findSnapshot($id);
        $payload = $this->decode($snapshot->payload_json);
        $this->normalise($payload); // fail before taking the safety snapshot

        $safety = $this->createSnapshot('Auto-backup before restoring “'.Str::limit($snapshot->notes ?? $snapshot->filename, 120).'”');

        return [...$this->importFromJson($payload), 'safety_id' => $safety->id];
    }

    /**
     * Replace the board with the default sample workspace (safety snapshot first).
     *
     * @return array{columns: int, tasks: int, safety_id: int}
     */
    public function resetToSample(): array
    {
        $owner = $this->ownerId();
        $safety = $this->createSnapshot('Auto-backup before reset to sample data');

        DB::transaction(function () use ($owner): void {
            $this->wipe($owner);

            (new PmoRosterProjectSeeder)->forOwner($owner)->run();
            (new BoardColumnSeeder)->forOwner($owner)->run();
            (new TaskSeeder)->forOwner($owner)->run();
            (new ActivityLogSeeder)->forOwner($owner)->run();
        });

        $stats = $this->currentStats();

        return ['columns' => $stats['columns'], 'tasks' => $stats['tasks'], 'safety_id' => $safety->id];
    }

    /**
     * @return array{columns: int, tasks: int, people: int, projects: int}
     */
    public function currentStats(): array
    {
        $owner = $this->ownerId();

        return [
            'columns' => BoardColumn::query()->where('user_id', $owner)->count(),
            'tasks' => Task::query()->where('user_id', $owner)->count(),
            'people' => TeamMember::query()->where('user_id', $owner)->count(),
            'projects' => Project::query()->where('user_id', $owner)->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Normalisation (both formats → one validated shape)
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  array<mixed>  $p
     * @return Normalised
     *
     * @throws PortabilityException
     */
    public function normalise(array $p): array
    {
        if (! isset($p['columns']) || ! is_array($p['columns']) || $p['columns'] === []) {
            throw new PortabilityException('This file is not a Kanban PMO backup: no board columns were found.');
        }
        if (count($p['columns']) > self::MAX_COLUMNS) {
            throw new PortabilityException('The backup has more than '.self::MAX_COLUMNS.' columns.');
        }

        $columns = [];
        $cardTotal = 0;
        foreach (array_values($p['columns']) as $i => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $name = $this->str($raw['name'] ?? '', 100) ?: 'Column '.($i + 1);
            $cards = [];
            foreach (is_array($raw['cards'] ?? null) ? $raw['cards'] : [] as $card) {
                if (is_array($card) && ($normalised = $this->normaliseCard($card)) !== null) {
                    $cards[] = $normalised;
                }
            }
            $cardTotal += count($cards);

            $columns[] = [
                'name' => $name,
                'color' => $this->hex($raw['color'] ?? null) ?? '#a78bfa',
                'wip' => max(0, min(999, (int) ($raw['wip'] ?? $raw['wip_limit'] ?? 0))),
                'icon' => $this->str($raw['icon'] ?? '', 10) ?: (BoardColumn::DEFAULTS[$i]['icon'] ?? '📋'),
                'is_done' => array_key_exists('is_done', $raw) ? (bool) $raw['is_done'] : (bool) preg_match(self::DONE_NAME, $name),
                'cards' => $cards,
            ];
        }

        if ($columns === []) {
            throw new PortabilityException('This file is not a Kanban PMO backup: no valid columns were found.');
        }
        if ($cardTotal > self::MAX_CARDS) {
            throw new PortabilityException('The backup has more than '.number_format(self::MAX_CARDS).' cards.');
        }

        $me = $this->str($p['me'] ?? '', 255) ?: null;
        $people = $this->normalisePeople($p['people'] ?? null, $me, $columns);
        $me ??= collect($people)->firstWhere('is_me', true)['name'] ?? null;

        return [
            'me' => $me,
            'people' => $people,
            'projects' => $this->normaliseProjects($p['projects'] ?? null, $columns),
            'columns' => $columns,
            'activity' => $this->normaliseActivity($p['activity'] ?? null),
        ];
    }

    /**
     * @param  array<mixed>  $card
     * @return NormalisedCard|null
     */
    private function normaliseCard(array $card): ?array
    {
        $title = $this->str($card['title'] ?? '', 255);
        if ($title === '') {
            return null;
        }

        $prio = is_string($card['prio'] ?? null) ? $card['prio'] : (is_string($card['priority'] ?? null) ? $card['priority'] : 'medium');

        // Laravel format carries subtask titles; the original HTML only carries counts.
        $subtasks = [];
        if (is_array($card['subtasks'] ?? null)) {
            foreach ($card['subtasks'] as $sub) {
                $subTitle = $this->str(is_array($sub) ? ($sub['title'] ?? '') : $sub, 255);
                if ($subTitle !== '') {
                    $subtasks[] = ['title' => $subTitle, 'done' => is_array($sub) && (bool) ($sub['done'] ?? $sub['is_completed'] ?? false)];
                }
            }
        } else {
            $total = max(0, min(self::MAX_SUBTASKS, (int) ($card['subT'] ?? 0)));
            $done = max(0, min($total, (int) ($card['subD'] ?? 0)));
            for ($n = 1; $n <= $total; $n++) {
                $subtasks[] = ['title' => "Checklist item {$n}", 'done' => $n <= $done];
            }
        }

        $tags = [];
        foreach (is_array($card['tags'] ?? null) ? $card['tags'] : [] as $tag) {
            $tag = $this->str($tag, 30);
            if ($tag !== '' && ! in_array(mb_strtolower($tag), array_map(mb_strtolower(...), $tags), true)) {
                $tags[] = $tag;
            }
        }

        return [
            'title' => $title,
            'project' => $this->str($card['project'] ?? '', 255),
            'pic' => $this->str($card['pic'] ?? '', 255),
            'prio' => array_key_exists($prio, Task::PRIORITIES) ? $prio : 'medium',
            'due' => $this->date($card['due'] ?? $card['due_date'] ?? null),
            'subtasks' => array_slice($subtasks, 0, self::MAX_SUBTASKS),
            'tags' => array_slice($tags, 0, 10),
            'notes' => $this->str($card['notes'] ?? '', 5000) ?: null,
            'icon' => $this->str($card['icon'] ?? '', 10) ?: '📌',
            'color' => $this->palette($card['color'] ?? null, 'violet'),
            'created' => $this->timestamp($card['created'] ?? null),
            'updated' => $this->timestamp($card['updated'] ?? null),
            'completed' => $this->timestamp($card['completed'] ?? null),
        ];
    }

    /**
     * Roster from the file, or — like the original app — derived from "me" and card PICs.
     *
     * @param  list<NormalisedColumn>  $columns
     * @return list<NormalisedPerson>
     */
    private function normalisePeople(mixed $raw, ?string $me, array $columns): array
    {
        $candidates = [];

        if (is_array($raw) && $raw !== []) {
            foreach ($raw as $person) {
                if (! is_array($person)) {
                    continue;
                }
                $name = $this->str($person['name'] ?? '', 255);
                $email = $this->str($person['email'] ?? '', 255);
                $phone = $this->str($person['phone'] ?? $person['phone_whatsapp'] ?? '', 30);
                $candidates[] = [
                    'name' => $name,
                    'role' => $this->str($person['role'] ?? '', 255) ?: null,
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null,
                    'phone' => preg_match('/^\+?[0-9][0-9\s\-]{6,28}$/', $phone) ? $phone : null,
                    'icon' => $this->str($person['icon'] ?? '', 10) ?: '👤',
                    'color' => $this->palette($person['color'] ?? null, 'pink'),
                    'is_me' => (bool) ($person['is_me'] ?? false) || ($me !== null && mb_strtolower($name) === mb_strtolower($me)),
                    'active' => (bool) ($person['active'] ?? $person['is_active'] ?? true),
                ];
            }
        } else {
            // Like the original app: "me" first, then every PIC named on a card.
            $names = $me === null ? [] : [$me];
            foreach ($columns as $column) {
                foreach ($column['cards'] as $card) {
                    $names[] = $card['pic'];
                }
            }
            foreach ($names as $name) {
                $isMe = $me !== null && $name === $me;
                $candidates[] = ['name' => $name, 'role' => $isMe ? 'PMO Officer' : null, 'email' => null, 'phone' => null, 'icon' => '👤', 'color' => 'pink', 'is_me' => $isMe, 'active' => true];
            }
        }

        $people = $this->uniqueByName($candidates);

        // Exactly one "Me".
        $meFound = false;
        foreach ($people as $i => $person) {
            $people[$i]['is_me'] = $person['is_me'] && ! $meFound;
            $meFound = $meFound || $person['is_me'];
        }

        return $people;
    }

    /**
     * Projects from the file plus any project named on a card (the original app had no project list).
     *
     * @param  list<NormalisedColumn>  $columns
     * @return list<NormalisedProject>
     */
    private function normaliseProjects(mixed $raw, array $columns): array
    {
        $candidates = [];

        foreach (is_array($raw) ? $raw : [] as $project) {
            if (! is_array($project)) {
                continue;
            }
            $status = is_string($project['status'] ?? null) ? $project['status'] : 'active';
            $candidates[] = [
                'name' => $this->str($project['name'] ?? '', 255),
                'code' => $this->str($project['code'] ?? '', 20) ?: null,
                'description' => $this->str($project['description'] ?? '', 2000) ?: null,
                'icon' => $this->str($project['icon'] ?? '', 10) ?: '📁',
                'color' => $this->palette($project['color'] ?? null, 'violet'),
                'status' => array_key_exists($status, Project::STATUSES) ? $status : 'active',
            ];
        }

        foreach ($columns as $column) {
            foreach ($column['cards'] as $card) {
                $candidates[] = ['name' => $card['project'], 'code' => null, 'description' => null, 'icon' => '📁', 'color' => 'violet', 'status' => 'active'];
            }
        }

        $projects = $this->uniqueByName($candidates);

        // Codes must be unique per workspace.
        $codes = [];
        foreach ($projects as $i => $project) {
            $code = $project['code'] === null ? null : strtoupper($project['code']);
            if ($code !== null && isset($codes[$code])) {
                $code = null;
            }
            $projects[$i]['code'] = $code;
            if ($code !== null) {
                $codes[$code] = true;
            }
        }

        return $projects;
    }

    /**
     * @return list<NormalisedActivity>
     */
    private function normaliseActivity(mixed $raw): array
    {
        $activity = [];

        foreach (is_array($raw) ? array_slice($raw, 0, self::MAX_ACTIVITY) : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $task = $this->str($entry['task'] ?? '', 250);
            if ($task === '') {
                continue;
            }
            $what = is_string($entry['what'] ?? null) ? $entry['what'] : 'updated';

            $activity[] = [
                'who' => $this->str($entry['who'] ?? '', 255) ?: 'Imported',
                'what' => self::VERBS[$what] ?? (array_key_exists($what, ActivityLog::ACTIONS) ? $what : ActivityLog::UPDATED),
                'task' => $task,
                'meta' => $this->str($entry['meta'] ?? '', 250) ?: null,
                'at' => $this->timestamp($entry['ts'] ?? null) ?? Carbon::now(),
            ];
        }

        return $activity;
    }

    /**
     * Keep the first item per name (case-insensitive), dropping empty names.
     *
     * @template T of array{name: string}
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function uniqueByName(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $name = $item['name'];
            $key = mb_strtolower($name);

            if ($name !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    // ──────────────────────────────────────────────────────────────────
    // Persistence
    // ──────────────────────────────────────────────────────────────────

    private function wipe(int $owner): void
    {
        Task::query()->where('user_id', $owner)->delete();        // subtasks cascade
        BoardColumn::query()->where('user_id', $owner)->delete();
        TeamMember::query()->where('user_id', $owner)->delete();
        Project::query()->where('user_id', $owner)->delete();
        ActivityLog::query()->where('user_id', $owner)->delete();
    }

    /**
     * @param  Normalised  $data
     */
    private function insert(int $owner, array $data): void
    {
        $projectIds = [];
        foreach ($data['projects'] as $i => $project) {
            $model = Project::query()->create([...$project, 'user_id' => $owner, 'sort_order' => $i + 1]);
            $projectIds[mb_strtolower($model->name)] = $model;
        }

        $memberIds = [];
        foreach ($data['people'] as $i => $person) {
            $model = TeamMember::query()->create([
                'user_id' => $owner,
                'name' => $person['name'],
                'role' => $person['role'],
                'email' => $person['email'],
                'phone_whatsapp' => $person['phone'],
                'icon' => $person['icon'],
                'color' => $person['color'],
                'is_me' => $person['is_me'],
                'is_active' => $person['active'],
                'sort_order' => $i + 1,
            ]);
            $memberIds[mb_strtolower($model->name)] = $model;
        }

        Task::withoutTimestamps(function () use ($owner, $data, $projectIds, $memberIds): void {
            foreach ($data['columns'] as $c => $column) {
                $columnModel = BoardColumn::query()->create([
                    'user_id' => $owner,
                    'name' => $column['name'],
                    'icon' => $column['icon'],
                    'color' => $column['color'],
                    'wip_limit' => $column['wip'],
                    'is_done_column' => $column['is_done'],
                    'sort_order' => ($c + 1) * BoardColumn::SORT_STEP,
                ]);

                foreach ($column['cards'] as $k => $card) {
                    $project = $card['project'] === '' ? null : ($projectIds[mb_strtolower($card['project'])] ?? null);
                    $member = $card['pic'] === '' ? null : ($memberIds[mb_strtolower($card['pic'])] ?? null);
                    $created = $card['created'] ?? Carbon::now();
                    $isDone = $column['is_done'] || $card['prio'] === 'done';

                    $task = new Task;
                    $task->forceFill([
                        'user_id' => $owner,
                        'board_column_id' => $columnModel->id,
                        'project_id' => $project?->id,
                        'project_name_snapshot' => $card['project'] === '' ? null : ($project->name ?? $card['project']),
                        // PIC History SOP: a PIC who is not on the roster keeps their name as a snapshot.
                        'team_member_id' => $member?->id,
                        'pic_name_snapshot' => $card['pic'] === '' ? null : ($member->name ?? $card['pic']),
                        'title' => $card['title'],
                        'priority' => $column['is_done'] ? 'done' : $card['prio'],
                        'icon' => $card['icon'],
                        'color' => $card['color'],
                        'due_date' => $card['due'],
                        'tags' => $card['tags'] === [] ? null : $card['tags'],
                        'notes' => $card['notes'],
                        'sort_order' => ($k + 1) * Task::SORT_STEP,
                        'completed_at' => $isDone ? ($card['completed'] ?? $card['updated'] ?? $created) : null,
                        'created_at' => $created,
                        'updated_at' => $card['updated'] ?? $created,
                    ])->save();

                    foreach ($card['subtasks'] as $s => $sub) {
                        Subtask::query()->create([
                            'task_id' => $task->id,
                            'title' => $sub['title'],
                            'is_completed' => $sub['done'],
                            'sort_order' => ($s + 1) * 10,
                        ]);
                    }
                }
            }
        });

        ActivityLog::withoutTimestamps(function () use ($owner, $data): void {
            foreach ($data['activity'] as $entry) {
                (new ActivityLog)->forceFill([
                    'user_id' => $owner,
                    'actor_name' => $entry['who'],
                    'action_type' => $entry['what'],
                    'task_title' => $entry['task'],
                    'meta_details' => $entry['meta'],
                    'created_at' => $entry['at'],
                    'updated_at' => $entry['at'],
                ])->save();
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function exportCard(Task $t): array
    {
        [$done, $total] = $t->subtaskCounts();

        return [
            'id' => 't'.$t->id,
            'title' => $t->title,
            'project' => (string) $t->projectLabel(),
            'pic' => (string) $t->picLabel(),
            'prio' => $t->priority,
            'due' => (string) $t->due_date?->toDateString(),
            'subD' => $done,
            'subT' => $total,
            'subtasks' => $t->subtasks->map(fn (Subtask $s): array => ['title' => $s->title, 'done' => $s->is_completed])->values()->all(),
            'tags' => $t->tags ?? [],
            'notes' => (string) $t->notes,
            'icon' => $t->icon,
            'color' => $t->color,
            'created' => (int) ($t->created_at?->getTimestampMs() ?? 0),
            'updated' => (int) ($t->updated_at?->getTimestampMs() ?? 0),
            'completed' => $t->completed_at?->getTimestampMs(),
        ];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function snapshotFilename(): string
    {
        return 'Kanban_PMO_Neo_'.Carbon::now((string) config('pmo.timezone'))->format('Y-m-d_His').'.json';
    }

    /**
     * Neutralise spreadsheet formula injection (=, +, -, @ …) in text cells.
     */
    private function csvText(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function str(mixed $value, int $max): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value)), 0, $max);
    }

    private function hex(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value), $m)) {
            return null;
        }

        $hex = strtolower($m[1]);

        return '#'.(strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex);
    }

    private function palette(mixed $value, string $default): string
    {
        return is_string($value) && in_array($value, NeoPalette::keys(), true) ? $value : $default;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }

    /**
     * Millisecond epoch (original app) or ISO-8601 string → Carbon.
     */
    private function timestamp(mixed $value): ?Carbon
    {
        if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
            $ms = (int) $value;

            return $ms > 0 && $ms < 32503680000000 ? Carbon::createFromTimestampMs($ms) : null;
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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
