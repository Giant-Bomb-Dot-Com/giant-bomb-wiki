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
  let uri = "/search?type=wiki&q=" + encodeURIComponent(searchText);
  let wikiType = document.getElementById("search_dropdown").value;
  if (wikiType !== "All") uri += "&wikiType=" + wikiType;

  window.location.href = uri;
}
