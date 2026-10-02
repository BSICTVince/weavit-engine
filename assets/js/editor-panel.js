/**
 * A "Weavit" icon in the block editor's top toolbar (next to the default
 * WP tabs, before Save — same pattern as RankMath's SEO icon) that opens
 * a single tabbed sidebar panel. Each tab (Details, SEO, …) is
 * contributed by a different plugin file via the `weavit_editor_panel_tabs`
 * PHP filter (inc/editor-panel.php) instead of each registering its own
 * classic meta box — one editing surface per post, not several stacked
 * below the content.
 */
( function ( wp ) {
	if ( ! window.weavitEditorPanel || ! window.weavitEditorPanel.tabs ) {
		return;
	}

	var el                        = wp.element.createElement;
	var registerPlugin            = wp.plugins.registerPlugin;
	var PluginSidebar             = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PanelRow                  = wp.components.PanelRow;
	var TabPanel                  = wp.components.TabPanel;
	var TextControl                = wp.components.TextControl;
	var TextareaControl            = wp.components.TextareaControl;
	var CheckboxControl            = wp.components.CheckboxControl;
	var useSelect                 = wp.data.useSelect;
	var useDispatch               = wp.data.useDispatch;

	var tabs = window.weavitEditorPanel.tabs;
	var icon = 'admin-generic'; // Same dashicon as the Weavit admin menu, for recognizability.

	function FieldControls( fields, meta, setField ) {
		return Object.keys( fields ).map( function ( key ) {
			var field = fields[ key ];

			if ( 'checkbox' === field.type ) {
				return el( PanelRow, { key: key },
					el( CheckboxControl, {
						label: field.label,
						help: field.help,
						checked: !! meta[ key ],
						onChange: function ( value ) { setField( key, value ); },
					} )
				);
			}

			var Control = 'textarea' === field.type ? TextareaControl : TextControl;

			return el( PanelRow, { key: key },
				el( Control, {
					label: field.label,
					help: field.help,
					value: meta[ key ] || '',
					type: 'url' === field.type ? 'url' : undefined,
					onChange: function ( value ) { setField( key, value ); },
				} )
			);
		} );
	}

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

		var tabKeys = Object.keys( tabs );

		var padding = { padding: '16px' };

		var body = tabKeys.length > 1
			? el( TabPanel, {
				tabs: tabKeys.map( function ( key ) { return { name: key, title: tabs[ key ].label }; } ),
			}, function ( tab ) {
				return el( 'div', { style: padding }, FieldControls( tabs[ tab.name ].fields, meta, setField ) );
			} )
			: el( 'div', { style: padding }, FieldControls( tabs[ tabKeys[ 0 ] ].fields, meta, setField ) );

		return el( PluginSidebar, { name: 'weavit-details', title: 'Weavit', icon: icon, className: 'weavit-editor-panel' }, body );
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
