jQuery(document).ready(function ($) {

	'use strict';

	$( "#connect_to_instance" ).dialog({
		autoOpen: false,
		modal: true,
		open: function(event, ui) {
			$(".ui-dialog-titlebar-close").hide();
			var recently_items = JSON.parse(localStorage.getItem('gianism-mastodon-recently-use-instance')) || [];
			recently_items.forEach( function(element) {
				if(element) {
					$( "#recentry-instances" ).append(
						$('<li>').append(
							$('<a>').attr('href', element).attr('rel', 'nofollow').addClass('wpg-button connect').text(element)
							.click(function(event) {
								event.preventDefault();
								$('#instance_url').val(element);
								$( "#dialog_accept_button" ).click();
					})));
				}
			});
		},
		close: function(event, ui) {
			$('#connect_to_instance_error').hide();
			$('#instance_url').removeClass('ui-state-error').val('');
			$( "#recentry-instances" ).empty();
		},
		buttons: [
			{
				text: 'OK',
				click: function() {
					var url = $('#instance_url').val();
					var button = $(this).data('link');
					$.getJSON( wpApiSettings.root + 'gianism-mastodon/v1/instance', {
						url: url
					})
					.done( function(instance) {
						if('uri' in instance) {
							var uri = instance.uri;
							var recently_items = JSON.parse(localStorage.getItem('gianism-mastodon-recently-use-instance')) || [];
							var index = recently_items.indexOf(uri);
							if( index >= 0 ) {
								recently_items.splice(index, 1);
							}
							recently_items.unshift(uri);
							recently_items.length = 5;
							localStorage.setItem('gianism-mastodon-recently-use-instance', JSON.stringify(recently_items));
							var href = button.href;
							var found = href.match(/^(.*instance_url=)%2A(.*)$/);
							if(found) {
								href = found[1] + encodeURIComponent(url) + found[2];
								location.href = href;
							}
						}
					})
					.fail( function(error) {
						$('#connect_to_instance_error').show();
						$('#instance_url').addClass('ui-state-error').focus();
					});
				},
				id: 'dialog_accept_button',
			},
			{
				text: 'Cancel',
				click: function() {
					$(this).dialog("close");
				},
			}
		],
	});
	$( ".with-instance-dialog" ).click(function(event) {
		event.preventDefault();
		$( "#connect_to_instance" ).data('link', this).dialog('open');
	});

	$( "#comment_link_acct_delete_dialog" ).dialog({
		autoOpen: false,
		modal: true,
		open: function(event, ui) {
			$(".ui-dialog-titlebar-close").hide();
		},
		buttons: [
			{
				text: 'OK',
				click: function() {
					$.ajax({
						url: wpApiSettings.root + 'gianism-mastodon/v1/comment_link',
						method: 'DELETE',
						dataType: "json",
						beforeSend: function ( xhr ) {
							xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
						},
					})
					.done( function(data, textStatus, jqXHR) {
						$("#comment_link_acct_delete_dialog").dialog("close");
					})
					.fail( function(data, textStatus, jqXHR) {
						$("#comment_link_acct").val( $("#comment_link_acct").data('previouse_selected_value') );
						$(this).dialog("close");
					});
				},
			},
			{
				text: 'Cancel',
				click: function() {
					$("#comment_link_acct").val( $("#comment_link_acct").data('previouse_selected_value') );
					$(this).dialog("close");
				},
			}
		],
	});
	$( "#comment_link_acct_dialog" ).dialog({
		autoOpen: false,
		modal: true,
		open: function(event, ui) {
			$(".ui-dialog-titlebar-close").hide();
		},
		buttons: [
			{
				text: 'OK',
				click: function() {
					var acct = $( "#comment_link_acct" ).val();
					var href = $( "#comment_link_acct" ).data('href');
					$.ajax({
						url: wpApiSettings.root + 'gianism-mastodon/v1/extract_domain',
						method: 'POST',
						data: {
							url: acct
						},
						dataType: "json",
						beforeSend: function ( xhr ) {
							xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
						},
					})
					.done( function(data) {
						var found = href.match(/^(.*instance_url)(.*)$/);
						if(found) {
							href = found[1] + '=' + encodeURIComponent(data.instance_url) + '&amp;acct=' + encodeURIComponent(acct) + found[2];
							location.href = href;
						}
					})
					.fail( function() {
						$("#comment_link_acct").val( $("#comment_link_acct").data('previouse_selected_value') );
						$(this).dialog("close");
					});
				},
			},
			{
				text: 'Cancel',
				click: function() {
					$("#comment_link_acct").val( $("#comment_link_acct").data('previouse_selected_value') );
					$(this).dialog("close");
				},
			}
		],
	});
	$( "#comment_link_acct" )
	.on('focus', function(event) {
		$(this).data('previouse_selected_value', $(this).val());
	})
	.on('change', function(event) {
		var acct = $(this).val();
		if( acct ) {
			$( "#comment_link_acct_dialog" ).data('link', this).dialog('open');
		} else {
			$( "#comment_link_acct_delete_dialog" ).dialog('open');
		}
	});
	$(document).on('click', '.tag_insert_buttons', function(e) {
		var target = $(e.target)[0];
		if('BUTTON' === target.tagName) {
			var f      = '#' + $(e.currentTarget).data('for');
			var v      = $(f).val();
			var selin  = $(f).prop('selectionStart');
			var selout = $(f).prop('selectionEnd');
			var tag    = $(target).text();
			$(f).val( v.substr(0,selin) + tag + v.substr(selout) ).prop({
				"selectionStart": selin + tag.length,
				"selectionEnd":   selin + tag.length,
			})
			.trigger("focus");
		} else if('I' === target.tagName || 'detail-switch' === $(target).attr('class') ) {
			target = $(e.currentTarget).children('div');
			if( 'open' === $(target).data('state') ) {
				$(target).data('state', 'close').children('i').attr('class', 'far fa-plus-square');
				$(e.currentTarget).children('button').css('width', '');
				$(e.currentTarget).children('.title').remove();
			} else {
				$(target).data('state', 'open').children('i').attr('class', 'far fa-minus-square');
				$(e.currentTarget).children('button').each(function(index, element){
					$(element).css('width', '10em').after(' <span class="title">' + $(element).attr('title') + '<br></span>');
				});
			}
		}
	});
});
