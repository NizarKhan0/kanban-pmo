<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\Concerns\SeedsForOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Module 03 demo data: 14 realistic task cards across the 5 standard columns
 * for user_id = 1, with subtasks and tags. Due dates are relative to today so
 * the board always shows a mix of overdue, due-today and upcoming work.
 * Idempotent — safe to run repeatedly.
 */
class TaskSeeder extends Seeder
{
    use SeedsForOwner;

    public function run(): void
    {
        if (! User::query()->whereKey($this->ownerId)->exists()) {
            $this->command?->warn('TaskSeeder skipped: user #'.$this->ownerId.' does not exist.');

            return;
        }

        $columns = BoardColumn::query()->where('user_id', $this->ownerId)->pluck('id', 'name');
        $doneColumns = BoardColumn::query()->where('user_id', $this->ownerId)->where('is_done_column', true)->pluck('id')->all();
        $projects = Project::query()->where('user_id', $this->ownerId)->pluck('id', 'name');
        $members = TeamMember::query()->where('user_id', $this->ownerId)->pluck('id', 'name');

        $missing = array_diff(['Backlog', 'To Do', 'In Progress', 'Review', 'Done'], $columns->keys()->all());
        if ($missing !== []) {
            $this->command?->warn('TaskSeeder skipped: run BoardColumnSeeder first (missing: '.implode(', ', $missing).').');

            return;
        }

        $today = Carbon::now((string) config('pmo.timezone', 'Asia/Kuala_Lumpur'))->startOfDay();
        $order = [];

        DB::transaction(function () use ($columns, $doneColumns, $projects, $members, $today, &$order): void {
            foreach ($this->tasks() as $row) {
                $columnId = (int) $columns[$row['column']];
                $order[$columnId] = ($order[$columnId] ?? 0) + Task::SORT_STEP;
                $isDone = in_array($columnId, $doneColumns, true);

                $task = Task::query()->updateOrCreate(
                    ['user_id' => $this->ownerId, 'title' => $row['title']],
                    [
                        'board_column_id' => $columnId,
                        'project_id' => $projects[$row['project']] ?? null,
                        'project_name_snapshot' => $row['project'],
                        'team_member_id' => $members[$row['pic']] ?? null,
                        'pic_name_snapshot' => $row['pic'],
                        'priority' => $isDone ? 'done' : $row['priority'],
                        'icon' => $row['icon'],
                        'color' => $row['color'],
                        'due_date' => $row['due'] === null ? null : $today->copy()->addDays($row['due'])->toDateString(),
                        'tags' => $row['tags'],
                        'notes' => $row['notes'] ?? null,
                        'sort_order' => $order[$columnId],
                        'completed_at' => $isDone ? $today->copy()->addDays($row['due'] ?? 0)->setTime(17, 0) : null,
                    ],
                );

                $task->subtasks()->delete();
                foreach ($row['subtasks'] as $index => [$title, $completed]) {
                    $task->subtasks()->create([
                        'title' => $title,
                        'is_completed' => $isDone || $completed,
                        'sort_order' => ($index + 1) * 10,
                    ]);
                }
            }
        });

        $this->command?->info('Tasks seeded: '.count($this->tasks()).' cards for user #'.$this->ownerId.'.');
    }

    /**
     * @return list<array{title: string, column: string, project: string, pic: string, priority: string, icon: string, color: string, due: ?int, tags: list<string>, notes?: string, subtasks: list<array{0: string, 1: bool}>}>
     */
    private function tasks(): array
    {
        return [
            // ── Backlog ─────────────────────────────────────────────
            [
                'title' => 'Define data retention policy for audit logs', 'column' => 'Backlog',
                'project' => 'ePJS', 'pic' => 'Farah L.', 'priority' => 'low', 'icon' => '🔐', 'color' => 'sky',
                'due' => 21, 'tags' => ['compliance', 'policy'],
                'subtasks' => [['Review PDPA retention requirements', false], ['Propose retention periods per log type', false]],
            ],
            [
                'title' => 'Research SSO integration with national digital ID', 'column' => 'Backlog',
                'project' => 'MyWarga', 'pic' => 'Afqah N.', 'priority' => 'medium', 'icon' => '🔐', 'color' => 'fuchsia',
                'due' => null, 'tags' => ['research', 'auth'],
                'subtasks' => [['Compare OIDC vs SAML options', false], ['Estimate integration effort', false]],
            ],
            [
                'title' => 'Draft UAT plan for Q1 release', 'column' => 'Backlog',
                'project' => 'SPK-KuliM', 'pic' => 'Haziq M.', 'priority' => 'low', 'icon' => '🧪', 'color' => 'amber',
                'due' => 30, 'tags' => ['uat', 'planning'],
                'subtasks' => [],
            ],

            // ── To Do ───────────────────────────────────────────────
            [
                'title' => 'Prepare SRS document for application module', 'column' => 'To Do',
                'project' => 'eMyHaji 2.0', 'pic' => 'Farah L.', 'priority' => 'high', 'icon' => '📄', 'color' => 'emerald',
                'due' => 3, 'tags' => ['documentation', 'srs'],
                'subtasks' => [
                    ['Gather stakeholder requirements', true],
                    ['Draft functional requirements', true],
                    ['Define non-functional requirements', false],
                    ['Internal review with PMO', false],
                    ['Sign-off from product owner', false],
                ],
            ],
            [
                'title' => 'Set up staging environment on cloud', 'column' => 'To Do',
                'project' => 'MyResearch', 'pic' => 'Afqah N.', 'priority' => 'medium', 'icon' => '🛠️', 'color' => 'cyan',
                'due' => 5, 'tags' => ['devops', 'infra'],
                'subtasks' => [['Provision VM and database', false], ['Configure CI/CD pipeline', false], ['Enable SSL certificate', false]],
            ],
            [
                'title' => 'Prepare monthly steering committee slides', 'column' => 'To Do',
                'project' => 'SPK-KuliM', 'pic' => 'Ikhwan A.', 'priority' => 'medium', 'icon' => '📊', 'color' => 'pink',
                'due' => 0, 'tags' => ['reporting', 'pmo'],
                'subtasks' => [['Collect project status updates', true], ['Update risk register summary', false]],
            ],

            // ── In Progress ─────────────────────────────────────────
            [
                'title' => 'FPX payment integration', 'column' => 'In Progress',
                'project' => 'eMyHaji 2.0', 'pic' => 'Afqah N.', 'priority' => 'high', 'icon' => '💳', 'color' => 'rose',
                'due' => -2, 'tags' => ['payment', 'integration', 'fpx'],
                'notes' => 'Blocked: awaiting production merchant credentials from the payment gateway provider.',
                'subtasks' => [
                    ['Register merchant sandbox account', true],
                    ['Implement payment request API', true],
                    ['Handle callback and reconciliation', false],
                    ['Error handling and retries', false],
                    ['Security review', false],
                ],
            ],
            [
                'title' => 'Grant application workflow engine', 'column' => 'In Progress',
                'project' => 'MyResearch', 'pic' => 'Afqah N.', 'priority' => 'high', 'icon' => '⚙️', 'color' => 'violet',
                'due' => 7, 'tags' => ['workflow', 'backend'],
                'subtasks' => [['Model approval stages', true], ['Build reviewer assignment rules', false], ['Email notifications per stage', false]],
            ],
            [
                'title' => 'Regression test suite for case management', 'column' => 'In Progress',
                'project' => 'ePJS', 'pic' => 'Haziq M.', 'priority' => 'medium', 'icon' => '🧪', 'color' => 'sky',
                'due' => -1, 'tags' => ['qa', 'automation'],
                'notes' => 'Test data refresh pending from the database team.',
                'subtasks' => [['Automate case creation flow', true], ['Automate escalation flow', false], ['Nightly test run schedule', false]],
            ],
            [
                'title' => 'Weekly PMO status report', 'column' => 'In Progress',
                'project' => 'ePJS', 'pic' => 'Ikhwan A.', 'priority' => 'low', 'icon' => '📣', 'color' => 'pink',
                'due' => 1, 'tags' => ['reporting'],
                'subtasks' => [],
            ],

            // ── Review ──────────────────────────────────────────────
            [
                'title' => 'Monthly report module UAT', 'column' => 'Review',
                'project' => 'SPK-KuliM', 'pic' => 'Haziq M.', 'priority' => 'high', 'icon' => '🧪', 'color' => 'amber',
                'due' => 2, 'tags' => ['uat', 'reporting'],
                'subtasks' => [
                    ['Prepare UAT test scripts', true],
                    ['Execute UAT with business users', true],
                    ['Log and triage defects', true],
                    ['Obtain UAT sign-off', false],
                ],
            ],
            [
                'title' => 'Citizen profile API code review', 'column' => 'Review',
                'project' => 'MyWarga', 'pic' => 'Ikhwan A.', 'priority' => 'medium', 'icon' => '🔍', 'color' => 'fuchsia',
                'due' => 4, 'tags' => ['code-review', 'api'],
                'subtasks' => [['Review validation rules', true], ['Check rate limiting', false]],
            ],

            // ── Done ────────────────────────────────────────────────
            [
                'title' => 'Project kick-off meeting with stakeholders', 'column' => 'Done',
                'project' => 'eMyHaji 2.0', 'pic' => 'Ikhwan A.', 'priority' => 'done', 'icon' => '🤝', 'color' => 'emerald',
                'due' => -10, 'tags' => ['meeting'],
                'subtasks' => [['Send meeting invitation', true], ['Circulate minutes of meeting', true]],
            ],
            [
                'title' => 'Disbursement module go-live', 'column' => 'Done',
                'project' => 'eBantuanJKM 2.0', 'pic' => 'Afqah N.', 'priority' => 'done', 'icon' => '🚀', 'color' => 'rose',
                'due' => -5, 'tags' => ['release', 'go-live'],
                'subtasks' => [['Production deployment', true], ['Post go-live monitoring', true], ['Handover to support team', true]],
            ],
        ];
    }
}
