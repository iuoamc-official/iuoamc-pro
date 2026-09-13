@php($model=is_object($coauthor)?$coauthor:null)
@php($data=is_array($coauthor)?$coauthor:[])
@php($pivotRoles=$model ? (json_decode((string)$model->pivot?->contribution_roles,true) ?: []) : [])
<section class="journal-coauthor">
    <div class="journal-coauthor-head"><strong>{{ __('journal.author') }}</strong><button type="button" data-remove-coauthor>{{ __('journal.remove_author') }}</button></div>
    @if($model)<input type="hidden" name="coauthors[{{ $index }}][id]" value="{{ $model->id }}">@elseif(isset($data['id']))<input type="hidden" name="coauthors[{{ $index }}][id]" value="{{ $data['id'] }}">@endif
    <div class="form-grid two-columns">
        <label class="field"><span>{{ __('journal.author_name') }} *</span><input name="coauthors[{{ $index }}][name]" required maxlength="255" value="{{ $data['name'] ?? $model?->name }}"></label>
        <label class="field"><span>{{ __('journal.author_latin_name') }}</span><input name="coauthors[{{ $index }}][latin_name]" dir="ltr" maxlength="255" value="{{ $data['latin_name'] ?? $model?->latin_name }}"></label>
        <label class="field"><span>{{ __('journal.author_email') }}</span><input type="email" name="coauthors[{{ $index }}][email]" dir="ltr" maxlength="254" value="{{ $data['email'] ?? $model?->email }}"></label>
        <label class="field"><span>ORCID</span><input name="coauthors[{{ $index }}][orcid]" dir="ltr" value="{{ $data['orcid'] ?? $model?->orcid }}"></label>
        <label class="field"><span>{{ __('journal.country_code') }}</span><input name="coauthors[{{ $index }}][country_code]" dir="ltr" maxlength="2" value="{{ $data['country_code'] ?? $model?->country_code }}"></label>
        <label class="field"><span>{{ __('journal.affiliation') }}</span><input name="coauthors[{{ $index }}][affiliation_name]" maxlength="255" value="{{ $data['affiliation_name'] ?? $model?->pivot?->affiliation_name }}"></label>
        <label class="field"><span>ROR URL</span><input type="url" name="coauthors[{{ $index }}][affiliation_ror]" dir="ltr" maxlength="255" value="{{ $data['affiliation_ror'] ?? $model?->pivot?->affiliation_ror }}"></label>
        <label class="field span-two"><span>{{ __('journal.credit_roles') }} *</span><select name="coauthors[{{ $index }}][contribution_roles][]" multiple size="7" required>@foreach($creditRoles as $role)<option value="{{ $role }}" @selected(in_array($role,$data['contribution_roles'] ?? $pivotRoles,true))>{{ __('journal.credit.'.$role) }}</option>@endforeach</select></label>
    </div>
</section>
