/**
 * Automatically converts Lua-escaped text into a live form and triggers
 * a search when the dropdown selection changes.
 */
$(function () {
  $(".lua-form-wrapper").each(function () {
    var $container = $(this);
    var rawText = $container.text(); // Get escaped text

    // Decode HTML and inject
    var tempDiv = document.createElement("div");
    tempDiv.innerHTML = rawText;
    $container.empty().append(tempDiv.childNodes);

    // Auto-submit on change
    $container.find("select").on("change", function () {
      $(this).closest("form").submit();
    });

    // Reveal the container
    $container.addClass("is-ready");
  });
});
