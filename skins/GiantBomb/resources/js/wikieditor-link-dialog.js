/**
 * Injects custom formatting buttons into the WikiEditor OOUI Link Dialog.
 */
$(document).on("dialogopen", function (event) {
  var $dialog = $(event.target),
    $targetWrapper,
    $targetInput,
    $header,
    $controls,
    config,
    allPrefixes;

  if ($dialog.attr("id") !== "wikieditor-toolbar-link-dialog") return;
  if ($dialog.find(".custom-format-controls").length > 0) return;

  $targetWrapper = $dialog.find(".mw-wikiEditor-InsertLink-TitleInputField");
  $targetInput = $targetWrapper.find("input").first();
  $header = $targetWrapper.find(".oo-ui-fieldLayout-header");

  $controls = $("<span>")
    .addClass("custom-format-controls")
    .css({ "margin-left": "10px", display: "inline-block" });

  config = [
    { label: "Accessory", prefix: "Accessories/" },
    { label: "Character", prefix: "Characters/" },
    { label: "Company", prefix: "Companies/" },
    { label: "Concept", prefix: "Concepts/" },
    { label: "Franchise", prefix: "Franchises/" },
    { label: "Game", prefix: "Games/" },
    { label: "Genre", prefix: "Genres/" },
    { label: "Location", prefix: "Locations/" },
    { label: "Person", prefix: "People/" },
    { label: "Platform", prefix: "Platforms/" },
    { label: "Object", prefix: "Objects/" },
    { label: "Theme", prefix: "Themes/" },
  ];

  allPrefixes = config
    .map(function (item) {
      return item.prefix.replace(/[-\/\\^$*+?.()|[\]{}]/g, "\\$&");
    })
    .join("|");

  $.each(config, function (i, btn) {
    $("<button>")
      .text("+ " + btn.label)
      .attr("type", "button")
      .addClass("ui-button ui-widget ui-state-default ui-corner-all")
      .css({
        cursor: "pointer",
        "margin-right": "5px",
        padding: "1px 6px",
        "font-size": "0.85em",
      })
      .on("click", function (e) {
        e.preventDefault();

        var currentVal = $targetInput.val();

        // remove prefix if it exists
        var prefixRegex = new RegExp("^(" + allPrefixes + ")", "i");
        var baseTitle = currentVal.replace(prefixRegex, "");

        // replace spaces and punctuation with underscore
        var sanitized = baseTitle.replace(
          /[\s!\"#$%&'()*+,\-./:;<=>?@\[\\\]^`{|}~]+/g,
          "_",
        );
        sanitized = sanitized.replace(/^_+/, "");
        var formatted = btn.prefix + sanitized;

        // update input field and refresh search
        $targetInput.val(formatted).trigger("change");
        $targetInput.trigger($.Event("keyup", { which: 65 })).focus();
      })
      .appendTo($controls);
  });

  $header.append($controls);
});
