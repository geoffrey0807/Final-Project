document.addEventListener("DOMContentLoaded", () => {
  let viewer = null;
  let activeTrigger = null;
  let previousOverflow = "";
  let activeImage = null;

  const fitImageToViewer = () => {
    if (!activeImage?.naturalWidth || !activeImage.naturalHeight) return;
    const maxWidth = window.innerWidth * 0.9;
    const maxHeight = window.innerHeight * 0.86;
    const scale = Math.min(
      maxWidth / activeImage.naturalWidth,
      maxHeight / activeImage.naturalHeight,
    );
    activeImage.style.width = `${Math.round(activeImage.naturalWidth * scale)}px`;
    activeImage.style.height = `${Math.round(activeImage.naturalHeight * scale)}px`;
  };

  const closeViewer = () => {
    if (!viewer) return;
    viewer.remove();
    viewer = null;
    activeImage = null;
    document.body.style.overflow = previousOverflow;
    activeTrigger?.focus();
    activeTrigger = null;
  };

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;
    const trigger = event.target.closest("[data-announcement-attachment-open]");
    if (trigger) {
      if (viewer) closeViewer();

      const source = trigger.dataset.imageSrc;
      if (!source) return;

      activeTrigger = trigger;
      previousOverflow = document.body.style.overflow;
      document.body.style.overflow = "hidden";

      viewer = document.createElement("div");
      viewer.className = "announcement-attachment-viewer";
      viewer.setAttribute("role", "dialog");
      viewer.setAttribute("aria-modal", "true");
      viewer.setAttribute("aria-label", "Announcement attachment");

      const closeButton = document.createElement("button");
      closeButton.className = "announcement-attachment-viewer-close";
      closeButton.type = "button";
      closeButton.setAttribute("aria-label", "Close attachment");
      closeButton.textContent = "×";

      const image = document.createElement("img");
      activeImage = image;
      image.src = source;
      image.alt = trigger.dataset.imageAlt ?? "Announcement attachment";
      image.addEventListener("load", fitImageToViewer, { once: true });

      viewer.append(closeButton, image);
      viewer.addEventListener("keydown", (keyEvent) => {
        if (keyEvent.key === "Escape") {
          keyEvent.preventDefault();
          keyEvent.stopPropagation();
          closeViewer();
        } else if (keyEvent.key === "Tab") {
          keyEvent.preventDefault();
          keyEvent.stopPropagation();
          closeButton.focus();
        }
      });
      const popupOverlay = trigger.closest(
        "[data-registrar-announcement-gate-overlay]",
      );
      (popupOverlay ?? document.body).append(viewer);
      if (image.complete && image.naturalWidth > 0) fitImageToViewer();
      closeButton.focus();
      return;
    }

    if (viewer && (event.target === viewer || event.target.closest(
      ".announcement-attachment-viewer-close",
    ))) {
      closeViewer();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && viewer) {
      event.preventDefault();
      closeViewer();
    }
  });

  window.addEventListener("resize", fitImageToViewer);
});
