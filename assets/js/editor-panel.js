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

	/** Flattens the block tree into a depth-tagged list of weavit/* blocks only. */
	function collectWeavitBlocks( blocks, depth ) {
		var found = [];
		( blocks || [] ).forEach( function ( block ) {
			if ( block.name && 0 === block.name.indexOf( 'weavit/' ) ) {
				found.push( { clientId: block.clientId, name: block.name, depth: depth } );
			}
			if ( block.innerBlocks && block.innerBlocks.length ) {
				found = found.concat( collectWeavitBlocks( block.innerBlocks, depth + 1 ) );
			}
		} );
		return found;
	}

	function blockLabel( name ) {
		return name.replace( 'weavit/', '' ).replace( /-/g, ' ' ).replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );
	}

	function ComponentNavigator() {
		var blocks = useSelect( function ( select ) {
			return select( 'core/block-editor' ).getBlocks();
		}, [] );
		var selectedClientId = useSelect( function ( select ) {
			return select( 'core/block-editor' ).getSelectedBlockClientId();
		}, [] );
		var selectBlock = useDispatch( 'core/block-editor' ).selectBlock;

		var items = collectWeavitBlocks( blocks, 0 );

		if ( ! items.length ) {
			return el( 'p', { style: { padding: '16px', color: '#757575' } }, 'No Weavit components on this page yet — add a Section, Hero, or Button block from the inserter.' );
		}

		return el( 'ul', { style: { listStyle: 'none', margin: 0, padding: '8px' } },
			items.map( function ( item ) {
				var isSelected = item.clientId === selectedClientId;
				return el( 'li', { key: item.clientId, style: { paddingLeft: ( item.depth * 16 ) + 'px' } },
					el( 'button', {
						type: 'button',
						onClick: function () { selectBlock( item.clientId ); },
						style: {
							display: 'block', width: '100%', textAlign: 'left', padding: '8px 10px', marginBottom: '2px',
							border: 0, borderRadius: '4px', cursor: 'pointer',
							background: isSelected ? '#2271b1' : 'transparent',
							color: isSelected ? '#fff' : '#1e1e1e',
							fontWeight: isSelected ? 600 : 400,
						},
					}, blockLabel( item.name ) )
				);
			} )
		);
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

		function renderTabBody( tabKey ) {
			var tab = tabs[ tabKey ];
			if ( 'navigator' === tab.type ) {
				return el( ComponentNavigator );
			}
			return el( 'div', { style: { padding: '16px' } }, FieldControls( tab.fields, meta, setField ) );
		}

		var tabKeys = Object.keys( tabs );

		var body = tabKeys.length > 1
			? el( TabPanel, {
				tabs: tabKeys.map( function ( key ) { return { name: key, title: tabs[ key ].label }; } ),
			}, function ( tab ) {
				return renderTabBody( tab.name );
			} )
			: renderTabBody( tabKeys[ 0 ] );

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
