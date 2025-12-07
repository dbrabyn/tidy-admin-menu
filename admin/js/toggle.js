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

		if ( ! button ) {
			return;
		}

		// Check initial state from sessionStorage.
		var isActive = sessionStorage.getItem( STORAGE_KEY ) === 'true';

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
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
