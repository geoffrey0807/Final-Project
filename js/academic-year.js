document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("studentFiscalYearForm");
  const confirmationDialog = document.getElementById(
    "studentFiscalYearConfirm",
  );
  const confirmSaveButton = confirmationDialog?.querySelector(
    "[data-fiscal-year-save-confirm]",
  );
  if (
    confirmationDialog &&
    confirmationDialog.parentElement !== document.body
  ) {
    document.body.append(confirmationDialog);
  }
  const yearSelect = document.getElementById("studentAcademicYear");
  const semesterSelect = document.getElementById("studentSemester");
  const storageKey = "apexFiscalYearSelection";
  const endpoint = document.body.dataset.academicPeriodEndpoint;
  const periodSummary = document.querySelector("[data-fiscal-year-summary]");
  const activePeriodMatch = document.body.dataset.academicPeriod?.match(
    /^(\d{4})-(\d{4}):(first|second)$/,
  );

  if (periodSummary) {
    periodSummary.textContent = activePeriodMatch
      ? `AY ${activePeriodMatch[1]}–${activePeriodMatch[2]} · ${activePeriodMatch[3] === "first" ? "First Semester" : "Second Semester"}`
      : "Not set";
  }

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
      confirmationDialog?.showModal();
    },
    true,
  );

  confirmationDialog
    ?.querySelector("[data-fiscal-year-save-cancel]")
    ?.addEventListener("click", () => confirmationDialog.close());
  confirmSaveButton?.addEventListener("click", () => {
    confirmationDialog?.close();
    saveSelection();
  });

  document.querySelectorAll("[data-academic-clear]").forEach((button) => {
    button.addEventListener("click", async () => {
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
