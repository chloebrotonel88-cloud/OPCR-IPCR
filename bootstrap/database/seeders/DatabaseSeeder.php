<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\PcrForm;
use App\Models\PcrIndicator;
use App\Models\PcrOutput;
use App\Models\PcrStatusLog;
use App\Models\RatingPeriod;
use App\Models\SchoolYear;
use App\Models\User;
use App\Support\PersonName;
use App\Services\PcrAssignmentService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;

/**
 * Opol Community College as its own documents describe it: the people named in
 * the 2026 OPCR and the sample IPCRs, the offices they sit in, and the college's
 * actual commitments for the year.
 *
 * Everyone signs in with "password".
 */
class DatabaseSeeder extends Seeder
{
    private array $units = [];

    private array $people = [];

    public function run(): void
    {
        $organization = Organization::create([
            'name'       => 'Opol Community College',
            'short_name' => 'OCC',
            'code'       => 'occ',
            'address'    => 'Opol, Misamis Oriental',
            'head_title' => 'College President',
            'status'     => 'active',
        ]);

        app(CurrentOrganization::class)->set($organization->id);

        $this->offices();
        $this->accounts();
        $this->wireHeads();

        [$year, $firstPeriod] = $this->cycle();

        $this->collegeOpcr($year, $firstPeriod);
    }

    private function offices(): void
    {
        $this->units['college'] = OrgUnit::create([
            'name' => 'Opol Community College',
            'code' => 'OCC',
            'type' => 'college',
        ]);

        foreach ([
            'instruction' => ['Office of Instruction', 'INS'],
            'research'    => ['Research and Extension Office', 'RES'],
            'extension'   => ['Extension and Community Services', 'EXT'],
            'library'     => ['Library', 'LIB'],
            'bsit'        => ['BS Information Technology', 'BSIT'],
            'registrar'   => ['Office of the Registrar', 'REG'],
            'linkages'    => ['International Affairs and Linkages', 'IAL'],
        ] as $key => [$name, $code]) {
            $this->units[$key] = OrgUnit::create([
                'name'      => $name,
                'code'      => $code,
                'type'      => $key === 'bsit' ? 'program' : 'office',
                'parent_id' => $this->units['college']->id,
            ]);
        }
    }

    private function accounts(): void
    {
        // [key, name, email, role, position, unit]
        $roster = [
            ['admin', 'System Administrator', 'admin@occ.edu.ph', 'admin', 'System Administrator', 'college'],

            ['bation', 'Neilson D. Bation, DM', 'president@occ.edu.ph', 'president', 'College President', 'college'],
            ['canay', 'Dr. Wenie Rose D. Canay', 'qa@occ.edu.ph', 'qa', 'Director for Quality Assurance', 'college'],
            ['gurrea', 'Dr. Alma T. Gurrea', 'vpial@occ.edu.ph', 'vp', 'Vice President for International Affairs and Linkages', 'college'],

            ['pinto', 'Ronnel D. Pinto', 'research.head@occ.edu.ph', 'program_head', 'Research Coordinator', 'research'],
            ['arao', 'Dr. Dolorita N. Arao', 'extension.head@occ.edu.ph', 'program_head', 'Extension Coordinator', 'extension'],
            ['madriaga', 'Ruben Madriaga', 'bsit.head@occ.edu.ph', 'program_head', 'Program Head, BSIT', 'bsit'],
            ['arancon', 'Dr. Rodelia T. Arancon', 'library.head@occ.edu.ph', 'program_head', 'College Librarian', 'library'],
            ['nacua', 'Bernadeth T. Nacua', 'registrar@occ.edu.ph', 'program_head', 'College Registrar', 'registrar'],

            ['demetrio', 'Juanito R. Demetrio', 'demetrio@occ.edu.ph', 'employee', 'Research Associate', 'research'],
            ['verula', 'Annabelle T. Verula', 'verula@occ.edu.ph', 'employee', 'Guidance and Testing Officer', 'registrar'],
            ['iyo', 'Gaudencio Iyo Jr.', 'iyo@occ.edu.ph', 'employee', 'Information Systems Officer', 'bsit'],
            ['vacalares', 'Sophomore T. Vacalares', 'vacalares@occ.edu.ph', 'employee', 'Laboratory Head', 'bsit'],
            ['pacana', 'Amabelle Pacana', 'pacana@occ.edu.ph', 'employee', 'Instructor I', 'instruction'],
            ['magnetico', 'Daceil B. Magnetico', 'magnetico@occ.edu.ph', 'employee', 'Instructor I', 'instruction'],
            ['puertos', 'John Rey Puertos', 'puertos@occ.edu.ph', 'employee', 'Job Placement Officer', 'instruction'],
        ];

        foreach ($roster as [$key, $name, $email, $role, $position, $unit]) {
            $this->people[$key] = User::create(PersonName::parse($name) + [
                'email'          => $email,
                'password'       => 'password',
                'role'           => $role,
                'position_title' => $position,
                'org_unit_id'    => $this->units[$unit]->id,
            ]);
        }
    }

    /** Who heads each office, and which VP it answers to. */
    private function wireHeads(): void
    {
        $vp = $this->people['gurrea']->id;

        foreach ([
            'research'    => 'pinto',
            'extension'   => 'arao',
            'bsit'        => 'madriaga',
            'library'     => 'arancon',
            'registrar'   => 'nacua',
            'instruction' => 'madriaga',
        ] as $unit => $head) {
            $this->units[$unit]->update([
                'head_user_id' => $this->people[$head]->id,
                'vp_user_id'   => $vp,
            ]);
        }
    }

    private function cycle(): array
    {
        $year = SchoolYear::create([
            'label'      => '2026',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
            'status'     => 'open',
            'is_active'  => true,
        ]);

        $first = RatingPeriod::create([
            'school_year_id' => $year->id,
            'seq'            => 1,
            'label'          => 'January to June',
            'opens_at'       => '2026-06-01',
            'closes_at'      => '2026-06-30',
            'status'         => 'open',
            'is_active'      => true,
        ]);

        RatingPeriod::create([
            'school_year_id' => $year->id,
            'seq'            => 2,
            'label'          => 'July to December',
            'opens_at'       => '2026-12-01',
            'closes_at'      => '2026-12-31',
            'status'         => 'upcoming',
        ]);

        return [$year, $first];
    }

    /**
     * The college's 2026 commitments, taken from the OPCR the president signed.
     * Published, so everyone's IPCR has something to answer to.
     */
    private function collegeOpcr(SchoolYear $year, RatingPeriod $period): void
    {
        $assignments = app(PcrAssignmentService::class);

        \Illuminate\Support\Facades\Auth::login($this->people['bation']);

        $opcr = PcrForm::create([
            'type'           => 'opcr',
            'school_year_id' => $year->id,
            'org_unit_id'    => $this->units['college']->id,
            'status'         => 'published',
        ]);

        // Explicit: the create above goes through the model's default status.
        $opcr->forceFill(['status' => 'published'])->save();

        PcrStatusLog::record($opcr->id, null, 'draft', 'Form created');
        PcrStatusLog::record($opcr->id, 'approved', 'published', 'Targets approved and published');

        // [section, MFO/PPA, [[indicator, accountable keys]]]
        $sheet = [
            ['strategic', 'Digitalization', [
                ['75% implementation of an online entrance exam system that provides immediate results to all examinees from January to December 2026.', ['bation', 'verula']],
                ['Accommodate 80% of enrollees through the online enrollment system from January to December 2026.', ['bation', 'nacua']],
                ['Achieve 70% participation of the total student population in the online faculty evaluation from January to December 2026.', ['bation', 'canay']],
                ['70% of the total student population can access the online grading system from January to December 2026.', ['bation', 'iyo']],
                ['70% of the total student population can access the online library system from January to December 2026.', ['bation', 'arancon']],
            ]],
            ['strategic', 'Employability Preparation', [
                ['70% of the graduating students participate in the job fair programme hosted by OCC and/or other partner institutions.', ['puertos', 'verula']],
            ]],
            ['strategic', 'Modernization', [
                ['Accommodate 80% of the total Information Technology students in the laboratories from January to December 2026.', ['vacalares', 'madriaga']],
            ]],

            ['core', 'Instruction', [
                ['Increase of enrolment by 5% from January to December 2026 compared to the previous year.', ['madriaga', 'pacana']],
                ['Increase of student retention by 5% from January to December 2026 compared to the previous year.', ['madriaga', 'magnetico']],
                ['Increase of faculty retention rate by 5% from January to December 2026 compared to the previous year.', ['madriaga', 'canay']],
                ['Apply 1 additional programme or course from January to December 2026.', ['madriaga', 'pacana']],
                ['Achieve the National Passing Rate for board courses from January to December 2026.', ['canay', 'magnetico']],
                ['Comply with 5% of the required number of new book collections for each programme from the total library holdings.', ['arancon']],
                ['Provide an 8-hour faculty development training programme from January to December 2026.', ['canay']],
            ]],
            ['core', 'Research', [
                ['Publish 25 peer-reviewed researches, journals or articles from January to December 2026.', ['pinto', 'demetrio']],
                ['Conduct 1 research conference from January to December 2026.', ['pinto', 'demetrio']],
            ]],
            ['core', 'Extension', [
                ['2 signed MOU/MOA with local linkages from January to December 2026.', ['arao']],
                ['Adopt 2 communities from January to December 2026.', ['arao']],
                ['2 signed MOU/MOA with international linkages from January to December 2026.', ['gurrea']],
            ]],
            ['core', 'Production', [
                ['Utilise 2 capstone or research projects adopted or used by any institution or establishment.', ['madriaga', 'pinto']],
            ]],

            ['support', 'Actions on matters referred by the LCE', [
                ['Acted on matters referred to by the LCE or an authorised representative within the reglementary period from receipt, with 100% accuracy.', ['bation']],
            ]],
            ['support', 'Compliance to regulatory orders', [
                ['Regulatory orders issued by a competent authority are complied with within the given period, with 100% accuracy.', ['bation']],
            ]],
            ['support', 'Committee membership', [
                ['Attended activities such as regular meetings in other committee memberships.', ['bation']],
            ]],
            ['support', 'MANCOM meetings', [
                ['Attended all Management Committee meetings.', ['bation']],
            ]],
            ['support', 'Budget Utilization Rate', [
                ['100% compliance with the required budget utilisation rate.', ['bation']],
            ]],
        ];

        $order = 0;

        foreach ($sheet as [$section, $title, $lines]) {
            $output = PcrOutput::create([
                'form_id'    => $opcr->id,
                'section'    => $section,
                'title'      => $title,
                'sort_order' => ++$order,
            ]);

            foreach ($lines as $index => [$description, $accountableKeys]) {
                $line = PcrIndicator::create([
                    'output_id'        => $output->id,
                    'rating_period_id' => $period->id,
                    'description'      => "<p>{$description}</p>",
                    'sort_order'       => $index + 1,
                ]);

                // Who is accountable is a real assignment, not a typed name —
                // it opens each person's IPCR with the line already in it.
                foreach ($accountableKeys as $key) {
                    $assignee = $this->people[$key];

                    if ((int) $assignee->id === (int) $this->people['bation']->id) {
                        continue;   // the president owns the OPCR itself
                    }

                    $assignments->assignIndicator($line, $assignee, $this->people['bation']);
                }
            }
        }

        \Illuminate\Support\Facades\Auth::logout();
    }
}
