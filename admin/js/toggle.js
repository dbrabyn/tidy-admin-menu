/**
 * Tidy Admin Menu - Show All Toggle JavaScript
 *
 * Handles the "Show All" toggle button functionality.
 *
 * @package Tidy_Admin_Menu
 * @since 1.0.0
 */

( function() {
	'use strict';

	/**
	 * Initialize the toggle functionality.
	 */
	function init() {
		var button = document.querySelector( '.tidy-show-all-btn' );

		// Always start with hidden items collapsed (reset on page load).
		updateEmptySeparators( false );

		if ( ! button ) {
			return;
		}

		// Bind click event.
		button.addEventListener( 'click', function( e ) {
			e.preventDefault();
			var newState = ! ( button.getAttribute( 'aria-pressed' ) === 'true' );
			updateState( newState );
		} );

		// Handle keyboard.
		button.addEventListener( 'keydown', function( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				button.click();
			}
		} );

		// Fix submenu flyout positioning when Show All is active.
		initSubmenuPositioning();
	}

	/**
	 * Update the toggle state.
	 *
	 * @param {boolean} isActive Whether the toggle is active.
	 */
	function updateState( isActive ) {
		var button = document.querySelector( '.tidy-show-all-btn' );

		if ( ! button ) {
			return;
		}

		button.setAttribute( 'aria-pressed', isActive.toString() );

		// Update icon and text.
		var icon = button.querySelector( '.tidy-toggle-icon' );
		var text = button.querySelector( '.tidy-toggle-text' );

		if ( icon ) {
			icon.textContent = isActive ? '−' : '+';
		}

		if ( text ) {
			text.textContent = isActive ? button.dataset.textLess : button.dataset.textMore;
		}

		if ( isActive ) {
			document.body.classList.add( 'tidy-show-all-active' );
		} else {
			document.body.classList.remove( 'tidy-show-all-active' );
		}

		// Update empty separator visibility.
		updateEmptySeparators( isActive );
	}

	/**
	 * Fix submenu flyout positioning when Show All mode is active.
	 *
	 * Because #adminmenuwrap has overflow-x: hidden in Show All mode,
	 * flyout submenus get clipped. This repositions them with
	 * position: fixed so they escape the overflow container.
	 */
	function initSubmenuPositioning() {
		var menu = document.getElementById( 'adminmenu' );
		var wrap = document.getElementById( 'adminmenuwrap' );

		if ( ! menu || ! wrap ) {
			return;
		}

		var activeItem = null;
		var activeSubmenu = null;

		function positionSubmenu() {
			if ( ! activeItem || ! activeSubmenu ) {
				return;
			}
			var rect = activeItem.getBoundingClientRect();
			var top = rect.top;

			// Measure submenu height by temporarily making it visible off-screen.
			activeSubmenu.style.position = 'fixed';
			activeSubmenu.style.left = '-9999px';
			activeSubmenu.style.top = '0';
			activeSubmenu.style.maxHeight = '';
			activeSubmenu.style.overflowY = '';
			var submenuHeight = activeSubmenu.offsetHeight;

			// If submenu would overflow the viewport bottom, shift it up.
			var viewportHeight = window.innerHeight;
			var padding = 8;
			if ( top + submenuHeight > viewportHeight - padding ) {
				top = viewportHeight - submenuHeight - padding;
			}

			// Don't let it go above the admin bar.
			var adminBarHeight = 32;
			if ( top < adminBarHeight ) {
				top = adminBarHeight;
			}

			activeSubmenu.style.top = top + 'px';
			activeSubmenu.style.left = rect.right + 'px';
			activeSubmenu.style.zIndex = '10001';
		}

		function resetSubmenu( submenu ) {
			submenu.style.position = '';
			submenu.style.top = '';
			submenu.style.left = '';
			submenu.style.zIndex = '';
		}

		var items = menu.querySelectorAll( ':scope > li' );

		items.forEach( function( item ) {
			item.addEventListener( 'mouseenter', function() {
				if ( ! document.body.classList.contains( 'tidy-show-all-active' ) ) {
					return;
				}
				// Skip items with submenu already visible inline.
				if ( item.classList.contains( 'wp-has-current-submenu' ) || item.classList.contains( 'wp-menu-open' ) ) {
					return;
				}
				var submenu = item.querySelector( '.wp-submenu' );
				if ( ! submenu ) {
					return;
				}
				activeItem = item;
				activeSubmenu = submenu;
				positionSubmenu();
			} );

			item.addEventListener( 'mouseleave', function() {
				var submenu = item.querySelector( '.wp-submenu' );
				if ( submenu ) {
					resetSubmenu( submenu );
				}
				if ( activeItem === item ) {
					activeItem = null;
					activeSubmenu = null;
				}
			} );
		} );

		// Update position when the menu is scrolled.
		wrap.addEventListener( 'scroll', positionSubmenu );
	}

	/**
	 * Hide separators that have no visible items between them.
	 *
	 * When Show All is inactive, separators that only have hidden items
	 * between them (or are at the start/end) should be hidden.
	 *
	 * @param {boolean} showAll Whether Show All mode is active.
	 */
	function updateEmptySeparators( showAll ) {
		var menu = document.getElementById( 'adminmenu' );

		if ( ! menu ) {
			return;
		}

		// Get direct children only (not nested submenu items).
		var items = menu.querySelectorAll( ':scope > li' );

		// First pass: reset all separator classes.
		items.forEach( function( item ) {
			item.classList.remove( 'tidy-empty-separator' );
		} );

		// If showing all, don't hide any separators.
		if ( showAll ) {
			return;
		}

		// Second pass: find and mark empty separators.
		var lastSeparator = null;
		var hasVisibleSinceLastSeparator = false;

		items.forEach( function( item ) {
			// Skip the Show All toggle wrapper.
			if ( item.classList.contains( 'tidy-show-all-wrapper' ) ) {
				return;
			}

			var isSeparator = item.classList.contains( 'wp-menu-separator' );
			var isHidden = item.classList.contains( 'tidy-hidden-item' );
			var isCurrent = item.classList.contains( 'current' ) || item.classList.contains( 'wp-has-current-submenu' );

			if ( isSeparator ) {
				// If we had a previous separator and no visible items since, hide this one.
				if ( lastSeparator !== null && ! hasVisibleSinceLastSeparator ) {
					item.classList.add( 'tidy-empty-separator' );
				}
				lastSeparator = item;
				hasVisibleSinceLastSeparator = false;
			} else if ( ! isHidden || isCurrent ) {
				// Visible non-separator item (hidden items that are current count as visible).
				hasVisibleSinceLastSeparator = true;
			}
			// Hidden items (not current) don't count as visible content.
		} );

		// Handle trailing separator (no visible items after it).
		if ( lastSeparator !== null && ! hasVisibleSinceLastSeparator ) {
			lastSeparator.classList.add( 'tidy-empty-separator' );
		}

		// Third pass: hide leading separators (before any visible content).
		var foundVisible = false;
		items.forEach( function( item ) {
			if ( item.classList.contains( 'tidy-show-all-wrapper' ) ) {
				return;
			}

			var isSeparator = item.classList.contains( 'wp-menu-separator' );
			var isHidden = item.classList.contains( 'tidy-hidden-item' );
			var isCurrent = item.classList.contains( 'current' ) || item.classList.contains( 'wp-has-current-submenu' );

			if ( ! foundVisible ) {
				if ( isSeparator ) {
					item.classList.add( 'tidy-empty-separator' );
				} else if ( ! isHidden || isCurrent ) {
					foundVisible = true;
				}
			}
		} );
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
