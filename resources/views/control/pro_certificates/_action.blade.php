<form class="pc-transition pc-transition-{{ $action }}" method="post" action="{{ route('certificates.transition',['locale'=>app()->getLocale(),'certificate'=>$certificate->id,'action'=>$action]) }}">
    @csrf
    <input type="hidden" name="lock_version" value="{{ $certificate->lock_version }}">
    @if($action==='issue')<p>{{ __('certificates.issue_notice') }}</p><label class="pc-checkbox"><input type="checkbox" name="confirm_issue" value="1" required><span>{{ __('certificates.confirm_issue') }}</span></label>@endif
    @if(in_array($action,['return','revoke'],true))
        @if($action==='revoke')<p>{{ __('certificates.revoke_notice') }}</p>@endif
        <label class="pc-field"><span>{{ __('certificates.reason') }} *</span><textarea name="reason" required maxlength="1500" rows="3" aria-describedby="pc-reason-{{ $action }}">{{ old('reason') }}</textarea><small id="pc-reason-{{ $action }}">{{ __('certificates.reason_help') }}</small></label>
    @endif
    @if($action==='revoke')<label class="pc-checkbox"><input type="checkbox" name="confirm_revoke" value="1" required><span>{{ __('certificates.confirm_revoke') }}</span></label>@endif
    <button class="pc-button {{ $action==='revoke'?'pc-button-danger':($action==='return'?'pc-button-secondary':'pc-button-primary') }}" type="submit">{{ __('certificates.actions.'.$action) }}</button>
</form>
