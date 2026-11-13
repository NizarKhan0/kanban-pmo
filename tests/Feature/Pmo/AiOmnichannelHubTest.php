<?php

declare(strict_types=1);

namespace Tests\Feature\Pmo;

use App\Livewire\Pmo\AiOmnichannelHub;
use App\Livewire\Pmo\KanbanBoard;
use App\Models\AutomationLog;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\SavedAiPrompt;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Pmo\Ai\AiCompletion;
use App\Services\Pmo\Ai\AiProviderException;
use App\Services\Pmo\Ai\AiProviderGateway;
use App\Services\Pmo\AiPmoService;
use App\Services\Pmo\OmnichannelNotifierService;
use App\Support\TaskTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AiOmnichannelHubTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var array<string, BoardColumn> */
    private array $cols = [];

    protected function setUp(): void
    {
        parent::setUp();
        TaskTable::flush();

        config([
            'pmo.ai.api_key' => null,
            'pmo.ai.provider' => 'claude',
            'pmo.ai.model' => 'claude-sonnet-5-5',
            'pmo.telegram.enabled' => false,
            'pmo.email.enabled' => false,
            'pmo.whatsapp.enabled' => false,
        ]);
        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Livewire::test(KanbanBoard::class);
        $this->cols = BoardColumn::query()->where('user_id', $this->user->id)->get()->keyBy('name')->all();
    }

    public function test_hub_renders_channel_status_in_simulation_mode(): void
    {
        $this->get(route('pmo.hub'))
            ->assertOk()
            ->assertSee('AI &amp; Omnichannel Hub', false)
            ->assertSee('Channel Status')
            ->assertSee('AI Engine')
            ->assertSee('Telegram Bot')
            ->assertSee('Gmail SMTP')
            ->assertSee('WhatsApp Gateway')
            ->assertSee('Simulation Mode (.env Not Set)')
            ->assertSee('Execution Audit Logs')
            ->assertSee('Dispatch to Telegram')
            ->assertSee('Email Standup Report')
            ->assertSee('Share via WhatsApp')
            ->assertSee('Send Test Alert');
    }

    public function test_channel_status_turns_connected_when_env_is_set(): void
    {
        config([
            'pmo.ai.api_key' => 'sk-test',
            'pmo.telegram.enabled' => true, 'pmo.telegram.bot_token' => 't', 'pmo.telegram.chat_id' => '123',
            'pmo.email.enabled' => true, 'pmo.email.recipient' => 'pmo@example.com',
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p',
            'pmo.whatsapp.enabled' => true, 'pmo.whatsapp.gateway_url' => 'https://gw.example.com/send', 'pmo.whatsapp.default_phone' => '+60 12-345 6789',
        ]);

        $status = app(OmnichannelNotifierService::class)->channelStatus();

        foreach (['ai', 'telegram', 'gmail', 'whatsapp'] as $channel) {
            $this->assertTrue($status[$channel]['connected'], "{$channel} should be connected");
            $this->assertSame([], $status[$channel]['missing']);
        }
    }

    // ── AI: Smart Simulation ─────────────────────────────────────────

    public function test_ai_actions_are_simulated_and_logged_without_an_api_key(): void
    {
        $this->task('FPX payment integration', 'In Progress', 'high', due: Carbon::parse(Task::today())->subDays(2)->toDateString());

        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('runAi')
            ->assertSet('aiResult.status', 'simulated')
            ->assertSee('Simulation Mode')
            ->assertSee('[OVERDUE] FPX payment integration');

        $hub->call('setAction', 'risk')->call('runAi')->assertSee('RISK &amp; BLOCKER ANALYSIS', false);

        $logs = AutomationLog::query()->where('user_id', $this->user->id)->orderBy('id')->get();
        $this->assertSame(['ai:standup', 'ai:risk'], $logs->pluck('command_name')->all());
        $this->assertSame(['simulated'], $logs->pluck('status')->unique()->values()->all());
        $this->assertSame('ai_claude', $logs->first()?->channel);
        $this->assertSame('claude-sonnet-5-5', $logs->first()?->recipient);
    }

    public function test_capture_tasks_from_notes_and_add_to_board(): void
    {
        TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Afqah N.']);
        Project::query()->create(['user_id' => $this->user->id, 'name' => 'eMyHaji 2.0']);

        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('setAction', 'capture')
            ->call('runAi')
            ->assertHasErrors('aiNotes')
            ->set('aiNotes', "- Afqah to finish FPX callback handling for eMyHaji 2.0 by Friday (urgent)\n- @Haziq prepare UAT scripts next week\n- Notes:")
            ->call('runAi')
            ->assertHasNoErrors();

        $tasks = $hub->get('aiResult')['data']['tasks'];
        $this->assertCount(2, $tasks);
        $this->assertSame(['Finish FPX callback handling for eMyHaji 2.0', 'Afqah N.', 'eMyHaji 2.0', 'high'], [$tasks[0]['title'], $tasks[0]['pic'], $tasks[0]['project'], $tasks[0]['priority']]);
        $this->assertSame(Carbon::parse(Task::today())->next('Friday')->toDateString(), $tasks[0]['due_date']);
        $this->assertSame('Haziq', $tasks[1]['pic']);

        $hub->set('captureColumnId', $this->cols['To Do']->id)
            ->set('captureSelected.1', false)
            ->call('applyCapture')
            ->assertSet('aiResult', null);

        $created = Task::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame('Finish FPX callback handling for eMyHaji 2.0', $created->title);
        $this->assertSame($this->cols['To Do']->id, $created->board_column_id);
        $this->assertSame(['ai-capture'], $created->tags);
        $this->assertNotNull($created->team_member_id);
    }

    public function test_break_into_subtasks_and_improve_task(): void
    {
        $task = $this->task('fpx payment integration', 'In Progress', 'medium', due: Carbon::parse(Task::today())->subDay()->toDateString());
        $task->subtasks()->create(['title' => 'Run a security review']);

        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('setAction', 'subtasks')
            ->call('runAi')
            ->assertHasErrors('aiTaskId')
            ->set('aiTaskId', $task->id)
            ->call('runAi');

        $suggested = $hub->get('aiResult')['data']['subtasks'];
        $this->assertGreaterThanOrEqual(3, count($suggested));
        $this->assertLessThanOrEqual(7, count($suggested));
        $this->assertNotContains('Run a security review', $suggested, 'Existing subtasks are not repeated');

        $hub->call('applySubtasks');
        $this->assertSame(1 + count($suggested), $task->subtasks()->count());

        $hub->call('setAction', 'improve_task')->set('aiTaskId', $task->id)->call('runAi');
        $proposed = $hub->get('aiResult')['data']['proposed'];
        $this->assertSame('Complete fpx payment integration', $proposed['title']);
        $this->assertSame('high', $proposed['priority'], 'Overdue cards are raised to High');
        $this->assertSame(Carbon::parse(Task::today())->addDays(2)->toDateString(), $proposed['due_date']);

        $hub->call('applyImprovement');
        $task->refresh();
        $this->assertSame('Complete fpx payment integration', $task->title);
        $this->assertSame('high', $task->priority);
    }

    // ── AI: live provider (fake gateway) ─────────────────────────────

    public function test_live_answer_is_marked_sent_with_tokens(): void
    {
        $this->fakeGateway(fn (string $system, string $prompt, ?array $schema): AiCompletion => new AiCompletion(
            json_encode(['tasks' => [['title' => 'Draft SRS', 'project' => '', 'pic' => '', 'priority' => 'high', 'due_date' => '2099-01-31', 'notes' => 'from email']]]) ?: '',
            1234,
            'claude-sonnet-5-5',
        ));

        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('setAction', 'capture')
            ->set('aiNotes', 'Please draft the SRS by end of January.')
            ->call('runAi')
            ->assertSet('aiResult.status', 'sent')
            ->assertSet('aiResult.source', 'live');

        $this->assertSame('Draft SRS', $hub->get('aiResult')['data']['tasks'][0]['title']);

        $log = AutomationLog::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame(['sent', 1234], [$log->status, $log->tokens_used]);
    }

    public function test_provider_failure_is_logged_and_falls_back_to_simulation(): void
    {
        $this->fakeGateway(fn (): AiCompletion => throw new AiProviderException('The Claude API rejected the API key. Check PMO_AI_API_KEY in your .env file.'));

        Livewire::test(AiOmnichannelHub::class)
            ->call('runAi')
            ->assertSet('aiResult.status', 'failed')
            ->assertSet('aiResult.source', 'simulation')
            ->assertSee('rejected the API key')
            ->assertSee('DAILY STANDUP SUMMARY');

        $log = AutomationLog::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('PMO_AI_API_KEY', (string) $log->error_message);
    }

    public function test_copy_prompt_and_paste_json_answer(): void
    {
        $task = $this->task('Grant workflow engine', 'To Do');

        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('setAction', 'subtasks')
            ->set('aiTaskId', $task->id)
            ->call('openManual')
            ->assertSet('showManual', true);

        $this->assertStringContainsString('Grant workflow engine', $hub->get('manualPrompt'));
        $this->assertStringContainsString('JSON Schema', $hub->get('manualPrompt'));

        $hub->set('manualAnswer', 'not json at all')
            ->call('useManualAnswer')
            ->assertHasErrors('manualAnswer')
            ->set('manualAnswer', "Sure! ```json\n{\"subtasks\": [\"Model stages\", \"Build rules\", \"Notify reviewers\"]}\n```")
            ->call('useManualAnswer')
            ->assertHasNoErrors()
            ->assertSet('aiResult.source', 'manual');

        $this->assertSame(['Model stages', 'Build rules', 'Notify reviewers'], $hub->get('aiResult')['data']['subtasks']);
        $this->assertSame(0, AutomationLog::query()->count(), 'Manual answers are not provider calls');
    }

    // ── Saved prompt templates (CRUD) ────────────────────────────────

    public function test_saved_prompt_full_crud(): void
    {
        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('createPrompt')
            ->assertSet('showPromptModal', true)
            ->set('promptForm.title', '')
            ->call('savePrompt')
            ->assertHasErrors(['promptForm.title' => 'required', 'promptForm.custom_instruction' => 'required'])
            ->set('promptForm.title', 'Weekly Risk Review')
            ->set('promptForm.action_type', 'risk')
            ->set('promptForm.custom_instruction', 'Group by project.')
            ->call('savePrompt')
            ->assertHasNoErrors()
            ->assertSet('showPromptModal', false)
            ->assertSee('Weekly Risk Review');

        $prompt = SavedAiPrompt::query()->where('user_id', $this->user->id)->sole();

        $hub->call('runTemplate', $prompt->id)
            ->assertSet('aiAction', 'risk')
            ->assertSet('aiResult.status', 'simulated');
        $this->assertStringContainsString('Group by project.', (string) AutomationLog::query()->latest('id')->value('message'));

        $hub->call('editPrompt', $prompt->id)
            ->set('promptForm.title', 'Risk Review')
            ->call('savePrompt');
        $this->assertSame('Risk Review', $prompt->fresh()?->title);

        $hub->call('confirmDeletePrompt', $prompt->id)
            ->assertSet('showPromptDeleteModal', true)
            ->call('destroyPrompt');
        $this->assertModelMissing($prompt);
    }

    // ── Omnichannel notifier ─────────────────────────────────────────

    public function test_dispatch_is_simulated_when_channels_are_not_configured(): void
    {
        $hub = Livewire::test(AiOmnichannelHub::class)
            ->call('dispatchTelegram')
            ->call('dispatchEmail')
            ->set('whatsappPhone', '+60 12-345 6789')
            ->call('dispatchWhatsApp');

        $results = $hub->get('dispatchResults');
        $this->assertSame(['simulated', 'simulated', 'simulated'], array_column($results, 'status'));
        $this->assertStringStartsWith('https://wa.me/60123456789?text=', (string) $results[0]['link']);

        $this->assertSame(
            ['telegram', 'gmail', 'whatsapp'],
            AutomationLog::query()->where('user_id', $this->user->id)->orderBy('id')->pluck('channel')->all(),
        );
        Http::assertNothingSent();
    }

    public function test_live_telegram_whatsapp_and_email_dispatch(): void
    {
        config([
            'pmo.telegram.enabled' => true, 'pmo.telegram.bot_token' => 'SECRET-BOT-TOKEN', 'pmo.telegram.chat_id' => '-100123',
            'pmo.whatsapp.enabled' => true, 'pmo.whatsapp.gateway_url' => 'https://gw.example.com/send', 'pmo.whatsapp.api_token' => 'wa-token', 'pmo.whatsapp.default_phone' => '60123456789',
            'pmo.email.enabled' => true, 'pmo.email.recipient' => 'pmo@example.com',
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.username' => 'u', 'mail.mailers.smtp.password' => 'p',
        ]);
        Mail::fake();
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            'gw.example.com/*' => Http::response(['status' => 'queued']),
        ]);

        $notifier = app(OmnichannelNotifierService::class);
        $this->assertSame('sent', $notifier->sendTelegram('Hello team 👋')->status);
        $this->assertSame('sent', $notifier->sendWhatsApp('Hello team')->status);
        $this->assertSame('sent', $notifier->sendEmail('Subject', 'Body')->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org') && $request['chat_id'] === '-100123');
        Http::assertSent(fn ($request) => $request->url() === 'https://gw.example.com/send' && $request['phone'] === '60123456789' && $request->hasHeader('Authorization', 'Bearer wa-token'));

        $serialized = AutomationLog::query()->get()->toJson();
        $this->assertStringNotContainsString('SECRET-BOT-TOKEN', $serialized, 'Secrets are never written to the audit log');
        $this->assertStringNotContainsString('wa-token', $serialized);
    }

    public function test_telegram_rejection_is_logged_as_failed(): void
    {
        config(['pmo.telegram.enabled' => true, 'pmo.telegram.bot_token' => 'x', 'pmo.telegram.chat_id' => '1']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        Livewire::test(AiOmnichannelHub::class)
            ->call('sendTestAlert', 'telegram')
            ->assertSet('dispatchResults.0.status', 'failed');

        $log = AutomationLog::query()->sole();
        $this->assertSame('notify:test_alert', $log->command_name);
        $this->assertStringContainsString('chat not found', (string) $log->error_message);
    }

    // ── Artisan commands ─────────────────────────────────────────────

    public function test_send_daily_standup_command_runs_per_tenant(): void
    {
        $this->task('Owner task', 'To Do');
        $other = User::factory()->create();

        $this->artisan('pmo:send-daily-standup', ['--user' => $this->user->id])
            ->expectsOutputToContain('SIMULATED')
            ->assertSuccessful();

        $logs = AutomationLog::query()->get();
        $this->assertSame(['telegram', 'gmail', 'whatsapp'], $logs->pluck('channel')->all());
        $this->assertSame([$this->user->id], $logs->pluck('user_id')->unique()->values()->all());
        $this->assertSame(0, AutomationLog::query()->where('user_id', $other->id)->count());
        $this->assertStringContainsString('Owner task', (string) $logs->first()?->message);
    }

    public function test_check_overdue_tasks_alerts_pmo_and_pics(): void
    {
        $pic = TeamMember::query()->create(['user_id' => $this->user->id, 'name' => 'Haziq M.', 'email' => 'haziq@example.com', 'phone_whatsapp' => '+60 12-111 2222']);
        $this->task('Late regression suite', 'In Progress', 'high', $pic, Carbon::parse(Task::today())->subDays(3)->toDateString());
        $this->task('On time', 'In Progress', 'high', $pic, Carbon::parse(Task::today())->addDays(3)->toDateString());

        $this->artisan('pmo:check-overdue-tasks', ['--user' => $this->user->id, '--channel' => 'email,whatsapp'])->assertSuccessful();

        $logs = AutomationLog::query()->where('command_name', 'notify:overdue_alert')->get();
        $this->assertCount(4, $logs, 'PMO email + WhatsApp, PIC email + WhatsApp');
        $this->assertSame(['haziq@example.com', '60121112222'], $logs->whereNotNull('recipient')->pluck('recipient')->values()->all());
        $this->assertStringContainsString('[OVERDUE] Late regression suite', (string) $logs->first()?->message);
        $this->assertStringNotContainsString('On time', (string) $logs->first()?->message);
    }

    // ── Audit logs ───────────────────────────────────────────────────

    public function test_audit_log_filters_and_clear_with_confirmation(): void
    {
        $other = User::factory()->create();
        AutomationLog::query()->create(['user_id' => $other->id, 'channel' => 'telegram', 'command_name' => 'foreign', 'message' => 'x', 'status' => 'sent']);

        $make = fn (string $channel, string $status, int $daysAgo) => (new AutomationLog)->forceFill([
            'user_id' => $this->user->id, 'channel' => $channel, 'command_name' => "{$channel}-{$status}", 'message' => 'm', 'status' => $status,
            'created_at' => Carbon::now()->subDays($daysAgo), 'updated_at' => Carbon::now()->subDays($daysAgo),
        ])->save();
        $make('ai_claude', 'sent', 40);
        $make('telegram', 'failed', 1);
        $make('whatsapp', 'simulated', 1);

        $hub = Livewire::test(AiOmnichannelHub::class)
            ->assertDontSee('foreign')
            ->call('setLogChannel', 'ai');
        $this->assertSame(['ai_claude-sent'], collect($hub->instance()->logs->items())->pluck('command_name')->all());

        $hub->call('setLogChannel', 'all')->call('setLogStatus', 'failed');
        $this->assertSame(['telegram-failed'], collect($hub->instance()->logs->items())->pluck('command_name')->all());

        $hub->call('confirmClearLogs')
            ->assertSet('showClearModal', true)
            ->assertSet('clearCount', 1)
            ->call('clearLogs');

        $this->assertSame(2, AutomationLog::query()->where('user_id', $this->user->id)->count());

        $hub->call('confirmClearLogs')->set('clearRange', 'all')->assertSet('clearCount', 2)->call('clearLogs');
        $this->assertSame(0, AutomationLog::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(1, AutomationLog::query()->where('user_id', $other->id)->count());
    }

    public function test_tenant_isolation_for_templates_and_tasks(): void
    {
        $foreignUser = User::factory()->create();
        $foreignPrompt = SavedAiPrompt::query()->create(['user_id' => $foreignUser->id, 'title' => 'Foreign template', 'action_type' => 'risk', 'custom_instruction' => 'x']);
        $foreignColumn = BoardColumn::query()->create(['user_id' => $foreignUser->id, 'name' => 'Theirs']);
        $foreignTask = Task::query()->create(['user_id' => $foreignUser->id, 'board_column_id' => $foreignColumn->id, 'title' => 'Foreign task']);

        Livewire::test(AiOmnichannelHub::class)->assertDontSee('Foreign template')->assertDontSee('Foreign task');
        Livewire::test(AiOmnichannelHub::class)->call('editPrompt', $foreignPrompt->id)->assertStatus(404);
        Livewire::test(AiOmnichannelHub::class)->call('runTemplate', $foreignPrompt->id)->assertStatus(404);
        Livewire::test(AiOmnichannelHub::class)->call('setAction', 'subtasks')->set('aiTaskId', $foreignTask->id)->call('runAi')->assertStatus(404);

        $this->assertSame(0, $foreignTask->subtasks()->count());
    }

    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  \Closure(string, string, ?array<string, mixed>): AiCompletion  $handler
     */
    private function fakeGateway(\Closure $handler): void
    {
        config(['pmo.ai.api_key' => 'sk-test']);

        $this->app->instance(AiProviderGateway::class, new class($handler) extends AiProviderGateway
        {
            public function __construct(private readonly \Closure $handler) {}

            public function complete(string $system, string $prompt, ?array $schema = null): AiCompletion
            {
                return ($this->handler)($system, $prompt, $schema);
            }
        });
        $this->app->forgetInstance(AiPmoService::class);
    }

    private function task(string $title, string $column, string $priority = 'medium', ?TeamMember $member = null, ?string $due = null): Task
    {
        $col = $this->cols[$column];

        return Task::query()->create([
            'user_id' => $this->user->id,
            'board_column_id' => $col->id,
            'title' => $title,
            'priority' => $col->is_done_column ? 'done' : $priority,
            'team_member_id' => $member?->id,
            'pic_name_snapshot' => $member?->name,
            'due_date' => $due,
            'sort_order' => (int) Task::query()->where('board_column_id', $col->id)->max('sort_order') + 10,
        ]);
    }
}
