@if($customer->isArchived())
    <span class="customer-status archived">Arhivat</span>
@elseif($customer->hasVerifiedEmail())
    <span class="customer-status verified">Email verificat</span>
@else
    <span class="customer-status">Email neconfirmat</span>
@endif
