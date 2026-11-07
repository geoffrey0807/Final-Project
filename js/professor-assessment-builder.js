document.addEventListener("DOMContentLoaded", () => {
  const reopenDialog = document.querySelector("#assessmentReopenConfirm");
  let pendingReopenPanel = null;

  document.addEventListener("click", (event) => {
    const trigger =
      event.target instanceof Element
        ? event.target.closest("[data-assessment-reopen-trigger]")
        : null;
    if (!trigger || !reopenDialog) return;
    pendingReopenPanel = trigger.nextElementSibling;
    reopenDialog.showModal();
  });

  reopenDialog?.addEventListener("close", () => {
    if (reopenDialog.returnValue === "yes" && pendingReopenPanel) {
      pendingReopenPanel.hidden = false;
      pendingReopenPanel.querySelector("select")?.focus();
    }
    pendingReopenPanel = null;
  });

  const gradeReleaseDialog = document.querySelector(
    "#courseGradeReleaseConfirm",
  );
  const gradeReleasePrompt = gradeReleaseDialog?.querySelector(
    "#courseGradeReleaseConfirmTitle",
  );
  let pendingGradeForm = null;

  document
    .querySelectorAll("[data-grade-confirm-form]")
    .forEach((gradeForm) => {
      gradeForm.addEventListener("submit", (event) => {
        if (gradeForm.dataset.confirmed === "true") {
          delete gradeForm.dataset.confirmed;
          return;
        }
        if (!gradeReleaseDialog || !gradeReleasePrompt) return;

        event.preventDefault();
        pendingGradeForm = gradeForm;
        gradeReleasePrompt.textContent = gradeForm.dataset.gradeConfirmMessage;
        gradeReleaseDialog.showModal();
      });
    });

  gradeReleaseDialog?.addEventListener("close", () => {
    const confirmedForm = pendingGradeForm;
    pendingGradeForm = null;
    if (gradeReleaseDialog.returnValue === "yes" && confirmedForm) {
      confirmedForm.dataset.confirmed = "true";
      confirmedForm.requestSubmit();
    }
  });

  const form = document.querySelector("#courseAssessmentForm");
  const questionList = form?.querySelector("[data-assessment-question-list]");
  const questionTemplate = document.querySelector(
    "#courseAssessmentQuestionTemplate",
  );
  const addQuestionButton = form?.querySelector(
    "[data-assessment-add-question]",
  );
  const questionFormat = form?.querySelector('[name="question_format"]');
  const questionFormatField = form?.querySelector(
    "[data-question-format-field]",
  );
  if (
    !form ||
    !questionList ||
    !questionTemplate ||
    !addQuestionButton ||
    !questionFormat
  )
    return;

  let nextQuestionIndex = 0;

  const setFieldGroupEnabled = (group, enabled) => {
    if (!group) return;
    group.hidden = !enabled;
    group.querySelectorAll("input, select, textarea").forEach((input) => {
      if (!input.hasAttribute("data-required")) {
        input.dataset.required = String(input.required);
      }
      input.disabled = !enabled;
      input.required = enabled && input.dataset.required === "true";
    });
  };

  const updateQuestion = (question) => {
    const format = questionFormat.value;
    const typeField = question.querySelector("[data-question-type-field]");
    const typeSelect = question.querySelector("[data-question-type]");
    const selectedType =
      format === "Multiple Choice"
        ? "multiple_choice"
        : format === "True or False"
          ? "true_false"
          : typeSelect.value;

    if (format !== "Mixed") {
      typeSelect.value = selectedType;
    }
    typeField.hidden = format !== "Mixed";
    typeSelect.disabled = format !== "Mixed";

    setFieldGroupEnabled(
      question.querySelector("[data-multiple-choice-fields]"),
      selectedType === "multiple_choice",
    );
    setFieldGroupEnabled(
      question.querySelector("[data-true-false-fields]"),
      selectedType === "true_false",
    );
    question
      .querySelectorAll("[data-true-false-fields] select")
      .forEach((input) => {
        input.required = selectedType === "true_false";
      });
  };

  const syncBuilder = () => {
    questionFormatField.hidden = false;
    questionFormat.disabled = false;
    questionList
      .querySelectorAll("[data-assessment-question]")
      .forEach(updateQuestion);
  };

  const addQuestion = () => {
    const question = questionTemplate.content.cloneNode(true);
    const questionElement = question.querySelector(
      "[data-assessment-question]",
    );
    const index = String(nextQuestionIndex++);
    questionElement.querySelectorAll("[name]").forEach((input) => {
      input.name = input.name.replaceAll("__INDEX__", index);
    });
    questionElement.querySelector(
      "[data-assessment-question-title]",
    ).textContent = `Question ${questionList.children.length + 1}`;
    questionList.append(question);
    updateQuestion(questionList.lastElementChild);
  };

  addQuestionButton.addEventListener("click", addQuestion);
  questionFormat.addEventListener("change", syncBuilder);
  questionList.addEventListener("change", (event) => {
    if (event.target.matches("[data-question-type]"))
      updateQuestion(event.target.closest("[data-assessment-question]"));
  });
  questionList.addEventListener("click", (event) => {
    const removeButton = event.target.closest(
      "[data-assessment-remove-question]",
    );
    if (!removeButton || questionList.children.length <= 1) return;
    removeButton.closest("[data-assessment-question]").remove();
    questionList
      .querySelectorAll("[data-assessment-question-title]")
      .forEach((title, index) => {
        title.textContent = `Question ${index + 1}`;
      });
  });

  addQuestion();
  syncBuilder();
});
