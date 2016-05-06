window.draftsforfriends = {
	toggle_extend: function(key) {
		jQuery('[id^="draftsforfriends-extend-div-"]').hide();
		jQuery('[id^="draftsforfriends-extend-link-"]').show();
		jQuery('#draftsforfriends-extend-link-'+key).hide();
		jQuery('#draftsforfriends-extend-div-'+key).show();
		jQuery('#draftsforfriends-extend-div-'+key+' input[name="expires"]').focus();
		jQuery('#draftsforfriends-extend-div-'+key+' input[name="expires"]').select();
	},
	cancel_extend: function(key) {
		jQuery('#draftsforfriends-extend-div-'+key).hide();
		jQuery('#draftsforfriends-extend-link-'+key).show();
	},	
	extend: function(key) {
		document.getElementById('main-wpnonce').value = document.getElementById('wpnonce-' + key).value;
		document.getElementById('main-action').value = document.getElementById('action-' + key).value;
		document.getElementById('main-key').value = document.getElementById('key-' + key).value;
		document.getElementById('main-expires').value = document.getElementById('expires-' + key).value;
		var measure = document.getElementById('measure-' + key);
		document.getElementById('main-measure').value = measure.options[measure.selectedIndex].value;
		document.getElementById('draftsforfriends-extend-main').submit();
	}
};