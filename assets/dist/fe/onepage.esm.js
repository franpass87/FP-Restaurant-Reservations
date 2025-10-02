const G = /\D+/g;
function B(s) {
  return s ? String(s).replace(G, "") : "";
}
function b(s) {
  const t = B(s);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function k(s) {
  return B(s);
}
function Q(s, t) {
  const e = b(s), i = k(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function Z(s) {
  const t = k(s);
  return t.length >= 6 && t.length <= 15;
}
function tt(s) {
  const t = k(s);
  if (t === "")
    return { masked: "", digits: "" };
  const e = [3, 4], i = [];
  let n = 0, r = 0;
  for (; n < t.length; ) {
    const a = t.length - n;
    let l = e[r % e.length];
    a <= 4 && (l = a), i.push(t.slice(n, n + l)), n += l, r += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function q(s, t) {
  const e = s.value, { masked: i } = tt(e), n = s.selectionStart;
  if (s.value = i, n !== null) {
    const r = i.length - e.length, a = Math.max(0, n + r);
    s.setSelectionRange(a, a);
  }
  s.setAttribute("data-phone-local", k(s.value)), s.setAttribute("data-phone-cc", b(t));
}
function L(s, t) {
  const e = k(s.value), i = b(t);
  return {
    e164: Q(i, e),
    local: e,
    country: i
  };
}
function R(s) {
  if (s == null)
    return "";
  if (typeof s == "string")
    return s.trim();
  if (Array.isArray(s))
    return s.map((e) => R(e)).filter((e) => e !== "").join("; ");
  if (typeof s == "object") {
    if (typeof s.message == "string" && s.message.trim() !== "")
      return s.message.trim();
    if (typeof s.detail == "string" && s.detail.trim() !== "")
      return s.detail.trim();
  }
  return String(s).trim();
}
function et(s) {
  if (s == null)
    return "";
  const t = Array.isArray(s) ? [...s] : [s];
  for (; t.length > 0; ) {
    const e = t.shift();
    if (e == null)
      continue;
    if (Array.isArray(e)) {
      t.push(...e);
      continue;
    }
    if (typeof e != "object") {
      const n = R(e);
      if (n !== "")
        return n;
      continue;
    }
    const i = ["details", "detail", "debug", "error"];
    for (let n = 0; n < i.length; n += 1) {
      const r = i[n];
      if (Object.prototype.hasOwnProperty.call(e, r)) {
        const a = R(e[r]);
        if (a !== "")
          return a;
      }
    }
    Object.prototype.hasOwnProperty.call(e, "data") && e.data && typeof e.data == "object" && t.push(e.data);
  }
  return "";
}
function z(s, t) {
  const e = et(t);
  return e === "" ? s : s ? s.includes(e) ? s : s + " (" + e + ")" : e;
}
let I = null;
const O = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (s) => window.requestIdleCallback(s) : (s) => window.setTimeout(() => s(Date.now()), 1);
function it() {
  return I || (I = Promise.resolve().then(() => yt)), I;
}
function st(s) {
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
function f(s, t) {
  if (!s)
    return null;
  const e = Object.assign({ event: s }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
function j(s, t) {
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
function nt(s) {
  return j(s, "data-fp-resv-section");
}
function rt(s, t) {
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
function at(s, t) {
  s && (t ? (s.setAttribute("aria-disabled", "true"), s.setAttribute("disabled", "disabled")) : (s.removeAttribute("disabled"), s.setAttribute("aria-disabled", "false")));
}
function ot(s) {
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
function lt(s) {
  return s ? s.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]') : null;
}
const dt = ["date", "party", "slots", "details", "confirm"];
function ct(s) {
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
class U {
  constructor(t) {
    this.root = t, this.dataset = st(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(dt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((n) => n.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((n, r) => this.getStepOrderIndex(n) - this.getStepOrderIndex(r)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((n, r) => this.getStepOrderIndex(n) - this.getStepOrderIndex(r)).forEach((n) => {
      this.progress.appendChild(n);
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
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateSummary(), O(() => {
      this.loadStripeIfNeeded(), this.loadGoogleCalendarIfNeeded();
    }));
  }
  bind() {
    const t = this.handleFormInput.bind(this);
    this.form.addEventListener("input", t, !0), this.form.addEventListener("change", t, !0), this.form.addEventListener("focusin", this.handleFirstInteraction.bind(this)), this.form.addEventListener("blur", this.handleFieldBlur.bind(this), !0), this.form.addEventListener("keydown", this.handleKeydown.bind(this), !0), this.form.addEventListener("click", this.handleNavClick.bind(this)), this.form.addEventListener("submit", this.handleSubmit.bind(this)), this.root.addEventListener("click", this.handleDelegatedTrackingEvent), this.progress && (this.progress.addEventListener("click", this.handleProgressClick.bind(this)), this.progress.addEventListener("keydown", this.handleProgressKeydown.bind(this))), this.errorRetry && this.errorRetry.addEventListener("click", this.handleRetrySubmit.bind(this)), document.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("fp-resv:reservation:confirmed", this.handleReservationConfirmed), window.addEventListener("focus", this.handleWindowFocus);
  }
  getStepOrderIndex(t) {
    const e = t && t.getAttribute ? t.getAttribute("data-step") || "" : String(t || ""), i = typeof e == "string" ? e : "", n = this.stepOrder.indexOf(i);
    return n === -1 ? this.stepOrder.length + 1 : n;
  }
  initializeSections() {
    const t = this;
    this.sections.forEach(function(e, i) {
      const n = e.getAttribute("data-step") || String(i);
      t.state.sectionStates[n] = i === 0 ? "active" : "locked", i === 0 && t.dispatchSectionUnlocked(n), t.updateSectionAttributes(e, t.state.sectionStates[n], { silent: !0 });
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
        const n = b(i.phone_country_code);
        n && (e = n);
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
      const n = {
        start: i.getAttribute("data-slot") || "",
        label: i.textContent || "",
        status: i.getAttribute("data-slot-status") || ""
      }, r = this.availabilityRoot.querySelectorAll("button[data-slot]");
      Array.prototype.forEach.call(r, (a) => {
        a.setAttribute("aria-pressed", a === i ? "true" : "false");
      }), this.handleSlotSelected(n);
    });
    const t = () => {
      if (!this.availabilityController) {
        this.state.pendingAvailability = !0;
        return;
      }
      this.scheduleAvailabilityUpdate();
    };
    O(() => {
      it().then((e) => {
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
    const i = nt(e);
    if (!i) {
      this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
      return;
    }
    this.ensureSectionActive(i), this.isSectionValid(i) ? this.completeSection(i, !0) : this.updateSectionAttributes(i, "active");
    const n = e.getAttribute("data-fp-resv-field") || "";
    (n === "date" || n === "party" || n === "slots" || n === "time") && ((n === "date" || n === "party") && this.clearSlotSelection({ schedule: !1 }), this.scheduleAvailabilityUpdate()), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
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
    const n = e.getAttribute("data-fp-resv-nav");
    n === "prev" ? this.navigateToPrevious(i) : n === "next" && this.navigateToNext(i);
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
    const n = this.state.sectionStates[i];
    !n || n === "locked" || (t.preventDefault(), this.activateSectionByKey(i));
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
    const n = this.state.sectionStates[i];
    !n || n === "locked" || (t.preventDefault(), this.activateSectionByKey(i));
  }
  activateSectionByKey(t) {
    const e = this.sections.find(function(n) {
      return (n.getAttribute("data-step") || "") === t;
    });
    if (!e)
      return;
    let i = !1;
    this.sections.forEach((n) => {
      const r = n.getAttribute("data-step") || "";
      if (r === t)
        i = !0, this.updateSectionAttributes(n, "active", { silent: !0 }), this.dispatchSectionUnlocked(r);
      else if (i)
        this.updateSectionAttributes(n, "locked", { silent: !0 });
      else {
        const l = this.state.sectionStates[r] === "locked" ? "locked" : "completed";
        this.updateSectionAttributes(n, l, { silent: !0 });
      }
    }), this.updateProgressIndicators(), this.scrollIntoView(e), requestAnimationFrame(() => {
      const n = e.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
      n && typeof n.focus == "function" && n.focus({ preventScroll: !0 });
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
    f(e, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), this.scheduleAvailabilityUpdate();
  }
  applyMealSelection(t) {
    const e = t.getAttribute("data-fp-resv-meal") || "";
    this.hiddenMeal && (this.hiddenMeal.value = e);
    const i = ot(t.getAttribute("data-meal-price"));
    this.hiddenPrice && (this.hiddenPrice.value = i !== null ? String(i) : ""), this.clearSlotSelection({ schedule: !1 });
    const n = t.getAttribute("data-meal-notice");
    this.mealNotice && (n && n.trim() !== "" ? (this.mealNotice.textContent = n, this.mealNotice.hidden = !1) : (this.mealNotice.textContent = "", this.mealNotice.hidden = !0)), this.updateSubmitState();
  }
  clearSlotSelection(t = {}) {
    this.hiddenSlot && (this.hiddenSlot.value = "");
    const e = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
    if (e && (e.value = "", e.removeAttribute("data-slot-start")), this.availabilityRoot) {
      const n = this.availabilityRoot.querySelectorAll('button[data-slot][aria-pressed="true"]');
      Array.prototype.forEach.call(n, (r) => {
        r.setAttribute("aria-pressed", "false");
      });
    }
    const i = this.sections.find((n) => (n.getAttribute("data-step") || "") === "slots");
    if (i) {
      const n = i.getAttribute("data-step") || "", r = this.state.sectionStates[n] || "locked";
      this.updateSectionAttributes(i, "locked", { silent: !0 });
      const a = this.sections.indexOf(i);
      if (a !== -1)
        for (let l = a + 1; l < this.sections.length; l += 1) {
          const u = this.sections[l];
          this.updateSectionAttributes(u, "locked", { silent: !0 });
        }
      this.updateProgressIndicators(), (t.forceRewind && n || r === "completed" || r === "active") && this.activateSectionByKey(n);
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
    const n = this.sections.indexOf(t);
    if (n === -1)
      return;
    const r = this.sections[n + 1];
    if (!r)
      return;
    const a = r.getAttribute("data-step") || String(n + 1);
    this.state.sectionStates[a] !== "completed" && (this.state.sectionStates[a] = "active", this.updateSectionAttributes(r, "active"), this.dispatchSectionUnlocked(a), this.scrollIntoView(r));
  }
  navigateToPrevious(t) {
    const e = this.sections.indexOf(t);
    if (e <= 0)
      return;
    const i = this.sections[e - 1];
    if (!i)
      return;
    const n = i.getAttribute("data-step") || "";
    n && this.activateSectionByKey(n);
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
    f(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const n = t.getAttribute("data-step") || "", r = i && i.silent === !0;
    this.state.sectionStates[n] = e, t.setAttribute("data-state", e), e === "completed" ? t.setAttribute("data-complete-hidden", "true") : t.removeAttribute("data-complete-hidden");
    const a = e === "active";
    t.setAttribute("aria-hidden", a ? "false" : "true"), t.setAttribute("aria-expanded", a ? "true" : "false"), a ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert")) : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", "")), r || this.updateProgressIndicators();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const n = e.length || 1;
    Array.prototype.forEach.call(e, function(a, l) {
      const u = a.getAttribute("data-step") || "", h = t.state.sectionStates[u] || "locked";
      a.setAttribute("data-state", h), a.setAttribute("data-progress-state", h === "completed" ? "done" : h);
      const p = h === "locked";
      a.tabIndex = p ? -1 : 0, p ? a.setAttribute("aria-disabled", "true") : a.removeAttribute("aria-disabled"), h === "active" ? (a.setAttribute("aria-current", "step"), i = Math.max(i, l + 0.5)) : a.removeAttribute("aria-current"), h === "completed" ? (a.setAttribute("data-completed", "true"), i = Math.max(i, l + 1)) : a.removeAttribute("data-completed");
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
      f(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
    }
  }
  setSubmitButtonState(t, e) {
    if (!this.submitButton)
      return;
    const i = e === "sending" ? !1 : !!t, n = this.state.ctaEnabled;
    at(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), n !== i && e !== "sending" && f("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), n = this.form.querySelector('[data-fp-resv-field="first_name"]'), r = this.form.querySelector('[data-fp-resv-field="last_name"]'), a = this.form.querySelector('[data-fp-resv-field="email"]'), l = this.form.querySelector('[data-fp-resv-field="phone"]'), u = this.form.querySelector('[data-fp-resv-field="notes"]');
    let h = "";
    n && n.value && (h = n.value.trim()), r && r.value && (h = (h + " " + r.value.trim()).trim());
    let p = "";
    if (a && a.value && (p = a.value.trim()), l && l.value) {
      const m = this.getPhoneCountryCode(), C = (m ? "+" + m + " " : "") + l.value.trim();
      p = p !== "" ? p + " / " + C : C;
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
          m.textContent = h;
          break;
        case "contact":
          m.textContent = p;
          break;
        case "notes":
          m.textContent = u && u.value ? u.value : "";
          break;
      }
    });
  }
  async handleSubmit(t) {
    if (t.preventDefault(), !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    f(e, {
      source: "form",
      form_id: this.form && this.form.id ? this.form.id : this.root.id || "",
      date: i.date,
      party: i.party,
      meal: i.meal
    }), this.preparePhonePayload(), this.state.sending = !0, this.updateSubmitState(), this.clearError();
    const n = this.serializeForm(), r = this.getReservationEndpoint(), a = performance.now();
    let l = 0;
    try {
      const u = await fetch(r, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-WP-Nonce": n.fp_resv_nonce || ""
        },
        body: JSON.stringify(n),
        credentials: "same-origin"
      });
      if (l = Math.round(performance.now() - a), f("ui_latency", { op: "submit", ms: l }), !u.ok) {
        const p = await ct(u), m = p && p.message || this.copy.submitError;
        throw Object.assign(new Error(m), {
          status: u.status,
          payload: p
        });
      }
      const h = await u.json();
      this.handleSubmitSuccess(h);
    } catch (u) {
      l || (l = Math.round(performance.now() - a), f("ui_latency", { op: "submit", ms: l })), this.handleSubmitError(u, l);
    } finally {
      this.state.sending = !1, this.updateSubmitState();
    }
    return !1;
  }
  handleSubmitSuccess(t) {
    this.clearError();
    const e = t && t.message || this.copy.submitSuccess;
    this.successAlert && (this.successAlert.textContent = e, this.successAlert.hidden = !1, typeof this.successAlert.focus == "function" && this.successAlert.focus()), t && Array.isArray(t.tracking) && t.tracking.forEach((i) => {
      i && i.event && f(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", n = t && t.message || this.copy.submitError, r = t && typeof t == "object" && t.payload || null, a = z(n, r);
    this.errorAlert && this.errorMessage && (this.errorMessage.textContent = a, this.errorAlert.hidden = !1), this.state.hintOverride = a, this.updateSubmitState();
    const l = this.events.submit_error || "submit_error";
    f(l, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, n) => {
      typeof i == "string" && (e[n] = i);
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
    Z(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), f("phone_validation_error", { field: "phone" }), f("ui_validation_error", { field: "phone" }));
  }
  validateEmailField(t) {
    if (t.value.trim() === "") {
      t.setCustomValidity(""), t.removeAttribute("aria-invalid");
      return;
    }
    t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), f("ui_validation_error", { field: "email" }));
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
    const i = this.sections.find((n) => (n.getAttribute("data-step") || "") === "slots");
    i && (this.ensureSectionActive(i), t && t.start ? this.completeSection(i, !0) : this.updateSectionAttributes(i, "active")), this.updateSummary(), this.updateSubmitState();
  }
  handleAvailabilityLatency(t) {
    f("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    f("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    f(t, { source: "form" }), this.state.started = !0;
  }
  handleDelegatedTrackingEvent(t) {
    const e = t.target instanceof HTMLElement ? t.target : null;
    if (!e)
      return;
    const i = j(e, "data-fp-resv-event");
    if (!i)
      return;
    const n = i.getAttribute("data-fp-resv-event");
    if (!n)
      return;
    let r = rt(i, "data-fp-resv-payload");
    if ((!r || typeof r != "object") && (r = {}), r.trigger || (r.trigger = t.type || "click"), !r.href && i instanceof HTMLAnchorElement && i.href && (r.href = i.href), !r.label) {
      const a = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      a && (r.label = a.trim());
    }
    f(n, r);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    f(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && f(this.events.purchase || "purchase", e.purchase);
  }
  scrollIntoView(t) {
    typeof t.scrollIntoView == "function" && t.scrollIntoView({ behavior: "smooth", block: "start" });
    const e = lt(t);
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
  loadExternalScript(t, e, i) {
    if (typeof window > "u" || typeof document > "u")
      return Promise.resolve(null);
    if (typeof e == "function") {
      const n = e();
      if (n)
        return Promise.resolve(n);
    }
    return new Promise((n) => {
      const r = () => {
        if (typeof e == "function") {
          const u = e();
          n(u || null);
          return;
        }
        n(null);
      };
      let a = document.querySelector(`script[src="${t}"]`);
      if (!a && i && (a = document.querySelector(`script[${i}]`)), a) {
        if (typeof e == "function") {
          const u = e();
          if (u) {
            n(u);
            return;
          }
        }
        a.addEventListener("load", r, { once: !0 }), a.addEventListener("error", () => n(null), { once: !0 });
        return;
      }
      a = document.createElement("script"), a.src = t, a.async = !0, i && a.setAttribute(i, "1"), a.onload = r, a.onerror = () => n(null);
      const l = document.head || document.body || document.documentElement;
      if (!l) {
        n(null);
        return;
      }
      l.appendChild(a);
    });
  }
  loadStripeIfNeeded() {
    const t = this.integrations && (this.integrations.stripe || this.integrations.payments_stripe);
    return !t || typeof t == "object" && t.enabled === !1 ? Promise.resolve(null) : typeof window < "u" && window.Stripe ? Promise.resolve(window.Stripe) : (this.stripePromise || (this.stripePromise = this.loadExternalScript(
      "https://js.stripe.com/v3/",
      () => typeof window < "u" ? window.Stripe : null,
      "data-fp-resv-stripe"
    )), this.stripePromise);
  }
  loadGoogleCalendarIfNeeded() {
    const t = this.integrations && (this.integrations.googleCalendar || this.integrations.calendar_google);
    return !t || typeof t == "object" && t.enabled === !1 ? Promise.resolve(null) : typeof window < "u" && window.gapi ? Promise.resolve(window.gapi) : (this.googlePromise || (this.googlePromise = this.loadExternalScript(
      "https://apis.google.com/js/api.js",
      () => typeof window < "u" ? window.gapi : null,
      "data-fp-resv-google-api"
    )), this.googlePromise);
  }
}
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = U);
document.addEventListener("DOMContentLoaded", function() {
  const s = document.querySelectorAll("[data-fp-resv]");
  Array.prototype.forEach.call(s, function(t) {
    new U(t);
  });
});
document.addEventListener("fp-resv:tracking:push", function(s) {
  if (!s || !s.detail)
    return;
  const t = s.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  f(e, i && typeof i == "object" ? i : {});
});
const ut = 400, ht = 6e4, ft = 3, V = 600;
function pt(s, t) {
  let e;
  try {
    e = new URL(s, window.location.origin);
  } catch {
    const n = window.location.origin.replace(/\/$/, ""), r = s.startsWith("/") ? n + s : n + "/" + s;
    e = new URL(r, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function M(s) {
  for (; s.firstChild; )
    s.removeChild(s.firstChild);
}
function mt(s) {
  const t = s.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), n = t.querySelector("[data-fp-resv-slots-empty]"), r = t.querySelector("[data-fp-resv-slots-boundary]"), a = r ? r.querySelector("[data-fp-resv-slots-retry]") : null, l = /* @__PURE__ */ new Map();
  let u = null, h = null, p = null;
  a && a.addEventListener("click", () => {
    h && F(h, 0);
  });
  function m(o, d) {
    const g = typeof d == "string" ? d : d ? "loading" : "idle", y = typeof o == "string" ? o : "";
    e && (e.textContent = y, e.setAttribute("data-state", g));
    const A = g === "loading";
    t.setAttribute("data-loading", A ? "true" : "false"), i && i.setAttribute("aria-busy", A ? "true" : "false");
  }
  function x() {
    if (!i)
      return;
    M(i);
    const o = s.skeletonCount || 4;
    for (let d = 0; d < o; d += 1) {
      const g = document.createElement("li"), y = document.createElement("span");
      y.className = "fp-skeleton", g.appendChild(y), i.appendChild(g);
    }
  }
  function C(o) {
    n && (n.hidden = !1);
    const d = !o || !o.meal ? s.strings && s.strings.selectMeal || "" : s.strings && s.strings.slotsEmpty || "";
    m(d, "idle"), i && M(i);
  }
  function H() {
    n && (n.hidden = !0);
  }
  function D() {
    r && (r.hidden = !0);
  }
  function K(o) {
    const d = s.strings && s.strings.slotsError || s.strings && s.strings.submitError || "We could not update available times. Please try again.";
    if (r) {
      const g = r.querySelector("[data-fp-resv-slots-boundary-message]");
      g && (g.textContent = o || d), r.hidden = !1;
    }
    m(o || d, "error");
  }
  function W(o, d) {
    const g = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(g, (y) => {
      y.setAttribute("aria-pressed", y === d ? "true" : "false");
    }), p = o, typeof s.onSlotSelected == "function" && s.onSlotSelected(o);
  }
  function T(o, d) {
    if (D(), H(), !i)
      return;
    M(i);
    const g = o && Array.isArray(o.slots) ? o.slots : [];
    if (g.length === 0) {
      C(d);
      return;
    }
    g.forEach((y) => {
      const A = document.createElement("li"), S = document.createElement("button");
      S.type = "button", S.textContent = y.label || "", S.dataset.slot = y.start || "", S.dataset.slotStatus = y.status || "", S.setAttribute("aria-pressed", p && p.start === y.start ? "true" : "false"), S.addEventListener("click", () => W(y, S)), A.appendChild(S), i.appendChild(A);
    }), m(s.strings && s.strings.slotsUpdated || "", !1);
  }
  function F(o, d) {
    if (h = o, !o || !o.date || !o.party) {
      C(o);
      return;
    }
    const g = JSON.stringify([o.date, o.meal, o.party]), y = l.get(g);
    if (y && Date.now() - y.timestamp < ht && d === 0) {
      T(y.payload, o);
      return;
    }
    D(), x(), m(s.strings && s.strings.updatingSlots || "Updating availability…", "loading");
    const A = pt(s.endpoint, o), S = performance.now();
    fetch(A, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((c) => c.json().catch(() => ({})).then((P) => {
      if (!c.ok) {
        const v = new Error("availability_error");
        v.status = c.status, v.payload = P;
        const w = c.headers.get("Retry-After");
        if (w) {
          const E = Number.parseInt(w, 10);
          Number.isFinite(E) && (v.retryAfter = E);
        }
        throw v;
      }
      return P;
    })).then((c) => {
      const P = performance.now() - S;
      typeof s.onLatency == "function" && s.onLatency(P), l.set(g, { payload: c, timestamp: Date.now() }), T(c, o);
    }).catch((c) => {
      const P = performance.now() - S;
      typeof s.onLatency == "function" && s.onLatency(P);
      const v = c && c.payload && typeof c.payload == "object" ? c.payload.data || {} : {}, w = typeof c.status == "number" ? c.status : v && typeof v.status == "number" ? v.status : 0;
      let E = 0;
      if (c && typeof c.retryAfter == "number" && Number.isFinite(c.retryAfter))
        E = c.retryAfter;
      else if (v && typeof v.retry_after < "u") {
        const _ = Number.parseInt(v.retry_after, 10);
        Number.isFinite(_) && (E = _);
      }
      if (d >= ft - 1 ? !1 : w === 429 || w >= 500 && w < 600 ? !0 : w === 0) {
        const _ = d + 1;
        typeof s.onRetry == "function" && s.onRetry(_);
        const Y = E > 0 ? Math.max(E * 1e3, V) : V * Math.pow(2, d);
        window.setTimeout(() => F(o, _), Y);
        return;
      }
      const J = c && c.payload && (c.payload.message || c.payload.code) || v && v.message || s.strings && s.strings.slotsError || s.strings && s.strings.submitError || "We could not update available times. Please try again.", X = c && c.payload || v || null, $ = z(J, X);
      K($);
    });
  }
  return {
    schedule(o) {
      u && window.clearTimeout(u);
      const d = o || (typeof s.getParams == "function" ? s.getParams() : null);
      if (!d || !d.date || !d.party) {
        h = d, C(d || {});
        return;
      }
      u = window.setTimeout(() => {
        F(d, 0);
      }, ut);
    },
    revalidate() {
      if (!h)
        return;
      const o = JSON.stringify([h.date, h.meal, h.party]);
      l.delete(o), F(h, 0);
    },
    getSelection() {
      return p;
    }
  };
}
const yt = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: mt
}, Symbol.toStringTag, { value: "Module" }));
