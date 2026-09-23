jQuery(function ($) {
	var $list = $('#bootg-fields-list');
	var $empty = $('#bootg-fields-empty');
	var fieldTypes = window.bootgFormFieldTypes || {};

	function uid() {
		return 'field_' + Math.random().toString(36).slice(2, 10);
	}

	function toggleEmptyState() {
		$empty.toggle($list.children('.bootg-field-card').length === 0);
	}

	function renderOptionsBlock(field) {
		var opts = (field.options || []).join('\n');
		return (
			'<div class="bootg-field-card-row bootg-field-card-options">' +
			'<label>Options (one per line)</label>' +
			'<textarea class="bootg-input bootg-field-options" rows="4">' + escapeHtml(opts) + '</textarea>' +
			'</div>'
		);
	}

	function escapeHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function addFieldCard(field) {
		field = field || {};
		var type = field.type || 'text';
		var id = field.field_id || uid();
		var label = field.label || (fieldTypes[type] || 'Field');
		var placeholder = field.placeholder || '';
		var required = !!field.required;
		var isHalf = field.width === 'half';
		var typeLabel = fieldTypes[type] || type;
		var supportsWidth = type !== 'checkbox';

		var $card = $(
			'<div class="bootg-field-card" data-field-id="' + id + '" data-field-type="' + type + '">' +
			'<div class="bootg-field-card-head">' +
			'<span class="bootg-drag-handle" title="Drag to reorder">&#9776;</span>' +
			'<span class="bootg-field-type-badge">' + escapeHtml(typeLabel) + '</span>' +
			'<button type="button" class="bootg-field-remove" title="Remove field">&times;</button>' +
			'</div>' +
			'<div class="bootg-field-card-row">' +
			'<label>Label</label>' +
			'<input type="text" class="bootg-input bootg-field-label" value="' + escapeHtml(label) + '">' +
			'</div>' +
			(type !== 'checkbox' && type !== 'select'
				? '<div class="bootg-field-card-row"><label>Placeholder</label><input type="text" class="bootg-input bootg-field-placeholder" value="' + escapeHtml(placeholder) + '"></div>'
				: '') +
			(type === 'select' ? renderOptionsBlock(field) : '') +
			'<label class="bootg-toggle-row bootg-field-required-row">' +
			'<span class="bootg-toggle"><input type="checkbox" class="bootg-field-required" ' + (required ? 'checked' : '') + '><span class="bootg-toggle-slider"></span></span>' +
			'Required' +
			'</label>' +
			(supportsWidth
				? '<label class="bootg-toggle-row bootg-field-width-row">' +
					'<span class="bootg-toggle"><input type="checkbox" class="bootg-field-width" ' + (isHalf ? 'checked' : '') + '><span class="bootg-toggle-slider"></span></span>' +
					'Half width (pairs with the next half-width field)' +
					'</label>'
				: '') +
			'</div>'
		);

		$list.append($card);
		toggleEmptyState();
	}

	$('.bootg-palette-btn').on('click', function () {
		addFieldCard({ type: $(this).data('field-type') });
	});

	$list.on('click', '.bootg-field-remove', function () {
		$(this).closest('.bootg-field-card').remove();
		toggleEmptyState();
	});

	$list.sortable({ handle: '.bootg-drag-handle', placeholder: 'bootg-field-placeholder-ghost', forcePlaceholderSize: true });

	// Load existing fields (edit mode).
	try {
		var existing = JSON.parse(document.getElementById('bootg-existing-fields').textContent || '[]');
		existing.forEach(addFieldCard);
	} catch (e) {}
	toggleEmptyState();

	// Copy shortcode to clipboard.
	document.querySelectorAll('.bootg-shortcode').forEach(function (el) {
		el.addEventListener('click', function () {
			navigator.clipboard.writeText(el.getAttribute('data-shortcode')).then(function () {
				var prev = el.textContent;
				el.textContent = 'Copied!';
				setTimeout(function () { el.textContent = prev; }, 1200);
			});
		});
	});

	$('#bootg-form-builder-form').on('submit', function () {
		var fields = [];
		$list.children('.bootg-field-card').each(function () {
			var $c = $(this);
			var type = $c.data('field-type');
			var field = {
				field_id: $c.data('field-id'),
				type: type,
				label: $c.find('.bootg-field-label').val(),
				required: $c.find('.bootg-field-required').is(':checked')
			};
			if (type !== 'checkbox') {
				field.width = $c.find('.bootg-field-width').is(':checked') ? 'half' : 'full';
			}
			if (type !== 'checkbox' && type !== 'select') {
				field.placeholder = $c.find('.bootg-field-placeholder').val();
			}
			if (type === 'select') {
				field.options = ($c.find('.bootg-field-options').val() || '').split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
			}
			fields.push(field);
		});
		$('#bootg-schema-json').val(JSON.stringify(fields));
	});
});
