<?php

namespace Tests;

use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\PcrForm;
use App\Models\PcrIndicator;
use App\Models\PcrOutput;
use App\Models\RatingPeriod;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\CurrentOrganization;
use Laravel\Passport\Passport;

abstract class PmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function actingAsRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => $role], $attributes));

        return $this->actingAsUser($user);
    }

    protected function actingAsUser(User $user): User
    {
        Passport::actingAs($user, [], 'api');

        // A request resolves the organization from the signed-in user; tests
        // switch users mid-test, so drop the memoised value with them.
        app(CurrentOrganization::class)->forget();

        return $user;
    }

    protected function makeOrganization(array $attributes = []): Organization
    {
        $organization = Organization::create(array_merge([
            'name'   => 'Opol Community College',
            'code'   => 'occ',
            'status' => 'active',
        ], $attributes));

        app(CurrentOrganization::class)->set($organization->id);

        return $organization;
    }

    protected function makeUnit(array $attributes = []): OrgUnit
    {
        return OrgUnit::create(array_merge([
            'name' => 'BS Information Technology',
            'code' => 'BSIT',
            'type' => 'program',
        ], $attributes));
    }

    protected function makeSchoolYear(array $attributes = []): SchoolYear
    {
        return SchoolYear::create(array_merge([
            'label'      => 'SY 2026-2027',
            'start_date' => '2026-08-01',
            'end_date'   => '2027-07-31',
            'status'     => 'open',
            'is_active'  => true,
        ], $attributes));
    }

    protected function makePeriod(SchoolYear $year, int $seq = 1, string $status = 'open'): RatingPeriod
    {
        return RatingPeriod::create([
            'school_year_id' => $year->id,
            'seq'            => $seq,
            'label'          => $seq === 1 ? 'Mid-year Review' : 'End-year Review',
            'status'         => $status,
            'is_active'      => $seq === 1,
        ]);
    }

    protected function makeForm(array $attributes = []): PcrForm
    {
        return PcrForm::create(array_merge(['type' => 'ipcr', 'status' => 'draft'], $attributes));
    }

    protected function makeIndicator(PcrForm $form, string $section = 'core', array $attributes = []): PcrIndicator
    {
        $output = PcrOutput::create([
            'form_id' => $form->id,
            'section' => $section,
            'title'   => 'Research',
        ]);

        return PcrIndicator::create(array_merge([
            'output_id'   => $output->id,
            'description' => 'Publish 25 peer-reviewed articles from January to December 2026.',
        ], $attributes));
    }
}
