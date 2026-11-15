@props(['kind'])
<svg class="profile-art profile-art-{{ $kind }}" viewBox="0 0 200 115" fill="none" aria-hidden="true">
    <path class="art-grid" d="M10 95 100 45 190 95M30 105 120 55M70 110 160 60M10 70 90 115M45 50 155 115M85 35 190 95"/>
    @if($kind === 'hotel')
    <g class="art-structure"><path d="m55 35 45-20 45 20v55l-45 20-45-20Z"/><path d="m55 35 45 20 45-20M100 55v55M55 55l45 20 45-20M55 75l45 20 45-20"/><path class="art-accent" d="m112 47 7-3v12l-7 3Zm15-7 7-3v12l-7 3ZM112 69l7-3v12l-7 3Z"/></g>
    @elseif($kind === 'construction')
    <g class="art-structure"><path d="M70 100V15h70M40 27h120M100 15v12M140 15v40M132 55h16M70 38l50 25v37M70 63l50-25M70 83l50-20M57 100h27"/><path class="art-accent" d="m95 85 30-14 30 14v16l-30 13-30-13Z"/></g>
    @else
    <g class="art-structure"><path d="m25 55 27-13 27 13v40l-27 13-27-13Zm55-25 28-13 28 13v53l-28 13-28-13Zm53 27 22-10 23 10v38l-23 10-22-10Z"/><path class="art-accent" d="M52 65v28M108 42v38M155 66v25"/></g>
    @endif
</svg>
