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

    if (
      viewer &&
      (event.target === viewer ||
        event.target.closest(".announcement-attachment-viewer-close"))
    ) {
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

  let lessonViewer = null;
  let lessonTrigger = null;
  let lessonImage = null;
  let lessonImageFitSize = null;
  let lessonZoom = 1;

  const applyLessonZoom = () => {
    if (!lessonImage || !lessonImageFitSize) return;
    lessonImage.style.width = `${Math.round(lessonImageFitSize.width * lessonZoom)}px`;
    lessonImage.style.height = `${Math.round(lessonImageFitSize.height * lessonZoom)}px`;
    const zoomLabel = lessonViewer?.querySelector("[data-lesson-zoom-label]");
    if (zoomLabel) zoomLabel.textContent = `${Math.round(lessonZoom * 100)}%`;
  };

  const fitLessonImage = () => {
    if (!lessonImage?.naturalWidth || !lessonImage.naturalHeight) return;
    lessonImageFitSize = {
      width: lessonImage.naturalWidth,
      height: lessonImage.naturalHeight,
    };
    applyLessonZoom();
  };

  const closeLessonViewer = () => {
    if (!lessonViewer) return;
    lessonViewer.remove();
    lessonViewer = null;
    lessonImage = null;
    lessonImageFitSize = null;
    lessonZoom = 1;
    document.body.style.overflow = previousOverflow;
    lessonTrigger?.focus();
    lessonTrigger = null;
  };

  const openLessonViewer = (trigger) => {
    if (viewer) closeViewer();
    if (lessonViewer) closeLessonViewer();

    const previewSource = trigger.dataset.previewSrc;
    const downloadSource = trigger.dataset.downloadSrc;
    const fileName = trigger.dataset.fileName || "Lesson attachment";
    const fileMime = trigger.dataset.fileMime || "";
    if (!previewSource || !downloadSource) return;

    lessonTrigger = trigger;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    lessonViewer = document.createElement("div");
    lessonViewer.className =
      "announcement-attachment-viewer lesson-attachment-viewer";
    lessonViewer.setAttribute("role", "dialog");
    lessonViewer.setAttribute("aria-modal", "true");
    lessonViewer.setAttribute("aria-label", `Lesson attachment: ${fileName}`);

    const toolbar = document.createElement("div");
    toolbar.className = "lesson-attachment-viewer-toolbar";

    const zoomControls = document.createElement("div");
    zoomControls.className = "lesson-attachment-viewer-zoom-controls";
    const zoomOut = document.createElement("button");
    zoomOut.className = "lesson-attachment-viewer-control";
    zoomOut.type = "button";
    zoomOut.setAttribute("aria-label", "Zoom out");
    zoomOut.textContent = "−";
    const zoomLabel = document.createElement("span");
    zoomLabel.dataset.lessonZoomLabel = "";
    zoomLabel.textContent = "100%";
    const zoomIn = document.createElement("button");
    zoomIn.className = "lesson-attachment-viewer-control";
    zoomIn.type = "button";
    zoomIn.setAttribute("aria-label", "Zoom in");
    zoomIn.textContent = "+";
    const zoomReset = document.createElement("button");
    zoomReset.className =
      "lesson-attachment-viewer-control lesson-attachment-viewer-reset";
    zoomReset.type = "button";
    zoomReset.setAttribute("aria-label", "Reset zoom");
    zoomReset.textContent = "Reset";
    zoomControls.append(zoomOut, zoomLabel, zoomIn, zoomReset);

    const actions = document.createElement("div");
    actions.className = "lesson-attachment-viewer-actions";
    if (fileMime !== "application/pdf") {
      const download = document.createElement("a");
      download.className = "lesson-attachment-viewer-download";
      download.href = downloadSource;
      download.download = fileName;
      download.setAttribute("aria-label", "Download attachment");
      download.title = "Download attachment";
      const downloadIcon = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "svg",
      );
      downloadIcon.setAttribute("viewBox", "0 0 24 24");
      downloadIcon.setAttribute("aria-hidden", "true");
      downloadIcon.innerHTML = '<path d="M12 3v12m0 0 5-5m-5 5-5-5M4 20h16" />';
      download.append(downloadIcon);
      actions.append(download);
    }

    const closeButton = document.createElement("button");
    closeButton.className = "announcement-attachment-viewer-close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Exit attachment viewer");
    closeButton.textContent = "×";
    actions.append(closeButton);
    toolbar.append(zoomControls, actions);

    const media = document.createElement("div");
    media.className = "lesson-attachment-viewer-media";
    if (fileMime.startsWith("image/")) {
      lessonImage = document.createElement("img");
      lessonImage.className = "lesson-attachment-viewer-image";
      lessonImage.src = previewSource;
      lessonImage.alt = fileName;
      lessonImage.addEventListener("load", fitLessonImage, { once: true });
      media.append(lessonImage);
      zoomControls.hidden = false;
      zoomOut.addEventListener("click", () => {
        lessonZoom = Math.max(0.25, lessonZoom - 0.25);
        applyLessonZoom();
      });
      zoomIn.addEventListener("click", () => {
        lessonZoom = Math.min(4, lessonZoom + 0.25);
        applyLessonZoom();
      });
      zoomReset.addEventListener("click", () => {
        lessonZoom = 1;
        fitLessonImage();
      });
      media.addEventListener(
        "wheel",
        (wheelEvent) => {
          if (!wheelEvent.ctrlKey) return;
          wheelEvent.preventDefault();
          lessonZoom = Math.max(
            0.25,
            Math.min(4, lessonZoom + (wheelEvent.deltaY < 0 ? 0.1 : -0.1)),
          );
          applyLessonZoom();
        },
        { passive: false },
      );
    } else if (fileMime === "application/pdf") {
      const pdf = document.createElement("iframe");
      pdf.className = "lesson-attachment-viewer-pdf";
      pdf.src = previewSource;
      pdf.title = fileName;
      media.append(pdf);
      zoomControls.hidden = true;
    } else {
      const fallback = document.createElement("p");
      fallback.className = "lesson-attachment-viewer-fallback";
      fallback.textContent =
        "Preview is available for images and PDF files. Download this attachment to open it.";
      media.append(fallback);
      zoomControls.hidden = true;
    }

    lessonViewer.append(toolbar, media);
    lessonViewer.addEventListener("keydown", (keyEvent) => {
      if (keyEvent.key === "Escape") {
        keyEvent.preventDefault();
        closeLessonViewer();
      } else if (keyEvent.key === "Tab") {
        const focusable = Array.from(
          lessonViewer.querySelectorAll("button:not([disabled]), a[href]"),
        );
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (keyEvent.shiftKey && document.activeElement === first) {
          keyEvent.preventDefault();
          last.focus();
        } else if (!keyEvent.shiftKey && document.activeElement === last) {
          keyEvent.preventDefault();
          first.focus();
        }
      }
    });
    document.body.append(lessonViewer);
    if (lessonImage?.complete && lessonImage.naturalWidth > 0) fitLessonImage();
    closeButton.focus();
  };

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;
    const trigger = event.target.closest("[data-lesson-attachment-open]");
    if (trigger) {
      openLessonViewer(trigger);
      return;
    }
    if (
      lessonViewer &&
      (event.target === lessonViewer ||
        event.target.closest(".announcement-attachment-viewer-close"))
    ) {
      closeLessonViewer();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && lessonViewer) {
      event.preventDefault();
      closeLessonViewer();
    }
  });
  window.addEventListener("resize", fitLessonImage);
});
