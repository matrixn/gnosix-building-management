@if(app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'dna.view')&&app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'assets.view')&&app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'hospitality.rooms'))
<a class="identity-link" href="{{ route('app.dna.index',[$organization,'property'=>$dnaProperty->id,'focus'=>$dnaFocus,'simulate'=>$dnaSimulate??null]) }}">⌬ {{ !empty($dnaSimulate)?__('dna.simulate'):__('dna.title') }} ↗</a>
@endif
