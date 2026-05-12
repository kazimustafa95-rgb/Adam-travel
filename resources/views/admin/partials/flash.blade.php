@if (session('status'))
    <div class="flash">
        {{ session('status') }}
    </div>
@endif
