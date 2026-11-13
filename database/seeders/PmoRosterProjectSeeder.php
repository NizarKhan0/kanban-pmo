<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\Concerns\SeedsForOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Module 01 demo data: 6 projects and 4 team members for user_id = 1.
 * Idempotent — safe to run repeatedly.
 */
class PmoRosterProjectSeeder extends Seeder
{
    use SeedsForOwner;

    public function run(): void
    {
        if (! User::query()->whereKey($this->ownerId)->exists()) {
            $this->command?->warn('PmoRosterProjectSeeder skipped: user #'.$this->ownerId.' does not exist.');

            return;
        }

        DB::transaction(function (): void {
            $this->seedProjects();
            $this->seedTeamMembers();
        });

        $this->command?->info('PMO roster seeded: 6 projects, 4 team members for user #'.$this->ownerId.'.');
    }

    private function seedProjects(): void
    {
        $projects = [
            ['name' => 'eMyHaji 2.0', 'code' => 'EMH2', 'icon' => '🕋', 'color' => 'emerald', 'status' => 'active',
                'description' => 'Next-generation Hajj registration and pilgrim management portal.'],
            ['name' => 'MyResearch', 'code' => 'MYRS', 'icon' => '🔬', 'color' => 'cyan', 'status' => 'active',
                'description' => 'National research grant application and tracking platform.'],
            ['name' => 'ePJS', 'code' => 'EPJS', 'icon' => '🏛️', 'color' => 'violet', 'status' => 'active',
                'description' => 'Digital public service request and case management system.'],
            ['name' => 'MyWarga', 'code' => 'MYWG', 'icon' => '🏘️', 'color' => 'pink', 'status' => 'on_hold',
                'description' => 'Citizen community engagement and residency services app.'],
            ['name' => 'SPK-KuliM', 'code' => 'SPKK', 'icon' => '📊', 'color' => 'amber', 'status' => 'active',
                'description' => 'Performance monitoring and KPI reporting system.'],
            ['name' => 'eBantuanJKM 2.0', 'code' => 'EBJ2', 'icon' => '🤝', 'color' => 'rose', 'status' => 'completed',
                'description' => 'Welfare assistance application and disbursement tracking.'],
        ];

        foreach ($projects as $index => $project) {
            Project::query()->updateOrCreate(
                ['user_id' => $this->ownerId, 'name' => $project['name']],
                [...$project, 'sort_order' => $index + 1],
            );
        }
    }

    private function seedTeamMembers(): void
    {
        $members = [
            ['name' => 'Ikhwan A.', 'role' => 'PMO Officer', 'email' => 'ikhwan.a@example.com', 'phone_whatsapp' => '+60 12-345 6701',
                'icon' => '🧑‍💼', 'color' => 'pink', 'is_me' => true],
            ['name' => 'Afqah N.', 'role' => 'Developer', 'email' => 'afqah.n@example.com', 'phone_whatsapp' => '+60 12-345 6702',
                'icon' => '👩‍💻', 'color' => 'violet', 'is_me' => false],
            ['name' => 'Haziq M.', 'role' => 'QA / Tester', 'email' => 'haziq.m@example.com', 'phone_whatsapp' => '+60 12-345 6703',
                'icon' => '🧪', 'color' => 'cyan', 'is_me' => false],
            ['name' => 'Farah L.', 'role' => 'Business Analyst', 'email' => 'farah.l@example.com', 'phone_whatsapp' => '+60 12-345 6704',
                'icon' => '📋', 'color' => 'amber', 'is_me' => false],
        ];

        foreach ($members as $index => $member) {
            TeamMember::query()->updateOrCreate(
                ['user_id' => $this->ownerId, 'name' => $member['name']],
                [...$member, 'is_me' => false, 'is_active' => true, 'sort_order' => $index + 1],
            );
        }

        // Exactly one "Me" per user.
        TeamMember::query()
            ->where('user_id', $this->ownerId)
            ->where('name', 'Ikhwan A.')
            ->firstOrFail()
            ->setAsMe();
    }
}
