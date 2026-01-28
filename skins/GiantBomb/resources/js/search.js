// Search bar functionality - only initialize if elements exist
(function () {
  var searchBar = document.getElementById("gb-search-bar");
  var searchBtn = document.getElementById("gb-search-btn");

  if (searchBar) {
    searchBar.addEventListener("keypress", function (event) {
      if (event.key === "Enter") {
        searchGBWiki(this.value);
      }
    });
  }

  if (searchBtn) {
    searchBtn.addEventListener("click", function () {
      var bar = document.getElementById("gb-search-bar");
      if (bar) searchGBWiki(bar.value);
    });
  }
})();

function searchGBWiki(searchText) {
  let uri = "/search?type=wiki&q=" + encodeURIComponent(searchText);
  let wikiType = document.getElementById("search_dropdown").value;
  if (wikiType !== "All") uri += "&wikiType=" + wikiType;

  window.location.href = uri;
}

$(".clickable-box").click(function () {
  window.location = $(this).data("url");
});
