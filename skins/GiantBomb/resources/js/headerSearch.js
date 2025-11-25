document
  .getElementById("gb-header-search")
  .addEventListener("keypress", function (event) {
    if (event.key === "Enter") {
      searchGBWiki($(this).val());
    }
  });

document.getElementById("gb-header-btn").addEventListener("click", function () {
  searchGBWiki(document.getElementById("gb-header-search").value);
});

function searchGBWiki(searchText) {
  window.location.href =
    "/search?type=wiki&q=" + encodeURIComponent(searchText);
}
