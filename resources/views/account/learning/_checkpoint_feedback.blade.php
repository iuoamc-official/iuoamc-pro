@if(session('checkpoint_feedback'))
<section class="learning-concept-feedback" role="status"><strong>✓ {{ __('learning.checkpoint_explanation',['page'=>session('checkpoint_feedback.section')]) }}</strong><p>{{ session('checkpoint_feedback.explanation') }}</p></section>
@endif
