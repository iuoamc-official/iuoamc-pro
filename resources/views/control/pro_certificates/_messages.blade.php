@if(session('success'))<div class="pc-notice pc-notice-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="pc-notice pc-notice-error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
