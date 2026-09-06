@php
    $isOpcr = $form->type === 'opcr';
    $ratee  = $form->owner?->name ?? $form->orgUnit?->name;
    $lines  = fn ($output) => $output->indicators->filter(
        fn ($i) => ! $i->rating_period_id || (int) $i->rating_period_id === (int) $period?->id
    );
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #000; }
        h1 { font-size: 12px; text-align: center; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.6px solid #000; padding: 3px 4px; vertical-align: top; }
        th { text-align: center; font-weight: bold; background: #f2f2f2; }
        .lead { margin: 0 0 10px; line-height: 1.45; }
        .band td { background: #e6e6e6; font-weight: bold; letter-spacing: 0.4px; }
        .num { color: #444; }
        .sign { width: 100%; margin-top: 26px; border: 0; }
        .sign td { border: 0; text-align: center; padding-top: 26px; font-size: 8px; }
        .sign .rule { border-top: 0.6px solid #000; padding-top: 3px; }
        .legend { margin-top: 10px; font-size: 7.5px; font-style: italic; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>{{ $isOpcr ? 'OFFICE' : 'INDIVIDUAL' }} PERFORMANCE COMMITMENT AND REVIEW
        ({{ $isOpcr ? 'OPCR' : 'IPCR' }})</h1>

    <p class="lead">
        I, <strong>{{ mb_strtoupper($ratee) }}</strong>,
        {{ $isOpcr ? 'Head of the ' . $organization->name : 'of the ' . $organization->name }},
        commit to deliver and agree to be rated on the attainment of the following targets in
        accordance with the indicated measures for the period&nbsp;<strong>{{ $form->schoolYear?->label }}</strong>@if($isOpcr) (January to December)@elseif($period) ({{ $period->label }})@endif.
    </p>

    <table>
        <thead>
            <tr>
                <th rowspan="2" width="15%">{{ $isOpcr ? 'MFO/PPA' : 'Output' }}</th>
                <th rowspan="2" width="26%">Success Indicator<br><span class="muted">(Target + Measure)</span></th>
                @if($isOpcr)<th rowspan="2" width="9%">Alloted Budget</th>@endif
                @if($isOpcr)<th rowspan="2" width="14%">Individual / Accountable</th>@endif
                <th rowspan="2" width="{{ $isOpcr ? 18 : 30 }}%">Actual Accomplishments</th>
                <th colspan="4" width="12%">Rating</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr><th>Q</th><th>E</th><th>T</th><th>A</th></tr>
        </thead>
        <tbody>
            @foreach (['strategic' => 'STRATEGIC PRIORITY', 'core' => 'CORE FUNCTIONS', 'support' => 'SUPPORT FUNCTIONS'] as $key => $band)
                @php $outputs = $form->outputs->where('section', $key); @endphp
                @continue($outputs->isEmpty())

                <tr class="band"><td colspan="{{ $isOpcr ? 10 : 8 }}">{{ $band }}</td></tr>

                @foreach ($outputs->values() as $oi => $output)
                    @php $rows = $lines($output)->values(); @endphp

                    @forelse ($rows as $li => $line)
                        @php
                            $rating = $line->ratings->firstWhere('rating_period_id', $period?->id);
                            $acc    = $line->accomplishments->firstWhere('rating_period_id', $period?->id);
                            $people = $line->children->map(fn ($c) => $c->output?->form?->owner?->name)->filter();
                        @endphp
                        <tr>
                            @if ($li === 0)
                                <td rowspan="{{ max($rows->count(), 1) }}">
                                    <span class="num">{{ $oi + 1 }}.</span> {{ $output->title }}
                                </td>
                            @endif
                            <td>
                                <span class="num">{{ $oi + 1 }}.{{ $li + 1 }}.</span>
                                {{ trim(strip_tags($line->description)) }}
                            </td>
                            @if($isOpcr)
                                <td align="right">
                                    {{ $line->allotted_budget ? number_format($line->allotted_budget, 2) : '' }}
                                </td>
                                <td>{{ $people->isNotEmpty() ? $people->implode(' / ') : $line->accountable }}</td>
                            @endif
                            <td>{!! $acc?->actual_accomplishment !!}</td>
                            <td align="center">{{ $rating?->q }}</td>
                            <td align="center">{{ $rating?->e }}</td>
                            <td align="center">{{ $rating?->t }}</td>
                            <td align="center">{{ $rating?->a ? number_format($rating->a, 2) : '' }}</td>
                            <td>{!! $rating?->remarks !!}</td>
                        </tr>
                    @empty
                        <tr>
                            <td><span class="num">{{ $oi + 1 }}.</span> {{ $output->title }}</td>
                            <td colspan="{{ $isOpcr ? 9 : 7 }}"></td>
                        </tr>
                    @endforelse
                @endforeach
            @endforeach

            @if ($summary)
                <tr class="band">
                    <td colspan="{{ $isOpcr ? 8 : 6 }}" align="right"><strong>Final Average Rating</strong></td>
                    <td align="center"><strong>{{ number_format($summary->final_average, 2) }}</strong></td>
                    <td><strong>{{ $summary->adjectival }}</strong></td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="legend">
        Legend: Q &ndash; Quality &nbsp; E &ndash; Efficiency &nbsp; T &ndash; Timeliness &nbsp; A &ndash; Average
    </p>

    <table class="sign">
        <tr>
            <td><div class="rule">{{ $ratee }}<br>Ratee</div></td>
            <td><div class="rule">{{ $form->headReviewer?->name ?? $form->reviewed_by_name ?? ' ' }}<br>
                {{ $form->headReviewer?->position_title ?? 'Immediate Supervisor' }}</div></td>
            <td><div class="rule">{{ $form->vpReviewer?->name ?? $form->vp_reviewed_by_name ?? ' ' }}<br>
                {{ $organization->head_title ?? 'Head of Office' }}</div></td>
        </tr>
    </table>
</body>
</html>
