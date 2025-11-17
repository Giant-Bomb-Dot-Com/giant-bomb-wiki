/**
 * JavaScript file for the CookieConsent extension.
 */

let cookieConsent = (function ($) {
	"use strict";

	// Value of the above cookies when consent is given
	const COOKIECONSENT_CONSENT_GIVEN_COOKIE_VALUE = "given";

	// Cookie that is set when the dialog has been dismissed
	const COOKIECONSENT_DIALOG_DISMISSED_COOKIE_NAME =
		"cookieconsent_dialog_dismissed";

	return {
		/**
		 * Initializes the consent mode by doing the following:
		 *
		 * - Adding a click handler to `#manage-cookie-preferences` to allow users to update their consent preferences.
		 * - Opening the simple (initial) consent dialog if the dialog has not been dismissed before.
		 * - Processing any tags (scripts, iframes) if the dialog has been dismissed before.
		 */
		init: function () {
			$("#manage-cookie-preferences").click(function (e) {
				cookieConsent.openDetailedDialog();
			});

			if (!cookieConsent.isDismissed()) {
				cookieConsent.openSimpleDialog();
			} else {
				cookieConsent.processTags();
			}
		},

		/**
		 * Returns true if consent is given for the given category name, false otherwise.
		 *
		 * @param {string} categoryName
		 * @returns {boolean}
		 */
		isConsentGiven: function (categoryName) {
			return (
				mw.cookie.get(this.__getCookieName(categoryName)) ===
				COOKIECONSENT_CONSENT_GIVEN_COOKIE_VALUE
			);
		},

		/**
		 * Returns true if the consent dialog has been dismissed, false otherwise.
		 */
		isDismissed: function () {
			return mw.cookie.get(COOKIECONSENT_DIALOG_DISMISSED_COOKIE_NAME);
		},

		/**
		 * Processes all script and iframe tags that depend on consent, and fires the 'cookie-consent-tags-processed'
		 * event on the window after finishing.
		 */
		processTags: function () {
			this.__processScripts();
			this.__processIframes();

			const event = new CustomEvent("cookie-consent-tags-processed");
			window.dispatchEvent(event);
		},

		/**
		 * Opens the detailed cookie preferences dialog.
		 */
		openDetailedDialog: function () {
			const cookieConsent = this;

			function DetailedConsentDialog(config) {
				DetailedConsentDialog.super.call(this, config);
			}

			// The consent dialog is a process dialog
			// @see https://www.mediawiki.org/wiki/OOUI/Windows/Process_Dialogs
			OO.inheritClass(DetailedConsentDialog, OO.ui.ProcessDialog);

			const windowManager = new OO.ui.WindowManager();
			$(document.body).append(windowManager.$element);

			const consentDialog = new DetailedConsentDialog({
				size: "medium",
				classes: ["cookie-consent-dialog", "cookie-consent-detailed-dialog"],
			});

			DetailedConsentDialog.static.name =
				"ext.CookieConsent.detailedConsentDialog";
			DetailedConsentDialog.static.title = mw
				.message("cookieconsent-detailed-dialog-title")
				.text();
			DetailedConsentDialog.static.actions = [
				{
					action: "save-preferences",
					label: mw.message("cookieconsent-save-preferences").text(),
					flags: ["primary", "progressive"],
				},
			];

			if (mw.cookie.get(COOKIECONSENT_DIALOG_DISMISSED_COOKIE_NAME)) {
				// If the dialog has been dismissed before, add a "Cancel" button
				DetailedConsentDialog.static.actions.push({
					action: "cancel",
					label: mw.message("cancel").text(),
					flags: "safe",
				});
			} else {
				// Otherwise, add a back button
				DetailedConsentDialog.static.actions.push({
					action: "back",
					label: mw.message("back").text(),
					flags: ["safe", "back"],
				});
			}

			DetailedConsentDialog.prototype.initialize = function () {
				DetailedConsentDialog.super.prototype.initialize.apply(this, arguments);

				// Necessary cookies checkbox
				const necessaryCookiesFieldsetItem = new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget({ selected: true, disabled: true }),
					{
						label: mw
							.message("cookieconsent-category-name-strictly-necessary")
							.text(),
						align: "inline",
						help: mw
							.message("cookieconsent-category-desc-strictly-necessary")
							.text(),
					},
				);

				// Other categories
				this.preferenceCheckboxes = {};
				this.preferenceFieldsetItems = [necessaryCookiesFieldsetItem];

				for (const [categoryName, category] of Object.entries(
					cookieConsent.__getConsentCategories(),
				)) {
					this.preferenceCheckboxes[categoryName] =
						new OO.ui.CheckboxInputWidget();
					this.preferenceFieldsetItems.push(
						new OO.ui.FieldLayout(this.preferenceCheckboxes[categoryName], {
							label: category.namemsg
								? mw.message(category.namemsg).text()
								: category.name,
							align: "inline",
							help: category.descriptionmsg
								? mw.message(category.descriptionmsg).text()
								: category.description,
						}),
					);
				}

				this.panel = new OO.ui.PanelLayout({
					padded: true,
					expanded: false,
				});

				this.content = new OO.ui.FieldsetLayout({
					classes: ["cookieconsent-dialog-fieldset-layout"],
				}).addItems(this.preferenceFieldsetItems);

				this.panel.$element.append(
					mw.message("cookieconsent-detailed-dialog-intro").parse(),
				);
				this.panel.$element.append(this.content.$element);
				this.panel.$element.append(
					mw.message("cookieconsent-detailed-dialog-outro").parse(),
				);

				this.$body.append(this.panel.$element);
			};

			// Let the dialog know which consent preferences have (previously) been given, so that the checkboxes can be
			// (un)checked accordingly
			DetailedConsentDialog.prototype.setConsentPreferences = function (
				consentPreferences,
			) {
				for (const categoryName of Object.keys(
					cookieConsent.__getConsentCategories(),
				)) {
					this.preferenceCheckboxes[categoryName].setSelected(
						consentPreferences[categoryName],
					);
				}
			};

			// Get the current consent preferences as visible in the dialog
			DetailedConsentDialog.prototype.getConsentPreferences = function () {
				const consentPreferences = {};

				for (const categoryName of Object.keys(
					cookieConsent.__getConsentCategories(),
				)) {
					consentPreferences[categoryName] =
						this.preferenceCheckboxes[categoryName].isSelected();
				}

				return consentPreferences;
			};

			// The setup process takes data inserted during the initialisation of the window, and updates the presentation
			// accordingly.
			DetailedConsentDialog.prototype.getSetupProcess = function (data) {
				return DetailedConsentDialog.super.prototype.getSetupProcess
					.call(this, data)
					.next(
						() => this.setConsentPreferences(data.consentPreferences),
						this,
					);
			};

			DetailedConsentDialog.prototype.getActionProcess = function (action) {
				return new OO.ui.Process(function () {
					if (action === "save-preferences") {
						cookieConsent.__updateConsentPreferences(
							this.getConsentPreferences(),
						);
					} else if (action === "cancel") {
						windowManager.closeWindow(consentDialog);
					} else if (action === "back") {
						cookieConsent.openSimpleDialog();
						windowManager.closeWindow(consentDialog);
					} else {
						// Fallback to parent handler
						return DetailedConsentDialog.super.prototype.getActionProcess.call(
							this,
							action,
						);
					}
				}, this);
			};

			DetailedConsentDialog.prototype.getBodyHeight = function () {
				return this.panel.$element.outerHeight(true);
			};

			windowManager.addWindows([consentDialog]);
			windowManager.openWindow(consentDialog, {
				consentPreferences: cookieConsent.__getConsentPreferences(),
			});
		},

		/**
		 * Opens the simple cookie preferences dialog.
		 */
		openSimpleDialog: function () {
			const cookieConsent = this;

			function SimpleConsentDialog(config) {
				SimpleConsentDialog.super.call(this, config);
			}

			// The consent dialog is a process dialog
			// @see https://www.mediawiki.org/wiki/OOUI/Windows/Process_Dialogs
			OO.inheritClass(SimpleConsentDialog, OO.ui.ProcessDialog);

			const windowManager = new OO.ui.WindowManager();
			$(document.body).append(windowManager.$element);

			const windowWidth =
				window.innerWidth ||
				document.documentElement.clientWidth ||
				document.body.clientWidth;

			const consentDialog = new SimpleConsentDialog({
				size: windowWidth < 720 ? "small" : "medium",
				classes: ["cookie-consent-dialog", "cookie-consent-simple-dialog"],
			});

			SimpleConsentDialog.static.name = "ext.CookieConsent.simpleConsentDialog";
			SimpleConsentDialog.static.title = mw
				.message("cookieconsent-simple-dialog-title")
				.text();
			SimpleConsentDialog.static.actions = [
				{
					action: "accept-all",
					icon: "check",
					label: mw.message("cookieconsent-accept-all").text(),
					flags: ["progressive"],
				},
				{
					action: "manage-preferences",
					label: mw.message("cookieconsent-manage-preferences-short").text(),
				},
			];

			SimpleConsentDialog.prototype.initialize = function () {
				SimpleConsentDialog.super.prototype.initialize.apply(this, arguments);

				this.panel = new OO.ui.PanelLayout({
					padded: true,
					expanded: false,
				});

				this.panel.$element.append(
					mw.message("cookieconsent-simple-dialog-content").parse(),
				);

				this.$body.append(this.panel.$element);
			};

			SimpleConsentDialog.prototype.getActionProcess = function (action) {
				return new OO.ui.Process(function () {
					if (action === "accept-all") {
						const consentPreferences = {};

						for (const categoryName of Object.keys(
							cookieConsent.__getConsentCategories(),
						)) {
							consentPreferences[categoryName] = true;
						}

						cookieConsent.__updateConsentPreferences(consentPreferences);
						windowManager.closeWindow(consentDialog);
					} else if (action === "manage-preferences") {
						cookieConsent.openDetailedDialog();
						windowManager.closeWindow(consentDialog);
					} else {
						// Fallback to parent handler
						return SimpleConsentDialog.super.prototype.getActionProcess.call(
							this,
							action,
						);
					}
				}, this);
			};

			SimpleConsentDialog.prototype.getBodyHeight = function () {
				return this.panel.$element.outerHeight(true);
			};

			windowManager.addWindows([consentDialog]);
			windowManager.openWindow(consentDialog, {
				consentPreferences: cookieConsent.__getConsentPreferences(),
			});
		},

		__getCookieName: function (categoryName) {
			return "cookieconsent_consent_" + categoryName;
		},

		__getConsentCategories: function () {
			return mw.config.get("wgCookieConsentCategories");
		},

		__getConsentPreferences: function () {
			const preferences = {};

			for (const categoryName of Object.keys(this.__getConsentCategories())) {
				preferences[categoryName] = this.isConsentGiven(categoryName);
			}

			return preferences;
		},

		__updateConsentPreferences: function (consentPreferences) {
			for (let [categoryName, isConsented] of Object.entries(
				consentPreferences,
			)) {
				const cookieName = this.__getCookieName(categoryName);
				const cookieValue = isConsented
					? COOKIECONSENT_CONSENT_GIVEN_COOKIE_VALUE
					: null;
				const cookieOptions = isConsented
					? { expires: 60 * 60 * 24 * 365 } // 1 year
					: {};

				mw.cookie.set(cookieName, cookieValue, cookieOptions);
			}

			if (!mw.cookie.get(COOKIECONSENT_DIALOG_DISMISSED_COOKIE_NAME)) {
				// Set the dialog to "dismissed" to disable automatic opening
				mw.cookie.set(
					COOKIECONSENT_DIALOG_DISMISSED_COOKIE_NAME,
					"dismissed",
					{ expires: 60 * 60 * 24 * 365 }, // 1 year
				);
			}

			// Reload the page
			location.reload();
		},

		__enableScript: function (script) {
			const newScript = document.createElement("script");
			newScript.text = script.text;
			for (const attributeName of script.getAttributeNames()) {
				newScript.setAttribute(
					attributeName,
					script.getAttribute(attributeName),
				);
			}

			newScript.setAttribute("type", "text/javascript");

			const parent = script.parentElement;

			parent.insertBefore(newScript, script);
			parent.removeChild(script);
		},

		__enableIframe: function (iframe) {
			const src = iframe.getAttribute("data-src");
			iframe.setAttribute("src", src);
		},

		__processScripts: function () {
			const scripts = document.querySelectorAll("script[data-cookieconsent]");
			for (const script of scripts) {
				const categories = script.getAttribute("data-cookieconsent").split(",");
				const consents = categories.map((category) =>
					cookieConsent.isConsentGiven(category),
				);
				if (consents.some((p) => p)) {
					this.__enableScript(script);
				}
			}
		},

		__processIframes: function () {
			const iframes = document.querySelectorAll("iframe[data-cookieconsent]");
			for (const iframe of iframes) {
				const categories = iframe.getAttribute("data-cookieconsent").split(",");
				const consents = categories.map((category) =>
					cookieConsent.isConsentGiven(category),
				);
				if (consents.some((p) => p)) {
					this.__enableIframe(iframe);
				}
			}
		},
	};
})(jQuery);

window.cookieConsent = cookieConsent;
