<?php

namespace Tests\Feature;

use App\Models\PcrOutput;
use App\Models\User;
use Tests\PmsTestCase;

/**
 * An individual does not invent headings. A core commitment sits under an
 * MFO/PPA the college already named in its OPCR, so "Research" on somebody's
 * IPCR is demonstrably the college's Research programme. Support Functions stay
 * their own — the OPCR never lists "Submission of DTR".
 */
class PcrOutputLinkTest extends PmsTestCase
{
    private function college(string $opcrStatus = 'published'): array
    {
        $this->makeOrganization();

        $unit = $this->makeUnit();
        $year = $this->makeSchoolYear();

        $opcr = $this->makeForm([
            'type' => 'opcr', 'org_unit_id' => $unit->id,
            'school_year_id' => $year->id, 'status' => $opcrStatus,
        ]);
        $collegeOutput = PcrOutput::create([
            'form_id' => $opcr->id, 'section' => 'core', 'title' => 'Research',
        ]);

        $employee = User::factory()->create(['role' => 'employee', 'org_unit_id' => $unit->id]);
        $ipcr     = $this->makeForm([
            'type' => 'ipcr', 'org_unit_id' => $unit->id,
            'school_year_id' => $year->id, 'user_id' => $employee->id,
        ]);

        return compact('unit', 'year', 'opcr', 'collegeOutput', 'employee', 'ipcr');
    }

    public function test_it_lists_the_colleges_headings(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr, 'collegeOutput' => $collegeOutput] = $this->college();

        $this->actingAsUser($employee);

        $response = $this->getJson("/api/pcr-forms/{$ipcr->id}/opcr-outputs")->assertSuccessful();

        $response->assertJsonCount(1)->assertJsonPath('0.title', 'Research');
        $this->assertSame($collegeOutput->id, $response->json('0.id'));
    }

    public function test_nothing_is_listed_while_the_opcr_is_unpublished(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr] = $this->college('draft');

        $this->actingAsUser($employee);

        $this->getJson("/api/pcr-forms/{$ipcr->id}/opcr-outputs")->assertSuccessful()->assertJsonCount(0);
    }

    public function test_a_core_heading_takes_its_wording_from_the_college(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr, 'collegeOutput' => $collegeOutput] = $this->college();

        $this->actingAsUser($employee);

        $response = $this->postJson('/api/pcr-outputs', [
            'form_id'          => $ipcr->id,
            'section'          => 'core',
            'title'            => 'Something the ratee typed instead',
            'parent_output_id' => $collegeOutput->id,
        ])->assertStatus(201);

        $output = PcrOutput::find($response->json('output.id'));

        $this->assertSame('Research', $output->title);
        $this->assertSame('core', $output->section);
        $this->assertSame($collegeOutput->id, (int) $output->parent_output_id);
    }

    public function test_a_core_heading_must_answer_to_the_college(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr] = $this->college();

        $this->actingAsUser($employee);

        $this->postJson('/api/pcr-outputs', [
            'form_id' => $ipcr->id,
            'section' => 'core',
            'title'   => 'My own idea of a core function',
        ])->assertStatus(422);
    }

    public function test_support_functions_are_the_ratees_own(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr] = $this->college();

        $this->actingAsUser($employee);

        $this->postJson('/api/pcr-outputs', [
            'form_id' => $ipcr->id,
            'section' => 'support',
            'title'   => 'Submission of DTR',
        ])->assertStatus(201);
    }

    public function test_a_heading_from_another_year_is_refused(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr, 'unit' => $unit] = $this->college();

        $otherYear = $this->makeSchoolYear([
            'label' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
        ]);
        $otherOpcr = $this->makeForm([
            'type' => 'opcr', 'org_unit_id' => $unit->id,
            'school_year_id' => $otherYear->id, 'status' => 'published',
        ]);
        $foreign = PcrOutput::create([
            'form_id' => $otherOpcr->id, 'section' => 'core', 'title' => 'Research',
        ]);

        $this->actingAsUser($employee);

        $this->postJson('/api/pcr-outputs', [
            'form_id'          => $ipcr->id,
            'section'          => 'core',
            'parent_output_id' => $foreign->id,
        ])->assertStatus(422);
    }

    public function test_a_core_heading_is_free_when_the_college_named_none(): void
    {
        $this->makeOrganization();

        $unit     = $this->makeUnit();
        $year     = $this->makeSchoolYear();
        $employee = User::factory()->create(['role' => 'employee', 'org_unit_id' => $unit->id]);
        $ipcr     = $this->makeForm([
            'type' => 'ipcr', 'org_unit_id' => $unit->id,
            'school_year_id' => $year->id, 'user_id' => $employee->id,
        ]);

        $this->actingAsUser($employee);

        // No published OPCR at all, so nothing to answer to yet.
        $this->postJson('/api/pcr-outputs', [
            'form_id' => $ipcr->id,
            'section' => 'core',
            'title'   => 'Research',
        ])->assertStatus(201);
    }

    public function test_a_delegated_heading_keeps_the_colleges_wording_when_edited(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr, 'collegeOutput' => $collegeOutput] = $this->college();

        $mine = PcrOutput::create([
            'form_id'          => $ipcr->id,
            'section'          => 'core',
            'title'            => 'Research',
            'parent_output_id' => $collegeOutput->id,
        ]);

        $this->actingAsUser($employee);

        // The sheet sends the row back without the parent, which used to strip it.
        $this->postJson('/api/pcr-outputs', [
            'id'      => $mine->id,
            'form_id' => $ipcr->id,
            'section' => 'core',
            'title'   => 'Renamed to whatever I like',
        ])->assertStatus(200);

        $mine->refresh();

        $this->assertSame('Research', $mine->title);
        $this->assertSame($collegeOutput->id, (int) $mine->parent_output_id);
    }

    public function test_a_delegated_heading_cannot_be_deleted_from_the_ipcr(): void
    {
        ['employee' => $employee, 'ipcr' => $ipcr, 'collegeOutput' => $collegeOutput] = $this->college();

        $mine = PcrOutput::create([
            'form_id'          => $ipcr->id,
            'section'          => 'core',
            'title'            => 'Research',
            'parent_output_id' => $collegeOutput->id,
        ]);

        $own = PcrOutput::create([
            'form_id' => $ipcr->id, 'section' => 'support', 'title' => 'Submission of DTR',
        ]);

        $this->actingAsUser($employee);

        $this->deleteJson("/api/pcr-outputs/{$mine->id}")->assertStatus(409);
        $this->assertNotNull(PcrOutput::find($mine->id));

        $this->deleteJson("/api/pcr-outputs/{$own->id}")->assertStatus(200);
        $this->assertNull(PcrOutput::find($own->id));
    }
}
