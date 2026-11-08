document.addEventListener("DOMContentLoaded", () => {
  const overlay = document.querySelector(
    "[data-registrar-announcement-gate-overlay]",
  );
  const form = overlay?.querySelector(
    "[data-registrar-announcement-acknowledgement]",
  );
  const button = form?.querySelector("[data-announcement-ack-button]");
  const errorMessage = form?.querySelector("[data-announcement-ack-error]");
  const queueStatus = overlay?.querySelector("[data-announcement-queue-status]");
  const title = overlay?.querySelector("#registrarAnnouncementTitle");
  const message = overlay?.querySelector("#registrarAnnouncementMessage");
  const attachment = overlay?.querySelector("[data-announcement-attachment-open]");
  const main = document.querySelector("main.app-main");
  if (
    !overlay ||
    !form ||
    !button ||
    !errorMessage ||
    !queueStatus ||
    !title ||
    !message ||
    !attachment ||
    !main
  ) return;

  const focusableSelector =
    'button:not([disabled]):not([hidden]), a[href]:not([hidden]), input:not([disabled]):not([hidden]), [tabindex]:not([tabindex="-1"]):not([hidden])';
  let reviewedCount = 0;
  const attachmentBase = overlay.dataset.siteRoot ?? "../";
  const restoreMain = () => {
    main.removeAttribute("inert");
    document.body.removeAttribute("data-registrar-announcement-gate");
  };
  button.focus();

  document.addEventListener("keydown", (event) => {
    if (!overlay.isConnected) return;
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    if (event.key !== "Tab") return;

    const focusable = Array.from(overlay.querySelectorAll(focusableSelector));
    if (!focusable.length) {
      event.preventDefault();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    errorMessage.hidden = true;
    button.disabled = true;
    button.classList.add("is-saving");

    try {
      const response = await fetch(form.dataset.endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "X-CSRF-Token": form.dataset.csrfToken ?? "",
          "X-Announcement-Id": form.dataset.announcementId ?? "",
        },
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) {
        if (result.reload) {
          window.location.reload();
          return;
        }
        throw new Error(result.message ?? "The notice could not be acknowledged.");
      }

      reviewedCount += 1;
      const remainingCount = Number(result.remainingCount);
      const nextAnnouncement = result.nextAnnouncement;
      if (nextAnnouncement) {
        if (!Number.isInteger(remainingCount) || remainingCount < 1) {
          window.location.reload();
          return;
        }

        title.textContent = nextAnnouncement.headline;
        message.textContent = nextAnnouncement.message;
        form.dataset.announcementId = String(nextAnnouncement.id);
        attachment.hidden = !nextAnnouncement.image_path;
        attachment.dataset.imageSrc = nextAnnouncement.image_path
          ? `${attachmentBase}${nextAnnouncement.image_path}`
          : "";
        attachment.dataset.imageAlt = nextAnnouncement.headline;
        queueStatus.textContent =
          `${reviewedCount + 1}/${reviewedCount + remainingCount}`;
        button.textContent = remainingCount > 1 ? "Next" : "Okay";
        button.disabled = false;
        return;
      }

      if (remainingCount !== 0) {
        window.location.reload();
        return;
      }

      overlay.classList.add("is-closing");
      window.setTimeout(() => {
        overlay.remove();
        restoreMain();
      }, 240);
    } catch (error) {
      errorMessage.textContent =
        error instanceof Error
          ? error.message
          : "The notice could not be acknowledged. Please try again.";
      errorMessage.hidden = false;
      button.disabled = false;
    } finally {
      button.classList.remove("is-saving");
    }
  });
});
