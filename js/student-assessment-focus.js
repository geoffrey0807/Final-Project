document.addEventListener("DOMContentLoaded", () => {
  const assessmentTimer = document.querySelector("[data-assessment-timer]");
  const assessmentTimeDisplay = assessmentTimer?.querySelector(
    "[data-assessment-time-display]",
  );
  const forfeitForm = document.querySelector("[data-assessment-forfeit-form]");

  if (assessmentTimer && assessmentTimeDisplay && forfeitForm) {
    let remainingSeconds = Number.parseInt(
      assessmentTimer.dataset.remainingSeconds ?? "0",
      10,
    );
    const timerDeadline = Date.now() + remainingSeconds * 1000;
    let isExpiring = false;

    const updateAssessmentTimer = () => {
      remainingSeconds = Math.max(
        0,
        Math.ceil((timerDeadline - Date.now()) / 1000),
      );
      assessmentTimeDisplay.textContent = `${String(Math.floor(remainingSeconds / 60)).padStart(2, "0")}:${String(remainingSeconds % 60).padStart(2, "0")}`;

      if (remainingSeconds === 0 && !isExpiring) {
        isExpiring = true;
        forfeitForm.requestSubmit();
      }
    };

    assessmentTimeDisplay.textContent = `${String(Math.floor(remainingSeconds / 60)).padStart(2, "0")}:${String(remainingSeconds % 60).padStart(2, "0")}`;
    if (remainingSeconds === 0) {
      updateAssessmentTimer();
    } else {
      window.setInterval(updateAssessmentTimer, 1000);
    }
  }

  const assessmentForm = document.querySelector(
    "[data-assessment-submit-form]",
  );
  const questionSteps = Array.from(
    assessmentForm?.querySelectorAll("[data-assessment-question-step]") ?? [],
  );
  const questionCount = assessmentForm?.querySelector(
    "[data-assessment-question-count]",
  );
  const nextQuestionButton = assessmentForm?.querySelector(
    "[data-assessment-next]",
  );

  if (
    assessmentForm &&
    questionSteps.length > 0 &&
    questionCount &&
    nextQuestionButton
  ) {
    let activeQuestionIndex = 0;

    const showActiveQuestion = () => {
      questionSteps.forEach((question, index) => {
        question.hidden = index !== activeQuestionIndex;
      });
      questionCount.textContent = `Question ${activeQuestionIndex + 1} of ${questionSteps.length}`;
      nextQuestionButton.textContent =
        activeQuestionIndex === questionSteps.length - 1 ? "Finish" : "Next";
    };

    nextQuestionButton.addEventListener("click", () => {
      const activeQuestion = questionSteps[activeQuestionIndex];
      const invalidControl = activeQuestion.querySelector(":invalid");
      if (invalidControl instanceof HTMLElement) {
        invalidControl.reportValidity();
        return;
      }

      if (activeQuestionIndex === questionSteps.length - 1) {
        assessmentForm.requestSubmit();
        return;
      }

      activeQuestionIndex += 1;
      showActiveQuestion();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    showActiveQuestion();
  }

  const takeDialog = document.querySelector("#assessmentTakeConfirm");
  const takeCountdown = takeDialog?.querySelector(
    "[data-assessment-take-countdown]",
  );
  let pendingTakeHref = null;
  let takeCountdownTimer = null;

  const clearTakeCountdown = () => {
    if (takeCountdownTimer !== null) {
      window.clearInterval(takeCountdownTimer);
      takeCountdownTimer = null;
    }
  };

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;
    const takeTrigger = event.target.closest("[data-assessment-take-trigger]");
    if (!takeTrigger || !takeDialog || takeDialog.open) return;

    event.preventDefault();
    pendingTakeHref = takeTrigger.href;
    let secondsRemaining = 5;
    if (takeCountdown) takeCountdown.textContent = String(secondsRemaining);
    takeDialog.showModal();
    takeCountdownTimer = window.setInterval(() => {
      secondsRemaining -= 1;
      if (takeCountdown) takeCountdown.textContent = String(secondsRemaining);
      if (secondsRemaining <= 0) takeDialog.close("timeout");
    }, 1000);
  });

  takeDialog?.addEventListener("close", () => {
    clearTakeCountdown();
    if (takeDialog.returnValue === "yes" && pendingTakeHref) {
      window.location.assign(pendingTakeHref);
    }
    pendingTakeHref = null;
  });

  const exitDialog = document.querySelector("#assessmentExitConfirm");
  const exitTrigger = document.querySelector("[data-assessment-exit-trigger]");
  const exitCancel = exitDialog?.querySelector("[data-assessment-exit-cancel]");
  if (exitDialog && exitTrigger) {
    const showExitConfirmation = () => {
      if (!exitDialog.open) exitDialog.showModal();
    };

    exitTrigger.addEventListener("click", showExitConfirmation);
    exitCancel?.addEventListener("click", () => exitDialog.close("cancel"));

    window.history.pushState(
      { assessmentTakeActive: true },
      "",
      window.location.href,
    );
    window.addEventListener("popstate", () => {
      window.history.pushState(
        { assessmentTakeActive: true },
        "",
        window.location.href,
      );
      showExitConfirmation();
    });
  }
});
