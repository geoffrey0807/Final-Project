document.addEventListener("DOMContentLoaded", () => {
  const region = document.querySelector("[data-portal-toast-region]");
  if (!region) return;

  const dismiss = (toast) => {
    if (!toast?.isConnected || toast.classList.contains("is-leaving")) return;
    toast.classList.add("is-leaving");
    window.setTimeout(() => toast.remove(), 190);
  };

  const scheduleDismiss = (toast) => {
    let timer = window.setTimeout(() => dismiss(toast), 5000);
    const pause = () => {
      window.clearTimeout(timer);
    };
    const resume = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => dismiss(toast), 5000);
    };
    toast.addEventListener("mouseenter", pause);
    toast.addEventListener("mouseleave", resume);
    toast.addEventListener("focusin", pause);
    toast.addEventListener("focusout", resume);
  };

  const addToast = ({ message, type = "info" }) => {
    if (typeof message !== "string" || !message.trim()) return;
    const toast = document.createElement("div");
    toast.className = `portal-toast portal-toast--${
      ["success", "error", "info"].includes(type) ? type : "info"
    }`;
    toast.dataset.portalToast = "";
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    const text = document.createElement("span");
    text.className = "portal-toast-message";
    text.textContent = message;

    const close = document.createElement("button");
    close.className = "portal-toast-dismiss";
    close.type = "button";
    close.dataset.portalToastDismiss = "";
    close.setAttribute("aria-label", "Dismiss notification");
    close.textContent = "×";
    close.addEventListener("click", () => dismiss(toast));

    toast.append(text, close);
    region.append(toast);
    scheduleDismiss(toast);
  };

  region.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;
    const close = event.target.closest("[data-portal-toast-dismiss]");
    if (close) dismiss(close.closest("[data-portal-toast]"));
  });
  region.querySelectorAll("[data-portal-toast]").forEach(scheduleDismiss);
  window.addEventListener("apex:toast", (event) => addToast(event.detail ?? {}));

  const toastableEndpoints = new Set([
    "admin_process.php",
    "admin_accounts_process.php",
    "admin_support_requests_process.php",
    "auth_process.php",
    "contact_request_process.php",
    "professor_process.php",
    "registrar_announcements_process.php",
    "registrar_programs_process.php",
    "student_process.php",
    "support_requests_delete.php",
  ]);
  const nativeFetch = window.fetch.bind(window);
  window.fetch = async (...args) => {
    const response = await nativeFetch(...args);
    const request = args[0];
    const requestUrl = new URL(
      request instanceof Request ? request.url : String(request),
      window.location.href,
    );
    const method = (args[1]?.method ?? (request instanceof Request ? request.method : "GET"))
      .toUpperCase();
    const apiPathMatch = requestUrl.pathname.match(/(?:^|\/)api\/([^/]+)$/);

    if (
      method === "POST" &&
      requestUrl.origin === window.location.origin &&
      apiPathMatch &&
      toastableEndpoints.has(apiPathMatch[1])
    ) {
      response.clone().json().then((payload) => {
        if (
          typeof payload?.success === "boolean" &&
          typeof payload.message === "string"
        ) {
          window.dispatchEvent(
            new CustomEvent("apex:toast", {
              detail: {
                message: payload.message,
                type: payload.success ? "success" : "error",
              },
            }),
          );
        }
      }, () => {});
    }
    return response;
  };
});
