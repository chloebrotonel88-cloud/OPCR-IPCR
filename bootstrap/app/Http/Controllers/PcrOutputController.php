<?php

namespace App\Http\Controllers;

use App\Models\PcrForm;
use App\Models\PcrOutput;
use App\Services\PcrWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PcrOutputController extends Controller
{
    public function store(Request $request)
    {
        $id = $request->input('id');

        $data = $request->validate([
            'id'         => 'nullable|integer|exists:pcr_outputs,id',
            'form_id'    => 'required|integer|exists:pcr_forms,id',
            'section'          => ['required', Rule::in(PcrOutput::SECTIONS)],
            'title'            => 'required_without:parent_output_id|nullable|string|max:255',
            'parent_output_id' => 'nullable|integer|exists:pcr_outputs,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $form = PcrForm::findOrFail($data['form_id']);

        if (! PcrWorkflow::canEditCommitments($request->user(), $form)) {
            return response()->json([
                'message' => 'Commitments can only be changed while the form is a draft or has been returned to you.',
            ], 409);
        }

        if ($form->type === 'ipcr' && $data['section'] === 'strategic') {
            return response()->json([
                'message' => 'Strategic priorities belong to the office OPCR, not an individual IPCR.',
            ], 422);
        }

        $existing = $id ? PcrOutput::find($id) : null;

        $parentId = $data['parent_output_id'] ?? $existing?->parent_output_id;

        $parent = null;

        if (! empty($parentId)) {
            $parent = PcrOutput::with('form')->find($parentId);

            if (
                ! $parent
                || $parent->form?->type !== 'opcr'
                || (int) $parent->form->school_year_id !== (int) $form->school_year_id
            ) {
                return response()->json([
                    'message' => 'Pick an MFO/PPA from the college OPCR for this school year.',
                ], 422);
            }

            if (! PcrWorkflow::opcrIsVisible($parent->form)) {
                return response()->json([
                    'message' => 'The college OPCR has not been published yet.',
                ], 422);
            }
        }

        // A core commitment answers to something the college named. Support
        // Functions are the ratee's own — the OPCR never lists "Submission of DTR".
        if ($form->type === 'ipcr' && $data['section'] === 'core' && ! $parent) {
            $collegeHasCoreOutputs = PcrOutput::whereHas(
                'form',
                fn ($q) => $q->where('type', 'opcr')
                    ->where('school_year_id', $form->school_year_id)
                    ->whereIn('status', PcrWorkflow::OPCR_VISIBLE)
            )->where('section', 'core')->exists();

            if ($collegeHasCoreOutputs) {
                return response()->json([
                    'message' => 'Pick the college MFO/PPA this core function answers to.',
                ], 422);
            }
        }

        $attributes = [
            'form_id'          => $form->id,
            'parent_output_id' => $parent?->id,
            // Taken from the college's heading so an IPCR cannot drift from its wording.
            'section'          => $parent?->section ?? $data['section'],
            'title'            => $parent?->title ?? $data['title'],
            // Same rule as indicators: editing a heading keeps its position.
            'sort_order'       => $data['sort_order']
                ?? ($id
                    ? PcrOutput::where('id', $id)->value('sort_order')
                    : PcrOutput::where('form_id', $form->id)->max('sort_order') + 1),
        ];

        if ($id) {
            $output = PcrOutput::findOrFail($id);
            $output->update($attributes);

            return response()->json(['data' => 'updated', 'output' => $output->load('indicators')]);
        }

        $output = PcrOutput::create($attributes);

        return response()->json(['data' => 'created', 'output' => $output->load('indicators')], 201);
    }

    public function destroy(Request $request, $id)
    {
        $output = PcrOutput::with('form')->findOrFail($id);

        if (! PcrWorkflow::canEditCommitments($request->user(), $output->form)) {
            return response()->json([
                'message' => 'Commitments can only be changed while the form is a draft or has been returned to you.',
            ], 409);
        }

        if ($output->parent_output_id) {
            return response()->json([
                'message' => 'This MFO/PPA was handed down from the college OPCR. Whoever assigned it can withdraw it.',
            ], 409);
        }

        $output->delete();

        return response()->json(['data' => 'deleted']);
    }
}
