@extendsuhalten('layouts.control')
@section('title', __('memberships.application_data'))
@section('content')
<div class="membership-module">
    <section class="page-heading"><div><span class="eyebrow">IUOAMC / MEMBERSHIP APPLICATION</span><h1>{{ __('memberships.application_data') }}</h1><p><bdi>{{ $membership->full_name }}</bdi> · <bdi dir="ltr">{{ $membership->membership_number }}</bdi></p></div><a class="secondary-action" href="{{ route('memberships.show',['locale'=>app()->getLocale(),'membership'=>$membership->id]) }}">{{ __('memberships.back') }}</a></section>
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="institutional-form" method="post" enctype="multipart/form-data" action="{{ route('memberships.application.update',['locale'=>app()->getLocale(),'membership'=>$membership->id]) }}">
        @csrf
        @method('put')
        @include('control.memberships._application_fields',['application'=>$application,'photoRequired'=>$application===null])
        <div class="form-footer"><p>{{ __('memberships.application_lock_notice') }}</p><button type="submit" class="primary-action">{{ __('memberships.save_application') }}</button></div>
    </form>
</div>
@endsection
