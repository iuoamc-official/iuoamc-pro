<section class="form-card">
    <header><span class="form-step">03</span><div><h2>{{ __('memberships.application_data') }}</h2><p>{{ __('memberships.application_privacy') }}</p></div></header>
    <div class="form-grid two-columns">
        <label class="field"><span>{{ __('memberships.photo') }} @if($photoRequired)*@endif</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" @required($photoRequired)><small>{{ __('memberships.photo_help') }}</small></label>
        @if($application)<div class="field"><span>{{ __('memberships.current_photo') }}</span><strong>{{ __('memberships.photo_secured') }}</strong><small><code dir="ltr">{{ $application->photo_sha256 }}</code></small></div>@endif
        <label class="field"><span>{{ __('memberships.date_of_birth') }} *</span><input type="date" name="date_of_birth" required value="{{ old('date_of_birth',$application?->date_of_birth) }}"></label>
        <label class="field"><span>{{ __('memberships.nationality') }} *</span><input name="nationality_code" required maxlength="2" pattern="[A-Za-z]{2}" dir="ltr" value="{{ old('nationality_code',$application?->nationality_code) }}"></label>
        <label class="field"><span>{{ __('memberships.residence_country') }} *</span><input name="residence_country_code" required maxlength="2" pattern="[A-Za-z]{2}" dir="ltr" value="{{ old('residence_country_code',$application?->residence_country_code) }}"></label>
        <label class="field"><span>{{ __('memberships.city') }} *</span><input name="city" required maxlength="120" value="{{ old('city',$application?->city) }}"></label>
        <label class="field"><span>{{ __('memberships.postal_code') }}</span><input name="postal_code" maxlength="30" value="{{ old('postal_code',$application?->postal_code) }}"></label>
        <label class="field"><span>{{ __('memberships.identification_type') }} *</span><input name="identification_type" required maxlength="60" value="{{ old('identification_type',$application?->identification_type) }}"></label>
        <label class="field"><span>{{ __('memberships.identification_number') }} *</span><input name="identification_number" required maxlength="120" value="{{ old('identification_number',$application?->identification_number) }}"></label>
    </div>
    <label class="field"><span>{{ __('memberships.address') }} *</span><textarea name="address" required rows="3" maxlength="1000">{{ old('address',$application?->address) }}</textarea></label>
    <label class="field"><span>{{ __('memberships.qualifications') }}</span><textarea name="qualifications" rows="4" maxlength="3000">{{ old('qualifications',$application?->qualifications) }}</textarea></label>
    <label class="field"><input type="checkbox" name="application_consent" value="1" required @checked(old('application_consent'))> <span>{{ __('memberships.application_consent') }}</span></label>
</section>
