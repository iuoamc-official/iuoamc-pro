<section class="learning-project-history"><h2>{{ __('learning.project_history') }}</h2>
@forelse($history as $event)<details class="learning-card"><summary>{{ __('learning.project_version') }} {{ $event->submission_number }} · {{ __('learning.project_'.$event->event) }} · <bdi>{{ $event->occurred_at }} UTC</bdi></summary>
@if($event->event==='submitted')@foreach(\App\Services\SensoryLearningProject::FIELDS as $key)<h3>{{ __('learning.project_fields.'.$key) }}</h3><p class="learning-pre">{{ $event->details['fields'][$key] ?? '' }}</p>@endforeach
@else<p><strong>{{ __('learning.project_score') }}: {{ $event->details['score'] }} / 20</strong></p><p class="learning-pre">{{ $event->details['feedback'] }}</p>@foreach(__('learning.project_criteria') as $index=>$criterion)<p>{{ $criterion }}: {{ $event->details['rubric_scores'][$index] }} / 4</p>@endforeach @endif
</details>@empty<p class="learning-muted">{{ __('learning.project_no_history') }}</p>@endforelse
{{ $history->links() }}</section>
