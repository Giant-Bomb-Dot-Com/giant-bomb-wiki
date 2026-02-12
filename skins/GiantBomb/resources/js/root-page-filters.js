/**
 * MediaWiki:Common.js - Games Portal Logic
 * Includes: Form Conversion, Pagination, and Loading Spinner
 */
$(function () {
  // 1. LUA FORM CONVERTER
  $(".lua-form-wrapper").each(function () {
    var $container = $(this);
    var rawText = $container.text();

    var tempDiv = document.createElement("div");
    tempDiv.innerHTML = rawText;
    $container.empty().append(tempDiv.childNodes);

    var $form = $container.find("form");
    var $searchField = $container.find("#search-filter");
    var $selectField = $container.find("#root-dropdown-filter");
    var $resetBtn = $container.find("#reset-filter");
    var $searchBtn = $container.find("#apply-filter");
    var $sortField = $container.find("#sort-by");

    // 1. Search button - Manual Submit
    $searchBtn.on("click", function (e) {
      e.preventDefault();
      if (typeof showGlobalLoading === "function") showGlobalLoading();
      $form.submit();
    });

    // 3. Handle Enter Key in Search Field
    $searchField.on("keydown", function (e) {
      if (e.key === "Enter" || e.keyCode === 13) {
        e.preventDefault();
        if (typeof showGlobalLoading === "function") showGlobalLoading();
        $form.submit();
      }
    });

    // 3. Accessibility: Focus cursor at end of text if search exists
    if ($searchField.val()) {
      $searchField.focus();
      var val = $searchField.val();
      $searchField.val("").val(val); // Forces cursor to end
    }

    //4. Reset button
    $resetBtn.on("click", function (e) {
      e.preventDefault(); // Prevent default button behavior

      // 1. Visually clear the fields
      $searchField.val("");
      $selectField.val("All");
      $sortField.val("");

      // 2. Show loading spinner
      if (typeof showGlobalLoading === "function") showGlobalLoading();

      // 3. Redirect to the page without filters
      // This is the cleanest way to "reset" when using URL parameters

      window.location.href = mw.util.getUrl(null, {});
    });

    $container.addClass("is-ready");
  });

  // 2. HELPER: SPINNER OVERLAY
  // Injects the overlay into the grid and dims content
  function showGlobalLoading() {
    // Attach to body so it centers on the screen
    var $overlay = $(".loading-overlay");

    if (!$overlay.length) {
      $overlay = $(
        '<div class="loading-overlay"><div class="loading-spinner"></div></div>',
      );
      $("body").append($overlay);
    }

    $overlay.css("display", "flex");
    // Optional: add a class to body to prevent scrolling while loading
    $("body").css("overflow", "hidden");
  }

  // 3. PAGINATION FOOTER
  $(".pagination-wrapper").each(function () {
    var $wrapper = $(this);
    var total = parseInt($wrapper.data("total"));
    var limit = parseInt($wrapper.data("limit"));
    var offset = parseInt($wrapper.data("offset"));
    var platform = $wrapper.data("platform");
    var letter = $wrapper.data("letter");
    var search = $wrapper.data("search");
    var sortBy = $wrapper.data("sort");

    var currentPage = Math.floor(offset / limit) + 1;
    var totalPages = Math.ceil(total / limit);
    var start = offset + 1;
    var end = Math.min(offset + limit, total);

    // Pagination Info
    var $info = $('<div class="pagination-info">').text(
      "Showing " + start + "-" + end + " of " + total + " items",
    );

    // Pagination Controls
    var $controls = $('<div class="pagination-controls">');

    function createBtn(label, text, targetPage, className, disabled) {
      var $btn = $("<button>")
        .addClass("pagination-btn " + className)
        .attr("aria-label", label)
        .text(text);

      if (disabled) {
        $btn.attr("disabled", "");
      } else {
        $btn.on("click", function () {
          showGlobalLoading(); // Trigger spinner
          window.location.href = mw.util.getUrl(null, {
            ...(platform && { chosen_platform: platform }),
            ...(letter && { chosen_letter: letter }),
            search_filter: search,
            sort_by: sortBy,
            offset: (targetPage - 1) * limit,
            limit: limit,
          });
        });
      }
      return $btn;
    }

    // First/Prev Buttons
    $controls.append(
      createBtn("First page", "««", 1, "pagination-first", currentPage === 1),
    );
    $controls.append(
      createBtn(
        "Previous page",
        "‹",
        currentPage - 1,
        "pagination-prev",
        currentPage === 1,
      ),
    );

    // Page Numbers (sliding window of 5)
    var startRange = Math.max(1, Math.min(currentPage - 2, totalPages - 4));
    var endRange = Math.min(totalPages, Math.max(currentPage + 2, 5));

    for (var i = startRange; i <= endRange; i++) {
      if (i < 1) continue;
      var $pBtn = createBtn("Page " + i, i, i, "pagination-page", false);
      if (i === currentPage)
        $pBtn.addClass("active").attr("aria-current", "page");
      $controls.append($pBtn);
    }

    // Next/Last Buttons
    $controls.append(
      createBtn(
        "Next page",
        "›",
        currentPage + 1,
        "pagination-next",
        currentPage === totalPages,
      ),
    );
    $controls.append(
      createBtn(
        "Last page",
        "»»",
        totalPages,
        "pagination-last",
        currentPage === totalPages,
      ),
    );

    // Items Per Page Dropdown
    var $size = $('<div class="pagination-size">');
    $size.append('<label for="items-per-page">Items per page:</label>');
    var $select = $('<select id="items-per-page" class="pagination-select">');
    var options = [24, 48, 72, 96];

    options.forEach(function (val) {
      var $opt = $("<option>").val(val).text(val);
      if (val === limit) {
        $opt.prop("selected", true);
      }
      $select.append($opt);
    });

    $select.on("change", function () {
      showGlobalLoading(); // Trigger spinner
      window.location.href = mw.util.getUrl(null, {
        ...(platform && { chosen_platform: platform }),
        ...(letter && { chosen_letter: letter }),
        search_filter: search,
        sort_by: sortBy,
        offset: 0,
        limit: $(this).val(),
      });
    });
    $size.append($select);

    // Assemble and Replace
    var $mainNav = $('<div class="pagination">')
      .append($info)
      .append($controls)
      .append($size);

    $wrapper.replaceWith($mainNav);
  });

  $(".view-toggle").each(function () {
    var $container = $(this);
    $container.addClass("is-ready");
  });
});
