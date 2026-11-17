document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("studentFiscalYearForm");
  const confirmationDialog = document.getElementById(
    "studentFiscalYearConfirm",
  );
  const confirmSaveButton = confirmationDialog?.querySelector(
    "[data-fiscal-year-save-confirm]",
  );
  const confirmationTitle = document.getElementById(
    "studentFiscalYearConfirmTitle",
  );
  const confirmationDescription = document.getElementById(
    "studentFiscalYearConfirmDescription",
  );
  let confirmationAction = null;
  const fiscalYearDialog = document.getElementById("studentFiscalYearDialog");
  if (
    confirmationDialog &&
    confirmationDialog.parentElement !== document.body
  ) {
    document.body.append(confirmationDialog);
  }
  const yearSelect = document.getElementById("studentAcademicYear");
  const semesterSelect = document.getElementById("studentSemester");
  document.querySelectorAll("[data-fiscal-year-open]").forEach((button) => {
    button.addEventListener("click", () => fiscalYearDialog?.showModal());
  });
  document.querySelectorAll("[data-fiscal-year-close]").forEach((button) => {
    button.addEventListener("click", () => {
      document.getElementById("studentFiscalYearDialog")?.close();
    });
  });
  fiscalYearDialog?.addEventListener("click", (event) => {
    if (event.target === fiscalYearDialog) fiscalYearDialog.close();
  });
  const storageKey = "apexFiscalYearSelection";
  const endpoint = document.body.dataset.academicPeriodEndpoint;
  const periodSummaries = document.querySelectorAll(
    "[data-fiscal-year-summary]",
  );
  const activePeriodMatch = document.body.dataset.academicPeriod?.match(
    /^(\d{4})-(\d{4}):(first|second)$/,
  );

  periodSummaries.forEach((periodSummary) => {
    periodSummary.textContent = activePeriodMatch
      ? `AY ${activePeriodMatch[1]}–${activePeriodMatch[2]} · ${activePeriodMatch[3] === "first" ? "First Semester" : "Second Semester"}`
      : "Not set";
  });

  if (activePeriodMatch && yearSelect && semesterSelect) {
    yearSelect.value = `${activePeriodMatch[1]}-${activePeriodMatch[2]}`;
    semesterSelect.value = activePeriodMatch[3];
  }

  const readSelection = () => {
    try {
      const value = localStorage.getItem(storageKey);
      if (!value) return null;
      const selection = JSON.parse(value);
      if (
        typeof selection.academicYear !== "string" ||
        !/^\d{4}-\d{4}$/.test(selection.academicYear) ||
        !["first", "second"].includes(selection.semester)
      ) {
        return null;
      }
      return selection;
    } catch (error) {
      console.error("Unable to read the saved academic-year selection.", error);
      return null;
    }
  };

  const savePeriod = async (selection, notify = false) => {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        Accept: "application/json",
      },
      body: new URLSearchParams({
        academic_year: selection.academicYear,
        semester: selection.semester,
        ...(notify ? { notify: "1" } : {}),
      }),
    });
    const result = await response.json();
    if (!response.ok || result.success !== true) {
      throw new Error(result.message || "Unable to save the academic period.");
    }
  };

  const periodMode = document.body.dataset.academicPeriodMode || "global";
  if (periodMode !== "historical") {
    localStorage.removeItem(storageKey);
  }
  const savedSelection = periodMode === "historical" ? readSelection() : null;
  if (form && yearSelect && semesterSelect && endpoint && savedSelection) {
    if (
      Array.from(yearSelect.options).some(
        (option) => option.value === savedSelection.academicYear,
      )
    ) {
      yearSelect.value = savedSelection.academicYear;
      semesterSelect.value = savedSelection.semester;
      const periodValue = `${savedSelection.academicYear}:${savedSelection.semester}`;
      if (document.body.dataset.academicPeriod !== periodValue) {
        savePeriod(savedSelection)
          .then(() => window.location.reload())
          .catch((error) => {
            console.error(
              "Unable to restore the saved academic-year selection.",
              error,
            );
            const status = document.getElementById("studentFiscalYearStatus");
            if (status)
              status.textContent =
                "Unable to restore the saved academic-year selection.";
          });
        return;
      }
    }
  }

  const saveSelection = async () => {
    if (!endpoint || !yearSelect || !semesterSelect) return;
    const selection = {
      academicYear: yearSelect.value,
      semester: semesterSelect.value,
    };
    if (confirmSaveButton instanceof HTMLButtonElement)
      confirmSaveButton.disabled = true;
    try {
      localStorage.setItem(storageKey, JSON.stringify(selection));
      await savePeriod(selection, true);
      window.location.reload();
    } catch (error) {
      console.error("Unable to save the academic-year selection.", error);
      const status = document.getElementById("studentFiscalYearStatus");
      const message =
        error instanceof Error
          ? error.message
          : "Unable to save your academic period.";
      if (status) status.textContent = message;
      window.dispatchEvent(
        new CustomEvent("apex:toast", {
          detail: { message, type: "error" },
        }),
      );
    } finally {
      if (confirmSaveButton instanceof HTMLButtonElement)
        confirmSaveButton.disabled = false;
    }
  };

  form?.addEventListener(
    "submit",
    (event) => {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (!endpoint || !form.reportValidity() || !yearSelect || !semesterSelect)
        return;
      if (confirmationDialog && confirmSaveButton) {
        if (confirmationTitle)
          confirmationTitle.textContent = "Save this period selection?";
        if (confirmationDescription)
          confirmationDescription.textContent =
            "The selected academic year and semester will be applied.";
        confirmSaveButton.textContent = "Yes, save";
        confirmationAction = saveSelection;
        confirmationDialog.showModal();
      } else {
        saveSelection();
      }
    },
    true,
  );

  confirmationDialog
    ?.querySelector("[data-fiscal-year-save-cancel]")
    ?.addEventListener("click", () => {
      confirmationAction = null;
      confirmationDialog.close();
    });
  confirmSaveButton?.addEventListener("click", () => {
    confirmationDialog?.close();
    const action = confirmationAction;
    confirmationAction = null;
    action?.();
  });

  const clearPeriod = async () => {
    try {
      localStorage.removeItem(storageKey);
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          Accept: "application/json",
        },
        body: new URLSearchParams({ clear: "1", notify: "1" }),
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) {
        throw new Error(
          result.message || "Unable to clear the academic period.",
        );
      }
      window.location.reload();
    } catch (error) {
      console.error("Unable to clear the academic-year selection.", error);
      const status = document.getElementById("studentFiscalYearStatus");
      const message =
        error instanceof Error
          ? error.message
          : "Unable to clear your academic period.";
      if (status) status.textContent = message;
      window.dispatchEvent(
        new CustomEvent("apex:toast", {
          detail: { message, type: "error" },
        }),
      );
    }
  };

  document.querySelectorAll("[data-academic-clear]").forEach((button) => {
    button.addEventListener("click", () => {
      if (!confirmationDialog || !confirmSaveButton) {
        clearPeriod();
        return;
      }
      if (confirmationTitle)
        confirmationTitle.textContent = "Return to the current period?";
      if (confirmationDescription)
        confirmationDescription.textContent =
          "Your selected academic period will be cleared and the current period restored.";
      confirmSaveButton.textContent = "Yes, continue";
      confirmationAction = clearPeriod;
      confirmationDialog.showModal();
    });
  });

  if (document.body.dataset.academicReadonly !== "true") return;

  document
    .querySelectorAll(
      'form[action*="/api/student_process.php"], form[action*="/api/professor_process.php"], form[action*="/api/admin_process.php"]',
    )
    .forEach((activityForm) => {
      if (activityForm.querySelector('input[name="action"][value="add_user"]'))
        return;
      activityForm
        .querySelectorAll("button, input, select, textarea")
        .forEach((control) => {
          control.disabled = true;
        });
    });
  document
    .querySelectorAll(
      '[data-open-modal]:not([data-open-modal="addUserModal"]):not([data-open-modal="manageProgramsModal"]), .admin-account-actions',
    )
    .forEach((control) => {
      control.hidden = true;
    });
});
