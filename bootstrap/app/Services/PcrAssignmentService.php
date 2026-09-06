<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\PcrForm;
use App\Models\PcrIndicator;
use App\Models\PcrOutput;
use App\Models\PcrStatusLog;
use App\Models\RatingPeriod;
use App\Models\User;
use App\Support\Html;
use Illuminate\Support\Facades\DB;

/**
 * Delegation. A commitment cascades: an office target is handed to a head, who
 * breaks it into sub-tasks and hands those to faculty, who may delegate again.
 *
 * Assigning writes the sub-task straight into the assignee's own IPCR, already
 * tied to the line above it, so the chain cannot be left half-connected by
 * somebody forgetting to link it by hand.
 */
class PcrAssignmentService
{
    /**
     * Hand over a heading. The assignee gets the MFO/PPA in their own IPCR and
     * writes their own success indicators under it — the president names what
     * the college is committing to, the head decides how their office delivers it.
     */
    public function assignOutput(PcrOutput $parent, User $assignee, User $actor, ?int $periodId = null): PcrOutput
    {
        return DB::transaction(function () use ($parent, $assignee, $actor, $periodId) {
            $form   = $this->formFor($assignee, $parent->form, $periodId ?? $this->defaultPeriodFor($parent->form));
            $output = $this->outputFor($form, $parent, $actor);

            $this->announce($assignee, $actor, $form, $parent->title);

            ActivityLog::record(
                'PcrOutput',
                $output->id,
                'assign',
                "{$parent->title} assigned to {$assignee->name}"
            );

            return $output;
        });
    }

    /**
     * Hand over one line. The assignee gets the same heading plus that specific
     * commitment, seeded with its wording for them to reword as their own.
     */
    public function assignIndicator(
        PcrIndicator $parent,
        User $assignee,
        User $actor,
        ?int $periodId = null,
        bool $announce = true
    ): PcrIndicator {
        return DB::transaction(function () use ($parent, $assignee, $actor, $periodId, $announce) {
            $parentOutput = $parent->output;
            $period       = $periodId
                ?? $parent->rating_period_id
                ?? $this->defaultPeriodFor($parentOutput->form);
            $form         = $this->formFor($assignee, $parentOutput->form, $period);
            $output       = $this->outputFor($form, $parentOutput, $actor);

            $child = PcrIndicator::create([
                'output_id'           => $output->id,
                'rating_period_id'    => $period,
                'parent_indicator_id' => $parent->id,
                'description'         => $parent->description,
                'target_date'         => $parent->target_date,
                'assigned_by'         => $actor->id,
                'assigned_by_name'    => $actor->name,
                'sort_order'          => (PcrIndicator::where('output_id', $output->id)->max('sort_order') ?? 0) + 1,
            ]);

            if ($announce) {
                $this->announce($assignee, $actor, $form, $parent->description);
            }

            ActivityLog::record(
                'PcrIndicator',
                $child->id,
                'assign',
                "Assigned to {$assignee->name}: " . mb_substr(Html::toText($parent->description), 0, 120)
            );

            app(IndicatorProgressService::class)->recalculateFrom($child);

            return $child;
        });
    }

    private function announce(User $assignee, User $actor, PcrForm $form, string $what): void
    {
        Notification::send(
            $assignee->id,
            'assignment',
            "{$actor->name} assigned you a commitment",
            mb_substr($what, 0, 200) . ' — it is waiting in your IPCR as a draft.',
            $form->id
        );
    }

    /**
     * Withdrawing only makes sense while the assignee has not built on it —
     * once there is work or a score against the line, taking it away would
     * destroy someone's record.
     */
    public function withdraw(PcrIndicator $child): bool
    {
        if ($child->accomplishments()->exists() || $child->ratings()->exists()) {
            return false;
        }

        if ($child->children()->exists()) {
            return false;
        }

        $parent = $child->parent;
        $child->delete();

        if ($parent) {
            $sibling = $parent->children()->first();

            if ($sibling) {
                app(IndicatorProgressService::class)->recalculateFrom($sibling);
            } else {
                // Nothing hangs off it any more, so it is a plain task again —
                // clear the rolled-up figure rather than leave a phantom 100%.
                $parent->forceFill(['progress_pct' => 0, 'progress_status' => 'not_started'])->save();
                app(IndicatorProgressService::class)->recalculateFrom($parent);
            }
        }

        return true;
    }

    /**
     * Taking back a heading only makes sense while nothing hangs off it — once
     * the assignee has written commitments under it, that is their work.
     */
    public function withdrawOutput(PcrOutput $output): bool
    {
        if ($output->indicators()->exists() || $output->childOutputs()->exists()) {
            return false;
        }

        $output->delete();

        return true;
    }

    /** The assignee's own IPCR for that period, opened as a draft if they have none. */
    private function formFor(User $assignee, PcrForm $parentForm, ?int $periodId): PcrForm
    {
        $form = PcrForm::firstOrCreate(
            [
                'type'             => 'ipcr',
                'school_year_id'   => $parentForm->school_year_id,
                'user_id'          => $assignee->id,
                'rating_period_id' => $periodId,
            ],
            [
                'org_unit_id' => $assignee->org_unit_id ?? $parentForm->org_unit_id,
                'status'      => 'draft',
            ]
        );

        if ($form->wasRecentlyCreated) {
            PcrStatusLog::record($form->id, null, 'draft', 'Opened by an assignment');
        }

        return $form;
    }

    /** The line above decides the period; a heading falls back to the year's active one. */
    private function defaultPeriodFor(PcrForm $parentForm): ?int
    {
        return RatingPeriod::where('school_year_id', $parentForm->school_year_id)
            ->orderByDesc('is_active')
            ->orderBy('seq')
            ->value('id');
    }

    /** Mirror the heading the work sits under, tied back to it rather than matched by name. */
    private function outputFor(PcrForm $form, PcrOutput $parent, User $actor): PcrOutput
    {
        $existing = PcrOutput::where('form_id', $form->id)
            ->where('parent_output_id', $parent->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return PcrOutput::create([
            'form_id'          => $form->id,
            'parent_output_id' => $parent->id,
            'section'          => $parent->section,
            'title'            => $parent->title,
            'assigned_by'      => $actor->id,
            'assigned_by_name' => $actor->name,
            'sort_order'       => (PcrOutput::where('form_id', $form->id)->max('sort_order') ?? 0) + 1,
        ]);
    }
}
