@can('update',$asset)
@if(!$asset->archived_at&&$archivedPhotos->count())
<details><summary>{{ __('asset.archived_photos') }} ({{ $archivedPhotos->count() }})</summary>@foreach($archivedPhotos as $photo)<form method="POST" action="{{ route('app.assets.photos.archive',[$organization,$asset->uuid,$photo->uuid]) }}">@csrf<input type="hidden" name="revision" value="{{ $asset->revision }}"><input type="hidden" name="archived" value="0"><p>{{ $photo->caption??__('asset.photos') }}</p><button class="identity-text-button">{{ __('asset.restore_photo') }}</button></form>@endforeach</details>
@endif
@endcan
