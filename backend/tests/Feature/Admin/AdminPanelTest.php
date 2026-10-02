<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    private function admin(string $role = User::ROLE_SUPER_ADMIN): User
    {
        $user = User::create(['name' => 'Admin Uji', 'username' => 'admin'.$role, 'password' => 'password']);
        $user->assignRole($role);

        return $user;
    }

    public function test_login_page_and_web_login(): void
    {
        $this->makeProject();
        $this->admin();

        $this->get('/login')->assertOk()->assertSee('Selamat datang');
        $this->post('/login', ['username' => 'adminsuper_admin', 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk();
    }

    public function test_employee_account_cannot_open_web_admin(): void
    {
        $employee = $this->makeEmployee($this->makeProject());

        $this->post('/login', ['username' => $employee->user->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_all_pages_render_for_super_admin(): void
    {
        $project = $this->makeProject();
        $this->makeEmployee($project, ['is_team_leader' => true]);
        $this->actingAs($this->admin());

        foreach (['/', '/attendances', '/approvals', '/employees', '/shifts', '/locations', '/holidays', '/settings/project',
            '/settings/project?tab=signatories', '/positions', '/projects', '/users', '/settings/app'] as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/attendances/data', '/employees/data', '/approvals/leaves', '/approvals/offsite', '/users/data'] as $url) {
            $this->getJson($url.'?draw=1&start=0&length=10')->assertOk()->assertJsonStructure(['data', 'recordsTotal']);
        }
    }

    public function test_create_employee_with_account_and_assignment(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        $this->postJson('/employees', [
            'full_name' => 'Rina Kartika', 'title_suffix' => 'S.T.', 'position_id' => 'Ahli K3',
            'username' => 'rina.kartika', 'password' => 'rahasia123',
            'shift_id' => $project->defaultShift()->id, 'start_date' => '2026-10-01', 'is_team_leader' => '1',
        ])->assertOk();

        $employee = Employee::where('full_name', 'Rina Kartika')->sole();
        $this->assertSame('Ahli K3', $employee->position->name);
        $this->assertTrue($employee->user->hasRole(User::ROLE_TEAM_LEADER));
        $this->assertTrue($project->employees()->whereKey($employee->id)->exists());
    }

    public function test_manual_correction_handles_checkout_after_midnight(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'project_id' => $project->id, 'work_date' => '2026-10-02',
            'check_in_at' => '2026-10-02 08:00:00', 'status' => 'hadir', 'flags' => ['missing_checkout'],
        ]);
        $this->actingAs($this->admin());

        $this->putJson("/attendances/{$attendance->id}", [
            'status' => 'hadir', 'check_in_time' => '08:00', 'check_out_time' => '01:00', 'note' => 'Lupa check-out, pulang jam 1 pagi',
        ])->assertOk();

        $attendance->refresh();
        $this->assertSame('2026-10-03 01:00:00', $attendance->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(17 * 60, $attendance->duration_minutes);
        $this->assertNull($attendance->flags);
        $this->assertTrue($attendance->is_manual);
    }

    public function test_project_admin_is_limited_to_assigned_projects(): void
    {
        $mine = $this->makeProject();
        $other = $this->makeProject();
        $admin = $this->admin(User::ROLE_PROJECT_ADMIN);
        $admin->managedProjects()->attach($mine->id);
        $this->actingAs($admin);

        $this->get('/employees')->assertOk();
        $this->post("/projects/{$other->id}/switch")->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/settings/app')->assertForbidden();
    }

    public function test_team_leader_can_monitor_but_not_manage(): void
    {
        $project = $this->makeProject();
        $leader = $this->makeEmployee($project, ['is_team_leader' => true]);
        $leader->user->assignRole(User::ROLE_TEAM_LEADER);
        $this->actingAs($leader->user);

        $this->get('/')->assertOk();
        $this->get('/attendances')->assertOk();
        $this->get('/approvals')->assertOk();
        $this->get('/employees')->assertForbidden();
        $this->get('/settings/project')->assertForbidden();
    }

    public function test_project_settings_are_saved_with_proper_types(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        $this->putJson('/settings/project/location', [
            'allow_offsite' => '1', 'offsite_scope' => 'all', 'offsite_requires_approval' => '0', 'offsite_requires_note' => '1',
            'max_gps_accuracy_m' => '40', 'block_mock_location' => '1', 'require_checkout_in_location' => '0', 'max_work_hours' => '24',
        ])->assertOk();

        $s = app(SettingService::class)->project($project);
        $this->assertTrue($s['allow_offsite']);
        $this->assertFalse($s['require_checkout_in_location']);
        $this->assertSame(24, $s['max_work_hours']);
    }
}
