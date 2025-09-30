const J = /\D+/g;
function D(s) {
  return s ? String(s).replace(J, "") : "";
}
function C(s) {
  const t = D(s);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function k(s) {
  return D(s);
}
function K(s, t) {
  const e = C(s), i = k(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function G(s) {
  const t = k(s);
  return t.length >= 6 && t.length <= 15;
}
function X(s) {
  const t = k(s);
  if (t === "")
    return { masked: "", digits: "" };
  const e = [3, 4], i = [];
  let n = 0, r = 0;
  for (; n < t.length; ) {
    const a = t.length - n;
    let c = e[r % e.length];
    a <= 4 && (c = a), i.push(t.slice(n, n + c)), n += c, r += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function Y(s, t) {
  const e = s.value, { masked: i } = X(e), n = s.selectionStart;
  if (s.value = i, n !== null) {
    const r = i.length - e.length, a = Math.max(0, n + r);
    s.setSelectionRange(a, a);
  }
  s.setAttribute("data-phone-local", k(s.value)), s.setAttribute("data-phone-cc", C(t));
}
function x(s, t) {
  const e = k(s.value), i = C(t);
  return {
    e164: K(i, e),
    local: e,
    country: i
  };
}
let F = null;
const N = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (s) => window.requestIdleCallback(s) : (s) => window.setTimeout(() => s(Date.now()), 1);
function $() {
  return F || (F = Promise.resolve().then(() => ut)), F;
}
function Q(s) {
  const t = s.getAttribute("data-fp-resv");
  if (!t)
    return {};
  try {
    return JSON.parse(t);
  } catch (e) {
    window.console && window.console.warn && console.warn("[fp-resv] Impossibile analizzare il dataset del widget", e);
  }
  return {};
}
function h(s, t) {
  if (!s)
    return null;
  const e = Object.assign({ event: s }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
function B(s, t) {
  if (!s)
    return null;
  if (typeof s.closest == "function")
    return s.closest("[" + t + "]");
  let e = s;
  for (; e; ) {
    if (e.hasAttribute(t))
      return e;
    e = e.parentElement;
  }
  return null;
}
function Z(s) {
  return B(s, "data-fp-resv-section");
}
function tt(s, t) {
  if (!s)
    return {};
  const e = s.getAttribute(t);
  if (!e)
    return {};
  try {
    const i = JSON.parse(e);
    if (i && typeof i == "object")
      return i;
  } catch (i) {
    window.console && window.console.warn && console.warn("[fp-resv] Impossibile analizzare l'attributo", t, i);
  }
  return {};
}
function et(s, t) {
  s && (t ? (s.setAttribute("aria-disabled", "true"), s.setAttribute("disabled", "disabled")) : (s.removeAttribute("disabled"), s.setAttribute("aria-disabled", "false")));
}
function it(s) {
  if (s == null)
    return null;
  if (typeof s == "number")
    return Number.isFinite(s) ? s : null;
  const t = String(s).replace(",", "."), e = parseFloat(t);
  return Number.isNaN(e) ? null : e;
}
function O(s, t) {
  if (s && typeof s == "string")
    try {
      return new URL(s, window.location.origin).toString();
    } catch {
      return s;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
function st(s) {
  return s ? s.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]') : null;
}
const nt = ["date", "party", "slots", "details", "confirm"];
function rt(s) {
  return s.text().then((t) => {
    if (!t)
      return {};
    try {
      return JSON.parse(t);
    } catch {
      return {};
    }
  });
}
class V {
  constructor(t) {
    this.root = t, this.dataset = Q(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(nt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((n) => n.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((n, r) => this.getStepOrderIndex(n) - this.getStepOrderIndex(r)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((n, r) => this.getStepOrderIndex(n) - this.getStepOrderIndex(r)).forEach((n) => {
      this.progress.appendChild(n);
    }), this.submitButton = this.form ? this.form.querySelector("[data-fp-resv-submit]") : null, this.submitLabel = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-label]") || this.submitButton : null, this.submitSpinner = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-spinner]") : null, this.submitHint = this.form ? this.form.querySelector("[data-fp-resv-submit-hint]") : null, this.successAlert = this.form ? this.form.querySelector("[data-fp-resv-success]") : null, this.errorAlert = this.form ? this.form.querySelector("[data-fp-resv-error]") : null, this.errorMessage = this.form ? this.form.querySelector("[data-fp-resv-error-message]") : null, this.errorRetry = this.form ? this.form.querySelector("[data-fp-resv-error-retry]") : null, this.mealButtons = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-meal]")), this.mealNotice = t.querySelector("[data-fp-resv-meal-notice]"), this.hiddenMeal = this.form ? this.form.querySelector('input[name="fp_resv_meal"]') : null, this.hiddenPrice = this.form ? this.form.querySelector('input[name="fp_resv_price_per_person"]') : null, this.hiddenSlot = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, this.summaryTargets = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-summary]")), this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field="phone"]') : null, this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name="fp_resv_phone_e164"]') : null, this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name="fp_resv_phone_cc"]') : null, this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name="fp_resv_phone_local"]') : null, this.availabilityRoot = this.form ? this.form.querySelector("[data-fp-resv-slots]") : null, this.state = {
      started: !1,
      formValidEmitted: !1,
      sectionStates: {},
      unlocked: {},
      initialHint: this.submitHint ? this.submitHint.textContent : "",
      hintOverride: "",
      ctaEnabled: !1,
      sending: !1,
      pendingAvailability: !1,
      lastAvailabilityParams: null
    }, this.copy = {
      ctaDisabled: this.messages.cta_complete_fields || "Complete required fields",
      ctaEnabled: this.messages.cta_book_now || this.strings.actions && this.strings.actions.submit || "Book now",
      ctaSending: this.messages.cta_sending || "Sending…",
      updatingSlots: this.messages.msg_updating_slots || "Updating availability…",
      slotsUpdated: this.messages.msg_slots_updated || "Availability updated.",
      slotsEmpty: this.messages.slots_empty || "",
      selectMeal: this.messages.msg_select_meal || "Select a meal to view available times.",
      slotsError: this.messages.msg_slots_error || "We could not update available times. Please try again.",
      invalidPhone: this.messages.msg_invalid_phone || "Enter a valid phone number (minimum 6 digits).",
      invalidEmail: this.messages.msg_invalid_email || "Enter a valid email address.",
      submitError: this.messages.msg_submit_error || "We could not complete your reservation. Please try again.",
      submitSuccess: this.messages.msg_submit_success || "Reservation sent successfully."
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializeMeals(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateSummary(), N(() => {
      this.loadStripeIfNeeded(), this.loadGoogleCalendarIfNeeded();
    }));
  }
  bind() {
    this.form.addEventListener("input", this.handleFormInput.bind(this), !0), this.form.addEventListener("change", this.handleFormInput.bind(this), !0), this.form.addEventListener("focusin", this.handleFirstInteraction.bind(this)), this.form.addEventListener("blur", this.handleFieldBlur.bind(this), !0), this.form.addEventListener("keydown", this.handleKeydown.bind(this), !0), this.form.addEventListener("submit", this.handleSubmit.bind(this)), this.root.addEventListener("click", this.handleDelegatedTrackingEvent), this.errorRetry && this.errorRetry.addEventListener("click", this.handleRetrySubmit.bind(this)), document.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("focus", this.handleWindowFocus);
  }
  getStepOrderIndex(t) {
    const e = t && t.getAttribute ? t.getAttribute("data-step") || "" : String(t || ""), i = typeof e == "string" ? e : "", n = this.stepOrder.indexOf(i);
    return n === -1 ? this.stepOrder.length + 1 : n;
  }
  initializeSections() {
    const t = this;
    this.sections.forEach(function(e, i) {
      const n = e.getAttribute("data-step") || String(i);
      t.state.sectionStates[n] = i === 0 ? "active" : "locked", i === 0 && t.dispatchSectionUnlocked(n), t.updateSectionAttributes(e, t.state.sectionStates[n]);
    }), this.updateProgressIndicators();
  }
  initializeMeals() {
    const t = this;
    this.mealButtons.length !== 0 && this.mealButtons.forEach(function(e) {
      e.addEventListener("click", function(i) {
        i.preventDefault(), t.handleFirstInteraction(), t.handleMealSelection(e);
      }), e.hasAttribute("data-active") && t.hiddenMeal && t.applyMealSelection(e);
    });
  }
  initializeAvailability() {
    if (!this.availabilityRoot)
      return;
    const t = () => {
      if (!this.availabilityController) {
        this.state.pendingAvailability = !0;
        return;
      }
      this.scheduleAvailabilityUpdate();
    };
    N(() => {
      $().then((e) => {
        !e || typeof e.createAvailabilityController != "function" || !this.availabilityRoot || (this.availabilityController = e.createAvailabilityController({
          root: this.availabilityRoot,
          endpoint: this.getAvailabilityEndpoint(),
          strings: this.copy,
          getParams: () => this.collectAvailabilityParams(),
          onSlotSelected: (i) => this.handleSlotSelected(i),
          onLatency: (i) => this.handleAvailabilityLatency(i),
          onRetry: (i) => this.handleAvailabilityRetry(i)
        }), this.state.pendingAvailability && (this.state.pendingAvailability = !1, this.scheduleAvailabilityUpdate()));
      }).catch(() => {
      });
    }), t();
  }
  handleFormInput(t) {
    const e = t.target;
    if (!e)
      return;
    this.handleFirstInteraction(), e === this.phoneField && Y(this.phoneField, this.getPhoneCountryCode()), this.updateSummary();
    const i = Z(e);
    if (!i) {
      this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
      return;
    }
    this.ensureSectionActive(i), this.isSectionValid(i) ? this.completeSection(i, !0) : this.updateSectionAttributes(i, "active");
    const n = e.getAttribute("data-fp-resv-field") || "";
    (n === "date" || n === "party" || n === "slots" || n === "time") && this.scheduleAvailabilityUpdate(), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
  }
  handleFieldBlur(t) {
    const e = t.target;
    if (!e || !(e instanceof HTMLElement))
      return;
    const i = e.getAttribute("data-fp-resv-field");
    i && (i === "phone" && this.phoneField && this.validatePhoneField(), i === "email" && e instanceof HTMLInputElement && this.validateEmailField(e));
  }
  handleKeydown(t) {
    if (t.key !== "Enter")
      return;
    const e = t.target;
    !e || !(e instanceof HTMLElement) || e.tagName === "TEXTAREA" || e instanceof HTMLButtonElement && e.type === "submit" || (e instanceof HTMLInputElement && e.type || "") === "submit" || t.preventDefault();
  }
  handleRetrySubmit(t) {
    t.preventDefault(), this.clearError(), this.focusFirstInvalid(), this.updateSubmitState();
  }
  handleMealSelection(t) {
    this.mealButtons.forEach(function(i) {
      i.removeAttribute("data-active"), i.setAttribute("aria-pressed", "false");
    }), t.setAttribute("data-active", "true"), t.setAttribute("aria-pressed", "true"), this.applyMealSelection(t);
    const e = this.events.meal_selected || "meal_selected";
    h(e, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), this.scheduleAvailabilityUpdate();
  }
  applyMealSelection(t) {
    const e = t.getAttribute("data-fp-resv-meal") || "";
    this.hiddenMeal && (this.hiddenMeal.value = e);
    const i = it(t.getAttribute("data-meal-price"));
    this.hiddenPrice && (this.hiddenPrice.value = i !== null ? String(i) : "");
    const n = t.getAttribute("data-meal-notice");
    this.mealNotice && (n && n.trim() !== "" ? (this.mealNotice.textContent = n, this.mealNotice.hidden = !1) : (this.mealNotice.textContent = "", this.mealNotice.hidden = !0)), this.updateSubmitState();
  }
  ensureSectionActive(t) {
    const e = t.getAttribute("data-step") || "";
    this.state.sectionStates[e] === "locked" && (this.state.sectionStates[e] = "active", this.updateSectionAttributes(t, "active"), this.dispatchSectionUnlocked(e), this.scrollIntoView(t));
  }
  completeSection(t, e) {
    const i = t.getAttribute("data-step") || "";
    if (this.state.sectionStates[i] === "completed" || (this.state.sectionStates[i] = "completed", this.updateSectionAttributes(t, "completed"), this.updateProgressIndicators(), !e))
      return;
    const n = this.sections.indexOf(t);
    if (n === -1)
      return;
    const r = this.sections[n + 1];
    if (!r)
      return;
    const a = r.getAttribute("data-step") || String(n + 1);
    this.state.sectionStates[a] !== "completed" && (this.state.sectionStates[a] = "active", this.updateSectionAttributes(r, "active"), this.dispatchSectionUnlocked(a), this.scrollIntoView(r));
  }
  dispatchSectionUnlocked(t) {
    if (this.state.unlocked[t])
      return;
    this.state.unlocked[t] = !0;
    const e = this.events.section_unlocked || "section_unlocked";
    h(e, { section: t });
  }
  updateSectionAttributes(t, e) {
    const i = t.getAttribute("data-step") || "";
    this.state.sectionStates[i] = e, t.setAttribute("data-state", e), t.setAttribute("aria-hidden", e === "locked" ? "true" : "false"), t.setAttribute("aria-expanded", e === "active" ? "true" : "false"), this.updateProgressIndicators();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const n = e.length || 1;
    Array.prototype.forEach.call(e, function(a, c) {
      const p = a.getAttribute("data-step") || "", d = t.state.sectionStates[p] || "locked";
      a.setAttribute("data-state", d), a.setAttribute("data-progress-state", d === "completed" ? "done" : d), d === "active" ? (a.setAttribute("aria-current", "step"), i = Math.max(i, c + 0.5)) : a.removeAttribute("aria-current"), d === "completed" ? (a.setAttribute("data-completed", "true"), i = Math.max(i, c + 1)) : a.removeAttribute("data-completed");
    });
    const r = Math.min(100, Math.max(0, Math.round(i / n * 100)));
    this.progress.style.setProperty("--fp-progress-fill", r + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    let i = !0;
    return Array.prototype.forEach.call(e, function(n) {
      typeof n.checkValidity == "function" && !n.checkValidity() && (i = !1);
    }), i;
  }
  updateSubmitState() {
    if (!this.submitButton)
      return;
    const t = this.form.checkValidity();
    if (this.state.sending ? this.setSubmitButtonState(!1, "sending") : this.setSubmitButtonState(t, null), this.submitHint) {
      const e = this.state.hintOverride || (t ? this.state.initialHint : this.copy.ctaDisabled);
      this.submitHint.textContent = e;
    }
    if (t && !this.state.formValidEmitted) {
      const e = this.events.form_valid || "form_valid";
      h(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
    }
  }
  setSubmitButtonState(t, e) {
    if (!this.submitButton)
      return;
    const i = e === "sending" ? !1 : !!t, n = this.state.ctaEnabled;
    et(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), n !== i && e !== "sending" && h("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), n = this.form.querySelector('[data-fp-resv-field="first_name"]'), r = this.form.querySelector('[data-fp-resv-field="last_name"]'), a = this.form.querySelector('[data-fp-resv-field="email"]'), c = this.form.querySelector('[data-fp-resv-field="phone"]'), p = this.form.querySelector('[data-fp-resv-field="notes"]');
    let d = "";
    n && n.value && (d = n.value.trim()), r && r.value && (d = (d + " " + r.value.trim()).trim());
    let m = "";
    a && a.value && (m = a.value.trim()), c && c.value && (m = m !== "" ? m + " / " + c.value.trim() : c.value.trim()), this.summaryTargets.forEach(function(y) {
      switch (y.getAttribute("data-fp-resv-summary")) {
        case "date":
          y.textContent = t && t.value ? t.value : "";
          break;
        case "time":
          y.textContent = e && e.value ? e.value : "";
          break;
        case "party":
          y.textContent = i && i.value ? i.value : "";
          break;
        case "name":
          y.textContent = d;
          break;
        case "contact":
          y.textContent = m;
          break;
        case "notes":
          y.textContent = p && p.value ? p.value : "";
          break;
      }
    });
  }
  async handleSubmit(t) {
    if (t.preventDefault(), !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    h(e, {
      source: "form",
      form_id: this.form && this.form.id ? this.form.id : this.root.id || "",
      date: i.date,
      party: i.party,
      meal: i.meal
    }), this.preparePhonePayload(), this.state.sending = !0, this.updateSubmitState(), this.clearError();
    const n = this.serializeForm(), r = this.getReservationEndpoint(), a = performance.now();
    let c = 0;
    try {
      const p = await fetch(r, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-WP-Nonce": n.fp_resv_nonce || ""
        },
        body: JSON.stringify(n),
        credentials: "same-origin"
      });
      if (c = Math.round(performance.now() - a), h("ui_latency", { op: "submit", ms: c }), !p.ok) {
        const m = await rt(p), y = m && m.message || this.copy.submitError;
        throw Object.assign(new Error(y), {
          status: p.status,
          payload: m
        });
      }
      const d = await p.json();
      this.handleSubmitSuccess(d);
    } catch (p) {
      c || (c = Math.round(performance.now() - a), h("ui_latency", { op: "submit", ms: c })), this.handleSubmitError(p, c);
    } finally {
      this.state.sending = !1, this.updateSubmitState();
    }
    return !1;
  }
  handleSubmitSuccess(t) {
    this.clearError();
    const e = t && t.message || this.copy.submitSuccess;
    this.successAlert && (this.successAlert.textContent = e, this.successAlert.hidden = !1, typeof this.successAlert.focus == "function" && this.successAlert.focus()), t && Array.isArray(t.tracking) && t.tracking.forEach((i) => {
      i && i.event && h(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", n = t && t.message || this.copy.submitError;
    this.errorAlert && this.errorMessage && (this.errorMessage.textContent = n, this.errorAlert.hidden = !1), this.state.hintOverride = n, this.updateSubmitState();
    const r = this.events.submit_error || "submit_error";
    h(r, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    return t.forEach((i, n) => {
      typeof i == "string" && (e[n] = i);
    }), e;
  }
  preparePhonePayload() {
    if (!this.phoneField)
      return;
    const t = x(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = x(this.phoneField, this.getPhoneCountryCode());
    if (t.local === "") {
      this.phoneField.setCustomValidity(""), this.phoneField.removeAttribute("aria-invalid");
      return;
    }
    G(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), h("phone_validation_error", { field: "phone" }), h("ui_validation_error", { field: "phone" }));
  }
  validateEmailField(t) {
    if (t.value.trim() === "") {
      t.setCustomValidity(""), t.removeAttribute("aria-invalid");
      return;
    }
    t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), h("ui_validation_error", { field: "email" }));
  }
  focusFirstInvalid() {
    const t = this.form.querySelector("[data-fp-resv-field]:invalid, [required]:invalid");
    t && typeof t.focus == "function" && t.focus();
  }
  collectAvailabilityParams() {
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="party"]'), i = this.hiddenMeal ? this.hiddenMeal.value : "";
    return {
      date: t && t.value ? t.value : "",
      party: e && e.value ? e.value : "",
      meal: i
    };
  }
  scheduleAvailabilityUpdate() {
    if (!this.availabilityController) {
      this.state.pendingAvailability = !0;
      return;
    }
    const t = this.collectAvailabilityParams();
    this.state.lastAvailabilityParams = t, this.availabilityController && typeof this.availabilityController.schedule == "function" && this.availabilityController.schedule(t);
  }
  handleSlotSelected(t) {
    const e = this.form.querySelector('[data-fp-resv-field="time"]');
    e && (e.value = t && t.label ? t.label : "", t && t.start && e.setAttribute("data-slot-start", t.start)), this.hiddenSlot && (this.hiddenSlot.value = t && t.start ? t.start : ""), this.updateSummary(), this.updateSubmitState();
  }
  handleAvailabilityLatency(t) {
    h("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    h("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    h(t, { source: "form" }), this.state.started = !0;
  }
  handleDelegatedTrackingEvent(t) {
    const e = t.target instanceof HTMLElement ? t.target : null;
    if (!e)
      return;
    const i = B(e, "data-fp-resv-event");
    if (!i)
      return;
    const n = i.getAttribute("data-fp-resv-event");
    if (!n)
      return;
    let r = tt(i, "data-fp-resv-payload");
    if ((!r || typeof r != "object") && (r = {}), r.trigger || (r.trigger = t.type || "click"), !r.href && i instanceof HTMLAnchorElement && i.href && (r.href = i.href), !r.label) {
      const a = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      a && (r.label = a.trim());
    }
    h(n, r);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    h(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && h(this.events.purchase || "purchase", e.purchase);
  }
  scrollIntoView(t) {
    typeof t.scrollIntoView == "function" && t.scrollIntoView({ behavior: "smooth", block: "start" });
    const e = st(t);
    e && typeof e.focus == "function" && e.focus({ preventScroll: !0 });
  }
  isConsentField(t) {
    if (!t || !t.getAttribute)
      return !1;
    const e = t.getAttribute("data-fp-resv-field") || "";
    return e === "consent" || e === "marketing_consent" || e === "profiling_consent";
  }
  syncConsentState() {
    const t = window.fpResvTracking;
    if (!t || typeof t.updateConsent != "function")
      return;
    const e = {};
    let i = !1;
    const n = this.form.querySelector('[data-fp-resv-field="consent"]');
    n && "checked" in n && (e.analytics = n.checked ? "granted" : "denied", e.clarity = n.checked ? "granted" : "denied", i = !0);
    const r = this.form.querySelector('[data-fp-resv-field="marketing_consent"]');
    r && "checked" in r && (e.ads = r.checked ? "granted" : "denied", i = !0);
    const a = this.form.querySelector('[data-fp-resv-field="profiling_consent"]');
    a && "checked" in a && (e.personalization = a.checked ? "granted" : "denied", i = !0), i && t.updateConsent(e);
  }
  getPhoneCountryCode() {
    if (this.hiddenPhoneCc && this.hiddenPhoneCc.value)
      return C(this.hiddenPhoneCc.value) || "39";
    const t = this.config && this.config.defaults || {};
    return t.phone_country_code && C(t.phone_country_code) || "39";
  }
  getReservationEndpoint() {
    const t = this.config.endpoints || {};
    return O(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return O(t.availability, "/wp-json/fp-resv/v1/availability");
  }
  loadStripeIfNeeded() {
    const t = this.integrations && (this.integrations.stripe || this.integrations.payments_stripe);
    return !t || typeof t == "object" && t.enabled === !1 ? Promise.resolve(null) : (this.stripePromise || (this.stripePromise = import(
      /* webpackIgnore: true */
      "https://js.stripe.com/v3/"
    ).catch(() => null)), this.stripePromise);
  }
  loadGoogleCalendarIfNeeded() {
    const t = this.integrations && (this.integrations.googleCalendar || this.integrations.calendar_google);
    return !t || typeof t == "object" && t.enabled === !1 ? Promise.resolve(null) : (this.googlePromise || (this.googlePromise = import(
      /* webpackIgnore: true */
      "https://apis.google.com/js/api.js"
    ).catch(() => null)), this.googlePromise);
  }
}
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = V);
document.addEventListener("DOMContentLoaded", function() {
  const s = document.querySelectorAll("[data-fp-resv]");
  Array.prototype.forEach.call(s, function(t) {
    new V(t);
  });
});
document.addEventListener("fp-resv:tracking:push", function(s) {
  if (!s || !s.detail)
    return;
  const t = s.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  h(e, i && typeof i == "object" ? i : {});
});
const at = 400, ot = 6e4, lt = 3, T = 600;
function ct(s, t) {
  let e;
  try {
    e = new URL(s, window.location.origin);
  } catch {
    const n = window.location.origin.replace(/\/$/, ""), r = s.startsWith("/") ? n + s : n + "/" + s;
    e = new URL(r, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function L(s) {
  for (; s.firstChild; )
    s.removeChild(s.firstChild);
}
function dt(s) {
  const t = s.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), n = t.querySelector("[data-fp-resv-slots-empty]"), r = t.querySelector("[data-fp-resv-slots-boundary]"), a = r ? r.querySelector("[data-fp-resv-slots-retry]") : null, c = /* @__PURE__ */ new Map();
  let p = null, d = null, m = null;
  a && a.addEventListener("click", () => {
    d && P(d, 0);
  });
  function y(o, l) {
    const v = typeof l == "string" ? l : l ? "loading" : "idle", f = typeof o == "string" ? o : "";
    e && (e.textContent = f, e.setAttribute("data-state", v));
    const S = v === "loading";
    t.setAttribute("data-loading", S ? "true" : "false"), i && i.setAttribute("aria-busy", S ? "true" : "false");
  }
  function I() {
    if (!i)
      return;
    L(i);
    const o = s.skeletonCount || 4;
    for (let l = 0; l < o; l += 1) {
      const v = document.createElement("li"), f = document.createElement("span");
      f.className = "fp-skeleton", v.appendChild(f), i.appendChild(v);
    }
  }
  function q(o) {
    n && (n.hidden = !1);
    const l = !o || !o.meal ? s.strings && s.strings.selectMeal || "" : s.strings && s.strings.slotsEmpty || "";
    y(l, "idle"), i && L(i);
  }
  function z() {
    n && (n.hidden = !0);
  }
  function M() {
    r && (r.hidden = !0);
  }
  function j(o) {
    if (s.strings && s.strings.slotsError || s.strings && s.strings.submitError, r) {
      const l = r.querySelector("[data-fp-resv-slots-boundary-message]");
      l && (l.textContent = o), r.hidden = !1;
    }
    y(o, "error");
  }
  function U(o, l) {
    const v = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(v, (f) => {
      f.setAttribute("aria-pressed", f === l ? "true" : "false");
    }), m = o, typeof s.onSlotSelected == "function" && s.onSlotSelected(o);
  }
  function R(o, l) {
    if (M(), z(), !i)
      return;
    L(i);
    const v = o && Array.isArray(o.slots) ? o.slots : [];
    if (v.length === 0) {
      q(l);
      return;
    }
    v.forEach((f) => {
      const S = document.createElement("li"), g = document.createElement("button");
      g.type = "button", g.textContent = f.label || "", g.dataset.slot = f.start || "", g.dataset.slotStatus = f.status || "", g.setAttribute("aria-pressed", m && m.start === f.start ? "true" : "false"), g.addEventListener("click", () => U(f, g)), S.appendChild(g), i.appendChild(S);
    }), y(s.strings && s.strings.slotsUpdated || "", !1);
  }
  function P(o, l) {
    if (d = o, !o || !o.date || !o.party) {
      q(o);
      return;
    }
    const v = JSON.stringify([o.date, o.meal, o.party]), f = c.get(v);
    if (f && Date.now() - f.timestamp < ot && l === 0) {
      R(f.payload, o);
      return;
    }
    M(), I(), y(s.strings && s.strings.updatingSlots || "Updating availability…", "loading");
    const S = ct(s.endpoint, o), g = performance.now();
    fetch(S, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((u) => u.json().catch(() => ({})).then((E) => {
      if (!u.ok) {
        const b = new Error("availability_error");
        b.status = u.status, b.payload = E;
        const A = u.headers.get("Retry-After");
        if (A) {
          const w = Number.parseInt(A, 10);
          Number.isFinite(w) && (b.retryAfter = w);
        }
        throw b;
      }
      return E;
    })).then((u) => {
      const E = performance.now() - g;
      typeof s.onLatency == "function" && s.onLatency(E), c.set(v, { payload: u, timestamp: Date.now() }), R(u, o);
    }).catch((u) => {
      const E = performance.now() - g;
      typeof s.onLatency == "function" && s.onLatency(E);
      const b = u && u.payload && typeof u.payload == "object" ? u.payload.data || {} : {}, A = typeof u.status == "number" ? u.status : b && typeof b.status == "number" ? b.status : 0;
      let w = 0;
      if (u && typeof u.retryAfter == "number" && Number.isFinite(u.retryAfter))
        w = u.retryAfter;
      else if (b && typeof b.retry_after < "u") {
        const _ = Number.parseInt(b.retry_after, 10);
        Number.isFinite(_) && (w = _);
      }
      if (l >= lt - 1 ? !1 : A === 429 || A >= 500 && A < 600 ? !0 : A === 0) {
        const _ = l + 1;
        typeof s.onRetry == "function" && s.onRetry(_);
        const W = w > 0 ? Math.max(w * 1e3, T) : T * Math.pow(2, l);
        window.setTimeout(() => P(o, _), W);
        return;
      }
      const H = u && u.payload && (u.payload.message || u.payload.code) || b && b.message || s.strings && s.strings.slotsError || s.strings && s.strings.submitError || "We could not update available times. Please try again.";
      j(H);
    });
  }
  return {
    schedule(o) {
      p && window.clearTimeout(p);
      const l = o || (typeof s.getParams == "function" ? s.getParams() : null);
      if (!l || !l.date || !l.party) {
        d = l, q(l || {});
        return;
      }
      p = window.setTimeout(() => {
        P(l, 0);
      }, at);
    },
    revalidate() {
      if (!d)
        return;
      const o = JSON.stringify([d.date, d.meal, d.party]);
      c.delete(o), P(d, 0);
    },
    getSelection() {
      return m;
    }
  };
}
const ut = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: dt
}, Symbol.toStringTag, { value: "Module" }));
