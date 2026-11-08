document.addEventListener("DOMContentLoaded", () => {
  const profileMenu = document.querySelector("[data-profile-menu]");
  const profileMenuTrigger = document.querySelector(
    "[data-profile-menu-trigger]",
  );
  const profileMenuContent = document.querySelector(
    "[data-profile-menu-content]",
  );
  if (document.documentElement.dataset.portalProfileBound) {
    // The shared student script already owns profile interactions.
  } else {
    const setProfileMenuOpen = (open) => {
      profileMenuTrigger?.setAttribute("aria-expanded", String(open));
      if (profileMenuContent) profileMenuContent.hidden = !open;
    };

    profileMenuTrigger?.addEventListener("click", () => {
      setProfileMenuOpen(
        profileMenuTrigger.getAttribute("aria-expanded") !== "true",
      );
    });
    document.addEventListener("click", (event) => {
      if (profileMenu && !profileMenu.contains(event.target))
        setProfileMenuOpen(false);
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") setProfileMenuOpen(false);
    });
    document.documentElement.dataset.portalProfileBound = "true";
  }

  const dashboardShell = document.getElementById("studentDashboardShell");
  const sidebarToggle = document.querySelector(".student-sidebar-toggle");
  const mobileToggle = document.querySelector(".student-mobile-toggle");
  const sidebarBackdrop = document.querySelector(".student-sidebar-backdrop");

  if (!document.documentElement.dataset.portalSidebarBound) {
    document.documentElement.dataset.portalSidebarBound = "true";

    const setSidebarState = (open) => {
      if (!dashboardShell) return;
      dashboardShell.classList.toggle("sidebar-open", open);
      mobileToggle?.setAttribute("aria-expanded", String(open));
      sidebarToggle?.setAttribute("aria-expanded", String(open));
      sidebarToggle?.setAttribute(
        "aria-label",
        open ? "Close navigation" : "Collapse navigation",
      );
      mobileToggle?.setAttribute(
        "aria-label",
        open ? "Close admin navigation" : "Open admin navigation",
      );
    };

    sidebarToggle?.addEventListener("click", () => {
      if (window.matchMedia("(max-width: 900px)").matches) {
        setSidebarState(!dashboardShell?.classList.contains("sidebar-open"));
        return;
      }

      const collapsed =
        dashboardShell?.classList.toggle("sidebar-collapsed") ?? false;
      sidebarToggle.setAttribute("aria-expanded", String(!collapsed));
      sidebarToggle.setAttribute(
        "aria-label",
        collapsed ? "Expand navigation" : "Collapse navigation",
      );
    });

    mobileToggle?.addEventListener("click", () => {
      setSidebarState(!dashboardShell?.classList.contains("sidebar-open"));
    });
    sidebarBackdrop?.addEventListener("click", () => setSidebarState(false));
  }

  const tabBtns = document.querySelectorAll(".tab-btn");
  const tabPanels = document.querySelectorAll(".tab-panel");

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.getAttribute("data-target");

      tabBtns.forEach((b) => b.classList.remove("active"));
      tabPanels.forEach((p) => p.classList.remove("active"));

      btn.classList.add("active");
      const targetPanel = document.getElementById(targetId);
      if (targetPanel) {
        targetPanel.classList.add("active");
      }
    });
  });

  const hashTarget = window.location.hash.slice(1);
  if (hashTarget) {
    const hashButton = document.querySelector(
      `.tab-btn[data-target="${hashTarget}"]`,
    );
    hashButton?.click();
  }

  const openModalBtns = document.querySelectorAll("[data-open-modal]");
  const closeModalBtns = document.querySelectorAll("[data-close-modal]");
  const modalBackdrops = document.querySelectorAll(".modal-backdrop");

  document
    .querySelectorAll('.modal-backdrop[id^="placementGradeModal-"]')
    .forEach((backdrop) => document.body.append(backdrop));

  const placementForm = document.querySelector("[data-placement-form]");
  const placementConfirm = placementForm?.querySelector(
    "[data-placement-confirm]",
  );
  placementForm
    ?.querySelector("[data-open-placement-confirm]")
    ?.addEventListener("click", () => placementConfirm?.showModal());
  placementForm
    ?.querySelector("[data-close-placement-confirm]")
    ?.addEventListener("click", () => placementConfirm?.close());
  placementForm?.addEventListener("change", (event) => {
    if (!(event.target instanceof HTMLSelectElement)) return;
    const row = event.target.closest("tr");
    const yearSelect = row?.querySelector("[data-placement-year-select]");
    const progressionSelect = row?.querySelector(
      "[data-placement-progression-select]",
    );
    if (
      yearSelect instanceof HTMLSelectElement &&
      progressionSelect instanceof HTMLSelectElement
    ) {
      const choices = JSON.parse(yearSelect.dataset.placementChoices || "{}");
      if (event.target === yearSelect) {
        const matchingStatus = Object.entries(choices).find(
          ([, year]) => String(year) === yearSelect.value,
        )?.[0];
        if (matchingStatus) progressionSelect.value = matchingStatus;
      } else if (event.target === progressionSelect) {
        const matchingYear = choices[progressionSelect.value];
        if (matchingYear !== undefined) yearSelect.value = String(matchingYear);
      }
    }
    const selectedCheckbox = row?.querySelector('input[name$="[selected]"]');
    if (selectedCheckbox instanceof HTMLInputElement) {
      selectedCheckbox.checked = true;
    }
  });

  const courseConfirmDialog = document.getElementById("registrarCourseConfirm");
  const courseConfirmMessage = courseConfirmDialog?.querySelector(
    "#registrarCourseConfirmMessage",
  );
  let pendingCourseForm = null;
  document.querySelectorAll("[data-course-confirm-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      if (form.dataset.confirmed === "true") {
        delete form.dataset.confirmed;
        return;
      }
      if (!courseConfirmDialog || !courseConfirmMessage) return;

      event.preventDefault();
      pendingCourseForm = form;
      courseConfirmMessage.textContent = form.dataset.confirmMessage;
      courseConfirmDialog.showModal();
    });
  });
  courseConfirmDialog?.addEventListener("close", () => {
    const confirmedForm = pendingCourseForm;
    pendingCourseForm = null;
    if (courseConfirmDialog.returnValue === "yes" && confirmedForm) {
      confirmedForm.dataset.confirmed = "true";
      confirmedForm.requestSubmit();
    }
  });

  openModalBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const modalId = btn.getAttribute("data-open-modal");
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add("active");
      }
    });
  });

  const adminPasswordModal = document.getElementById("adminPasswordModal");
  const passwordRequestChannel = adminPasswordModal?.querySelector(
    "#passwordRequestChannel",
  );
  const passwordRequestReference = adminPasswordModal?.querySelector(
    "#passwordRequestReference",
  );
  const updatePasswordRequestFields = () => {
    if (!passwordRequestChannel || !passwordRequestReference) return;
    const ticketRequested = passwordRequestChannel.value === "ticket";
    passwordRequestReference.required = ticketRequested;
    passwordRequestReference.placeholder = ticketRequested
      ? "Required ticket number or reference"
      : "Optional";
  };
  passwordRequestChannel?.addEventListener(
    "change",
    updatePasswordRequestFields,
  );
  adminPasswordModal
    ?.querySelector("form")
    ?.addEventListener("submit", (event) => {
      const newPassword =
        adminPasswordModal.querySelector("#adminNewPassword").value;
      const confirmPassword = adminPasswordModal.querySelector(
        "#adminConfirmPassword",
      ).value;
      if (newPassword !== confirmPassword) {
        event.preventDefault();
        adminPasswordModal
          .querySelector("#adminConfirmPassword")
          .setCustomValidity("Passwords do not match.");
        adminPasswordModal
          .querySelector("#adminConfirmPassword")
          .reportValidity();
      }
    });
  adminPasswordModal
    ?.querySelector("#adminConfirmPassword")
    ?.addEventListener("input", (event) => {
      event.currentTarget.setCustomValidity("");
    });

  document
    .querySelectorAll(
      '[data-user-id][data-user-name][data-return-to][data-open-modal="adminPasswordModal"]',
    )
    .forEach((button) => {
      button.addEventListener("click", () => {
        if (!adminPasswordModal) return;
        adminPasswordModal.querySelector("#passwordUserId").value =
          button.dataset.userId || "";
        adminPasswordModal.querySelector("#passwordUserName").textContent =
          button.dataset.userName || "";
        adminPasswordModal.querySelector("#passwordReturnTo").value =
          button.dataset.returnTo || "active";
        adminPasswordModal.querySelector("#adminNewPassword").value = "";
        adminPasswordModal.querySelector("#adminConfirmPassword").value = "";
        passwordRequestChannel.value = "contact";
        passwordRequestReference.value = "";
        updatePasswordRequestFields();
      });
    });

  const lessonFileInput = document.getElementById("lesson_file");
  const lessonFileName = document.getElementById("lessonFileName");
  lessonFileInput?.addEventListener("change", () => {
    const file = lessonFileInput.files?.[0];
    if (file && lessonFileName) {
      lessonFileName.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    }
  });

  const userRoleSelect = document.querySelector(
    "[data-student-course-toggle], [data-user-role-toggle]",
  );
  const studentCourseGroup = document.getElementById("studentCourseGroup");
  const studentCourseSelect = document.getElementById("user_course_id");
  const userDepartmentGroup = document.getElementById("userDepartmentGroup");
  const userDepartmentSelect = document.getElementById("user_department");
  const userDepartmentLabel = document.querySelector("[data-department-label]");
  const userProgramGroup = document.getElementById("userProgramGroup");
  const userProgramSelect = document.getElementById("user_program");
  const userIdNumber = document.querySelector("[data-role-id-number]");
  const userIdHint = document.querySelector("[data-role-id-hint]");
  const roleIdPrefixes = { dean: "D", professor: "EDU", student: "STU" };
  const roleIdExamples = { dean: "1001", professor: "2001", student: "3001" };
  const currentYear = new Date().getFullYear();
  const updateStudentCourseVisibility = () => {
    const isStudent = userRoleSelect?.value === "student";
    if (studentCourseGroup) studentCourseGroup.hidden = !isStudent;
    if (studentCourseSelect) studentCourseSelect.required = isStudent;
    const role = userRoleSelect?.value;
    const needsDepartment = role === "dean" || role === "professor";
    const needsProgram = role === "student";
    if (userDepartmentGroup) userDepartmentGroup.hidden = !needsDepartment;
    if (userDepartmentSelect) {
      userDepartmentSelect.disabled = !needsDepartment;
      userDepartmentSelect.required = needsDepartment;
      userDepartmentSelect.value = "";
    }
    if (userDepartmentLabel) {
      userDepartmentLabel.textContent =
        role === "dean"
          ? "Faculty/College Jurisdiction *"
          : "Assigned Teaching Department *";
    }
    if (userProgramGroup) userProgramGroup.hidden = !needsProgram;
    if (userProgramSelect) {
      userProgramSelect.disabled = !needsProgram;
      userProgramSelect.required = needsProgram;
      userProgramSelect.value = "";
    }
    if (userIdNumber && userIdHint) {
      const prefix = roleIdPrefixes[role];
      userIdNumber.pattern = prefix
        ? `${prefix}-[0-9]{4}-[0-9]{4}`
        : "(?:D|EDU|STU)-[0-9]{4}-[0-9]{4}";
      userIdNumber.placeholder = prefix
        ? `${prefix}-${currentYear}-${roleIdExamples[role]}`
        : "Select a role first";
      userIdHint.textContent = prefix
        ? `Use the format ${prefix}-YYYY-NNNN, for example ${userIdNumber.placeholder}.`
        : "Select a role to see the required ID format.";
    }
  };
  userIdNumber?.addEventListener("input", () => {
    userIdNumber.value = userIdNumber.value.toUpperCase();
  });

  const courseCodeInput = document.getElementById("course_code");
  const uppercaseCourseCode = () => {
    const cursor = courseCodeInput.selectionStart;
    courseCodeInput.value = courseCodeInput.value.toUpperCase();
    if (cursor !== null) courseCodeInput.setSelectionRange(cursor, cursor);
  };
  courseCodeInput?.addEventListener("input", (event) => {
    if (!event.isComposing) uppercaseCourseCode();
  });
  courseCodeInput?.addEventListener("compositionend", uppercaseCourseCode);

  const courseNameInput = document.getElementById("course_name");
  const minorTitleWords = new Set([
    "a",
    "an",
    "and",
    "as",
    "at",
    "but",
    "by",
    "down",
    "for",
    "from",
    "in",
    "into",
    "nor",
    "of",
    "off",
    "on",
    "onto",
    "or",
    "out",
    "over",
    "per",
    "so",
    "the",
    "through",
    "to",
    "under",
    "until",
    "up",
    "upon",
    "via",
    "with",
    "within",
    "without",
    "yet",
  ]);
  const titleCaseCourseName = (value) => {
    let wordIndex = 0;
    const wordCount = [
      ...value.matchAll(/[\p{L}\p{N}][\p{L}\p{M}\p{N}'’.-]*/gu),
    ].length;
    return value.replace(/[\p{L}\p{N}][\p{L}\p{M}\p{N}'’.-]*/gu, (word) => {
      const currentWordIndex = wordIndex++;
      const lowerWord = word.toLocaleLowerCase();
      if (
        currentWordIndex > 0 &&
        currentWordIndex < wordCount - 1 &&
        minorTitleWords.has(lowerWord)
      ) {
        return lowerWord;
      }
      const characters = [...lowerWord];
      characters[0] = characters[0].toLocaleUpperCase();
      return characters.join("");
    });
  };
  const formatCourseName = () => {
    const cursor = courseNameInput.selectionStart;
    courseNameInput.value = titleCaseCourseName(courseNameInput.value);
    if (cursor !== null) courseNameInput.setSelectionRange(cursor, cursor);
  };
  courseNameInput?.addEventListener("input", (event) => {
    if (!event.isComposing) formatCourseName();
  });
  courseNameInput?.addEventListener("compositionend", formatCourseName);

  userRoleSelect?.addEventListener("change", updateStudentCourseVisibility);
  updateStudentCourseVisibility();

  const assessmentQuestions = document.getElementById("assessmentQuestions");
  const addQuestionButton = document.querySelector("[data-add-question]");
  const updateQuestionType = (editor) => {
    const type = editor.querySelector(".assessment-question-type")?.value;
    const choicesGroup = editor.querySelector(".assessment-choices-group");
    const choices = editor.querySelector('[name$="[choices]"]');
    const answer = editor.querySelector(".assessment-answer");
    if (choicesGroup) choicesGroup.hidden = type !== "multiple_choice";
    if (choices) choices.required = false;
    if (answer)
      answer.placeholder =
        type === "multiple_choice"
          ? "Enter the exact correct choice"
          : "Enter the expected answer";
  };
  assessmentQuestions
    ?.querySelectorAll(".assessment-question-editor")
    .forEach((editor) => {
      editor
        .querySelector(".assessment-question-type")
        ?.addEventListener("change", () => updateQuestionType(editor));
      updateQuestionType(editor);
    });
  addQuestionButton?.addEventListener("click", () => {
    if (!assessmentQuestions) return;
    const index = assessmentQuestions.querySelectorAll(
      ".assessment-question-editor",
    ).length;
    const editor = document.createElement("div");
    editor.className = "assessment-question-editor";
    editor.innerHTML = `
            <div class="assessment-question-editor-heading"><strong>Question ${index + 1}</strong><button type="button" class="assessment-remove-question">Remove</button></div>
            <div class="form-group"><label>Question *</label><textarea name="questions[${index}][text]" class="form-control" rows="2" required></textarea></div>
            <div class="form-group"><label>Question type *</label><select name="questions[${index}][type]" class="form-control assessment-question-type"><option value="multiple_choice">Multiple choice</option><option value="identification">Identification</option></select></div>
            <div class="form-group assessment-choices-group"><label>Choices (one per line, optional)</label><textarea name="questions[${index}][choices]" class="form-control" rows="3" placeholder="Choice A&#10;Choice B&#10;Choice C"></textarea></div>
            <div class="form-group"><label>Correct answer *</label><input name="questions[${index}][answer]" class="form-control assessment-answer" required></div>`;
    assessmentQuestions.appendChild(editor);
    editor
      .querySelector(".assessment-question-type")
      ?.addEventListener("change", () => updateQuestionType(editor));
    editor
      .querySelector(".assessment-remove-question")
      ?.addEventListener("click", () => editor.remove());
    updateQuestionType(editor);
  });

  document.querySelectorAll(".course-schedule-edit").forEach((button) => {
    button.addEventListener("click", () => {
      const modal = document.getElementById("courseScheduleModal");
      if (!modal) return;

      modal.querySelector("#schedule_course_id").value =
        button.dataset.courseId || "";
      modal.querySelector("#schedule_course_name").textContent =
        button.dataset.courseName || "";
      modal.querySelector("#schedule_start_time").value =
        button.dataset.start || "";
      modal.querySelector("#schedule_end_time").value =
        button.dataset.end || "";
      modal.querySelector("#schedule_room").value = button.dataset.room || "";
      modal.querySelector("#schedule_professor").value =
        button.dataset.professorId || "";
      const professorSearch = modal.querySelector("[data-professor-search]");
      if (professorSearch) professorSearch.value = "";
      const professorPicker = modal.querySelector("[data-professor-picker]");
      if (professorPicker) professorPicker.dataset.open = "false";
      const form = modal.querySelector("form[data-course-form]");
      if (form) {
        form.dataset.capacity = button.dataset.capacity || "0";
        form.dataset.enrolledCount = button.dataset.enrolledCount || "0";
        form.dataset.courseCode = button.dataset.courseCode || "";
      }
      const days = (button.dataset.days || "").split(",").filter(Boolean);
      modal
        .querySelectorAll('input[name="meeting_days[]"]')
        .forEach((input) => {
          input.checked = days.includes(input.value);
        });
      modal
        .querySelectorAll(".course-student-select option")
        .forEach((option) => {
          option.selected = (option.dataset.enrolledCourseIds || "")
            .split(",")
            .includes(button.dataset.courseId || "");
        });
      const studentSearch = modal.querySelector("[data-student-search]");
      if (studentSearch) studentSearch.value = "";
      const studentPicker = modal.querySelector("[data-student-picker]");
      if (studentPicker) studentPicker.dataset.open = "false";
      const cohortFilter = modal.querySelector("[data-cohort-filter]");
      cohortFilter
        ?.querySelectorAll('input[type="checkbox"]')
        .forEach((input) => {
          input.checked = false;
        });
      if (cohortFilter) {
        cohortFilter.open = false;
        const count = cohortFilter.querySelector("[data-filter-count]");
        if (count) {
          count.textContent = "";
          count.hidden = true;
        }
      }
      modal.querySelector("[data-cohort-applied-inputs]")?.replaceChildren();
      if (form) updateCourseAssignmentForm(form);
      renderProfessorPicker(modal);
    });
  });

  const renderProfessorPicker = (root) => {
    const picker = root.querySelector("[data-professor-picker]");
    const professorSelect = picker?.querySelector("#schedule_professor");
    const search = picker?.querySelector("[data-professor-search]");
    const results = picker?.querySelector("[data-professor-results]");
    const selectedLabel = picker?.querySelector("[data-professor-selected]");
    if (!picker || !professorSelect || !search || !results || !selectedLabel)
      return;

    const selectedProfessor = professorSelect.selectedOptions[0];
    selectedLabel.replaceChildren();
    selectedLabel.hidden = !selectedProfessor?.value;
    if (selectedProfessor?.value) {
      const chip = document.createElement("span");
      chip.className = "course-professor-picker-chip";
      const name = document.createElement("span");
      name.textContent = [
        selectedProfessor.dataset.professorName,
        selectedProfessor.dataset.professorIdNumber ||
          selectedProfessor.dataset.professorEmail,
      ]
        .filter(Boolean)
        .join(" · ");
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "course-student-picker-remove";
      remove.setAttribute(
        "aria-label",
        `Remove ${selectedProfessor.dataset.professorName}`,
      );
      remove.textContent = "×";
      remove.addEventListener("click", () => {
        professorSelect.value = "";
        renderProfessorPicker(root);
        search.focus();
      });
      chip.append(name, remove);
      selectedLabel.append(chip);
    }

    const query = search.value.trim().toLocaleLowerCase();
    const matches = Array.from(professorSelect.options)
      .filter((option) => option.value)
      .map((option) => {
        const name = (option.dataset.professorName || "").toLocaleLowerCase();
        const idNumber = (
          option.dataset.professorIdNumber || ""
        ).toLocaleLowerCase();
        const email = (option.dataset.professorEmail || "").toLocaleLowerCase();
        const score = !query
          ? 0
          : idNumber === query
            ? 0
            : idNumber.startsWith(query)
              ? 1
              : name.startsWith(query)
                ? 2
                : email.startsWith(query)
                  ? 3
                  : idNumber.includes(query)
                    ? 4
                    : name.includes(query)
                      ? 5
                      : email.includes(query)
                        ? 6
                        : Number.POSITIVE_INFINITY;
        return { option, score, name };
      })
      .filter((match) => match.score !== Number.POSITIVE_INFINITY)
      .sort(
        (first, second) =>
          first.score - second.score || first.name.localeCompare(second.name),
      );

    results.replaceChildren();
    if (!matches.length) {
      const empty = document.createElement("p");
      empty.className = "course-professor-picker-empty";
      empty.textContent = "No matching professors.";
      results.append(empty);
    } else {
      matches.slice(0, 50).forEach(({ option }) => {
        const result = document.createElement("button");
        result.type = "button";
        result.className = "course-professor-picker-option";
        result.setAttribute("role", "option");
        result.setAttribute("aria-selected", String(option.selected));
        const name = document.createElement("span");
        name.className = "course-student-picker-option-name";
        name.textContent = option.dataset.professorName || "";
        const details = document.createElement("span");
        details.className = "course-student-picker-option-details";
        details.textContent = [
          option.dataset.professorIdNumber,
          option.dataset.professorEmail,
        ]
          .filter(Boolean)
          .join(" · ");
        result.append(name, details);
        result.addEventListener("mousedown", (event) => event.preventDefault());
        result.addEventListener("click", () => {
          professorSelect.value = option.value;
          search.value = "";
          picker.dataset.open = "false";
          renderProfessorPicker(root);
          search.focus();
        });
        results.append(result);
      });
    }

    const isOpen = picker.dataset.open === "true";
    results.hidden = !isOpen;
    search.setAttribute("aria-expanded", String(isOpen));
  };

  const renderStudentPicker = (form) => {
    const picker = form.querySelector("[data-student-picker]");
    const studentSelect = picker?.querySelector(".course-student-select");
    const search = picker?.querySelector("[data-student-search]");
    const results = picker?.querySelector("[data-student-results]");
    const selectedList = picker?.querySelector("[data-student-selected]");
    if (!picker || !studentSelect || !search || !results || !selectedList)
      return;
    const currentCourseId =
      form.querySelector('[name="course_id"]')?.value || "";

    const selectedOptions = Array.from(studentSelect.selectedOptions);
    selectedList.replaceChildren();
    selectedOptions.forEach((option) => {
      const chip = document.createElement("span");
      chip.className = "course-student-picker-chip";
      const name = document.createElement("span");
      name.textContent = `${option.dataset.studentName} · ${option.dataset.studentIdNumber}`;
      chip.append(name);
      const isExistingEnrollment =
        currentCourseId !== "" &&
        (option.dataset.enrolledCourseIds || "")
          .split(",")
          .includes(currentCourseId);
      if (isExistingEnrollment) {
        const status = document.createElement("small");
        status.className = "course-student-picker-existing";
        status.textContent = "Enrolled";
        chip.append(status);
      } else {
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "course-student-picker-remove";
        remove.setAttribute(
          "aria-label",
          `Remove ${option.dataset.studentName}`,
        );
        remove.textContent = "×";
        remove.addEventListener("click", () => {
          option.selected = false;
          updateCourseAssignmentForm(form);
          search.focus();
        });
        chip.append(remove);
      }
      selectedList.append(chip);
    });

    const query = search.value.trim().toLocaleLowerCase();
    const matches = Array.from(studentSelect.options)
      .filter(
        (option) => !option.hidden && !option.disabled && !option.selected,
      )
      .map((option) => {
        const name = (option.dataset.studentName || "").toLocaleLowerCase();
        const idNumber = (
          option.dataset.studentIdNumber || ""
        ).toLocaleLowerCase();
        const email = (option.dataset.studentEmail || "").toLocaleLowerCase();
        const searchableText = option.textContent.toLocaleLowerCase();
        const score = !query
          ? 0
          : idNumber === query
            ? 0
            : idNumber.startsWith(query)
              ? 1
              : name.startsWith(query)
                ? 2
                : email.startsWith(query)
                  ? 3
                  : idNumber.includes(query)
                    ? 4
                    : name.includes(query)
                      ? 5
                      : email.includes(query)
                        ? 6
                        : searchableText.includes(query)
                          ? 7
                          : Number.POSITIVE_INFINITY;
        return { option, score, name };
      })
      .filter((match) => match.score !== Number.POSITIVE_INFINITY)
      .sort(
        (first, second) =>
          first.score - second.score || first.name.localeCompare(second.name),
      );

    results.replaceChildren();
    if (!matches.length) {
      const empty = document.createElement("p");
      empty.className = "course-student-picker-empty";
      empty.textContent = search.value.trim()
        ? "No matching eligible students."
        : "Choose a year level to view eligible students.";
      results.append(empty);
    } else {
      matches.slice(0, 50).forEach(({ option }) => {
        const result = document.createElement("button");
        result.type = "button";
        result.className = "course-student-picker-option";
        result.setAttribute("role", "option");
        result.setAttribute("aria-selected", "false");
        const name = document.createElement("span");
        name.className = "course-student-picker-option-name";
        name.textContent = option.dataset.studentName || "";
        const details = document.createElement("span");
        details.className = "course-student-picker-option-details";
        details.textContent = [
          option.dataset.studentIdNumber,
          option.dataset.studentEmail,
          option.textContent.split("·").slice(2).join("·").trim(),
        ]
          .filter(Boolean)
          .join(" · ");
        result.append(name, details);
        result.addEventListener("mousedown", (event) => event.preventDefault());
        result.addEventListener("click", () => {
          option.selected = true;
          search.value = "";
          updateCourseAssignmentForm(form);
          search.focus();
        });
        results.append(result);
      });
    }

    const isOpen = picker.dataset.open === "true";
    results.hidden = !isOpen;
    search.setAttribute("aria-expanded", String(isOpen));
  };

  const updateCourseAssignmentForm = (form) => {
    const studentSelects = Array.from(
      form.querySelectorAll(".course-student-select"),
    );
    const courseId = form.querySelector('[name="course_id"]')?.value || "";
    const courseCode =
      form.querySelector('[name="course_code"]')?.value.trim().toUpperCase() ||
      (form.dataset.courseCode || "").toUpperCase();
    const enrolled = Number(form.dataset.enrolledCount || 0);
    const availableStudentIds = new Set();
    const cohortProgramIds = Array.from(
      form.querySelectorAll(
        '[data-cohort-applied-inputs] [name="assignment_program_ids[]"]',
      ),
    ).map((input) => input.value);
    const cohortYearLevels = Array.from(
      form.querySelectorAll(
        '[data-cohort-applied-inputs] [name="assignment_year_levels[]"]',
      ),
    ).map((input) => input.value);
    const cohortAssignmentReady = cohortYearLevels.length > 0;

    form.querySelectorAll("[data-course-section]").forEach((section) => {
      const toggle = section.querySelector("[data-section-toggle]");
      const enabled = toggle?.checked ?? true;
      section
        .querySelector("[data-section-details]")
        ?.toggleAttribute("hidden", !enabled);
      section.querySelectorAll(".course-section-input").forEach((input) => {
        input.disabled = !enabled;
      });
    });

    studentSelects.forEach((studentSelect) =>
      studentSelect.querySelectorAll("option").forEach((option) => {
        const matchesProgram =
          cohortProgramIds.length === 0 ||
          cohortProgramIds.includes(option.dataset.programId);
        const matchesYear = cohortYearLevels.includes(option.dataset.yearLevel);
        const enrolledCourseIds = (
          option.dataset.enrolledCourseIds || ""
        ).split(",");
        const enrolledInCurrentCourse =
          courseId !== "" && enrolledCourseIds.includes(courseId);
        const enrolledCourseCodes = (option.dataset.enrolledCourseCodes || "")
          .split(",")
          .map((value) => value.toUpperCase());
        const alreadyEnrolled =
          (courseCode !== "" && enrolledCourseCodes.includes(courseCode)) ||
          enrolledInCurrentCourse;
        option.hidden =
          !enrolledInCurrentCourse &&
          (!cohortAssignmentReady ||
            !matchesProgram ||
            !matchesYear ||
            alreadyEnrolled);
        option.disabled = option.hidden;
        if (option.hidden) option.selected = false;
        if (!option.hidden && !alreadyEnrolled)
          availableStudentIds.add(option.value);
      }),
    );
    const availableStudents = availableStudentIds.size;
    const currentlyEnrolledStudents = studentSelects.reduce(
      (count, studentSelect) =>
        count +
        Array.from(studentSelect.selectedOptions).filter(
          (option) =>
            courseId !== "" &&
            (option.dataset.enrolledCourseIds || "")
              .split(",")
              .includes(courseId),
        ).length,
      0,
    );

    const availableSeats = Math.max(
      0,
      Number(form.dataset.capacity || 0) - enrolled,
    );
    studentSelects.forEach((studentSelect) => {
      const selectedCount = Array.from(studentSelect.selectedOptions).filter(
        (option) => !option.disabled,
      ).length;
      studentSelect.querySelectorAll("option").forEach((option) => {
        if (!option.hidden) {
          option.disabled = alreadyAtCapacity(selectedCount, availableSeats);
          if (option.selected) option.disabled = false;
        }
      });
    });

    const seatSummary = form.querySelector("[data-seat-summary]");
    if (seatSummary) {
      seatSummary.textContent = cohortAssignmentReady
        ? `${availableSeats} seat(s) remain; ${availableStudents} eligible student(s) available; ${currentlyEnrolledStudents} already enrolled.`
        : currentlyEnrolledStudents
          ? `${currentlyEnrolledStudents} student(s) already enrolled. Choose a year level to add more students.`
          : "Choose at least one year level to view eligible students.";
    }
    renderStudentPicker(form);
  };
  const alreadyAtCapacity = (selectedCount, availableSeats) =>
    selectedCount >= availableSeats;
  const showCourseFormToast = (message, type = "info") => {
    window.dispatchEvent(
      new CustomEvent("apex:toast", {
        detail: { message, type },
      }),
    );
  };

  document.querySelectorAll("form[data-course-form]").forEach((form) => {
    const cohortFilter = form.querySelector("[data-cohort-filter]");
    const appliedCohortInputs = form.querySelector(
      "[data-cohort-applied-inputs]",
    );
    const cohortPopover = cohortFilter?.querySelector(
      ".course-cohort-filter-popover",
    );
    const positionCohortPopover = () => {
      if (!cohortFilter || !cohortPopover) return;
      if (!cohortFilter.open) {
        cohortFilter.removeAttribute("data-placement");
        cohortPopover.style.removeProperty("max-height");
        cohortPopover.style.removeProperty("overflow-y");
        return;
      }

      cohortPopover.style.removeProperty("max-height");
      cohortPopover.style.removeProperty("overflow-y");
      const filterSummary = cohortFilter.querySelector("summary");
      if (!filterSummary) return;

      const summaryRect = filterSummary.getBoundingClientRect();
      const edgePadding = 16;
      const gap = 12;
      const availableBelow = Math.max(
        0,
        window.innerHeight - summaryRect.bottom - edgePadding - gap,
      );
      const availableAbove = Math.max(0, summaryRect.top - edgePadding - gap);
      const naturalHeight = cohortPopover.scrollHeight;
      const placeAbove =
        naturalHeight > availableBelow && availableAbove > availableBelow;
      const availableHeight = placeAbove ? availableAbove : availableBelow;

      cohortFilter.dataset.placement = placeAbove ? "top" : "bottom";
      if (naturalHeight > availableHeight) {
        cohortPopover.style.maxHeight = `${Math.max(1, availableHeight)}px`;
        cohortPopover.style.overflowY = "auto";
      }
    };
    cohortFilter?.addEventListener("toggle", positionCohortPopover);
    window.addEventListener("resize", positionCohortPopover);
    window.addEventListener("scroll", positionCohortPopover, true);
    const updateAppliedCohort = () => {
      if (!cohortFilter || !appliedCohortInputs) return;
      const programIds = Array.from(
        cohortFilter.querySelectorAll("[data-filter-program-choice]:checked"),
      ).map((input) => input.value);
      const yearLevels = Array.from(
        cohortFilter.querySelectorAll("[data-filter-year-choice]:checked"),
      ).map((input) => input.value);
      appliedCohortInputs.replaceChildren();
      [
        ...programIds.map((value) => ["assignment_program_ids[]", value]),
        ...yearLevels.map((value) => ["assignment_year_levels[]", value]),
      ].forEach(([name, value]) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        appliedCohortInputs.appendChild(input);
      });
      const count = cohortFilter.querySelector("[data-filter-count]");
      if (count) {
        const selectedCount = programIds.length + yearLevels.length;
        count.textContent = selectedCount ? String(selectedCount) : "";
        count.hidden = selectedCount === 0;
      }
      updateCourseAssignmentForm(form);
    };
    cohortFilter
      ?.querySelector("[data-filter-apply]")
      ?.addEventListener("click", () => {
        updateAppliedCohort();
        cohortFilter.open = false;
      });
    cohortFilter
      ?.querySelector("[data-filter-reset]")
      ?.addEventListener("click", () => {
        cohortFilter
          .querySelectorAll('input[type="checkbox"]')
          .forEach((input) => {
            input.checked = false;
          });
        updateAppliedCohort();
      });
    const studentPicker = form.querySelector("[data-student-picker]");
    const studentSearch = studentPicker?.querySelector("[data-student-search]");
    studentSearch?.addEventListener("focus", () => {
      studentPicker.dataset.open = "true";
      renderStudentPicker(form);
    });
    studentSearch?.addEventListener("input", () => renderStudentPicker(form));
    studentSearch?.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        studentPicker.dataset.open = "false";
        renderStudentPicker(form);
      } else if (event.key === "ArrowDown") {
        studentPicker
          .querySelector("[data-student-results] .course-student-picker-option")
          ?.focus();
        event.preventDefault();
      }
    });
    const professorPicker = form.querySelector("[data-professor-picker]");
    const professorSearch = professorPicker?.querySelector(
      "[data-professor-search]",
    );
    professorSearch?.addEventListener("focus", () => {
      professorPicker.dataset.open = "true";
      renderProfessorPicker(form);
    });
    professorSearch?.addEventListener("input", () => {
      professorPicker.dataset.open = "true";
      renderProfessorPicker(form);
    });
    professorSearch?.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        professorPicker.dataset.open = "false";
        renderProfessorPicker(form);
      } else if (event.key === "ArrowDown") {
        professorPicker
          .querySelector(".course-professor-picker-option")
          ?.focus();
        event.preventDefault();
      }
    });
    professorPicker?.addEventListener("focusout", () => {
      window.setTimeout(() => {
        if (!professorPicker.contains(document.activeElement)) {
          professorPicker.dataset.open = "false";
          renderProfessorPicker(form);
        }
      }, 0);
    });
    studentPicker?.addEventListener("focusout", () => {
      window.setTimeout(() => {
        if (!studentPicker.contains(document.activeElement)) {
          studentPicker.dataset.open = "false";
          renderStudentPicker(form);
        }
      }, 0);
    });
    form
      .querySelectorAll(
        '.course-student-select, [data-section-toggle], [name="course_code"]',
      )
      .forEach((input) => {
        input.addEventListener("input", () => updateCourseAssignmentForm(form));
        input.addEventListener("change", () =>
          updateCourseAssignmentForm(form),
        );
      });
    document.addEventListener("pointerdown", (event) => {
      if (studentPicker && !studentPicker.contains(event.target)) {
        studentPicker.dataset.open = "false";
        renderStudentPicker(form);
      }
      if (professorPicker && !professorPicker.contains(event.target)) {
        professorPicker.dataset.open = "false";
        renderProfessorPicker(form);
      }
    });
    updateCourseAssignmentForm(form);
    renderProfessorPicker(form);

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const action = form.querySelector('[name="action"]')?.value;
      const requiresProfessor =
        action === "update_course_schedule" || action === "add_course";
      if (
        requiresProfessor &&
        !form.querySelector("#schedule_professor")?.value
      ) {
        showCourseFormToast(
          "Choose a professor before saving the schedule.",
          "error",
        );
        form.querySelector("[data-professor-search]")?.focus();
        return;
      }
      const selectedStudents = [];
      form
        .querySelectorAll(".course-student-select:not(:disabled)")
        .forEach((select) => {
          Array.from(select.selectedOptions).forEach((option) =>
            selectedStudents.push(option.value),
          );
        });
      if (new Set(selectedStudents).size !== selectedStudents.length) {
        showCourseFormToast(
          "A student can only be assigned to one section of this course.",
          "error",
        );
        return;
      }
      if (
        form.querySelector("#course_code") &&
        form.querySelector("[data-section-toggle]") &&
        !form.querySelector("[data-section-toggle]:checked")
      ) {
        showCourseFormToast(
          "Select at least one section before creating the course.",
          "error",
        );
        return;
      }
      const submitButton = form.querySelector('[type="submit"]');
      if (submitButton) submitButton.disabled = true;
      try {
        const endpoint = form.getAttribute("action");
        if (!endpoint) {
          throw new Error(
            "The course form is missing its API endpoint. Reload the page and try again.",
          );
        }
        const endpointUrl = new URL(endpoint, window.location.href);
        const response = await fetch(endpointUrl, {
          method: "POST",
          body: new FormData(form),
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });
        const responseText = await response.text();
        let result;
        try {
          result = JSON.parse(responseText);
        } catch {
          if (
            response.redirected ||
            /\/auth\/login\.php(?:[?#]|$)/i.test(response.url)
          ) {
            throw new Error(
              "Your session has expired. Sign in again, then retry saving the course.",
            );
          }
          throw new Error(
            `The course API at ${response.url} returned an unexpected response (HTTP ${response.status}). Please try again or contact the administrator.`,
          );
        }
        if (!response.ok || !result.success) {
          showCourseFormToast(
            result.message ||
              `The course could not be saved (HTTP ${response.status}).`,
            "error",
          );
          if (response.status === 401 && result.redirect) {
            window.location.assign(new URL(result.redirect, endpointUrl).href);
          }
          return;
        }
        showCourseFormToast(result.message, "success");
        window.setTimeout(() => window.location.reload(), 1100);
      } catch (error) {
        showCourseFormToast(
          error instanceof Error
            ? error.message
            : "The request could not be completed due to a network error. Please check your connection and try again.",
          "error",
        );
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });
  });

  closeModalBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const modal = btn.closest(".modal-backdrop");
      if (modal) {
        modal.classList.remove("active");
      }
    });
  });

  modalBackdrops.forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        modal.classList.remove("active");
      }
    });
  });

  const searchInputs = document.querySelectorAll("[data-table-search]");
  searchInputs.forEach((input) => {
    const tableId = input.getAttribute("data-table-search");
    const table = document.getElementById(tableId);
    if (!table) return;

    input.addEventListener("input", () => {
      const query = input.value.toLowerCase().trim();
      const rows = table.querySelectorAll("tbody tr");

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });
  });

  document.querySelectorAll("[data-account-filter]").forEach((filter) => {
    const allRoles = filter.querySelector("[data-role-all]");
    const roleChoices = [...filter.querySelectorAll("[data-role-choice]")];
    const form = filter.querySelector("form");
    const statusChoices = [
      ...filter.querySelectorAll('input[name="status[]"]'),
    ];

    const syncRoleChoices = () => {
      if (!allRoles) return;
      roleChoices.forEach((choice) => {
        choice.disabled = allRoles.checked;
        if (allRoles.checked) choice.checked = false;
      });
    };

    allRoles?.addEventListener("change", syncRoleChoices);
    roleChoices.forEach((choice) => {
      choice.addEventListener("change", () => {
        if (choice.checked && allRoles) allRoles.checked = false;
        syncRoleChoices();
      });
    });
    form?.addEventListener("submit", () => {
      if (
        allRoles &&
        !allRoles.checked &&
        !roleChoices.some((choice) => choice.checked)
      ) {
        allRoles.checked = true;
      }
      if (!statusChoices.some((choice) => choice.checked)) {
        const activeStatus = statusChoices.find(
          (choice) => choice.value === "active",
        );
        if (activeStatus) activeStatus.checked = true;
      }
    });
    syncRoleChoices();
  });
});
