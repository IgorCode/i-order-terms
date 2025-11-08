/* global jQuery:true, ajaxurl:true, iOrderTerms:true */

/**
 * @package IOrderTerms
 * @author Igor Jerosimic
 */

// Inspired by the Simple Page Ordering plugin ( https://wordpress.org/plugins/simple-page-ordering/ )
jQuery(document).ready(function($)
{
	"use strict";

	/**
	 * Get term ID from element.
	 * @param el
	 * @returns {number|undefined}
	 */
	function getTermId(el)
	{
		var ret;

		if (el.length) {
			ret = parseInt(el.find('.check-column input').val(), 10);

			if (!ret) {
				// Try to find the term ID by other means
				ret = parseInt(el.attr('id').replace('tag-', ''), 10);
			}

			if (!$.isNumeric(ret)) {
				$sort.sortable('cancel');
				return;
			}
		}

		return ret;
	}

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
			// get taxonomy name
			var $taxonomy = $('input[name="taxonomy"]');
			if (!$taxonomy.length) {
				$sort.sortable('cancel');
				return;
			}


			var $term = ui.item,
				$termPrev = $term.prev(),
				$termNext = $term.next(),
				$checkerHolder = $term.find('.check-column'),
				$checker = $term.find('.check-column input'),

				taxonomy = $taxonomy[0].value,
				termId = getTermId($term),
				// termParentId = parseInt($term.find('.parent').text(), 10),
				prevTermId = getTermId($termPrev),
				nextTermId = getTermId($termNext),
				prevTermParentId,
				nextTermParentId;


			// Get parent ID's
			if ($termPrev.length && prevTermId !== undefined) {
				prevTermParentId = parseInt($termPrev.find('.parent').text(), 10);
			}
			if ($termNext.length && nextTermId !== undefined) {
				nextTermParentId = parseInt($termNext.find('.parent').text(), 10);
			}


			// At least one of prev/next should be present
			// don't allow moving items in between its own children
			if ((prevTermId === undefined && nextTermId === undefined) ||
				(prevTermParentId === termId) ||
				(nextTermParentId === termId)
				) {
				$sort.sortable('cancel');
				return;
			}


			// Disable new reorder until ajax returns
			$sort.sortable('disable');


			// Show spinner
			if ($checker.length) {
				//$checker.hide().after('<img src="images/wpspin_light-2x.gif" width="16" height="16" style="margin-left:8px;" alt="" />');
				$checker.hide().after('<span class="spinner" style="display:block;"></span>');
			} else {
				//$checkerHolder.append('<img src="images/wpspin_light-2x.gif" width="16" height="16" style="margin-left:8px;" alt="" />');
				$checkerHolder.prepend('<span class="spinner" style="display:block;"></span>');
			}


			// Force terms reload to avoid badly positioned children (visually)
			var forceReload = (nextTermId === undefined && nextTermParentId === prevTermId);


			// Send sort cmd via ajax
			var data = {
				action: 'i-order-terms',
				nonce: (iOrderTerms && iOrderTerms.nonce) ? iOrderTerms.nonce : '',
				term_id: termId,
				term_prev_id: prevTermId,
				term_next_id: nextTermId,
				taxonomy: taxonomy,
				force_reload: forceReload
			};
			$.post(ajaxurl, data, function (response) {
				var $ajaxResponse = $('#ajax-response');
				$ajaxResponse.empty();

				if (response.status === 'ok') {
					if (response.force_reload) {
						window.location.reload();
						return;
					}
				} else {
					var p = document.createElement('p');
					p.textContent = response.message;
					var errorDiv = document.createElement('div');
					errorDiv.className = 'error';
					errorDiv.appendChild(p);

					$ajaxResponse.append(errorDiv);
					$sort.sortable('cancel');

					console.error(response.message);
				}

				// Remove spinner
				if ($checker.length) {
					//$checker.show().siblings('img').remove();
					$checker.show().siblings('.spinner').remove();
				} else {
					//$checkerHolder.children('img').remove();
					$checkerHolder.children('.spinner').remove();
				}

				// Enable sorting again
				$sort.sortable('enable');
			}, 'json');


			// Fix table row colors
			$('table.wp-list-table > tbody > tr').each(function (index, el) {
				if (index % 2 === 0) {
					$(el).addClass('alternate');
				} else {
					$(el).removeClass('alternate');
				}
			});
		}
	});


	// Catch add-tag ajax calls
	$(document).ajaxSuccess(function (event, xhr, settings) {
		if (settings.data && settings.data.indexOf('action=add-tag') !== -1 && xhr.responseText && xhr.responseText.indexOf('wp_error') === -1) {
			$sort.sortable('refresh');
		}
	});
});
