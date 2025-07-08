// Store the original content when the page loads
let homeContent = null;

const dynamicContent = document.getElementById("dynamic-content");
if (dynamicContent) {
  homeContent = dynamicContent.innerHTML;
}

document.querySelectorAll(".wiki-btn").forEach(function (btn) {
  btn.addEventListener("click", function () {
    const title = this.getAttribute("data-title");
    if (title === "Home") {
      // Restore the original content instead of reloading
      if (homeContent) {
        document.getElementById("dynamic-content").innerHTML = homeContent;
      }
      return;
    }
    fetch(
      `/skins/GiantBomb/includes/partials/results.php?title=${encodeURIComponent(
        title,
      )}`,
    )
      .then((response) => response.text())
      .then((html) => {
        document.getElementById("dynamic-content").innerHTML = html;
      });
  });
});
