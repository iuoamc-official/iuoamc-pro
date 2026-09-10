<details class="membership-action">
    <summary>{{ __('memberships.actions.'.$action) }}</summary>
    <form method="post" action="{{ route('memberships.transition',['locale'=>app()->getLocale(),'membership'=>$membership->id,'action'=>$action]) }}">
        @csrf<input type="hidden" name="lock_version" value="{{ $membership->lock_version }}">
        @if(in_array($action,['approve','renew'],true))
            <p>{{ __('memberships.dates_notice') }}</p>
            @if($action==='renew' && $membership->periods->first())<p>{{ __('memberships.renew_after') }} <bdi dir="ltr">{{ $membership->periods->first()->valid_until->format('Y-m-d') }}</bdi></p>@endif
            <div class="form-grid two-columns">
                <label class="field"><span>{{ __('memberships.valid_from') }} *</span><input type="date" name="valid_from" required></label>
                <label class="field"><span>{{ __('memberships.valid_until') }} *</span><input type="date" name="valid_until" required></label>
            </div>
        @endif
        <label class="field"><span>{{ __('memberships.reason') }} *</span><textarea name="reason" required maxlength="1500" rows="3"></textarea></label>
        <button class="{{ in_array($action,['revoke','reject'],true)?'danger-action':'primary-action' }}" type="submit">{{ __('memberships.actions.'.$action) }}</button>
    </form>
</details>
