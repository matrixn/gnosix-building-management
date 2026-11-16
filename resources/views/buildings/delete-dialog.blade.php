<dialog class="bld-delete-dialog identity-card" {{ ($permanent ?? false) ? 'data-permanent-dialog' : '' }} data-building-delete-dialog aria-labelledby="delete-dialog-title" aria-describedby="delete-dialog-copy">
<h2 id="delete-dialog-title">{{ __(($permanent ?? false) ? 'building.purge_question' : 'building.delete_question') }}</h2><h3 data-delete-name></h3><p class="identity-caption" data-delete-reference></p>
<p id="delete-dialog-copy">{{ __(($permanent ?? false) ? 'building.purge_explanation' : 'building.delete_explanation') }}</p>
<div class="bld-delete-actions"><button type="button" class="identity-text-button" data-delete-cancel autofocus>{{ __('building.cancel_delete') }}</button><button type="button" class="bld-danger" data-delete-confirm>{{ __(($permanent ?? false) ? 'building.confirm_purge' : 'building.confirm_delete') }}</button></div>
</dialog><noscript><p class="identity-caption">{{ __('building.delete_js_required') }}</p></noscript>
