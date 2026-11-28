/**
 * Editor side of the Members Only block. Written without a build step.
 *
 * The section is an ordinary container: its blocks are saved in the post and
 * the PHP render callback decides, per visitor, whether to print them.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var be = wp.blockEditor;
	var c = wp.components;
	var data = window.membersOnlyData || { roles: [], memberships: [], courses: [], hasMepr: false, hasLearnDash: false };

	var lockIcon = el(
		'svg',
		{ viewBox: '0 0 24 24', width: 16, height: 16, 'aria-hidden': true, focusable: false },
		el( 'path', {
			fill: 'currentColor',
			d: 'M17 10h-1V7a4 4 0 0 0-8 0v3H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2Zm-7-3a2 2 0 1 1 4 0v3h-4Z',
		} )
	);

	/**
	 * Label for a list of chosen IDs or slugs, using the names the server sent.
	 */
	function names( chosen, choices ) {
		return chosen
			.map( function ( value ) {
				var match = choices.filter( function ( choice ) {
					return choice.value === value;
				} )[ 0 ];
				return match ? match.label : String( value );
			} )
			.join( ', ' );
	}

	/**
	 * "Shown to: ..." text for the bar above the section.
	 */
	function summary( a ) {
		switch ( a.audience ) {
			case 'guests':
				return __( 'Signed-out visitors', 'members-only' );
			case 'roles':
				return a.roles.length ? names( a.roles, data.roles ) : __( 'nobody yet: choose a role', 'members-only' );
			case 'memberships':
				return a.memberships.length
					? names( a.memberships, data.memberships )
					: __( 'nobody yet: choose a membership', 'members-only' );
			case 'courses':
				return a.courses.length
					? sprintf(
							/* translators: %s: course names. */
							__( 'Students of %s', 'members-only' ),
							names( a.courses, data.courses )
					  )
					: __( 'nobody yet: choose a course', 'members-only' );
			default:
				return __( 'Signed-in visitors', 'members-only' );
		}
	}

	/**
	 * A list of checkboxes that edits one array attribute.
	 */
	function Checklist( props ) {
		if ( ! props.choices.length ) {
			return el( 'p', { className: 'members-only-empty' }, props.empty );
		}

		return el(
			'fieldset',
			{ className: 'members-only-checklist' },
			el( 'legend', null, props.legend ),
			props.choices.map( function ( choice ) {
				return el( c.CheckboxControl, {
					key: String( choice.value ),
					label: choice.label,
					checked: props.value.indexOf( choice.value ) !== -1,
					onChange: function ( on ) {
						props.onChange( function ( current ) {
							var next = current.filter( function ( v ) {
								return v !== choice.value;
							} );
							return on ? next.concat( [ choice.value ] ) : next;
						} );
					},
					__nextHasNoMarginBottom: true,
				} );
			} )
		);
	}

	wp.blocks.registerBlockType( 'members-only/section', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var key = { roles: 'roles', memberships: 'memberships', courses: 'courses' }[ a.audience ];
			var nobody = key && ! a[ key ].length;

			// Each checkbox passes a change function, applied to the list as it is now.
			function update( name ) {
				return function ( change ) {
					set( { [ name ]: change( props.attributes[ name ] || [] ) } );
				};
			}

			var audiences = [
				{ value: 'members', label: __( 'Signed-in visitors', 'members-only' ) },
				{ value: 'guests', label: __( 'Signed-out visitors', 'members-only' ) },
				{ value: 'roles', label: __( 'Chosen roles', 'members-only' ) },
			];
			if ( data.hasMepr || a.audience === 'memberships' ) {
				audiences.push( { value: 'memberships', label: __( 'MemberPress members', 'members-only' ) } );
			}
			if ( data.hasLearnDash || a.audience === 'courses' ) {
				audiences.push( { value: 'courses', label: __( 'LearnDash students', 'members-only' ) } );
			}

			var blockProps = be.useBlockProps( {
				className: 'members-only-section' + ( nobody ? ' is-nobody' : '' ),
			} );
			var innerProps = be.useInnerBlocksProps(
				{ className: 'members-only-section__inner' },
				{
					template: [ [ 'core/paragraph', { placeholder: __( 'Add what this audience should see…', 'members-only' ) } ] ],
				}
			);

			return el(
				'div',
				blockProps,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Who can see this', 'members-only' ) },
						el( c.RadioControl, {
							label: __( 'Show this section to', 'members-only' ),
							hideLabelFromVision: true,
							selected: a.audience,
							options: audiences,
							onChange: function ( v ) {
								set( { audience: v } );
							},
						} ),
						a.audience === 'roles' &&
							el( Checklist, {
								legend: __( 'Roles', 'members-only' ),
								choices: data.roles,
								value: a.roles,
								empty: __( 'No roles found.', 'members-only' ),
								onChange: update( 'roles' ),
							} ),
						a.audience === 'memberships' &&
							el( Checklist, {
								legend: __( 'Any of these memberships', 'members-only' ),
								choices: data.memberships,
								value: a.memberships,
								empty: data.hasMepr
									? __( 'No published memberships yet.', 'members-only' )
									: __( 'MemberPress is not active, so nobody sees this section.', 'members-only' ),
								onChange: update( 'memberships' ),
							} ),
						a.audience === 'courses' &&
							el( Checklist, {
								legend: __( 'Enrolled in any of these courses', 'members-only' ),
								choices: data.courses,
								value: a.courses,
								empty: data.hasLearnDash
									? __( 'No published courses yet.', 'members-only' )
									: __( 'LearnDash is not active, so nobody sees this section.', 'members-only' ),
								onChange: update( 'courses' ),
							} ),
						nobody &&
							el(
								c.Notice,
								{ status: 'warning', isDismissible: false, className: 'members-only-notice' },
								__( 'Nobody can see this section until you choose at least one.', 'members-only' )
							)
					),
					a.audience !== 'guests' &&
						el(
							c.PanelBody,
							{ title: __( 'Everyone else', 'members-only' ) },
							el( c.ToggleControl, {
								label: __( 'Show a short message instead', 'members-only' ),
								help: a.showPrompt
									? __( 'Signed-out visitors also get a Log in button that brings them back here.', 'members-only' )
									: __( 'The section simply disappears for everyone else.', 'members-only' ),
								checked: a.showPrompt,
								onChange: function ( v ) {
									set( { showPrompt: v } );
								},
								__nextHasNoMarginBottom: true,
							} ),
							a.showPrompt &&
								el( c.TextareaControl, {
									label: __( 'Message', 'members-only' ),
									help: __( 'Leave empty for "This section is for members. Log in to see it."', 'members-only' ),
									value: a.promptText,
									onChange: function ( v ) {
										set( { promptText: v } );
									},
									__nextHasNoMarginBottom: true,
								} )
						)
				),
				el(
					'div',
					{ className: 'members-only-section__bar' },
					lockIcon,
					el(
						'span',
						null,
						el( 'strong', null, __( 'Members Only', 'members-only' ) ),
						' · ',
						sprintf(
							/* translators: %s: who can see the section. */
							__( 'Shown to: %s', 'members-only' ),
							summary( a )
						)
					),
					a.audience !== 'guests' &&
						a.showPrompt &&
						el(
							'span',
							{ className: 'members-only-section__hint' },
							__( 'Others see a sign-in message', 'members-only' )
						)
				),
				el( 'div', innerProps )
			);
		},

		save: function () {
			var blockProps = be.useBlockProps.save( { className: 'members-only-section' } );
			return el( 'div', be.useInnerBlocksProps.save( blockProps ) );
		},
	} );
} )( window.wp );
