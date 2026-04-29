(function () {
  console.log("Moderation Bridge: Extension loaded.");

  var lastClickedFieldId = null;

  // 1. Listen for clicks on ANY Page Forms upload link/button
  document.addEventListener("click", function (event) {
    var target = event.target.closest(".pfUploadable, .pfRemoteSelect");
    if (!target) return;

    // Find the input ID
    lastClickedFieldId = target.id || target.closest(".pfUploadable")?.id;

    // Fallback: If no ID, get the pfInputID from the link's href
    if (!lastClickedFieldId && target.href) {
      var params = new URLSearchParams(target.href.split("?")[1]);
      lastClickedFieldId = params.get("pfInputID");
    }

    console.log(
      "Moderation Bridge: Upload started for field: " + lastClickedFieldId,
    );
  });

  var bridgeInterval = setInterval(function () {
    var labels = document.querySelectorAll(".oo-ui-labelElement-label");
    var successFound = false;
    for (var i = 0; i < labels.length; i++) {
      if (labels[i].innerText.indexOf("sent to moderation") !== -1) {
        successFound = true;
        break;
      }
    }

    if (successFound) {
      console.log("Moderation Bridge: Success message detected!");

      // 2. Get the finalized filename and extension
      var nameInput = document.querySelector(
        '.mw-upload-bookletLayout-infoForm input[type="text"]',
      );
      var baseName = nameInput ? nameInput.value : "";
      var filename = baseName;

      // Grab extension from the actual file selected in the OOUI input
      var fileInput = document.querySelector('input[type="file"]');
      if (fileInput && fileInput.files && fileInput.files[0]) {
        var ext = fileInput.files[0].name.split(".").pop();
        if (!filename.toLowerCase().endsWith("." + ext.toLowerCase())) {
          filename += "." + ext;
        }
      }

      if (filename) {
        // 3. Determine the target field to update with the filename
        var targetField = null;

        if (lastClickedFieldId) {
          targetField = document.getElementById(lastClickedFieldId);
        }

        // If we still can't find it by ID, use the specific field name as a last resort
        // Note: Page Forms uses TemplateName[FieldName]
        if (!targetField) {
          targetField = document.querySelector("input.pfRemoteSelect");
        }

        if (targetField) {
          targetField.value = filename;
          targetField.dispatchEvent(new Event("change", { bubbles: true }));
          console.log("Moderation Bridge: Updated field: " + targetField.id);

          // 4. Close the UI
          var dismissBtn = document.querySelector(
            ".oo-ui-processDialog-errors-actions .oo-ui-buttonElement-button",
          );
          if (dismissBtn) dismissBtn.click();

          setTimeout(function () {
            if (typeof OO !== "undefined" && OO.ui && OO.ui.getWindowManager) {
              var wm = OO.ui.getWindowManager();
              if (wm && wm.getCurrentWindow()) {
                wm.getCurrentWindow().close();
              }
            }
          }, 400);

          clearInterval(bridgeInterval);
        } else {
          console.error("Moderation Bridge: Could not find target field.");
          clearInterval(bridgeInterval);
        }
      }
    }
  }, 1000);
})();
