@if($list->hasPages())
    <nav class="ops-links">
        @if($list->previousPageUrl())<a class="identity-link" href="{{ $list->previousPageUrl() }}">← {{ __('ops.previous') }}</a>@endif
        @if($list->nextPageUrl())<a class="identity-link" href="{{ $list->nextPageUrl() }}">{{ __('ops.next') }} →</a>@endif
    </nav>
@endif
