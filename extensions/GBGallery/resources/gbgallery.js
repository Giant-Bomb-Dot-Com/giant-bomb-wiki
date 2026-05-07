(function () {
  var items = Array.from(
    document.querySelectorAll(".gb-gallery-item[data-thumb]"),
  );
  if (!items.length) {
    return;
  }

  items.forEach(function (item) {
    var img = document.createElement("img");
    img.src = item.getAttribute("data-thumb");
    img.alt = item.getAttribute("data-alt") || "";
    img.loading = "lazy";
    var link = item.querySelector("a");
    if (link) {
      link.prepend(img);
    }
  });

  var currentIndex = 0;
  var overlay = null;
  var lbImg = null;
  var lbCaption = null;
  var lbCounter = null;

  function show(index) {
    currentIndex = index;
    var item = items[index];
    var fullUrl = item.getAttribute("data-full");
    var alt = item.getAttribute("data-alt") || "";
    lbImg.src = fullUrl;
    lbImg.alt = alt;
    lbCaption.textContent = alt;
    lbCaption.style.display = alt ? "" : "none";
    lbCounter.textContent = index + 1 + " / " + items.length;
  }

  function open(index) {
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.className = "gb-lightbox";
      overlay.id = "gb-images-lightbox";
      overlay.setAttribute("data-ad-context", "lightbox");
      overlay.innerHTML =
        '<button class="gb-lightbox-close" aria-label="Close">&times;</button>' +
        '<span class="gb-lightbox-counter"></span>' +
        '<button class="gb-lightbox-nav gb-lightbox-prev" aria-label="Previous">&#8249;</button>' +
        '<img class="gb-lightbox-img" src="" alt="" />' +
        '<button class="gb-lightbox-nav gb-lightbox-next" aria-label="Next">&#8250;</button>' +
        '<div class="gb-lightbox-caption"></div>';
      document.body.appendChild(overlay);

      lbImg = overlay.querySelector(".gb-lightbox-img");
      lbCaption = overlay.querySelector(".gb-lightbox-caption");
      lbCounter = overlay.querySelector(".gb-lightbox-counter");

      overlay
        .querySelector(".gb-lightbox-close")
        .addEventListener("click", close);
      overlay
        .querySelector(".gb-lightbox-prev")
        .addEventListener("click", function () {
          show((currentIndex - 1 + items.length) % items.length);
        });
      overlay
        .querySelector(".gb-lightbox-next")
        .addEventListener("click", function () {
          show((currentIndex + 1) % items.length);
        });
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) {
          close();
        }
      });
    }

    overlay.style.display = "flex";
    document.body.style.overflow = "hidden";
    show(index);
  }

  function close() {
    if (overlay) {
      overlay.style.display = "none";
      document.body.style.overflow = "";
    }
  }

  document.addEventListener("keydown", function (e) {
    if (!overlay || overlay.style.display === "none") {
      return;
    }
    if (e.key === "Escape") {
      close();
    } else if (e.key === "ArrowLeft") {
      show((currentIndex - 1 + items.length) % items.length);
    } else if (e.key === "ArrowRight") {
      show((currentIndex + 1) % items.length);
    }
  });

  items.forEach(function (item, index) {
    var link = item.querySelector("a");
    if (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        open(index);
      });
    }
  });
})();
