const J = /\D+/g;
function B(r) {
  return r ? String(r).replace(J, "") : "";
}
function b(r) {
  const t = B(r);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function k(r) {
  return B(r);
}
function G(r, t) {
  const e = b(r), i = k(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function X(r) {
  const t = k(r);
  return t.length >= 6 && t.length <= 15;
}
function Y(r) {
  const t = k(r);
  if (t === "")
    return { masked: "", digits: "" };
  const e = [3, 4], i = [];
  let s = 0, n = 0;
  for (; s < t.length; ) {
    const a = t.length - s;
    let l = e[n % e.length];
    a <= 4 && (l = a), i.push(t.slice(s, s + l)), s += l, n += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function q(r, t) {
  const e = r.value, { masked: i } = Y(e), s = r.selectionStart;
  if (r.value = i, s !== null) {
    const n = i.length - e.length, a = Math.max(0, s + n);
    r.setSelectionRange(a, a);
  }
  r.setAttribute("data-phone-local", k(r.value)), r.setAttribute("data-phone-cc", b(t));
}
function L(r, t) {
  const e = k(r.value), i = b(t);
  return {
    e164: G(i, e),
    local: e,
    country: i
  };
}
let I = null;
const D = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (r) => window.requestIdleCallback(r) : (r) => window.setTimeout(() => r(Date.now()), 1);
function $() {
  return I || (I = Promise.resolve().then(() => ht)), I;
}
function Q(r) {
  const t = r.getAttribute("data-fp-resv");
  if (!t)
    return {};
  try {
    return JSON.parse(t);
  } catch (e) {
    window.console && window.console.warn && console.warn("[fp-resv] Impossibile analizzare il dataset del widget", e);
  }
  return {};
}
function u(r, t) {
  if (!r)
    return null;
  const e = Object.assign({ event: r }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
function V(r, t) {
  if (!r)
    return null;
  if (typeof r.closest == "function")
    return r.closest("[" + t + "]");
  let e = r;
  for (; e; ) {
    if (e.hasAttribute(t))
      return e;
    e = e.parentElement;
  }
  return null;
}
function Z(r) {
  return V(r, "data-fp-resv-section");
}
function tt(r, t) {
  if (!r)
    return {};
  const e = r.getAttribute(t);
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
function et(r, t) {
  r && (t ? (r.setAttribute("aria-disabled", "true"), r.setAttribute("disabled", "disabled")) : (r.removeAttribute("disabled"), r.setAttribute("aria-disabled", "false")));
}
function it(r) {
  if (r == null)
    return null;
  if (typeof r == "number")
    return Number.isFinite(r) ? r : null;
  const t = String(r).replace(",", "."), e = parseFloat(t);
  return Number.isNaN(e) ? null : e;
}
function N(r, t) {
  if (r && typeof r == "string")
    try {
      return new URL(r, window.location.origin).toString();
    } catch {
      return r;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
function st(r) {
  return r ? r.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]') : null;
}
const rt = ["date", "party", "slots", "details", "confirm"];
function nt(r) {
  return r.text().then((t) => {
    if (!t)
      return {};
    try {
      return JSON.parse(t);
    } catch {
      return {};
    }
  });
}
class z {
  constructor(t) {
    this.root = t, this.dataset = Q(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(rt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((s) => s.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((s, n) => this.getStepOrderIndex(s) - this.getStepOrderIndex(n)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((s, n) => this.getStepOrderIndex(s) - this.getStepOrderIndex(n)).forEach((s) => {
      this.progress.appendChild(s);
    }), this.submitButton = this.form ? this.form.querySelector("[data-fp-resv-submit]") : null, this.submitLabel = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-label]") || this.submitButton : null, this.submitSpinner = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-spinner]") : null, this.submitHint = this.form ? this.form.querySelector("[data-fp-resv-submit-hint]") : null, this.successAlert = this.form ? this.form.querySelector("[data-fp-resv-success]") : null, this.errorAlert = this.form ? this.form.querySelector("[data-fp-resv-error]") : null, this.errorMessage = this.form ? this.form.querySelector("[data-fp-resv-error-message]") : null, this.errorRetry = this.form ? this.form.querySelector("[data-fp-resv-error-retry]") : null, this.mealButtons = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-meal]")), this.mealNotice = t.querySelector("[data-fp-resv-meal-notice]"), this.hiddenMeal = this.form ? this.form.querySelector('input[name="fp_resv_meal"]') : null, this.hiddenPrice = this.form ? this.form.querySelector('input[name="fp_resv_price_per_person"]') : null, this.hiddenSlot = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null, this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field="party"]') : null, this.summaryTargets = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-summary]")), this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field="phone"]') : null, this.phonePrefixField = this.form ? this.form.querySelector('[data-fp-resv-field="phone_prefix"]') : null, this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name="fp_resv_phone_e164"]') : null, this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name="fp_resv_phone_cc"]') : null, this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name="fp_resv_phone_local"]') : null, this.availabilityRoot = this.form ? this.form.querySelector("[data-fp-resv-slots]") : null, this.state = {
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
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateSummary(), D(() => {
      this.loadStripeIfNeeded(), this.loadGoogleCalendarIfNeeded();
    }));
  }
  bind() {
    const t = this.handleFormInput.bind(this);
    this.form.addEventListener("input", t, !0), this.form.addEventListener("change", t, !0), this.form.addEventListener("focusin", this.handleFirstInteraction.bind(this)), this.form.addEventListener("blur", this.handleFieldBlur.bind(this), !0), this.form.addEventListener("keydown", this.handleKeydown.bind(this), !0), this.form.addEventListener("click", this.handleNavClick.bind(this)), this.form.addEventListener("submit", this.handleSubmit.bind(this)), this.root.addEventListener("click", this.handleDelegatedTrackingEvent), this.progress && (this.progress.addEventListener("click", this.handleProgressClick.bind(this)), this.progress.addEventListener("keydown", this.handleProgressKeydown.bind(this))), this.errorRetry && this.errorRetry.addEventListener("click", this.handleRetrySubmit.bind(this)), document.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("focus", this.handleWindowFocus);
  }
  getStepOrderIndex(t) {
    const e = t && t.getAttribute ? t.getAttribute("data-step") || "" : String(t || ""), i = typeof e == "string" ? e : "", s = this.stepOrder.indexOf(i);
    return s === -1 ? this.stepOrder.length + 1 : s;
  }
  initializeSections() {
    const t = this;
    this.sections.forEach(function(e, i) {
      const s = e.getAttribute("data-step") || String(i);
      t.state.sectionStates[s] = i === 0 ? "active" : "locked", i === 0 && t.dispatchSectionUnlocked(s), t.updateSectionAttributes(e, t.state.sectionStates[s], { silent: !0 });
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
  initializePhoneField() {
    if (this.phonePrefixField) {
      this.updatePhoneCountryFromPrefix();
      return;
    }
    this.phoneField && q(this.phoneField, this.getPhoneCountryCode());
  }
  updatePhoneCountryFromPrefix() {
    if (!this.phonePrefixField)
      return;
    const t = b(this.phonePrefixField.value);
    let e = t;
    if (e === "" && this.phoneCountryCode) {
      const i = b(this.phoneCountryCode);
      i && (e = i);
    }
    if (e === "" && this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const i = b(this.hiddenPhoneCc.value);
      i && (e = i);
    }
    if (e === "") {
      const i = this.config && this.config.defaults || {};
      if (i.phone_country_code) {
        const s = b(i.phone_country_code);
        s && (e = s);
      }
    }
    e === "" && (e = "39"), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = e), t !== "" && (this.phoneCountryCode = t), this.phoneField && q(this.phoneField, e);
  }
  initializeDateField() {
    if (!this.dateField)
      return;
    const t = () => {
      if (typeof this.dateField.showPicker == "function")
        try {
          this.dateField.showPicker();
        } catch {
        }
    };
    this.dateField.addEventListener("focus", t), this.dateField.addEventListener("click", t);
  }
  initializeAvailability() {
    if (!this.availabilityRoot)
      return;
    this.availabilityRoot.addEventListener("click", (e) => {
      if (this.availabilityController)
        return;
      const i = e.target instanceof HTMLElement ? e.target.closest("button[data-slot]") : null;
      if (!i)
        return;
      e.preventDefault();
      const s = {
        start: i.getAttribute("data-slot") || "",
        label: i.textContent || "",
        status: i.getAttribute("data-slot-status") || ""
      }, n = this.availabilityRoot.querySelectorAll("button[data-slot]");
      Array.prototype.forEach.call(n, (a) => {
        a.setAttribute("aria-pressed", a === i ? "true" : "false");
      }), this.handleSlotSelected(s);
    });
    const t = () => {
      if (!this.availabilityController) {
        this.state.pendingAvailability = !0;
        return;
      }
      this.scheduleAvailabilityUpdate();
    };
    D(() => {
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
    this.handleFirstInteraction(), e === this.phoneField ? q(this.phoneField, this.getPhoneCountryCode()) : e === this.phonePrefixField && this.updatePhoneCountryFromPrefix(), this.updateSummary();
    const i = Z(e);
    if (!i) {
      this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
      return;
    }
    this.ensureSectionActive(i), this.isSectionValid(i) ? this.completeSection(i, !0) : this.updateSectionAttributes(i, "active");
    const s = e.getAttribute("data-fp-resv-field") || "";
    (s === "date" || s === "party" || s === "slots" || s === "time") && ((s === "date" || s === "party") && this.clearSlotSelection({ schedule: !1 }), this.scheduleAvailabilityUpdate()), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
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
  handleNavClick(t) {
    const e = t.target instanceof HTMLElement ? t.target.closest("[data-fp-resv-nav]") : null;
    if (!e)
      return;
    const i = e.closest("[data-fp-resv-section]");
    if (!i)
      return;
    t.preventDefault(), this.handleFirstInteraction();
    const s = e.getAttribute("data-fp-resv-nav");
    s === "prev" ? this.navigateToPrevious(i) : s === "next" && this.navigateToNext(i);
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
    const s = this.state.sectionStates[i];
    !s || s === "locked" || (t.preventDefault(), this.activateSectionByKey(i));
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
    const s = this.state.sectionStates[i];
    !s || s === "locked" || (t.preventDefault(), this.activateSectionByKey(i));
  }
  activateSectionByKey(t) {
    const e = this.sections.find(function(s) {
      return (s.getAttribute("data-step") || "") === t;
    });
    if (!e)
      return;
    let i = !1;
    this.sections.forEach((s) => {
      const n = s.getAttribute("data-step") || "";
      if (n === t)
        i = !0, this.updateSectionAttributes(s, "active", { silent: !0 }), this.dispatchSectionUnlocked(n);
      else if (i)
        this.updateSectionAttributes(s, "locked", { silent: !0 });
      else {
        const l = this.state.sectionStates[n] === "locked" ? "locked" : "completed";
        this.updateSectionAttributes(s, l, { silent: !0 });
      }
    }), this.updateProgressIndicators(), this.scrollIntoView(e), requestAnimationFrame(() => {
      const s = e.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
      s && typeof s.focus == "function" && s.focus({ preventScroll: !0 });
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
    u(e, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), this.scheduleAvailabilityUpdate();
  }
  applyMealSelection(t) {
    const e = t.getAttribute("data-fp-resv-meal") || "";
    this.hiddenMeal && (this.hiddenMeal.value = e);
    const i = it(t.getAttribute("data-meal-price"));
    this.hiddenPrice && (this.hiddenPrice.value = i !== null ? String(i) : ""), this.clearSlotSelection({ schedule: !1 });
    const s = t.getAttribute("data-meal-notice");
    this.mealNotice && (s && s.trim() !== "" ? (this.mealNotice.textContent = s, this.mealNotice.hidden = !1) : (this.mealNotice.textContent = "", this.mealNotice.hidden = !0)), this.updateSubmitState();
  }
  clearSlotSelection(t = {}) {
    this.hiddenSlot && (this.hiddenSlot.value = "");
    const e = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
    if (e && (e.value = "", e.removeAttribute("data-slot-start")), this.availabilityRoot) {
      const s = this.availabilityRoot.querySelectorAll('button[data-slot][aria-pressed="true"]');
      Array.prototype.forEach.call(s, (n) => {
        n.setAttribute("aria-pressed", "false");
      });
    }
    const i = this.sections.find((s) => (s.getAttribute("data-step") || "") === "slots");
    if (i) {
      const s = i.getAttribute("data-step") || "", n = this.state.sectionStates[s] || "locked";
      this.updateSectionAttributes(i, "locked", { silent: !0 });
      const a = this.sections.indexOf(i);
      if (a !== -1)
        for (let l = a + 1; l < this.sections.length; l += 1) {
          const f = this.sections[l];
          this.updateSectionAttributes(f, "locked", { silent: !0 });
        }
      this.updateProgressIndicators(), (t.forceRewind && s || n === "completed" || n === "active") && this.activateSectionByKey(s);
    }
    t.schedule !== !1 && this.scheduleAvailabilityUpdate(), this.updateSummary(), this.updateSubmitState();
  }
  ensureSectionActive(t) {
    const e = t.getAttribute("data-step") || "";
    this.state.sectionStates[e] === "locked" && (this.state.sectionStates[e] = "active", this.updateSectionAttributes(t, "active"), this.dispatchSectionUnlocked(e), this.scrollIntoView(t));
  }
  completeSection(t, e) {
    const i = t.getAttribute("data-step") || "";
    if (this.state.sectionStates[i] === "completed" || (this.state.sectionStates[i] = "completed", this.updateSectionAttributes(t, "completed"), this.updateProgressIndicators(), !e))
      return;
    const s = this.sections.indexOf(t);
    if (s === -1)
      return;
    const n = this.sections[s + 1];
    if (!n)
      return;
    const a = n.getAttribute("data-step") || String(s + 1);
    this.state.sectionStates[a] !== "completed" && (this.state.sectionStates[a] = "active", this.updateSectionAttributes(n, "active"), this.dispatchSectionUnlocked(a), this.scrollIntoView(n));
  }
  navigateToPrevious(t) {
    const e = this.sections.indexOf(t);
    if (e <= 0)
      return;
    const i = this.sections[e - 1];
    if (!i)
      return;
    const s = i.getAttribute("data-step") || "";
    s && this.activateSectionByKey(s);
  }
  navigateToNext(t) {
    if (!this.isSectionValid(t)) {
      const e = this.findFirstInvalid(t);
      e && (typeof e.reportValidity == "function" && e.reportValidity(), typeof e.focus == "function" && e.focus({ preventScroll: !1 }));
      return;
    }
    this.completeSection(t, !0);
  }
  dispatchSectionUnlocked(t) {
    if (this.state.unlocked[t])
      return;
    this.state.unlocked[t] = !0;
    const e = this.events.section_unlocked || "section_unlocked";
    u(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const s = t.getAttribute("data-step") || "", n = i && i.silent === !0;
    this.state.sectionStates[s] = e, t.setAttribute("data-state", e), e === "completed" ? t.setAttribute("data-complete-hidden", "true") : t.removeAttribute("data-complete-hidden");
    const a = e === "active";
    t.setAttribute("aria-hidden", a ? "false" : "true"), t.setAttribute("aria-expanded", a ? "true" : "false"), a ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert")) : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", "")), n || this.updateProgressIndicators();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const s = e.length || 1;
    Array.prototype.forEach.call(e, function(a, l) {
      const f = a.getAttribute("data-step") || "", c = t.state.sectionStates[f] || "locked";
      a.setAttribute("data-state", c), a.setAttribute("data-progress-state", c === "completed" ? "done" : c);
      const p = c === "locked";
      a.tabIndex = p ? -1 : 0, p ? a.setAttribute("aria-disabled", "true") : a.removeAttribute("aria-disabled"), c === "active" ? (a.setAttribute("aria-current", "step"), i = Math.max(i, l + 0.5)) : a.removeAttribute("aria-current"), c === "completed" ? (a.setAttribute("data-completed", "true"), i = Math.max(i, l + 1)) : a.removeAttribute("data-completed");
    });
    const n = Math.min(100, Math.max(0, Math.round(i / s * 100)));
    this.progress.style.setProperty("--fp-progress-fill", n + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    let i = !0;
    return Array.prototype.forEach.call(e, function(s) {
      typeof s.checkValidity == "function" && !s.checkValidity() && (i = !1);
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
      u(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
    }
  }
  setSubmitButtonState(t, e) {
    if (!this.submitButton)
      return;
    const i = e === "sending" ? !1 : !!t, s = this.state.ctaEnabled;
    et(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), s !== i && e !== "sending" && u("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), s = this.form.querySelector('[data-fp-resv-field="first_name"]'), n = this.form.querySelector('[data-fp-resv-field="last_name"]'), a = this.form.querySelector('[data-fp-resv-field="email"]'), l = this.form.querySelector('[data-fp-resv-field="phone"]'), f = this.form.querySelector('[data-fp-resv-field="notes"]');
    let c = "";
    s && s.value && (c = s.value.trim()), n && n.value && (c = (c + " " + n.value.trim()).trim());
    let p = "";
    if (a && a.value && (p = a.value.trim()), l && l.value) {
      const m = this.getPhoneCountryCode(), E = (m ? "+" + m + " " : "") + l.value.trim();
      p = p !== "" ? p + " / " + E : E;
    }
    this.summaryTargets.forEach(function(m) {
      switch (m.getAttribute("data-fp-resv-summary")) {
        case "date":
          m.textContent = t && t.value ? t.value : "";
          break;
        case "time":
          m.textContent = e && e.value ? e.value : "";
          break;
        case "party":
          m.textContent = i && i.value ? i.value : "";
          break;
        case "name":
          m.textContent = c;
          break;
        case "contact":
          m.textContent = p;
          break;
        case "notes":
          m.textContent = f && f.value ? f.value : "";
          break;
      }
    });
  }
  async handleSubmit(t) {
    if (t.preventDefault(), !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    u(e, {
      source: "form",
      form_id: this.form && this.form.id ? this.form.id : this.root.id || "",
      date: i.date,
      party: i.party,
      meal: i.meal
    }), this.preparePhonePayload(), this.state.sending = !0, this.updateSubmitState(), this.clearError();
    const s = this.serializeForm(), n = this.getReservationEndpoint(), a = performance.now();
    let l = 0;
    try {
      const f = await fetch(n, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-WP-Nonce": s.fp_resv_nonce || ""
        },
        body: JSON.stringify(s),
        credentials: "same-origin"
      });
      if (l = Math.round(performance.now() - a), u("ui_latency", { op: "submit", ms: l }), !f.ok) {
        const p = await nt(f), m = p && p.message || this.copy.submitError;
        throw Object.assign(new Error(m), {
          status: f.status,
          payload: p
        });
      }
      const c = await f.json();
      this.handleSubmitSuccess(c);
    } catch (f) {
      l || (l = Math.round(performance.now() - a), u("ui_latency", { op: "submit", ms: l })), this.handleSubmitError(f, l);
    } finally {
      this.state.sending = !1, this.updateSubmitState();
    }
    return !1;
  }
  handleSubmitSuccess(t) {
    this.clearError();
    const e = t && t.message || this.copy.submitSuccess;
    this.successAlert && (this.successAlert.textContent = e, this.successAlert.hidden = !1, typeof this.successAlert.focus == "function" && this.successAlert.focus()), t && Array.isArray(t.tracking) && t.tracking.forEach((i) => {
      i && i.event && u(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", s = t && t.message || this.copy.submitError;
    this.errorAlert && this.errorMessage && (this.errorMessage.textContent = s, this.errorAlert.hidden = !1), this.state.hintOverride = s, this.updateSubmitState();
    const n = this.events.submit_error || "submit_error";
    u(n, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, s) => {
      typeof i == "string" && (e[s] = i);
    }), this.phoneField) {
      const i = L(this.phoneField, this.getPhoneCountryCode());
      i.e164 && (e.fp_resv_phone = i.e164), i.country && (e.fp_resv_phone_cc = i.country), i.local && (e.fp_resv_phone_local = i.local);
    }
    if (this.phonePrefixField && this.phonePrefixField.value && !e.fp_resv_phone_cc) {
      const i = b(this.phonePrefixField.value);
      i && (e.fp_resv_phone_cc = i);
    }
    return e;
  }
  preparePhonePayload() {
    if (!this.phoneField)
      return;
    const t = L(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = L(this.phoneField, this.getPhoneCountryCode());
    if (t.local === "") {
      this.phoneField.setCustomValidity(""), this.phoneField.removeAttribute("aria-invalid");
      return;
    }
    X(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), u("phone_validation_error", { field: "phone" }), u("ui_validation_error", { field: "phone" }));
  }
  validateEmailField(t) {
    if (t.value.trim() === "") {
      t.setCustomValidity(""), t.removeAttribute("aria-invalid");
      return;
    }
    t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), u("ui_validation_error", { field: "email" }));
  }
  focusFirstInvalid() {
    const t = this.form.querySelector("[data-fp-resv-field]:invalid, [required]:invalid");
    t && typeof t.focus == "function" && t.focus();
  }
  findFirstInvalid(t) {
    return t ? t.querySelector("[data-fp-resv-field]:invalid, [required]:invalid") : null;
  }
  collectAvailabilityParams() {
    const t = this.hiddenMeal ? this.hiddenMeal.value : "", e = this.dateField && this.dateField.value ? this.dateField.value : "", i = this.partyField && this.partyField.value ? this.partyField.value : "";
    return {
      date: e,
      party: i,
      meal: t
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
    this.handleFirstInteraction();
    const e = this.form.querySelector('[data-fp-resv-field="time"]');
    if (e) {
      e.value = t && t.label ? t.label : "", t && t.start && e.setAttribute("data-slot-start", t.start);
      try {
        e.dispatchEvent(new Event("input", { bubbles: !0 }));
      } catch {
      }
    }
    this.hiddenSlot && (this.hiddenSlot.value = t && t.start ? t.start : "");
    const i = this.sections.find((s) => (s.getAttribute("data-step") || "") === "slots");
    i && (this.ensureSectionActive(i), t && t.start ? this.completeSection(i, !0) : this.updateSectionAttributes(i, "active")), this.updateSummary(), this.updateSubmitState();
  }
  handleAvailabilityLatency(t) {
    u("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    u("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    u(t, { source: "form" }), this.state.started = !0;
  }
  handleDelegatedTrackingEvent(t) {
    const e = t.target instanceof HTMLElement ? t.target : null;
    if (!e)
      return;
    const i = V(e, "data-fp-resv-event");
    if (!i)
      return;
    const s = i.getAttribute("data-fp-resv-event");
    if (!s)
      return;
    let n = tt(i, "data-fp-resv-payload");
    if ((!n || typeof n != "object") && (n = {}), n.trigger || (n.trigger = t.type || "click"), !n.href && i instanceof HTMLAnchorElement && i.href && (n.href = i.href), !n.label) {
      const a = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      a && (n.label = a.trim());
    }
    u(s, n);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    u(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && u(this.events.purchase || "purchase", e.purchase);
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
    const s = this.form.querySelector('[data-fp-resv-field="consent"]');
    s && "checked" in s && (e.analytics = s.checked ? "granted" : "denied", e.clarity = s.checked ? "granted" : "denied", i = !0);
    const n = this.form.querySelector('[data-fp-resv-field="marketing_consent"]');
    n && "checked" in n && (e.ads = n.checked ? "granted" : "denied", i = !0);
    const a = this.form.querySelector('[data-fp-resv-field="profiling_consent"]');
    a && "checked" in a && (e.personalization = a.checked ? "granted" : "denied", i = !0), i && t.updateConsent(e);
  }
  getPhoneCountryCode() {
    if (this.phonePrefixField && this.phonePrefixField.value) {
      const e = b(this.phonePrefixField.value);
      if (e)
        return e;
    }
    if (this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const e = b(this.hiddenPhoneCc.value);
      if (e)
        return e;
    }
    if (this.phoneCountryCode) {
      const e = b(this.phoneCountryCode);
      if (e)
        return e;
    }
    const t = this.config && this.config.defaults || {};
    if (t.phone_country_code) {
      const e = b(t.phone_country_code);
      if (e)
        return e;
    }
    return "39";
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
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = z);
document.addEventListener("DOMContentLoaded", function() {
  const r = document.querySelectorAll("[data-fp-resv]");
  Array.prototype.forEach.call(r, function(t) {
    new z(t);
  });
});
document.addEventListener("fp-resv:tracking:push", function(r) {
  if (!r || !r.detail)
    return;
  const t = r.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  u(e, i && typeof i == "object" ? i : {});
});
const at = 400, ot = 6e4, lt = 3, O = 600;
function dt(r, t) {
  let e;
  try {
    e = new URL(r, window.location.origin);
  } catch {
    const s = window.location.origin.replace(/\/$/, ""), n = r.startsWith("/") ? s + r : s + "/" + r;
    e = new URL(n, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function R(r) {
  for (; r.firstChild; )
    r.removeChild(r.firstChild);
}
function ct(r) {
  const t = r.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), s = t.querySelector("[data-fp-resv-slots-empty]"), n = t.querySelector("[data-fp-resv-slots-boundary]"), a = n ? n.querySelector("[data-fp-resv-slots-retry]") : null, l = /* @__PURE__ */ new Map();
  let f = null, c = null, p = null;
  a && a.addEventListener("click", () => {
    c && F(c, 0);
  });
  function m(o, d) {
    const v = typeof d == "string" ? d : d ? "loading" : "idle", y = typeof o == "string" ? o : "";
    e && (e.textContent = y, e.setAttribute("data-state", v));
    const A = v === "loading";
    t.setAttribute("data-loading", A ? "true" : "false"), i && i.setAttribute("aria-busy", A ? "true" : "false");
  }
  function x() {
    if (!i)
      return;
    R(i);
    const o = r.skeletonCount || 4;
    for (let d = 0; d < o; d += 1) {
      const v = document.createElement("li"), y = document.createElement("span");
      y.className = "fp-skeleton", v.appendChild(y), i.appendChild(v);
    }
  }
  function E(o) {
    s && (s.hidden = !1);
    const d = !o || !o.meal ? r.strings && r.strings.selectMeal || "" : r.strings && r.strings.slotsEmpty || "";
    m(d, "idle"), i && R(i);
  }
  function U() {
    s && (s.hidden = !0);
  }
  function M() {
    n && (n.hidden = !0);
  }
  function H(o) {
    if (r.strings && r.strings.slotsError || r.strings && r.strings.submitError, n) {
      const d = n.querySelector("[data-fp-resv-slots-boundary-message]");
      d && (d.textContent = o), n.hidden = !1;
    }
    m(o, "error");
  }
  function j(o, d) {
    const v = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(v, (y) => {
      y.setAttribute("aria-pressed", y === d ? "true" : "false");
    }), p = o, typeof r.onSlotSelected == "function" && r.onSlotSelected(o);
  }
  function T(o, d) {
    if (M(), U(), !i)
      return;
    R(i);
    const v = o && Array.isArray(o.slots) ? o.slots : [];
    if (v.length === 0) {
      E(d);
      return;
    }
    v.forEach((y) => {
      const A = document.createElement("li"), S = document.createElement("button");
      S.type = "button", S.textContent = y.label || "", S.dataset.slot = y.start || "", S.dataset.slotStatus = y.status || "", S.setAttribute("aria-pressed", p && p.start === y.start ? "true" : "false"), S.addEventListener("click", () => j(y, S)), A.appendChild(S), i.appendChild(A);
    }), m(r.strings && r.strings.slotsUpdated || "", !1);
  }
  function F(o, d) {
    if (c = o, !o || !o.date || !o.party) {
      E(o);
      return;
    }
    const v = JSON.stringify([o.date, o.meal, o.party]), y = l.get(v);
    if (y && Date.now() - y.timestamp < ot && d === 0) {
      T(y.payload, o);
      return;
    }
    M(), x(), m(r.strings && r.strings.updatingSlots || "Updating availability…", "loading");
    const A = dt(r.endpoint, o), S = performance.now();
    fetch(A, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((h) => h.json().catch(() => ({})).then((_) => {
      if (!h.ok) {
        const g = new Error("availability_error");
        g.status = h.status, g.payload = _;
        const w = h.headers.get("Retry-After");
        if (w) {
          const C = Number.parseInt(w, 10);
          Number.isFinite(C) && (g.retryAfter = C);
        }
        throw g;
      }
      return _;
    })).then((h) => {
      const _ = performance.now() - S;
      typeof r.onLatency == "function" && r.onLatency(_), l.set(v, { payload: h, timestamp: Date.now() }), T(h, o);
    }).catch((h) => {
      const _ = performance.now() - S;
      typeof r.onLatency == "function" && r.onLatency(_);
      const g = h && h.payload && typeof h.payload == "object" ? h.payload.data || {} : {}, w = typeof h.status == "number" ? h.status : g && typeof g.status == "number" ? g.status : 0;
      let C = 0;
      if (h && typeof h.retryAfter == "number" && Number.isFinite(h.retryAfter))
        C = h.retryAfter;
      else if (g && typeof g.retry_after < "u") {
        const P = Number.parseInt(g.retry_after, 10);
        Number.isFinite(P) && (C = P);
      }
      if (d >= lt - 1 ? !1 : w === 429 || w >= 500 && w < 600 ? !0 : w === 0) {
        const P = d + 1;
        typeof r.onRetry == "function" && r.onRetry(P);
        const W = C > 0 ? Math.max(C * 1e3, O) : O * Math.pow(2, d);
        window.setTimeout(() => F(o, P), W);
        return;
      }
      const K = h && h.payload && (h.payload.message || h.payload.code) || g && g.message || r.strings && r.strings.slotsError || r.strings && r.strings.submitError || "We could not update available times. Please try again.";
      H(K);
    });
  }
  return {
    schedule(o) {
      f && window.clearTimeout(f);
      const d = o || (typeof r.getParams == "function" ? r.getParams() : null);
      if (!d || !d.date || !d.party) {
        c = d, E(d || {});
        return;
      }
      f = window.setTimeout(() => {
        F(d, 0);
      }, at);
    },
    revalidate() {
      if (!c)
        return;
      const o = JSON.stringify([c.date, c.meal, c.party]);
      l.delete(o), F(c, 0);
    },
    getSelection() {
      return p;
    }
  };
}
const ht = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: ct
}, Symbol.toStringTag, { value: "Module" }));
