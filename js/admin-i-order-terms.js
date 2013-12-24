/*global jQuery:true, ajaxurl:true */

// inspired by Simple Page Ordering plugin ( http://wordpress.org/extend/plugins/simple-page-ordering/ )
jQuery(document).ready(function($) {

	$('table.wp-list-table > tbody').sortable({
		items: '> tr:not(.inline-edit-row)',
		axis: 'y',
		containment: 'table.wp-list-table',
		cursor: 'move',
		distance: 5,
		forceHelperSize: true,
		forcePlaceholderSize: true,
		helper: 'clone',
		opacity: 0.7,
		scrollSensitivity: 40,
		update: function(event, ui) {
			function get_term_id(el) {
				var ret;

				if (el.length) {
					ret = el.find('.check-column input').val();

					if (el && !ret) {
						// try to find term ID by other means
						ret = el.attr('id').replace('tag-', '');
						if (!$.isNumeric(ret)) {
							$('table.wp-list-table > tbody').sortable('cancel');
							return;
						}
					}
				}

				return ret;
			}


			// get taxonomy name
			var $taxonomy = $('input[name="taxonomy"]');
			if (!$taxonomy.length) {
				$('table.wp-list-table > tbody').sortable('cancel');
				return;
			}


			var $term = ui.item,
				$term_prev = $term.prev(),
				$term_next = $term.next(),
				$checker_holder = $term.find('.check-column'),
				$checker = $term.find('.check-column input'),

				taxonomy = $taxonomy[0].value,
				term_id = get_term_id($term),
				term_parent_id = $term.find('.parent').html(),
				prev_term_id = get_term_id($term_prev),
				next_term_id = get_term_id($term_next),
				prev_term_parent_id,
				next_term_parent_id;


			// can only sort items with same parent
			if ($term_prev.length && prev_term_id !== undefined) {
				prev_term_parent_id = $term_prev.find('.parent').html();
				if (prev_term_parent_id !== term_parent_id) {
//					// try to find prev item in same level
//					var temp_prev = $term_prev.prev(), temp_prev_parent_id;
//					while (temp_prev.length) {
//						temp_prev_parent_id = temp_prev.find('.parent').html();
//						if (temp_prev_parent_id === term_parent_id) {
//							prev_term_id = get_term_id(temp_prev);
//							prev_term_parent_id = temp_prev_parent_id;
//							break;
//						}
//						temp_prev = temp_prev.prev();
//					}
					prev_term_id = undefined;
				}
			}
			if ($term_next.length && next_term_id !== undefined) {
				next_term_parent_id = $term_next.find('.parent').html();
				if (next_term_parent_id !== term_parent_id) {
//					// try to find prev item in same level
//					var temp_next = $term_next.next(), temp_next_parent_id;
//					while (temp_next.length) {
//						temp_next_parent_id = temp_next.find('.parent').html();
//						if (temp_next_parent_id === term_parent_id) {
//							next_term_id = get_term_id(temp_next);
//							next_term_parent_id = temp_next_parent_id;
//							break;
//						}
//						temp_next = temp_next.next();
//					}
					next_term_id = undefined;
				}
			}


			// at least one of prev/next should be present
			// don't allow moving items in between its own children
			if ((prev_term_id === undefined && next_term_id === undefined) ||
				(prev_term_parent_id === term_id) ||
				(next_term_parent_id === term_id)
				) {
				$('table.wp-list-table > tbody').sortable('cancel');
				return;
			}


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
					$('table.wp-list-table > tbody').sortable('cancel');

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
			}, 'json');


			// fix table row colors
			$('table.wp-list-table > tbody > tr').each(function(index, el) {
				if (index % 2 === 0) $(el).addClass('alternate');
				else $(el).removeClass('alternate');
			});
		}
	});

	$('table.wp-list-table > tbody th, table.wp-list-table > tbody td').css('cursor', 'move');
});
