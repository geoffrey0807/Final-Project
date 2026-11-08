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
  const homepageCategoryFields = document.querySelector(
    "[data-homepage-category-fields]",
  );
  const homepageCategories = Array.from(
    homepageCategoryFields?.querySelectorAll('input[name="homepage_category"]') ?? [],
  );
  const updateHomepageCategory = () => {
    const enabled = Boolean(homepageNotice?.checked);
    if (homepageCategoryFields) homepageCategoryFields.hidden = !enabled;
    homepageCategories.forEach((category) => {
      category.disabled = !enabled;
      category.required = enabled;
    });
    if (audienceSelector) audienceSelector.hidden = enabled;
    const switchLabel = document.querySelector("[data-homepage-notice-label]");

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
    const resetAnnouncementForm = () => {
      if (!announcementForm) return;
      announcementForm.reset();
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
        homepageCategories.forEach((category) => {
          category.checked = category.value === button.dataset.homepageCategory;
        });
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
    if (switchLabel) switchLabel.textContent = enabled ? "On" : "Off";
  };
  homepageNotice?.addEventListener("change", updateHomepageCategory);
  updateHomepageCategory();

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
  const resetAnnouncementForm = () => {
    if (!announcementForm) return;
    announcementForm.reset();
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
      homepageCategories.forEach((category) => {
        category.checked = category.value === button.dataset.homepageCategory;
      });
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
});
