(() => {
  // resources/js/toast-stack-factory.js
  var DEFAULT_ERROR_MS = 8e3;
  var DEFAULT_TOAST_MS = 5e3;
  function toastStackFactory(initial = [], copy = {}) {
    const mergeCopy = { ...window.__toastCopy, ...copy };
    return {
      toasts: [],
      copy: mergeCopy,
      init() {
        initial.forEach((toast) => this.push(toast));
        if (typeof Livewire !== "undefined") {
          Livewire.on("toast", (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            this.push(data ?? {});
          });
        } else {
          window.addEventListener("toast", (event) => {
            const detail = event.detail ?? {};
            if (Array.isArray(detail)) {
              detail.forEach((t) => this.push(t));
            } else {
              this.push(detail);
            }
          });
        }
      },
      push(raw) {
        const type = raw.type ?? "info";
        const persistent = raw.persistent ?? type === "error";
        const id = raw.id ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const toast = {
          id,
          type,
          title: raw.title ?? null,
          message: raw.message ?? "",
          persistent
        };
        if (!toast.message) {
          return;
        }
        const allowRepeat = raw.dedupe === false;
        const recentDuplicate = this.toasts.some(
          (t) => t.message === toast.message && t.type === toast.type
        );
        if (!allowRepeat && recentDuplicate) {
          return;
        }
        this.toasts = [...this.toasts, toast].slice(-3);
        if (!persistent) {
          const ms = raw.durationMs ?? (type === "warning" ? DEFAULT_ERROR_MS : DEFAULT_TOAST_MS);
          window.setTimeout(() => this.dismiss(id), ms);
        }
      },
      dismiss(id) {
        this.toasts = this.toasts.filter((t) => t.id !== id);
      },
      typeClass(type) {
        return {
          success: "toast toast--success",
          info: "toast toast--info",
          warning: "toast toast--warning",
          error: "toast toast--error"
        }[type] ?? "toast toast--info";
      },
      liveRegion(type) {
        return type === "error" ? "assertive" : "polite";
      }
    };
  }
  function attachGlobalToastHelpers(copy = {}) {
    window.showToast = (payload) => {
      window.dispatchEvent(new CustomEvent("toast", { detail: payload }));
    };
    window.toastStack = (initial = []) => toastStackFactory(initial, { ...window.__toastCopy, ...copy });
  }

  // resources/js/toast-prime.js
  attachGlobalToastHelpers();
})();
