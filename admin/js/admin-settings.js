/**
 * Tidy Admin Menu - Settings Page JavaScript
 *
 * Handles drag-drop reordering, visibility toggles, and AJAX saving.
 *
 * @package Tidy_Admin_Menu
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	var TidySettings = {
		/**
		 * Separator counter.
		 */
		separatorCount: 0,

		/**
		 * Track if there are unsaved changes.
		 */
		hasUnsavedChanges: false,

		/**
		 * Current active role (when in role mode).
		 */
		activeRole: '',

		/**
		 * Current apply_to setting.
		 */
		applyTo: 'all',

		/**
		 * Initialize the settings page.
		 */
		init: function() {
			this.applyTo = tidyAdminMenu.applyTo || 'all';
			this.activeRole = tidyAdminMenu.activeRole || '';
			this.countExistingSeparators();
			this.initSortable();
			this.bindEvents();
			this.bindSaveButton();
		},

		/**
		 * Count existing separators to set counter.
		 */
		countExistingSeparators: function() {
			var maxNum = 0;
			$( '.tidy-separator-item' ).each( function() {
				var slug = $( this ).data( 'slug' );
				var match = slug.match( /separator(\d+)/ );
				if ( match ) {
					var num = parseInt( match[1], 10 );
					if ( num > maxNum ) {
						maxNum = num;
					}
				}
			} );
			this.separatorCount = maxNum;
		},

		/**
		 * Initialize jQuery UI Sortable.
		 */
		initSortable: function() {
			var self = this;

			$( '#tidy-menu-list' ).sortable( {
				handle: '.tidy-drag-handle',
				placeholder: 'tidy-menu-item ui-sortable-placeholder',
				tolerance: 'pointer',
				cursor: 'grabbing',
				opacity: 0.8,
				update: function() {
					self.markUnsaved();
				}
			} );
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Visibility toggle.
			$( document ).on( 'change', '.tidy-visibility-toggle', function() {
				var $item = $( this ).closest( '.tidy-menu-item' );
				$item.toggleClass( 'tidy-is-hidden', ! this.checked );
				self.updateShowAllCheckbox();
				self.markUnsaved();
			} );

			// Show All checkbox.
			$( '#tidy-show-all' ).on( 'change', function() {
				var showAll = this.checked;
				$( '.tidy-visibility-toggle' ).each( function() {
					if ( this.checked !== showAll ) {
						this.checked = showAll;
						$( this ).closest( '.tidy-menu-item' ).toggleClass( 'tidy-is-hidden', ! showAll );
					}
				} );
				self.markUnsaved();
			} );

			// Add separator.
			$( '#tidy-add-separator' ).on( 'click', function() {
				self.addSeparator();
			} );

			// Remove separator.
			$( document ).on( 'click', '.tidy-remove-separator', function() {
				$( this ).closest( '.tidy-menu-item' ).fadeOut( 200, function() {
					$( this ).remove();
					self.markUnsaved();
				} );
			} );

			// Settings change.
			$( 'input[name="tidy_apply_to"]' ).on( 'change', function() {
				self.saveSettings();
			} );

			// Role tab clicks - warn about unsaved changes.
			$( document ).on( 'click', '.tidy-role-tabs .nav-tab', function( e ) {
				// Don't do anything for disabled tabs.
				if ( $( this ).hasClass( 'nav-tab-disabled' ) ) {
					e.preventDefault();
					return false;
				}

				if ( self.hasUnsavedChanges ) {
					if ( ! confirm( 'You have unsaved changes. Are you sure you want to switch roles? Your changes will be lost.' ) ) {
						e.preventDefault();
						return false;
					}
				}
				// Allow navigation to proceed.
			} );

			// Export.
			$( '#tidy-export' ).on( 'click', function() {
				self.exportConfig();
			} );

			// Import.
			$( '#tidy-import' ).on( 'click', function() {
				self.importConfig();
			} );

			// File input change - update filename display and show/hide import button.
			$( '#tidy-import-file' ).on( 'change', function() {
				var $filename = $( '.tidy-import-filename' );
				var $importBtn = $( '#tidy-import' );
				if ( this.files.length ) {
					$filename.text( this.files[0].name ).addClass( 'has-file' );
					$importBtn.removeClass( 'tidy-import-hidden' );
				} else {
					$filename.text( tidyAdminMenu.strings.noFileChosen ).removeClass( 'has-file' );
					$importBtn.addClass( 'tidy-import-hidden' );
				}
			} );

			// Keyboard navigation.
			$( document ).on( 'keydown', '.tidy-menu-item', function( e ) {
				self.handleKeyboard( e, $( this ) );
			} );
		},

		/**
		 * Handle keyboard navigation.
		 *
		 * @param {Event} e Keyboard event.
		 * @param {jQuery} $item Current item.
		 */
		handleKeyboard: function( e, $item ) {
			var self = this;

			// Alt + Arrow keys for reordering.
			if ( e.altKey && ( e.keyCode === 38 || e.keyCode === 40 ) ) {
				e.preventDefault();

				if ( e.keyCode === 38 ) {
					// Move up.
					var $prev = $item.prev( '.tidy-menu-item' );
					if ( $prev.length ) {
						$item.insertBefore( $prev );
						$item.trigger( 'focus' );
						self.markUnsaved();
					}
				} else {
					// Move down.
					var $next = $item.next( '.tidy-menu-item' );
					if ( $next.length ) {
						$item.insertAfter( $next );
						$item.trigger( 'focus' );
						self.markUnsaved();
					}
				}
			}

			// Space or Enter to toggle visibility.
			if ( e.keyCode === 32 || e.keyCode === 13 ) {
				var $checkbox = $item.find( '.tidy-visibility-toggle' );
				if ( $checkbox.length && ! $( e.target ).is( 'input, button' ) ) {
					e.preventDefault();
					$checkbox.prop( 'checked', ! $checkbox.prop( 'checked' ) ).trigger( 'change' );
				}
			}
		},

		/**
		 * Add a new separator.
		 */
		addSeparator: function() {
			this.separatorCount++;
			var slug = 'separator' + this.separatorCount;

			var html = '<li class="tidy-menu-item tidy-separator-item" data-slug="' + slug + '" role="option" tabindex="0">' +
				'<span class="tidy-drag-handle" aria-hidden="true">' +
					'<span class="dashicons dashicons-menu"></span>' +
				'</span>' +
				'<span class="tidy-item-content tidy-separator-content">' +
					'<span class="tidy-separator-line"></span>' +
					'<span class="tidy-separator-label">' + tidyAdminMenu.strings.separator + '</span>' +
					'<span class="tidy-separator-line"></span>' +
				'</span>' +
				'<button type="button" class="tidy-remove-separator button-link" aria-label="Remove separator">' +
					'<span class="dashicons dashicons-no-alt"></span>' +
				'</button>' +
			'</li>';

			$( '#tidy-menu-list' ).append( html );
			this.markUnsaved();
		},

		/**
		 * Mark that there are unsaved changes.
		 */
		markUnsaved: function() {
			this.hasUnsavedChanges = true;
			$( '#tidy-save-settings' ).prop( 'disabled', false ).addClass( 'button-primary' );
			this.updateStatus( 'unsaved' );
		},

		/**
		 * Update the Show All checkbox state based on individual visibility toggles.
		 */
		updateShowAllCheckbox: function() {
			var $checkboxes = $( '.tidy-visibility-toggle' );
			var allChecked = $checkboxes.length > 0 && $checkboxes.filter( ':checked' ).length === $checkboxes.length;
			$( '#tidy-show-all' ).prop( 'checked', allChecked );
		},

		/**
		 * Bind save button click handler.
		 */
		bindSaveButton: function() {
			var self = this;

			$( '#tidy-save-settings' ).on( 'click', function() {
				if ( self.hasUnsavedChanges ) {
					self.saveAll();
				}
			} );
		},

		/**
		 * Save all settings (order and hidden items).
		 */
		saveAll: function() {
			var self = this;
			var order = [];
			var hidden = [];

			$( '#tidy-menu-list .tidy-menu-item' ).each( function() {
				var slug = $( this ).data( 'slug' );
				order.push( slug );
				if ( $( this ).hasClass( 'tidy-is-hidden' ) ) {
					hidden.push( slug );
				}
			} );

			this.updateStatus( 'saving' );
			$( '#tidy-save-settings' ).prop( 'disabled', true );

			var postData = {
				action: 'tidy_save_all_settings',
				nonce: tidyAdminMenu.nonce,
				order: order,
				hidden: hidden
			};

			// Include role if in role mode.
			if ( this.applyTo === 'role' && this.activeRole ) {
				postData.role = this.activeRole;
			}

			$.post( tidyAdminMenu.ajaxUrl, postData )
			.done( function( response ) {
				if ( response.success ) {
					self.hasUnsavedChanges = false;
					$( '#tidy-save-settings' ).removeClass( 'button-primary' );
					self.updateStatus( 'saved' );
					// Reload to update the admin menu sidebar.
					setTimeout( function() {
						window.location.reload();
					}, 500 );
				} else {
					$( '#tidy-save-settings' ).prop( 'disabled', false );
					self.updateStatus( 'error' );
				}
			} )
			.fail( function() {
				$( '#tidy-save-settings' ).prop( 'disabled', false );
				self.updateStatus( 'error' );
			} );
		},

		/**
		 * Save menu order via AJAX.
		 */
		saveOrder: function() {
			var self = this;
			var order = [];

			$( '#tidy-menu-list .tidy-menu-item' ).each( function() {
				order.push( $( this ).data( 'slug' ) );
			} );

			$.post( tidyAdminMenu.ajaxUrl, {
				action: 'tidy_save_menu_order',
				nonce: tidyAdminMenu.nonce,
				order: order
			} )
			.done( function( response ) {
				if ( response.success ) {
					self.updateStatus( 'saved' );
				} else {
					self.updateStatus( 'error' );
				}
			} )
			.fail( function() {
				self.updateStatus( 'error' );
			} );
		},

		/**
		 * Save hidden items via AJAX.
		 */
		saveHidden: function() {
			var self = this;
			var hidden = [];

			$( '#tidy-menu-list .tidy-menu-item.tidy-is-hidden' ).each( function() {
				hidden.push( $( this ).data( 'slug' ) );
			} );

			$.post( tidyAdminMenu.ajaxUrl, {
				action: 'tidy_save_hidden_items',
				nonce: tidyAdminMenu.nonce,
				hidden: hidden
			} )
			.done( function( response ) {
				if ( response.success ) {
					self.updateStatus( 'saved' );
				} else {
					self.updateStatus( 'error' );
				}
			} )
			.fail( function() {
				self.updateStatus( 'error' );
			} );
		},

		/**
		 * Save general settings.
		 */
		saveSettings: function() {
			var self = this;
			var applyTo = $( 'input[name="tidy_apply_to"]:checked' ).val();

			this.updateStatus( 'saving' );

			$.post( tidyAdminMenu.ajaxUrl, {
				action: 'tidy_save_settings',
				nonce: tidyAdminMenu.nonce,
				apply_to: applyTo
			} )
			.done( function( response ) {
				if ( response.success ) {
					self.updateStatus( 'saved' );
					// Reload page to refresh data based on new setting.
					setTimeout( function() {
						window.location.reload();
					}, 500 );
				} else {
					self.updateStatus( 'error' );
				}
			} )
			.fail( function() {
				self.updateStatus( 'error' );
			} );
		},

		/**
		 * Export configuration.
		 */
		exportConfig: function() {
			var self = this;

			var postData = {
				action: 'tidy_export_config',
				nonce: tidyAdminMenu.nonce
			};

			// Include role if in role mode.
			if ( self.applyTo === 'role' && self.activeRole ) {
				postData.role = self.activeRole;
			}

			$.post( tidyAdminMenu.ajaxUrl, postData )
			.done( function( response ) {
				if ( response.success ) {
					var data = JSON.stringify( response.data, null, 2 );
					var blob = new Blob( [ data ], { type: 'application/json' } );
					var url = URL.createObjectURL( blob );
					var a = document.createElement( 'a' );
					a.href = url;
					// Include role in filename if applicable.
					var filename = 'tidy-admin-menu-config';
					if ( self.applyTo === 'role' && self.activeRole ) {
						filename += '-' + self.activeRole;
					}
					a.download = filename + '.json';
					document.body.appendChild( a );
					a.click();
					document.body.removeChild( a );
					URL.revokeObjectURL( url );
				} else {
					alert( tidyAdminMenu.strings.error );
				}
			} )
			.fail( function() {
				alert( tidyAdminMenu.strings.error );
			} );
		},

		/**
		 * Import configuration.
		 */
		importConfig: function() {
			var self = this;
			var fileInput = document.getElementById( 'tidy-import-file' );

			if ( ! fileInput.files.length ) {
				alert( 'Please select a file to import.' );
				return;
			}

			var file = fileInput.files[0];
			var reader = new FileReader();

			reader.onload = function( e ) {
				var config = e.target.result;

				self.updateStatus( 'saving' );

				var postData = {
					action: 'tidy_import_config',
					nonce: tidyAdminMenu.nonce,
					config: config
				};

				// Include role if in role mode.
				if ( self.applyTo === 'role' && self.activeRole ) {
					postData.role = self.activeRole;
				}

				$.post( tidyAdminMenu.ajaxUrl, postData )
				.done( function( response ) {
					if ( response.success ) {
						self.updateStatus( 'saved' );
						window.location.reload();
					} else {
						self.updateStatus( 'error' );
						alert( response.data.message || tidyAdminMenu.strings.error );
					}
				} )
				.fail( function() {
					self.updateStatus( 'error' );
				} );
			};

			reader.readAsText( file );
		},

		/**
		 * Update save status indicator.
		 *
		 * @param {string} status Status: 'unsaved', 'saving', 'saved', or 'error'.
		 */
		updateStatus: function( status ) {
			var $status = $( '#tidy-save-status' );
			var text = '';

			$status.removeClass( 'unsaved saving saved error' ).addClass( status );

			switch ( status ) {
				case 'unsaved':
					text = tidyAdminMenu.strings.unsaved;
					break;
				case 'saving':
					text = tidyAdminMenu.strings.saving;
					break;
				case 'saved':
					text = tidyAdminMenu.strings.saved;
					// Clear after delay.
					setTimeout( function() {
						$status.removeClass( 'saved' ).text( '' );
					}, 2000 );
					break;
				case 'error':
					text = tidyAdminMenu.strings.error;
					break;
			}

			$status.text( text );
		}
	};

	// Initialize on document ready.
	$( function() {
		TidySettings.init();
	} );

} )( jQuery );
