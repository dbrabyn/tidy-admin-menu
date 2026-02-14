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
				},
				stop: function() {
					// Re-sync class state with checkbox state after sorting.
					self.syncVisibilityClasses();
				}
			} );
		},

		/**
		 * Sync visibility classes with checkbox states.
		 *
		 * Ensures the tidy-is-hidden class matches the checkbox state
		 * after DOM manipulation (e.g., sorting). Also removes any
		 * inline opacity styles left by jQuery UI Sortable.
		 */
		syncVisibilityClasses: function() {
			$( '#tidy-menu-list .tidy-menu-item' ).each( function() {
				var $item = $( this );
				// Remove inline opacity style left by jQuery UI Sortable.
				$item.css( 'opacity', '' );
				var $checkbox = $item.find( '.tidy-visibility-toggle' );
				if ( $checkbox.length ) {
					if ( $checkbox.prop( 'checked' ) ) {
						$item.removeClass( 'tidy-is-hidden' );
					} else {
						$item.addClass( 'tidy-is-hidden' );
					}
				}
			} );
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Reset button.
			$( '#tidy-reset-menu' ).on( 'click', function() {
				self.resetMenu();
			} );

			// Visibility toggle.
			$( document ).on( 'change', '.tidy-visibility-toggle', function() {
				var $item = $( this ).closest( '.tidy-menu-item' );
				if ( this.checked ) {
					$item.removeClass( 'tidy-is-hidden' );
				} else {
					$item.addClass( 'tidy-is-hidden' );
				}
				self.updateShowAllCheckbox();
				self.markUnsaved();
			} );

			// Show All checkbox (top-level items only).
			$( '#tidy-show-all' ).on( 'change', function() {
				var showAll = this.checked;
				$( '#tidy-menu-list > .tidy-menu-item' ).each( function() {
					var $checkbox = $( this ).find( '> .tidy-item-content .tidy-visibility-toggle' );
					if ( $checkbox.length && $checkbox.prop( 'checked' ) !== showAll ) {
						$checkbox.prop( 'checked', showAll );
						$( this ).toggleClass( 'tidy-is-hidden', ! showAll );
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

			// Extra options checkbox changes (with hardcoded sync).
			$( '.tidy-extra-options input[type="checkbox"]' ).on( 'change', function() {
				self.syncHardcodedToSubmenu( $( this ) );
				self.markUnsaved();
			} );

			// Show Submenu Items toggle.
			$( '#tidy-show-submenus' ).on( 'change', function() {
				var expand = this.checked;
				$( '.tidy-has-submenu' ).each( function() {
					var $btn = $( this ).find( '> .tidy-submenu-toggle' );
					if ( expand ) {
						$( this ).addClass( 'tidy-parent-expanded' );
						$btn.attr( 'aria-expanded', 'true' );
					} else {
						$( this ).removeClass( 'tidy-parent-expanded' );
						$btn.attr( 'aria-expanded', 'false' );
					}
				} );
			} );

			// Submenu expand/collapse toggle.
			$( document ).on( 'click', '.tidy-submenu-toggle', function( e ) {
				e.preventDefault();
				var $parent = $( this ).closest( '.tidy-menu-item' );
				var expanded = $parent.hasClass( 'tidy-parent-expanded' );
				$parent.toggleClass( 'tidy-parent-expanded', ! expanded );
				$( this ).attr( 'aria-expanded', ! expanded ? 'true' : 'false' );
			} );

			// Submenu visibility toggle.
			$( document ).on( 'change', '.tidy-submenu-visibility', function() {
				var $subItem = $( this ).closest( '.tidy-submenu-item' );
				if ( this.checked ) {
					$subItem.removeClass( 'tidy-is-hidden' );
				} else {
					$subItem.addClass( 'tidy-is-hidden' );
				}
				var $parent = $( this ).closest( '.tidy-menu-item' );
				self.updateSubmenuBadge( $parent );
				self.updateEmptyParentState( $parent );
				self.updateParentBulkToggle( $parent );
				self.syncSubmenuToHardcoded( $subItem );
				self.markUnsaved();
			} );

			// Per-parent bulk toggle.
			$( document ).on( 'change', '.tidy-parent-bulk-toggle', function() {
				var showAll = this.checked;
				var $parent = $( this ).closest( '.tidy-menu-item' );
				$parent.find( '.tidy-submenu-visibility' ).each( function() {
					if ( this.checked !== showAll ) {
						this.checked = showAll;
						$( this ).closest( '.tidy-submenu-item' ).toggleClass( 'tidy-is-hidden', ! showAll );
						self.syncSubmenuToHardcoded( $( this ).closest( '.tidy-submenu-item' ) );
					}
				} );
				$parent.find( '.tidy-bulk-toggle-label' ).text(
					showAll ? tidyAdminMenu.strings.hideAllSubs : tidyAdminMenu.strings.showAllSubs
				);
				self.updateSubmenuBadge( $parent );
				self.updateEmptyParentState( $parent );
				self.markUnsaved();
			} );

			// Role tab clicks - warn about unsaved changes.
			$( document ).on( 'click', '.tidy-role-tabs .nav-tab', function( e ) {
				// Don't do anything for disabled tabs.
				if ( $( this ).hasClass( 'nav-tab-disabled' ) ) {
					e.preventDefault();
					return false;
				}

				if ( self.hasUnsavedChanges ) {
					if ( ! confirm( tidyAdminMenu.strings.confirmSwitchRole ) ) {
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
				'<button type="button" class="tidy-remove-separator button-link" aria-label="' + tidyAdminMenu.strings.removeSeparator + '">' +
					'<span class="dashicons dashicons-no-alt"></span>' +
				'</button>' +
			'</li>';

			$( '#tidy-menu-list' ).prepend( html );
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
			var $checkboxes = $( '#tidy-menu-list > .tidy-menu-item > .tidy-item-content .tidy-visibility-toggle' );
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

			$( '#tidy-menu-list > .tidy-menu-item' ).each( function() {
				var slug = $( this ).data( 'slug' );
				order.push( slug );
				if ( $( this ).hasClass( 'tidy-is-hidden' ) ) {
					hidden.push( slug );
				}
			} );

			// Collect hidden submenu items.
			var hiddenSubmenus = {};
			$( '.tidy-submenu-item.tidy-is-hidden' ).each( function() {
				var parentSlug = $( this ).data( 'parent-slug' );
				var slug = $( this ).data( 'slug' );
				if ( parentSlug && slug ) {
					if ( ! hiddenSubmenus[ parentSlug ] ) {
						hiddenSubmenus[ parentSlug ] = [];
					}
					hiddenSubmenus[ parentSlug ].push( slug );
				}
			} );

			this.updateStatus( 'saving' );
			$( '#tidy-save-settings' ).prop( 'disabled', true );

			var postData = {
				action: 'tidy_save_all_settings',
				nonce: tidyAdminMenu.nonce,
				order: order,
				hidden: hidden,
				hidden_submenus: JSON.stringify( hiddenSubmenus ),
				hide_collapse_menu: $( '#tidy-hide-collapse-menu' ).prop( 'checked' ) ? 'true' : 'false',
				hide_theme_editor: $( '#tidy-hide-theme-editor' ).prop( 'checked' ) ? 'true' : 'false',
				hide_plugin_editor: $( '#tidy-hide-plugin-editor' ).prop( 'checked' ) ? 'true' : 'false',
				hide_available_tools: $( '#tidy-hide-available-tools' ).prop( 'checked' ) ? 'true' : 'false',
				hide_privacy: $( '#tidy-hide-privacy' ).prop( 'checked' ) ? 'true' : 'false',
				hide_customize: $( '#tidy-hide-customize' ).prop( 'checked' ) ? 'true' : 'false'
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
			var hideCollapseMenu = $( '#tidy-hide-collapse-menu' ).prop( 'checked' );

			this.updateStatus( 'saving' );

			$.post( tidyAdminMenu.ajaxUrl, {
				action: 'tidy_save_settings',
				nonce: tidyAdminMenu.nonce,
				apply_to: applyTo,
				hide_collapse_menu: hideCollapseMenu ? 'true' : 'false'
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
					// Build descriptive filename with site name and timestamp.
					var date = new Date();
					var dateStr = date.getFullYear() + '-' +
						String( date.getMonth() + 1 ).padStart( 2, '0' ) + '-' +
						String( date.getDate() ).padStart( 2, '0' ) + '-' +
						String( date.getHours() ).padStart( 2, '0' ) +
						String( date.getMinutes() ).padStart( 2, '0' );
					var filename = 'tidy-config';
					if ( tidyAdminMenu.siteName ) {
						filename += '-' + tidyAdminMenu.siteName;
					}
					if ( self.applyTo === 'role' && self.activeRole ) {
						filename += '-' + self.activeRole;
					}
					filename += '-' + dateStr;
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
				alert( tidyAdminMenu.strings.selectFileToImport );
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
		 * Reset menu to default.
		 */
		resetMenu: function() {
			if ( ! confirm( tidyAdminMenu.strings.confirmReset ) ) {
				return;
			}

			var postData = {
				action: 'tidy_reset_menu',
				nonce: tidyAdminMenu.nonce
			};

			// Include role if in role mode.
			if ( this.applyTo === 'role' && this.activeRole ) {
				postData.role = this.activeRole;
			}

			$.post( tidyAdminMenu.ajaxUrl, postData )
			.done( function( response ) {
				if ( response.success ) {
					window.location.reload();
				} else {
					alert( tidyAdminMenu.strings.resetError );
				}
			} )
			.fail( function() {
				alert( tidyAdminMenu.strings.resetError );
			} );
		},

		/**
		 * Hardcoded checkbox to submenu item mappings.
		 */
		hardcodedMappings: {
			'tidy-hide-theme-editor': { parent: 'themes.php', submenu: 'theme-editor.php' },
			'tidy-hide-plugin-editor': { parent: 'plugins.php', submenu: 'plugin-editor.php' },
			'tidy-hide-available-tools': { parent: 'tools.php', submenu: 'tools.php' },
			'tidy-hide-privacy': { parent: 'options-general.php', submenu: 'options-privacy.php' },
			'tidy-hide-customize': { parent: 'themes.php', submenu: 'customize.php' }
		},

		/**
		 * Sync a hardcoded Extra Options checkbox to its dynamic submenu item.
		 *
		 * @param {jQuery} $checkbox The hardcoded checkbox that changed.
		 */
		syncHardcodedToSubmenu: function( $checkbox ) {
			var id = $checkbox.attr( 'id' );
			var mapping = this.hardcodedMappings[ id ];
			if ( ! mapping ) {
				return;
			}
			var isHidden = $checkbox.prop( 'checked' );
			var $subItem = $( '.tidy-submenu-item[data-parent-slug="' + mapping.parent + '"][data-slug="' + mapping.submenu + '"]' );
			if ( $subItem.length ) {
				$subItem.find( '.tidy-submenu-visibility' ).prop( 'checked', ! isHidden );
				$subItem.toggleClass( 'tidy-is-hidden', isHidden );
				var $parent = $subItem.closest( '.tidy-menu-item' );
				this.updateSubmenuBadge( $parent );
				this.updateEmptyParentState( $parent );
				this.updateParentBulkToggle( $parent );
			}
		},

		/**
		 * Sync a dynamic submenu item change to its hardcoded Extra Options checkbox.
		 *
		 * @param {jQuery} $subItem The submenu item that changed.
		 */
		syncSubmenuToHardcoded: function( $subItem ) {
			var parentSlug = $subItem.data( 'parent-slug' );
			var slug = $subItem.data( 'slug' );
			var isHidden = $subItem.hasClass( 'tidy-is-hidden' );

			var self = this;
			$.each( this.hardcodedMappings, function( checkboxId, mapping ) {
				if ( mapping.parent === parentSlug && mapping.submenu === slug ) {
					$( '#' + checkboxId ).prop( 'checked', isHidden );
					return false; // Break.
				}
			} );
		},

		/**
		 * Update the submenu badge count for a parent item.
		 *
		 * @param {jQuery} $parent The parent menu item.
		 */
		updateSubmenuBadge: function( $parent ) {
			var $badge = $parent.find( '> .tidy-submenu-badge' );
			if ( ! $badge.length ) {
				return;
			}
			var hiddenCount = $parent.find( '.tidy-submenu-item.tidy-is-hidden' ).length;
			if ( hiddenCount > 0 ) {
				$badge.text( tidyAdminMenu.strings.nHidden.replace( '%d', hiddenCount ) ).removeClass( 'tidy-badge-hidden' );
			} else {
				$badge.addClass( 'tidy-badge-hidden' );
			}
		},

		/**
		 * Update the empty parent indicator when all submenus are hidden.
		 *
		 * @param {jQuery} $parent The parent menu item.
		 */
		updateEmptyParentState: function( $parent ) {
			var totalSubs = $parent.find( '.tidy-submenu-item' ).length;
			var hiddenSubs = $parent.find( '.tidy-submenu-item.tidy-is-hidden' ).length;
			var allHidden = totalSubs > 0 && hiddenSubs === totalSubs;
			var $checkbox = $parent.find( '> .tidy-item-content .tidy-visibility-toggle' );

			if ( allHidden && $checkbox.prop( 'checked' ) ) {
				$checkbox.prop( 'checked', false );
				$parent.addClass( 'tidy-is-hidden' );
				this.updateShowAllCheckbox();
			} else if ( ! allHidden && ! $checkbox.prop( 'checked' ) && $parent.hasClass( 'tidy-all-subs-hidden' ) ) {
				$checkbox.prop( 'checked', true );
				$parent.removeClass( 'tidy-is-hidden' );
				this.updateShowAllCheckbox();
			}

			$parent.toggleClass( 'tidy-all-subs-hidden', allHidden );
		},

		/**
		 * Update the per-parent bulk toggle checkbox state.
		 *
		 * @param {jQuery} $parent The parent menu item.
		 */
		updateParentBulkToggle: function( $parent ) {
			var $subs = $parent.find( '.tidy-submenu-visibility' );
			var allChecked = $subs.length > 0 && $subs.filter( ':checked' ).length === $subs.length;
			var $bulk = $parent.find( '.tidy-parent-bulk-toggle' );
			$bulk.prop( 'checked', allChecked );
			$parent.find( '.tidy-bulk-toggle-label' ).text(
				allChecked ? tidyAdminMenu.strings.hideAllSubs : tidyAdminMenu.strings.showAllSubs
			);
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
