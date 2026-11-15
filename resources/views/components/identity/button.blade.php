@props(['type' => 'submit', 'secondary' => false])
<button type="{{ $type }}" {{ $attributes->class(['identity-button', 'secondary' => $secondary]) }}><span>{{ $slot }}</span><span aria-hidden="true">↗</span></button>
