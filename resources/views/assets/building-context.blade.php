@if(app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'assets.view'))
 data-assets-room-url="{{ route('app.assets.room',[$organization,$property,'SPACE_UUID']) }}"
 data-assets-room-title="{{ __('asset.room_equipment') }}" data-assets-room-loading="{{ __('asset.loading') }}" data-assets-room-empty="{{ __('asset.room_empty') }}" data-assets-room-error="{{ __('asset.room_save_first') }}" data-assets-room-all="{{ __('asset.room_all') }}" data-assets-room-add="{{ __('asset.room_add') }}" data-assets-room-next="{{ __('asset.more') }}"
@endif
