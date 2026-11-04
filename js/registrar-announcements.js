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
    if (enabled && audienceAll) {
      audienceAll.checked = true;
      updateAudience();
    }
  };
  homepageNotice?.addEventListener("change", updateHomepageCategory);
  updateHomepageCategory();
});
