/* global jQuery:true, ajaxurl:true */

/**
 * @package IOrderTerms
 * @author Igor Jerosimic
 */

// inspired by Simple Page Ordering plugin ( http://wordpress.org/extend/plugins/simple-page-ordering/ )
jQuery(document).ready(function($) {

	var $sort = $('table.wp-list-table > tbody');
	$sort.sortable({
		items: '> tr:not(.inline-edit-row)',
		axis: 'y',
		containment: 'table.wp-list-table',
		cursor: 'move',
		distance: 5,
		forceHelperSize: true,
		forcePlaceholderSize: true,
		helper: 'clone',
		opacity: 0.8,
		scrollSensitivity: 40,
		update: function(event, ui) {
			function get_term_id(el) {
				var ret;

				if (el.length) {
					ret = parseInt(el.find('.check-column input').val(), 10);

					if (!ret) {
						// try to find term ID by other means
						ret = parseInt(el.attr('id').replace('tag-', ''), 10);
					}

					if (!$.isNumeric(ret)) {
						$sort.sortable('cancel');
						return;
					}
				}

				return ret;
			}


			// get taxonomy name
			var $taxonomy = $('input[name="taxonomy"]');
			if (!$taxonomy.length) {
				$sort.sortable('cancel');
				return;
			}


			var $term = ui.item,
				$term_prev = $term.prev(),
				$term_next = $term.next(),
				$checker_holder = $term.find('.check-column'),
				$checker = $term.find('.check-column input'),

				taxonomy = $taxonomy[0].value,
				term_id = get_term_id($term),
				// term_parent_id = parseInt($term.find('.parent').text(), 10),
				prev_term_id = get_term_id($term_prev),
				next_term_id = get_term_id($term_next),
				prev_term_parent_id,
				next_term_parent_id;


			// get parent ID's
			if ($term_prev.length && prev_term_id !== undefined) {
				prev_term_parent_id = parseInt($term_prev.find('.parent').text(), 10);
			}
			if ($term_next.length && next_term_id !== undefined) {
				next_term_parent_id = parseInt($term_next.find('.parent').text(), 10);
			}


			// at least one of prev/next should be present
			// don't allow moving items in between its own children
			if ((prev_term_id === undefined && next_term_id === undefined) ||
				(prev_term_parent_id === term_id) ||
				(next_term_parent_id === term_id)
				) {
				$sort.sortable('cancel');
				return;
			}


			// disable new reorder until ajax returns
			$sort.sortable('disable');


			// show spinner
			if ($checker.length) {
				//$checker.hide().after('<img src="images/wpspin_light-2x.gif" width="16" height="16" style="margin-left:8px;" alt="" />');
				$checker.hide().after('<span class="spinner" style="display:block;"></span>');
			} else {
				//$checker_holder.append('<img src="images/wpspin_light-2x.gif" width="16" height="16" style="margin-left:8px;" alt="" />');
				$checker_holder.prepend('<span class="spinner" style="display:block;"></span>');
			}


			// force terms reload to avoid badly positioned children (visually)
			var force_reload = (next_term_id === undefined && next_term_parent_id === prev_term_id);


			// send sort cmd via ajax
			var data = {
				action: 'i-order-terms',
				term_id: term_id,
				term_prev_id: prev_term_id,
				term_next_id: next_term_id,
				taxonomy: taxonomy,
				force_reload: force_reload
			};
			$.post(ajaxurl, data, function(data) {
				if (data.status === 'ok') {
					if (data.force_reload) {
						window.location.reload();
						return;
					} else {
						$('#ajax-response').empty();
					}
				} else {
					$('#ajax-response').empty().append('<div class="error"><p>' + data.message + '</p></div>');
					$sort.sortable('cancel');

					console.error(data.message);
				}

				// remove spinner
				if ($checker.length) {
					//$checker.show().siblings('img').remove();
					$checker.show().siblings('.spinner').remove();
				} else {
					//$checker_holder.children('img').remove();
					$checker_holder.children('.spinner').remove();
				}

				// enable sorting again
				$sort.sortable('enable');
			}, 'json');


			// fix table row colors
			$('table.wp-list-table > tbody > tr').each(function(index, el) {
				if (index % 2 === 0) {
					$(el).addClass('alternate');
				} else {
					$(el).removeClass('alternate');
				}
			});
		}
	});


	// catch add-tag ajax calls
	$(document).ajaxSuccess(function(event, xhr, settings) {
		if (settings.data && settings.data.indexOf('action=add-tag') !== -1 && xhr.responseText && xhr.responseText.indexOf('wp_error') === -1) {
			$sort.sortable('refresh');
		}
	});
});
