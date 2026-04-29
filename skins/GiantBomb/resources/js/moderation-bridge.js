/**
 * Moderation Bridge for OOUI Upload Booklet
 */
(function () {
  console.log("Moderation Bridge: OOUI Booklet Monitoring active.");

  var bridgeInterval = setInterval(function () {
    // 1. Look for the "sent to moderation" success text
    var labels = document.querySelectorAll(".oo-ui-labelElement-label");
    var successLabel = null;

    for (var i = 0; i < labels.length; i++) {
      if (labels[i].innerText.includes("sent to moderation")) {
        successLabel = labels[i];
        break;
      }
    }

    if (successLabel) {
      console.log("Moderation Bridge: Success detected!");

      // 2. Extract filename from the OOUI "Name" input
      // The HTML shows an input with id "ooui-7" but that changes.
      // We target it via its parent layout class.
      var nameInput = document.querySelector(
        '.mw-upload-bookletLayout-infoForm input[type="text"]',
      );
      var filename = nameInput ? nameInput.value : null;

      if (filename) {
        console.log("Moderation Bridge: Found filename: " + filename);

        // 3. Find the Page Forms target field.
        // We'll look for the input that Page Forms marked as the source for this upload.
        // It usually adds a class or we can use the ID from the pfInputID URL parameter.
        var targetField = null;
        var urlParams = new URLSearchParams(window.location.search);
        var inputId = urlParams.get("pfInputID");

        if (inputId) {
          targetField = document.getElementById(inputId);
        }

        // Fallback: If ID is missing, look for the most recently clicked PF upload field
        if (!targetField) {
          targetField =
            document.querySelector(".pfUploadable.active") ||
            document.querySelector("input.pfRemoteSelect");
        }

        if (targetField) {
          // Update the field and trigger change so Page Forms handles the preview
          targetField.value = filename;
          jQuery(targetField).trigger("change");
          console.log("Moderation Bridge: Form field updated successfully.");

          // 4. Auto-close the UI
          // Click 'Dismiss' on the success message
          var dismissBtn = document.querySelector(
            ".oo-ui-processDialog-errors-actions a.oo-ui-buttonElement-button",
          );
          if (dismissBtn) dismissBtn.click();

          // Click 'Cancel' (which now acts as Close) on the main dialog
          setTimeout(function () {
            var closeBtn = document.querySelector(
              ".oo-ui-processDialog-actions-safe a.oo-ui-buttonElement-button",
            );
            if (closeBtn) closeBtn.click();
          }, 500);

          clearInterval(bridgeInterval);
        } else {
          console.warn(
            "Moderation Bridge: Could not find target form field to update.",
          );
        }
      }
    }
  }, 1000);

  // Timeout after 5 minutes
  setTimeout(function () {
    clearInterval(bridgeInterval);
  }, 300000);
})();
