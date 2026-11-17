document.addEventListener("DOMContentLoaded", () => {
  const audienceSelector = document.querySelector("[data-announcement-audience]");
  const audienceAll = audienceSelector?.querySelector("[data-audience-all]");
  const audienceRoles = Array.from(
    audienceSelector?.querySelectorAll("[data-audience-role]") ?? [],
  );
  const audienceSummary = audienceSelector?.querySelector(
    "[data-audience-summary]",
  );

  const updateAudience = () => {
    if (!audienceAll || !audienceSummary) return;
    if (audienceAll.checked) {
      audienceRoles.forEach((role) => {
        role.checked = false;
        role.disabled = true;
      });
      audienceSummary.textContent = "All users";
      return;
    }

    audienceRoles.forEach((role) => {
      role.disabled = false;
    });
    const selectedRoles = audienceRoles
      .filter((role) => role.checked)
      .map((role) => role.closest("label")?.textContent.trim())
      .filter(Boolean);
    audienceSummary.textContent = selectedRoles.length
      ? selectedRoles.join(", ")
      : "Choose one or more roles";
  };

  audienceAll?.addEventListener("change", updateAudience);
  audienceRoles.forEach((role) => {
    role.addEventListener("change", () => {
      if (role.checked && audienceAll) audienceAll.checked = false;
      updateAudience();
    });
  });
  updateAudience();

  const homepageNotice = document.querySelector("[data-homepage-notice]");
  const homepageCategory = document.querySelector("[data-homepage-category]");
  const announcementForm = document.querySelector(
    "[data-registrar-announcement-form]",
  );
  const announcementAction = announcementForm?.querySelector('[name="action"]');
  const announcementId = announcementForm?.querySelector('[name="announcement_id"]');
  const announcementTitle = announcementForm?.querySelector(
    "[data-announcement-form-title]",
  );
  const announcementSubmit = announcementForm?.querySelector(
    "[data-announcement-submit]",
  );
  const announcementImageNote = announcementForm?.querySelector(
    "[data-announcement-image-note]",
  );
  const announcementImageInput = announcementForm?.querySelector(
    "[data-announcement-image-input]",
  );
  const announcementUploadTitle = announcementForm?.querySelector(
    "[data-announcement-upload-title]",
  );

  const updateHomepageCategory = () => {
    const enabled = Boolean(homepageNotice?.checked);
    if (homepageCategory) {
      homepageCategory.disabled = !enabled;
      homepageCategory.required = enabled;
    }
    if (audienceSelector) {
      audienceSelector.hidden = enabled;
      if (enabled) {
        if (audienceAll) audienceAll.checked = false;
        audienceRoles.forEach((role) => {
          role.checked = false;
        });
      } else if (
        audienceAll &&
        !audienceRoles.some((role) => role.checked)
      ) {
        audienceAll.checked = true;
      }
      updateAudience();
    }
  };

  const resetAnnouncementForm = () => {
    if (!announcementForm) return;
    announcementForm.reset();
    if (announcementUploadTitle) announcementUploadTitle.textContent = "Choose an image";
    if (announcementAction) announcementAction.value = "publish";
    if (announcementId) announcementId.value = "";
    if (announcementTitle) announcementTitle.textContent = "Publish Announcement";
    if (announcementSubmit) announcementSubmit.textContent = "Publish Announcement";
    if (announcementImageNote) {
      announcementImageNote.textContent =
        "JPG, PNG, or WebP up to 5 MB. Without an image, the notice is text only.";
    }
    updateAudience();
    updateHomepageCategory();
  };

  announcementImageInput?.addEventListener("change", () => {
    const file = announcementImageInput.files?.[0];
    if (!file) return;
    if (announcementUploadTitle) announcementUploadTitle.textContent = file.name;
    if (announcementImageNote) {
      announcementImageNote.textContent =
        `${(file.size / 1024 / 1024).toFixed(2)} MB · JPG, PNG, or WebP`;
    }
  });
  document
    .querySelector('[data-open-modal="addAnnouncementModal"]:not([data-edit-announcement])')
    ?.addEventListener("click", resetAnnouncementForm);

  document.querySelectorAll("[data-edit-announcement]").forEach((button) => {
    button.addEventListener("click", () => {
      if (!announcementForm) return;
      resetAnnouncementForm();
      if (announcementAction) announcementAction.value = "update";
      if (announcementId) announcementId.value = button.dataset.announcementId || "";
      announcementForm.querySelector('[name="headline"]').value =
        button.dataset.announcementHeadline || "";
      announcementForm.querySelector('[name="message"]').value =
        button.dataset.announcementMessage || "";
      if (announcementTitle) announcementTitle.textContent = "Edit Announcement";
      if (announcementSubmit) announcementSubmit.textContent = "Save Changes";
      if (announcementImageNote && button.dataset.hasImage === "1") {
        announcementImageNote.textContent =
          "The current attachment will be kept unless you upload a replacement.";
      }

      if (homepageNotice) {
        homepageNotice.checked = button.dataset.homepageNotice === "1";
      }
      if (homepageCategory)
        homepageCategory.value = button.dataset.homepageCategory || "";
      if (audienceAll && audienceRoles.length) {
        let selectedRoles = [];
        try {
          selectedRoles = JSON.parse(button.dataset.audienceRoles || "[]");
        } catch {
          selectedRoles = [];
        }
        const allRoles = ["admin", "registrar", "dean", "professor", "student"];
        audienceAll.checked = allRoles.every((role) => selectedRoles.includes(role));
        audienceRoles.forEach((role) => {
          role.checked = selectedRoles.includes(role.value);
        });
      }
      updateAudience();
      updateHomepageCategory();
    });
  });

  homepageNotice?.addEventListener("change", updateHomepageCategory);
  updateHomepageCategory();
});
