document
  .getElementById("gb-search-bar")
  .addEventListener("keypress", function (event) {
    if (event.key === "Enter") {
      searchGBWiki($(this).val());
    }
  });

document.getElementById("gb-search-btn").addEventListener("click", function () {
  searchGBWiki(document.getElementById("gb-search-bar").value);
});

function searchGBWiki(searchText) {
  window.location.href =
    "/search?type=wiki&q=" + encodeURIComponent(searchText);
}
