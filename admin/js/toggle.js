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

	var STORAGE_KEY = 'tidy_admin_menu_show_all';

	/**
	 * Initialize the toggle functionality.
	 */
	function init() {
		var button = document.querySelector( '.tidy-show-all-btn' );

		// Check initial state from sessionStorage.
		var isActive = sessionStorage.getItem( STORAGE_KEY ) === 'true';

		// Always update empty separators on load, even if no button.
		updateEmptySeparators( isActive );

		if ( ! button ) {
			return;
		}

		// Apply initial state.
		updateState( isActive );

		// Bind click event.
		button.addEventListener( 'click', function( e ) {
			e.preventDefault();
			var newState = ! ( button.getAttribute( 'aria-pressed' ) === 'true' );
			updateState( newState );
			sessionStorage.setItem( STORAGE_KEY, newState.toString() );
		} );

		// Handle keyboard.
		button.addEventListener( 'keydown', function( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				button.click();
			}
		} );
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

			if ( isSeparator ) {
				// If we had a previous separator and no visible items since, hide this one.
				if ( lastSeparator !== null && ! hasVisibleSinceLastSeparator ) {
					item.classList.add( 'tidy-empty-separator' );
				}
				lastSeparator = item;
				hasVisibleSinceLastSeparator = false;
			} else if ( ! isHidden ) {
				// Visible non-separator item.
				hasVisibleSinceLastSeparator = true;
			}
			// Hidden items don't count as visible content.
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

			if ( ! foundVisible ) {
				if ( isSeparator ) {
					item.classList.add( 'tidy-empty-separator' );
				} else if ( ! isHidden ) {
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
