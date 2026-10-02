/**
 * A "Weavit" icon in the block editor's top toolbar (next to the default
 * WP tabs, before Save — same pattern as RankMath's SEO icon) that opens
 * a sidebar panel for this post type's custom fields (same data as the
 * classic "Details" meta box below the content — this is just a faster
 * way to reach it, not a replacement).
 */
( function ( wp ) {
	if ( ! window.weavitEditorPanel || ! window.weavitEditorPanel.fields ) {
		return;
	}

	var el                        = wp.element.createElement;
	var registerPlugin            = wp.plugins.registerPlugin;
	var PluginSidebar             = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PanelBody                 = wp.components.PanelBody;
	var PanelRow                  = wp.components.PanelRow;
	var TextControl                = wp.components.TextControl;
	var TextareaControl            = wp.components.TextareaControl;
	var CheckboxControl            = wp.components.CheckboxControl;
	var useSelect                 = wp.data.useSelect;
	var useDispatch               = wp.data.useDispatch;

	var fields = window.weavitEditorPanel.fields;
	var icon   = 'admin-generic'; // Same dashicon as the Weavit admin menu, for recognizability.

	function WeavitPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		function setField( key, value ) {
			var next = {};
			next[ key ] = value;
			editPost( { meta: Object.assign( {}, meta, next ) } );
		}

		var rows = Object.keys( fields ).map( function ( key ) {
			var field = fields[ key ];

			if ( 'checkbox' === field.type ) {
				return el( PanelRow, { key: key },
					el( CheckboxControl, {
						label: field.label,
						checked: !! meta[ key ],
						onChange: function ( value ) { setField( key, value ); },
					} )
				);
			}

			var Control = 'textarea' === field.type ? TextareaControl : TextControl;

			return el( PanelRow, { key: key },
				el( Control, {
					label: field.label,
					value: meta[ key ] || '',
					type: 'url' === field.type ? 'url' : undefined,
					onChange: function ( value ) { setField( key, value ); },
				} )
			);
		} );

		return el( PluginSidebar, { name: 'weavit-details', title: 'Weavit', icon: icon },
			el( PanelBody, {}, rows )
		);
	}

	registerPlugin( 'weavit-editor-panel', {
		icon: icon,
		render: function () {
			return el( wp.element.Fragment, {},
				el( PluginSidebarMoreMenuItem, { target: 'weavit-details', icon: icon }, 'Weavit' ),
				el( WeavitPanel )
			);
		},
	} );
} )( window.wp );
