const W = /\D+/g;
function D(s) {
  return s ? String(s).replace(W, "") : "";
}
function C(s) {
  const t = D(s);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function k(s) {
  return D(s);
}
function J(s, t) {
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
  let r = 0, n = 0;
  for (; r < t.length; ) {
    const a = t.length - r;
    let c = e[n % e.length];
    a <= 4 && (c = a), i.push(t.slice(r, r + c)), r += c, n += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function Y(s, t) {
  const e = s.value, { masked: i } = X(e), r = s.selectionStart;
  if (s.value = i, r !== null) {
    const n = i.length - e.length, a = Math.max(0, r + n);
    s.setSelectionRange(a, a);
  }
  s.setAttribute("data-phone-local", k(s.value)), s.setAttribute("data-phone-cc", C(t));
}
function R(s, t) {
  const e = k(s.value), i = C(t);
  return {
    e164: J(i, e),
    local: e,
    country: i
  };
}
let F = null;
const T = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (s) => window.requestIdleCallback(s) : (s) => window.setTimeout(() => s(Date.now()), 1);
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
function N(s, t) {
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
const rt = ["date", "party", "slots", "details", "confirm"];
function nt(s) {
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
    const e = Array.from(rt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((r) => r.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((r, n) => this.getStepOrderIndex(r) - this.getStepOrderIndex(n)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((r, n) => this.getStepOrderIndex(r) - this.getStepOrderIndex(n)).forEach((r) => {
      this.progress.appendChild(r);
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
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializeMeals(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateSummary(), T(() => {
      this.loadStripeIfNeeded(), this.loadGoogleCalendarIfNeeded();
    }));
  }
  bind() {
    this.form.addEventListener("input", this.handleFormInput.bind(this), !0), this.form.addEventListener("change", this.handleFormInput.bind(this), !0), this.form.addEventListener("focusin", this.handleFirstInteraction.bind(this)), this.form.addEventListener("blur", this.handleFieldBlur.bind(this), !0), this.form.addEventListener("keydown", this.handleKeydown.bind(this), !0), this.form.addEventListener("submit", this.handleSubmit.bind(this)), this.root.addEventListener("click", this.handleDelegatedTrackingEvent), this.progress && (this.progress.addEventListener("click", this.handleProgressClick.bind(this)), this.progress.addEventListener("keydown", this.handleProgressKeydown.bind(this))), this.errorRetry && this.errorRetry.addEventListener("click", this.handleRetrySubmit.bind(this)), document.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("focus", this.handleWindowFocus);
  }
  getStepOrderIndex(t) {
    const e = t && t.getAttribute ? t.getAttribute("data-step") || "" : String(t || ""), i = typeof e == "string" ? e : "", r = this.stepOrder.indexOf(i);
    return r === -1 ? this.stepOrder.length + 1 : r;
  }
  initializeSections() {
    const t = this;
    this.sections.forEach(function(e, i) {
      const r = e.getAttribute("data-step") || String(i);
      t.state.sectionStates[r] = i === 0 ? "active" : "locked", i === 0 && t.dispatchSectionUnlocked(r), t.updateSectionAttributes(e, t.state.sectionStates[r], { silent: !0 });
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
    T(() => {
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
    const r = e.getAttribute("data-fp-resv-field") || "";
    (r === "date" || r === "party" || r === "slots" || r === "time") && this.scheduleAvailabilityUpdate(), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
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
  handleProgressClick(t) {
    if (!this.progress)
      return;
    const e = t.target && typeof t.target.closest == "function" ? t.target.closest("[data-step]") : null;
    if (!e || !this.progress.contains(e))
      return;
    const i = e.getAttribute("data-step") || "";
    if (!i)
      return;
    const r = this.state.sectionStates[i];
    !r || r === "locked" || (t.preventDefault(), this.activateSectionByKey(i));
  }
  handleProgressKeydown(t) {
    if (!this.progress || t.key !== "Enter" && t.key !== " " && t.key !== "Spacebar" && t.key !== "Space")
      return;
    const e = t.target && typeof t.target.closest == "function" ? t.target.closest("[data-step]") : null;
    if (!e || !this.progress.contains(e))
      return;
    const i = e.getAttribute("data-step") || "";
    if (!i)
      return;
    const r = this.state.sectionStates[i];
    !r || r === "locked" || (t.preventDefault(), this.activateSectionByKey(i));
  }
  activateSectionByKey(t) {
    const e = this.sections.find(function(r) {
      return (r.getAttribute("data-step") || "") === t;
    });
    if (!e)
      return;
    let i = !1;
    this.sections.forEach((r) => {
      const n = r.getAttribute("data-step") || "";
      if (n === t)
        i = !0, this.updateSectionAttributes(r, "active", { silent: !0 }), this.dispatchSectionUnlocked(n);
      else if (i)
        this.updateSectionAttributes(r, "locked", { silent: !0 });
      else {
        const c = this.state.sectionStates[n] === "locked" ? "locked" : "completed";
        this.updateSectionAttributes(r, c, { silent: !0 });
      }
    }), this.updateProgressIndicators(), this.scrollIntoView(e), requestAnimationFrame(() => {
      const r = e.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
      r && typeof r.focus == "function" && r.focus({ preventScroll: !0 });
    }), this.updateSubmitState();
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
    const r = t.getAttribute("data-meal-notice");
    this.mealNotice && (r && r.trim() !== "" ? (this.mealNotice.textContent = r, this.mealNotice.hidden = !1) : (this.mealNotice.textContent = "", this.mealNotice.hidden = !0)), this.updateSubmitState();
  }
  ensureSectionActive(t) {
    const e = t.getAttribute("data-step") || "";
    this.state.sectionStates[e] === "locked" && (this.state.sectionStates[e] = "active", this.updateSectionAttributes(t, "active"), this.dispatchSectionUnlocked(e), this.scrollIntoView(t));
  }
  completeSection(t, e) {
    const i = t.getAttribute("data-step") || "";
    if (this.state.sectionStates[i] === "completed" || (this.state.sectionStates[i] = "completed", this.updateSectionAttributes(t, "completed"), this.updateProgressIndicators(), !e))
      return;
    const r = this.sections.indexOf(t);
    if (r === -1)
      return;
    const n = this.sections[r + 1];
    if (!n)
      return;
    const a = n.getAttribute("data-step") || String(r + 1);
    this.state.sectionStates[a] !== "completed" && (this.state.sectionStates[a] = "active", this.updateSectionAttributes(n, "active"), this.dispatchSectionUnlocked(a), this.scrollIntoView(n));
  }
  dispatchSectionUnlocked(t) {
    if (this.state.unlocked[t])
      return;
    this.state.unlocked[t] = !0;
    const e = this.events.section_unlocked || "section_unlocked";
    h(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const r = t.getAttribute("data-step") || "", n = i && i.silent === !0;
    this.state.sectionStates[r] = e, t.setAttribute("data-state", e);
    const a = e === "active";
    t.setAttribute("aria-hidden", a ? "false" : "true"), t.setAttribute("aria-expanded", a ? "true" : "false"), a ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert")) : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", "")), n || this.updateProgressIndicators();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const r = e.length || 1;
    Array.prototype.forEach.call(e, function(a, c) {
      const m = a.getAttribute("data-step") || "", d = t.state.sectionStates[m] || "locked";
      a.setAttribute("data-state", d), a.setAttribute("data-progress-state", d === "completed" ? "done" : d);
      const f = d === "locked";
      a.tabIndex = f ? -1 : 0, f ? a.setAttribute("aria-disabled", "true") : a.removeAttribute("aria-disabled"), d === "active" ? (a.setAttribute("aria-current", "step"), i = Math.max(i, c + 0.5)) : a.removeAttribute("aria-current"), d === "completed" ? (a.setAttribute("data-completed", "true"), i = Math.max(i, c + 1)) : a.removeAttribute("data-completed");
    });
    const n = Math.min(100, Math.max(0, Math.round(i / r * 100)));
    this.progress.style.setProperty("--fp-progress-fill", n + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    let i = !0;
    return Array.prototype.forEach.call(e, function(r) {
      typeof r.checkValidity == "function" && !r.checkValidity() && (i = !1);
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
    const i = e === "sending" ? !1 : !!t, r = this.state.ctaEnabled;
    et(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), r !== i && e !== "sending" && h("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), r = this.form.querySelector('[data-fp-resv-field="first_name"]'), n = this.form.querySelector('[data-fp-resv-field="last_name"]'), a = this.form.querySelector('[data-fp-resv-field="email"]'), c = this.form.querySelector('[data-fp-resv-field="phone"]'), m = this.form.querySelector('[data-fp-resv-field="notes"]');
    let d = "";
    r && r.value && (d = r.value.trim()), n && n.value && (d = (d + " " + n.value.trim()).trim());
    let f = "";
    a && a.value && (f = a.value.trim()), c && c.value && (f = f !== "" ? f + " / " + c.value.trim() : c.value.trim()), this.summaryTargets.forEach(function(y) {
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
          y.textContent = f;
          break;
        case "notes":
          y.textContent = m && m.value ? m.value : "";
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
    const r = this.serializeForm(), n = this.getReservationEndpoint(), a = performance.now();
    let c = 0;
    try {
      const m = await fetch(n, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-WP-Nonce": r.fp_resv_nonce || ""
        },
        body: JSON.stringify(r),
        credentials: "same-origin"
      });
      if (c = Math.round(performance.now() - a), h("ui_latency", { op: "submit", ms: c }), !m.ok) {
        const f = await nt(m), y = f && f.message || this.copy.submitError;
        throw Object.assign(new Error(y), {
          status: m.status,
          payload: f
        });
      }
      const d = await m.json();
      this.handleSubmitSuccess(d);
    } catch (m) {
      c || (c = Math.round(performance.now() - a), h("ui_latency", { op: "submit", ms: c })), this.handleSubmitError(m, c);
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
    const i = t && typeof t.status == "number" ? t.status : "unknown", r = t && t.message || this.copy.submitError;
    this.errorAlert && this.errorMessage && (this.errorMessage.textContent = r, this.errorAlert.hidden = !1), this.state.hintOverride = r, this.updateSubmitState();
    const n = this.events.submit_error || "submit_error";
    h(n, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    return t.forEach((i, r) => {
      typeof i == "string" && (e[r] = i);
    }), e;
  }
  preparePhonePayload() {
    if (!this.phoneField)
      return;
    const t = R(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = R(this.phoneField, this.getPhoneCountryCode());
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
    const r = i.getAttribute("data-fp-resv-event");
    if (!r)
      return;
    let n = tt(i, "data-fp-resv-payload");
    if ((!n || typeof n != "object") && (n = {}), n.trigger || (n.trigger = t.type || "click"), !n.href && i instanceof HTMLAnchorElement && i.href && (n.href = i.href), !n.label) {
      const a = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      a && (n.label = a.trim());
    }
    h(r, n);
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
    const r = this.form.querySelector('[data-fp-resv-field="consent"]');
    r && "checked" in r && (e.analytics = r.checked ? "granted" : "denied", e.clarity = r.checked ? "granted" : "denied", i = !0);
    const n = this.form.querySelector('[data-fp-resv-field="marketing_consent"]');
    n && "checked" in n && (e.ads = n.checked ? "granted" : "denied", i = !0);
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
    return N(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return N(t.availability, "/wp-json/fp-resv/v1/availability");
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
const at = 400, ot = 6e4, lt = 3, O = 600;
function ct(s, t) {
  let e;
  try {
    e = new URL(s, window.location.origin);
  } catch {
    const r = window.location.origin.replace(/\/$/, ""), n = s.startsWith("/") ? r + s : r + "/" + s;
    e = new URL(n, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function L(s) {
  for (; s.firstChild; )
    s.removeChild(s.firstChild);
}
function dt(s) {
  const t = s.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), r = t.querySelector("[data-fp-resv-slots-empty]"), n = t.querySelector("[data-fp-resv-slots-boundary]"), a = n ? n.querySelector("[data-fp-resv-slots-retry]") : null, c = /* @__PURE__ */ new Map();
  let m = null, d = null, f = null;
  a && a.addEventListener("click", () => {
    d && P(d, 0);
  });
  function y(o, l) {
    const g = typeof l == "string" ? l : l ? "loading" : "idle", p = typeof o == "string" ? o : "";
    e && (e.textContent = p, e.setAttribute("data-state", g));
    const S = g === "loading";
    t.setAttribute("data-loading", S ? "true" : "false"), i && i.setAttribute("aria-busy", S ? "true" : "false");
  }
  function I() {
    if (!i)
      return;
    L(i);
    const o = s.skeletonCount || 4;
    for (let l = 0; l < o; l += 1) {
      const g = document.createElement("li"), p = document.createElement("span");
      p.className = "fp-skeleton", g.appendChild(p), i.appendChild(g);
    }
  }
  function q(o) {
    r && (r.hidden = !1);
    const l = !o || !o.meal ? s.strings && s.strings.selectMeal || "" : s.strings && s.strings.slotsEmpty || "";
    y(l, "idle"), i && L(i);
  }
  function z() {
    r && (r.hidden = !0);
  }
  function M() {
    n && (n.hidden = !0);
  }
  function U(o) {
    if (s.strings && s.strings.slotsError || s.strings && s.strings.submitError, n) {
      const l = n.querySelector("[data-fp-resv-slots-boundary-message]");
      l && (l.textContent = o), n.hidden = !1;
    }
    y(o, "error");
  }
  function j(o, l) {
    const g = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(g, (p) => {
      p.setAttribute("aria-pressed", p === l ? "true" : "false");
    }), f = o, typeof s.onSlotSelected == "function" && s.onSlotSelected(o);
  }
  function x(o, l) {
    if (M(), z(), !i)
      return;
    L(i);
    const g = o && Array.isArray(o.slots) ? o.slots : [];
    if (g.length === 0) {
      q(l);
      return;
    }
    g.forEach((p) => {
      const S = document.createElement("li"), v = document.createElement("button");
      v.type = "button", v.textContent = p.label || "", v.dataset.slot = p.start || "", v.dataset.slotStatus = p.status || "", v.setAttribute("aria-pressed", f && f.start === p.start ? "true" : "false"), v.addEventListener("click", () => j(p, v)), S.appendChild(v), i.appendChild(S);
    }), y(s.strings && s.strings.slotsUpdated || "", !1);
  }
  function P(o, l) {
    if (d = o, !o || !o.date || !o.party) {
      q(o);
      return;
    }
    const g = JSON.stringify([o.date, o.meal, o.party]), p = c.get(g);
    if (p && Date.now() - p.timestamp < ot && l === 0) {
      x(p.payload, o);
      return;
    }
    M(), I(), y(s.strings && s.strings.updatingSlots || "Updating availability…", "loading");
    const S = ct(s.endpoint, o), v = performance.now();
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
      const E = performance.now() - v;
      typeof s.onLatency == "function" && s.onLatency(E), c.set(g, { payload: u, timestamp: Date.now() }), x(u, o);
    }).catch((u) => {
      const E = performance.now() - v;
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
        const K = w > 0 ? Math.max(w * 1e3, O) : O * Math.pow(2, l);
        window.setTimeout(() => P(o, _), K);
        return;
      }
      const H = u && u.payload && (u.payload.message || u.payload.code) || b && b.message || s.strings && s.strings.slotsError || s.strings && s.strings.submitError || "We could not update available times. Please try again.";
      U(H);
    });
  }
  return {
    schedule(o) {
      m && window.clearTimeout(m);
      const l = o || (typeof s.getParams == "function" ? s.getParams() : null);
      if (!l || !l.date || !l.party) {
        d = l, q(l || {});
        return;
      }
      m = window.setTimeout(() => {
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
      return f;
    }
  };
}
const ut = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: dt
}, Symbol.toStringTag, { value: "Module" }));
