<section class="journal-coauthor">
    <div class="journal-coauthor-head"><strong>{{ __('journal.author') }}</strong><button type="button" data-remove-coauthor>{{ __('journal.remove_author') }}</button></div>
    <div class="journal-form-grid">
        <label><span>{{ __('journal.author_name') }} *</span><input name="coauthors[{{ $index }}][name]" value="{{ $coauthor['name'] ?? '' }}" maxlength="255" required></label>
        <label><span>{{ __('journal.latin_name') }}</span><input dir="ltr" name="coauthors[{{ $index }}][latin_name]" value="{{ $coauthor['latin_name'] ?? '' }}" maxlength="255"></label>
        <label><span>{{ __('journal.author_email') }} *</span><input type="email" dir="ltr" name="coauthors[{{ $index }}][email]" value="{{ $coauthor['email'] ?? '' }}" maxlength="254" required></label>
        <label><span>{{ __('journal.affiliation') }}</span><input name="coauthors[{{ $index }}][affiliation_name]" value="{{ $coauthor['affiliation_name'] ?? '' }}" maxlength="255"></label>
        <label><span>{{ __('journal.affiliation_ror') }}</span><input type="url" dir="ltr" name="coauthors[{{ $index }}][affiliation_ror]" value="{{ $coauthor['affiliation_ror'] ?? '' }}" maxlength="255"></label>
        <label><span>ORCID</span><input dir="ltr" name="coauthors[{{ $index }}][orcid]" value="{{ $coauthor['orcid'] ?? '' }}" placeholder="0000-0000-0000-000X"></label>
        <label><span>{{ __('journal.country_code') }}</span><input dir="ltr" name="coauthors[{{ $index }}][country_code]" value="{{ $coauthor['country_code'] ?? '' }}" maxlength="2"></label>
        <label class="wide"><span>{{ __('journal.credit_roles') }} *</span><select name="coauthors[{{ $index }}][contribution_roles][]" multiple size="7" required>@foreach($creditRoles as $role)<option value="{{ $role }}" @selected(in_array($role, $coauthor['contribution_roles'] ?? [], true))>{{ __('journal.credit.'.$role) }}</option>@endforeach</select></label>
    </div>
</section>
