const st = /\D+/g;
function U(a) {
  return a ? String(a).replace(st, "") : "";
}
function w(a) {
  const t = U(a);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function F(a) {
  return U(a);
}
function at(a, t) {
  const e = w(a), i = F(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function nt(a) {
  const t = F(a);
  return t.length >= 6 && t.length <= 15;
}
function rt(a) {
  const t = F(a);
  if (t === "")
    return { masked: "", digits: "" };
  const e = [3, 4], i = [];
  let s = 0, n = 0;
  for (; s < t.length; ) {
    const r = t.length - s;
    let c = e[n % e.length];
    r <= 4 && (c = r), i.push(t.slice(s, s + c)), s += c, n += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function I(a, t) {
  const e = a.value, { masked: i } = rt(e), s = a.selectionStart;
  if (a.value = i, s !== null) {
    const n = i.length - e.length, r = Math.max(0, s + n);
    a.setSelectionRange(r, r);
  }
  a.setAttribute("data-phone-local", F(a.value)), a.setAttribute("data-phone-cc", w(t));
}
function R(a, t) {
  const e = F(a.value), i = w(t);
  return {
    e164: at(i, e),
    local: e,
    country: i
  };
}
function T(a) {
  if (a == null)
    return "";
  if (typeof a == "string")
    return a.trim();
  if (Array.isArray(a))
    return a.map((e) => T(e)).filter((e) => e !== "").join("; ");
  if (typeof a == "object") {
    if (typeof a.message == "string" && a.message.trim() !== "")
      return a.message.trim();
    if (typeof a.detail == "string" && a.detail.trim() !== "")
      return a.detail.trim();
  }
  return String(a).trim();
}
function ot(a) {
  if (a == null)
    return "";
  const t = Array.isArray(a) ? [...a] : [a];
  for (; t.length > 0; ) {
    const e = t.shift();
    if (e == null)
      continue;
    if (Array.isArray(e)) {
      t.push(...e);
      continue;
    }
    if (typeof e != "object") {
      const s = T(e);
      if (s !== "")
        return s;
      continue;
    }
    const i = ["details", "detail", "debug", "error"];
    for (let s = 0; s < i.length; s += 1) {
      const n = i[s];
      if (Object.prototype.hasOwnProperty.call(e, n)) {
        const r = T(e[n]);
        if (r !== "")
          return r;
      }
    }
    Object.prototype.hasOwnProperty.call(e, "data") && e.data && typeof e.data == "object" && t.push(e.data);
  }
  return "";
}
function H(a, t) {
  const e = ot(t);
  return e === "" ? a : a ? a.includes(e) ? a : a + " (" + e + ")" : e;
}
let O = null;
const V = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (a) => window.requestIdleCallback(a) : (a) => window.setTimeout(() => a(Date.now()), 1);
function lt() {
  return O || (O = Promise.resolve().then(() => wt)), O;
}
function ct(a) {
  const t = a.getAttribute("data-fp-resv");
  if (!t)
    return {};
  try {
    return JSON.parse(t);
  } catch (e) {
    window.console && window.console.warn && console.warn("[fp-resv] Impossibile analizzare il dataset del widget", e);
  }
  return {};
}
function v(a, t) {
  if (!a)
    return null;
  const e = Object.assign({ event: a }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
function W(a, t) {
  if (!a)
    return null;
  if (typeof a.closest == "function")
    return a.closest("[" + t + "]");
  let e = a;
  for (; e; ) {
    if (e.hasAttribute(t))
      return e;
    e = e.parentElement;
  }
  return null;
}
function dt(a) {
  return W(a, "data-fp-resv-section");
}
function ut(a, t) {
  if (!a)
    return {};
  const e = a.getAttribute(t);
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
function ht(a, t) {
  a && (t ? (a.setAttribute("aria-disabled", "true"), a.setAttribute("disabled", "disabled")) : (a.removeAttribute("disabled"), a.setAttribute("aria-disabled", "false")));
}
function ft(a) {
  if (a == null)
    return null;
  if (typeof a == "number")
    return Number.isFinite(a) ? a : null;
  const t = String(a).replace(",", "."), e = parseFloat(t);
  return Number.isNaN(e) ? null : e;
}
function j(a, t) {
  if (a && typeof a == "string")
    try {
      return new URL(a, window.location.origin).toString();
    } catch {
      return a;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
function pt(a) {
  return a ? a.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]') : null;
}
const yt = ["date", "party", "slots", "details", "confirm"];
function mt(a) {
  return a.text().then((t) => {
    if (!t)
      return {};
    try {
      return JSON.parse(t);
    } catch {
      return {};
    }
  });
}
class J {
  constructor(t) {
    this.root = t, this.dataset = ct(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(yt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((s) => s.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((s, n) => this.getStepOrderIndex(s) - this.getStepOrderIndex(n)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((s, n) => this.getStepOrderIndex(s) - this.getStepOrderIndex(n)).forEach((s) => {
      this.progress.appendChild(s);
    }), this.submitButton = this.form ? this.form.querySelector("[data-fp-resv-submit]") : null, this.submitLabel = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-label]") || this.submitButton : null, this.submitSpinner = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-spinner]") : null, this.submitHint = this.form ? this.form.querySelector("[data-fp-resv-submit-hint]") : null, this.stickyCta = this.form ? this.form.querySelector("[data-fp-resv-sticky-cta]") : null, this.successAlert = this.form ? this.form.querySelector("[data-fp-resv-success]") : null, this.errorAlert = this.form ? this.form.querySelector("[data-fp-resv-error]") : null, this.errorMessage = this.form ? this.form.querySelector("[data-fp-resv-error-message]") : null, this.errorRetry = this.form ? this.form.querySelector("[data-fp-resv-error-retry]") : null, this.mealButtons = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-meal]")), this.mealNotice = t.querySelector("[data-fp-resv-meal-notice]"), this.mealNoticeText = this.mealNotice ? this.mealNotice.querySelector("[data-fp-resv-meal-notice-text]") || this.mealNotice : null, this.hiddenMeal = this.form ? this.form.querySelector('input[name="fp_resv_meal"]') : null, this.hiddenPrice = this.form ? this.form.querySelector('input[name="fp_resv_price_per_person"]') : null, this.hiddenSlot = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null, this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field="party"]') : null, this.summaryTargets = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-summary]")), this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field="phone"]') : null, this.phonePrefixField = this.form ? this.form.querySelector('[data-fp-resv-field="phone_prefix"]') : null, this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name="fp_resv_phone_e164"]') : null, this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name="fp_resv_phone_cc"]') : null, this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name="fp_resv_phone_local"]') : null, this.availabilityRoot = this.form ? this.form.querySelector("[data-fp-resv-slots]") : null, this.state = {
      started: !1,
      formValidEmitted: !1,
      sectionStates: {},
      unlocked: {},
      initialHint: this.submitHint ? this.submitHint.textContent : "",
      hintOverride: "",
      ctaEnabled: !1,
      sending: !1,
      pendingAvailability: !1,
      pendingAvailabilityOptions: null,
      lastAvailabilityParams: null,
      mealAvailability: {}
    }, this.copy = {
      ctaDisabled: this.messages.cta_complete_fields || "Complete required fields",
      ctaEnabled: this.messages.cta_book_now || this.strings.actions && this.strings.actions.submit || "Prenota ora",
      ctaSending: this.messages.cta_sending || "Invio…",
      updatingSlots: this.messages.msg_updating_slots || "Aggiornamento disponibilità…",
      slotsUpdated: this.messages.msg_slots_updated || "Disponibilità aggiornata.",
      slotsEmpty: this.messages.slots_empty || "",
      selectMeal: this.messages.msg_select_meal || "Seleziona un servizio per visualizzare gli orari disponibili.",
      slotsError: this.messages.msg_slots_error || "Impossibile aggiornare la disponibilità. Riprova.",
      invalidPhone: this.messages.msg_invalid_phone || "Inserisci un numero di telefono valido (minimo 6 cifre).",
      invalidEmail: this.messages.msg_invalid_email || "Enter a valid email address.",
      submitError: this.messages.msg_submit_error || "We could not complete your reservation. Please try again.",
      submitSuccess: this.messages.msg_submit_success || "Prenotazione inviata con successo.",
      mealFullNotice: this.messages.meal_full_notice || "Nessuna disponibilità per questo servizio. Scegli un altro giorno."
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateSummary(), V(() => {
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
      if (!e.hasAttribute("data-meal-default-notice")) {
        const i = e.getAttribute("data-meal-notice") || "";
        i !== "" && e.setAttribute("data-meal-default-notice", i);
      }
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
    this.phoneField && I(this.phoneField, this.getPhoneCountryCode());
  }
  updatePhoneCountryFromPrefix() {
    if (!this.phonePrefixField)
      return;
    const t = w(this.phonePrefixField.value);
    let e = t;
    if (e === "" && this.phoneCountryCode) {
      const i = w(this.phoneCountryCode);
      i && (e = i);
    }
    if (e === "" && this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const i = w(this.hiddenPhoneCc.value);
      i && (e = i);
    }
    if (e === "") {
      const i = this.config && this.config.defaults || {};
      if (i.phone_country_code) {
        const s = w(i.phone_country_code);
        s && (e = s);
      }
    }
    e === "" && (e = "39"), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = e), t !== "" && (this.phoneCountryCode = t), this.phoneField && I(this.phoneField, e);
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
      Array.prototype.forEach.call(n, (r) => {
        r.setAttribute("aria-pressed", r === i ? "true" : "false");
      }), this.handleSlotSelected(s);
    });
    const t = (e = {}) => {
      const i = e && typeof e == "object" ? { ...e } : {};
      if (!this.availabilityController) {
        this.state.pendingAvailability = !0, this.state.pendingAvailabilityOptions = i;
        return;
      }
      this.scheduleAvailabilityUpdate(i);
    };
    V(() => {
      lt().then((e) => {
        if (!(!e || typeof e.createAvailabilityController != "function" || !this.availabilityRoot) && (this.availabilityController = e.createAvailabilityController({
          root: this.availabilityRoot,
          endpoint: this.getAvailabilityEndpoint(),
          strings: this.copy,
          getParams: () => this.collectAvailabilityParams(),
          onSlotSelected: (i) => this.handleSlotSelected(i),
          onLatency: (i) => this.handleAvailabilityLatency(i),
          onRetry: (i) => this.handleAvailabilityRetry(i),
          onAvailabilitySummary: (i, s) => this.handleMealAvailabilitySummary(i, s)
        }), this.state.pendingAvailability)) {
          this.state.pendingAvailability = !1;
          const i = this.state.pendingAvailabilityOptions || {};
          this.state.pendingAvailabilityOptions = null, this.scheduleAvailabilityUpdate(i);
        }
      }).catch(() => {
      });
    }), t();
  }
  handleFormInput(t) {
    const e = t.target;
    if (!e)
      return;
    this.handleFirstInteraction(), e === this.phoneField ? I(this.phoneField, this.getPhoneCountryCode()) : e === this.phonePrefixField && this.updatePhoneCountryFromPrefix(), this.updateSummary();
    const i = e.getAttribute("data-fp-resv-field") || "", s = i && e.dataset.fpResvLastValue || "", n = i && typeof e.value == "string" ? e.value : "", r = !i || s !== n, c = dt(e);
    if (!c) {
      this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
      return;
    }
    this.ensureSectionActive(c), this.updateSectionAttributes(c, "active"), i && (e.dataset.fpResvLastValue = n), (i === "date" || i === "party" || i === "slots" || i === "time") && ((i === "date" || i === "party") && r && this.clearSlotSelection({ schedule: !1 }), (i !== "date" || r || t.type === "change") && this.scheduleAvailabilityUpdate()), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
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
        const c = this.state.sectionStates[n] === "locked" ? "locked" : "completed";
        this.updateSectionAttributes(s, c, { silent: !0 });
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
    this.mealButtons.forEach(function(n) {
      n.removeAttribute("data-active"), n.setAttribute("aria-pressed", "false");
    }), t.setAttribute("data-active", "true"), t.setAttribute("aria-pressed", "true");
    const e = t.getAttribute("data-fp-resv-meal") || "", i = this.state.mealAvailability ? this.state.mealAvailability[e] : "";
    if (this.applyMealAvailabilityIndicator(e, i), i === "full") {
      const n = t.getAttribute("data-meal-default-notice") || "", r = this.copy.mealFullNotice || n;
      r !== "" && t.setAttribute("data-meal-notice", r);
    }
    this.applyMealSelection(t), this.applyMealAvailabilityNotice(e, i, { skipSlotReset: !0 });
    const s = this.events.meal_selected || "meal_selected";
    v(s, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), i !== "full" && this.scheduleAvailabilityUpdate({ immediate: !0 });
  }
  updateMealNoticeFromButton(t, e) {
    if (!this.mealNotice)
      return;
    const i = typeof e == "string" ? e : t && t.getAttribute("data-meal-notice") || "", s = i ? i.trim() : "", n = this.mealNoticeText || this.mealNotice;
    s !== "" && n ? (n.textContent = s, this.mealNotice.hidden = !1) : n && (n.textContent = "", this.mealNotice.hidden = !0);
  }
  applyMealAvailabilityNotice(t, e, i = {}) {
    const s = this.mealButtons.find((c) => (c.getAttribute("data-fp-resv-meal") || "") === t);
    if (!s)
      return;
    const n = s.getAttribute("data-meal-default-notice") || "";
    if ((typeof e == "string" ? e : "") === "full") {
      const c = this.copy.mealFullNotice || n;
      c !== "" ? s.setAttribute("data-meal-notice", c) : n === "" && s.removeAttribute("data-meal-notice"), s.setAttribute("aria-disabled", "true"), s.setAttribute("data-meal-unavailable", "true"), s.hasAttribute("data-active") && (i.skipSlotReset !== !0 && this.clearSlotSelection({ schedule: !1 }), this.updateMealNoticeFromButton(s));
      return;
    }
    s.removeAttribute("aria-disabled"), s.removeAttribute("data-meal-unavailable"), n !== "" ? s.setAttribute("data-meal-notice", n) : s.hasAttribute("data-meal-notice") && s.removeAttribute("data-meal-notice"), s.hasAttribute("data-active") && this.updateMealNoticeFromButton(s);
  }
  applyMealSelection(t) {
    const e = t.getAttribute("data-fp-resv-meal") || "";
    this.hiddenMeal && (this.hiddenMeal.value = e);
    const i = ft(t.getAttribute("data-meal-price"));
    this.hiddenPrice && (this.hiddenPrice.value = i !== null ? String(i) : ""), this.clearSlotSelection({ schedule: !1 }), this.updateMealNoticeFromButton(t), this.updateSubmitState();
  }
  clearSlotSelection(t = {}) {
    this.hiddenSlot && (this.hiddenSlot.value = "");
    const e = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
    if (e && (e.value = "", e.removeAttribute("data-slot-start")), this.availabilityController && typeof this.availabilityController.clearSelection == "function" && this.availabilityController.clearSelection(), this.availabilityRoot) {
      const s = this.availabilityRoot.querySelectorAll('button[data-slot][aria-pressed="true"]');
      Array.prototype.forEach.call(s, (n) => {
        n.setAttribute("aria-pressed", "false");
      });
    }
    const i = this.sections.find((s) => (s.getAttribute("data-step") || "") === "slots");
    if (i) {
      const s = i.getAttribute("data-step") || "", n = this.state.sectionStates[s] || "locked";
      this.updateSectionAttributes(i, "locked", { silent: !0 });
      const r = this.sections.indexOf(i);
      if (r !== -1)
        for (let c = r + 1; c < this.sections.length; c += 1) {
          const p = this.sections[c];
          this.updateSectionAttributes(p, "locked", { silent: !0 });
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
    const r = n.getAttribute("data-step") || String(s + 1);
    this.state.sectionStates[r] !== "completed" && (this.state.sectionStates[r] = "active", this.updateSectionAttributes(n, "active"), this.dispatchSectionUnlocked(r), this.scrollIntoView(n));
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
    v(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const s = t.getAttribute("data-step") || "", n = i && i.silent === !0;
    this.state.sectionStates[s] = e, t.setAttribute("data-state", e), e === "completed" ? t.setAttribute("data-complete-hidden", "true") : t.removeAttribute("data-complete-hidden");
    const r = e === "active";
    t.setAttribute("aria-hidden", r ? "false" : "true"), t.setAttribute("aria-expanded", r ? "true" : "false"), r ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert"), t.style && typeof t.style.removeProperty == "function" && t.style.removeProperty("display")) : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", ""), t.style && typeof t.style.setProperty == "function" && t.style.setProperty("display", "none", "important")), n || this.updateProgressIndicators(), this.updateStickyCtaVisibility();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const s = e.length || 1;
    Array.prototype.forEach.call(e, function(r, c) {
      const p = r.getAttribute("data-step") || "", h = t.state.sectionStates[p] || "locked";
      r.setAttribute("data-state", h), r.setAttribute("data-progress-state", h === "completed" ? "done" : h);
      const b = r.querySelector(".fp-progress__label");
      b && (h === "active" ? b.removeAttribute("aria-hidden") : b.setAttribute("aria-hidden", "true"));
      const m = h === "locked";
      r.tabIndex = m ? -1 : 0, m ? r.setAttribute("aria-disabled", "true") : r.removeAttribute("aria-disabled"), h === "active" ? (r.setAttribute("aria-current", "step"), i = Math.max(i, c + 0.5)) : r.removeAttribute("aria-current"), h === "completed" ? (r.setAttribute("data-completed", "true"), i = Math.max(i, c + 1)) : r.removeAttribute("data-completed");
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
      v(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
    }
  }
  getActiveSectionKey() {
    for (let t = 0; t < this.sections.length; t += 1) {
      const i = this.sections[t].getAttribute("data-step") || "";
      if (i !== "" && this.state.sectionStates[i] === "active")
        return i;
    }
    return "";
  }
  getLastSectionKey() {
    return this.sections.length === 0 ? "" : this.sections[this.sections.length - 1].getAttribute("data-step") || "";
  }
  updateStickyCtaVisibility() {
    if (!this.stickyCta)
      return;
    const t = this.getLastSectionKey();
    if (t === "") {
      this.stickyCta.hidden = !1, this.stickyCta.removeAttribute("hidden"), this.stickyCta.removeAttribute("aria-hidden"), this.stickyCta.removeAttribute("inert"), this.stickyCta.style && typeof this.stickyCta.style.removeProperty == "function" && this.stickyCta.style.removeProperty("display");
      return;
    }
    this.getActiveSectionKey() === t ? (this.stickyCta.hidden = !1, this.stickyCta.removeAttribute("hidden"), this.stickyCta.removeAttribute("aria-hidden"), this.stickyCta.removeAttribute("inert"), this.stickyCta.style && typeof this.stickyCta.style.removeProperty == "function" && this.stickyCta.style.removeProperty("display")) : (this.stickyCta.hidden = !0, this.stickyCta.setAttribute("hidden", ""), this.stickyCta.setAttribute("aria-hidden", "true"), this.stickyCta.setAttribute("inert", ""), this.stickyCta.style && typeof this.stickyCta.style.setProperty == "function" && this.stickyCta.style.setProperty("display", "none", "important"));
  }
  setSubmitButtonState(t, e) {
    if (!this.submitButton)
      return;
    const i = e === "sending" ? !1 : !!t, s = this.state.ctaEnabled;
    ht(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), s !== i && e !== "sending" && v("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), s = this.form.querySelector('[data-fp-resv-field="first_name"]'), n = this.form.querySelector('[data-fp-resv-field="last_name"]'), r = this.form.querySelector('[data-fp-resv-field="email"]'), c = this.form.querySelector('[data-fp-resv-field="phone"]'), p = this.form.querySelector('[data-fp-resv-field="notes"]');
    let h = "";
    s && s.value && (h = s.value.trim()), n && n.value && (h = (h + " " + n.value.trim()).trim());
    let b = "";
    if (r && r.value && (b = r.value.trim()), c && c.value) {
      const m = this.getPhoneCountryCode(), x = (m ? "+" + m + " " : "") + c.value.trim();
      b = b !== "" ? b + " / " + x : x;
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
          m.textContent = b;
          break;
        case "notes":
          m.textContent = p && p.value ? p.value : "";
          break;
      }
    });
  }
  async handleSubmit(t) {
    if (t.preventDefault(), !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    v(e, {
      source: "form",
      form_id: this.form && this.form.id ? this.form.id : this.root.id || "",
      date: i.date,
      party: i.party,
      meal: i.meal
    }), this.preparePhonePayload(), this.state.sending = !0, this.updateSubmitState(), this.clearError();
    const s = this.serializeForm(), n = this.getReservationEndpoint(), r = performance.now();
    let c = 0;
    try {
      const p = await fetch(n, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-WP-Nonce": s.fp_resv_nonce || ""
        },
        body: JSON.stringify(s),
        credentials: "same-origin"
      });
      if (c = Math.round(performance.now() - r), v("ui_latency", { op: "submit", ms: c }), !p.ok) {
        const b = await mt(p), m = b && b.message || this.copy.submitError;
        throw Object.assign(new Error(m), {
          status: p.status,
          payload: b
        });
      }
      const h = await p.json();
      this.handleSubmitSuccess(h);
    } catch (p) {
      c || (c = Math.round(performance.now() - r), v("ui_latency", { op: "submit", ms: c })), this.handleSubmitError(p, c);
    } finally {
      this.state.sending = !1, this.updateSubmitState();
    }
    return !1;
  }
  handleSubmitSuccess(t) {
    this.clearError();
    const e = t && t.message || this.copy.submitSuccess;
    this.successAlert && (this.successAlert.textContent = e, this.successAlert.hidden = !1, typeof this.successAlert.focus == "function" && this.successAlert.focus()), t && Array.isArray(t.tracking) && t.tracking.forEach((i) => {
      i && i.event && v(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", s = t && t.message || this.copy.submitError, n = t && typeof t == "object" && t.payload || null, r = H(s, n);
    this.errorAlert && this.errorMessage && (this.errorMessage.textContent = r, this.errorAlert.hidden = !1), this.state.hintOverride = r, this.updateSubmitState();
    const c = this.events.submit_error || "submit_error";
    v(c, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, s) => {
      typeof i == "string" && (e[s] = i);
    }), this.phoneField) {
      const i = R(this.phoneField, this.getPhoneCountryCode());
      i.e164 && (e.fp_resv_phone = i.e164), i.country && (e.fp_resv_phone_cc = i.country), i.local && (e.fp_resv_phone_local = i.local);
    }
    if (this.phonePrefixField && this.phonePrefixField.value && !e.fp_resv_phone_cc) {
      const i = w(this.phonePrefixField.value);
      i && (e.fp_resv_phone_cc = i);
    }
    return e;
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
    nt(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), v("phone_validation_error", { field: "phone" }), v("ui_validation_error", { field: "phone" }));
  }
  validateEmailField(t) {
    if (t.value.trim() === "") {
      t.setCustomValidity(""), t.removeAttribute("aria-invalid");
      return;
    }
    t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), v("ui_validation_error", { field: "email" }));
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
      meal: t,
      requiresMeal: this.mealButtons.length > 0
    };
  }
  scheduleAvailabilityUpdate(t = {}) {
    const e = t && typeof t == "object" ? { ...t } : {};
    if (!this.availabilityController || typeof this.availabilityController.schedule != "function") {
      this.state.pendingAvailability = !0, this.state.pendingAvailabilityOptions = e;
      return;
    }
    const i = this.collectAvailabilityParams();
    this.state.lastAvailabilityParams = i, this.state.pendingAvailabilityOptions = null, this.availabilityController.schedule(i, e);
  }
  applyMealAvailabilityIndicator(t, e) {
    if (!t)
      return;
    const i = this.mealButtons.find((r) => (r.getAttribute("data-fp-resv-meal") || "") === t);
    if (!i)
      return;
    const s = ["available", "limited", "full"], n = e ? String(e).toLowerCase() : "";
    if (s.indexOf(n) === -1) {
      i.removeAttribute("data-availability-state");
      return;
    }
    i.setAttribute("data-availability-state", n);
  }
  handleMealAvailabilitySummary(t, e) {
    if (!e || !e.meal)
      return;
    const i = t && t.state ? String(t.state).toLowerCase() : "", s = ["available", "limited", "full"], n = e.meal;
    if (this.state.mealAvailability || (this.state.mealAvailability = {}), s.indexOf(i) === -1) {
      delete this.state.mealAvailability[n], this.applyMealAvailabilityIndicator(n, ""), this.applyMealAvailabilityNotice(n, "", { skipSlotReset: !0 });
      return;
    }
    this.state.mealAvailability[n] = i, this.applyMealAvailabilityIndicator(n, i), this.applyMealAvailabilityNotice(n, i);
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
    if (i) {
      const s = i.getAttribute("data-step") || "";
      this.ensureSectionActive(i), this.state.sectionStates[s] !== "active" && this.updateSectionAttributes(i, "active");
    }
    this.updateSummary(), this.updateSubmitState();
  }
  handleAvailabilityLatency(t) {
    v("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    v("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    v(t, { source: "form" }), this.state.started = !0;
  }
  handleDelegatedTrackingEvent(t) {
    const e = t.target instanceof HTMLElement ? t.target : null;
    if (!e)
      return;
    const i = W(e, "data-fp-resv-event");
    if (!i)
      return;
    const s = i.getAttribute("data-fp-resv-event");
    if (!s)
      return;
    let n = ut(i, "data-fp-resv-payload");
    if ((!n || typeof n != "object") && (n = {}), n.trigger || (n.trigger = t.type || "click"), !n.href && i instanceof HTMLAnchorElement && i.href && (n.href = i.href), !n.label) {
      const r = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      r && (n.label = r.trim());
    }
    v(s, n);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    v(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && v(this.events.purchase || "purchase", e.purchase);
  }
  scrollIntoView(t) {
    typeof t.scrollIntoView == "function" && t.scrollIntoView({ behavior: "smooth", block: "start" });
    const e = pt(t);
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
    const r = this.form.querySelector('[data-fp-resv-field="profiling_consent"]');
    r && "checked" in r && (e.personalization = r.checked ? "granted" : "denied", i = !0), i && t.updateConsent(e);
  }
  getPhoneCountryCode() {
    if (this.phonePrefixField && this.phonePrefixField.value) {
      const e = w(this.phonePrefixField.value);
      if (e)
        return e;
    }
    if (this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const e = w(this.hiddenPhoneCc.value);
      if (e)
        return e;
    }
    if (this.phoneCountryCode) {
      const e = w(this.phoneCountryCode);
      if (e)
        return e;
    }
    const t = this.config && this.config.defaults || {};
    if (t.phone_country_code) {
      const e = w(t.phone_country_code);
      if (e)
        return e;
    }
    return "39";
  }
  getReservationEndpoint() {
    const t = this.config.endpoints || {};
    return j(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return j(t.availability, "/wp-json/fp-resv/v1/availability");
  }
  loadExternalScript(t, e, i) {
    if (typeof window > "u" || typeof document > "u")
      return Promise.resolve(null);
    if (typeof e == "function") {
      const s = e();
      if (s)
        return Promise.resolve(s);
    }
    return new Promise((s) => {
      const n = () => {
        if (typeof e == "function") {
          const p = e();
          s(p || null);
          return;
        }
        s(null);
      };
      let r = document.querySelector(`script[src="${t}"]`);
      if (!r && i && (r = document.querySelector(`script[${i}]`)), r) {
        if (typeof e == "function") {
          const p = e();
          if (p) {
            s(p);
            return;
          }
        }
        r.addEventListener("load", n, { once: !0 }), r.addEventListener("error", () => s(null), { once: !0 });
        return;
      }
      r = document.createElement("script"), r.src = t, r.async = !0, i && r.setAttribute(i, "1"), r.onload = n, r.onerror = () => s(null);
      const c = document.head || document.body || document.documentElement;
      if (!c) {
        s(null);
        return;
      }
      c.appendChild(r);
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
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = J);
document.addEventListener("DOMContentLoaded", function() {
  const a = document.querySelectorAll("[data-fp-resv]");
  Array.prototype.forEach.call(a, function(t) {
    new J(t);
  });
});
document.addEventListener("fp-resv:tracking:push", function(a) {
  if (!a || !a.detail)
    return;
  const t = a.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  v(e, i && typeof i == "object" ? i : {});
});
const bt = 400, vt = 6e4, gt = 3, K = 600;
function St(a, t) {
  let e;
  try {
    e = new URL(a, window.location.origin);
  } catch {
    const s = window.location.origin.replace(/\/$/, ""), n = a.startsWith("/") ? s + a : s + "/" + a;
    e = new URL(n, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function D(a) {
  for (; a.firstChild; )
    a.removeChild(a.firstChild);
}
function At(a) {
  const t = a.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), s = t.querySelector("[data-fp-resv-slots-empty]"), n = t.querySelector("[data-fp-resv-slots-boundary]"), r = n ? n.querySelector("[data-fp-resv-slots-retry]") : null, c = /* @__PURE__ */ new Map();
  let p = null, h = null, b = null, m = 0;
  function M(o) {
    if (typeof o != "string")
      return "";
    const d = o.trim().toLowerCase();
    if (d === "")
      return "";
    const u = ((S) => typeof S.normalize == "function" ? S.normalize("NFD").replace(/[\u0300-\u036f]/g, "") : S)(d), y = (S) => S.some((l) => u.startsWith(l)), A = (S) => S.some((l) => u.includes(l));
    return y(["available", "open", "disponibil", "disponible", "liber", "libre", "apert", "abiert"]) ? "available" : d === "waitlist" || d === "busy" || y(["limited", "limit", "limitat", "limite", "cupos limit", "attesa"]) || A(["pochi posti", "quasi pien", "lista attesa", "few spots", "casi llen"]) ? "limited" : y(["full", "complet", "esaurit", "soldout", "sold out", "agotad", "chius", "plen"]) ? "full" : d;
  }
  function x(o, d) {
    const f = Array.isArray(o) ? o : [], u = f.length;
    if (u === 0)
      return { state: "full", slots: 0 };
    const y = f.map((l) => M(l && l.status)).filter((l) => l !== "");
    return y.some((l) => l === "limited") ? { state: "limited", slots: u } : y.some((l) => l === "available") ? { state: "available", slots: u } : d ? { state: "available", slots: u } : y.length === 0 ? { state: "available", slots: u } : { state: "full", slots: u };
  }
  function q(o, d) {
    if (typeof a.onAvailabilitySummary == "function")
      try {
        a.onAvailabilitySummary(d, o || h || {});
      } catch {
      }
  }
  r && r.addEventListener("click", () => {
    h && E(h, 0);
  });
  function L(o, d) {
    const f = typeof d == "string" ? d : d ? "loading" : "idle", u = typeof o == "string" ? o : "";
    e && (e.textContent = u, e.setAttribute("data-state", f));
    const y = f === "loading";
    t.setAttribute("data-loading", y ? "true" : "false"), i && i.setAttribute("aria-busy", y ? "true" : "false");
  }
  function X() {
    if (!i)
      return;
    D(i);
    const o = a.skeletonCount || 4;
    for (let d = 0; d < o; d += 1) {
      const f = document.createElement("li"), u = document.createElement("span");
      u.className = "fp-skeleton", f.appendChild(u), i.appendChild(f);
    }
  }
  function N(o) {
    s && (s.hidden = !1);
    const d = o && typeof o == "object", f = d && typeof o.meal == "string" ? o.meal.trim() : "", u = d && typeof o.date == "string" ? o.date.trim() : "", y = d && typeof o.party < "u" ? String(o.party).trim() : "", A = f !== "", C = A && u !== "" && (y !== "" && y !== "0"), g = A ? C && a.strings && a.strings.slotsEmpty || "" : a.strings && a.strings.selectMeal || "";
    L(g, "idle"), i && D(i), q(o, { state: C ? "full" : "unknown", slots: 0 });
  }
  function $() {
    s && (s.hidden = !0);
  }
  function z() {
    n && (n.hidden = !0);
  }
  function Y(o) {
    const d = a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "We could not update available times. Please try again.";
    if (n) {
      const f = n.querySelector("[data-fp-resv-slots-boundary-message]");
      f && (f.textContent = o || d), n.hidden = !1;
    }
    L(o || d, "error"), q(h, { state: "error", slots: 0 });
  }
  function G(o, d) {
    const f = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(f, (u) => {
      u.setAttribute("aria-pressed", u === d ? "true" : "false");
    }), b = o, typeof a.onSlotSelected == "function" && a.onSlotSelected(o);
  }
  function Q() {
    if (b = null, !i)
      return;
    const o = i.querySelectorAll("button[data-slot]");
    Array.prototype.forEach.call(o, (d) => {
      d.setAttribute("aria-pressed", "false");
    });
  }
  function B(o, d, f) {
    if (f && f !== m || d && h && d !== h || (z(), $(), !i))
      return;
    D(i);
    const u = o && Array.isArray(o.slots) ? o.slots : [];
    if (u.length === 0) {
      N(d);
      return;
    }
    u.forEach((A) => {
      const S = document.createElement("li"), l = document.createElement("button");
      l.type = "button", l.textContent = A.label || "", l.dataset.slot = A.start || "", l.dataset.slotStatus = A.status || "", l.setAttribute("aria-pressed", b && b.start === A.start ? "true" : "false"), l.addEventListener("click", () => G(A, l)), S.appendChild(l), i.appendChild(S);
    }), L(a.strings && a.strings.slotsUpdated || "", !1);
    const y = !!(o && (typeof o.has_availability < "u" && o.has_availability || o.meta && o.meta.has_availability));
    q(d, x(u, y));
  }
  function E(o, d) {
    if (h = o, !o || !o.date || !o.party) {
      N(o);
      return;
    }
    const f = ++m, u = JSON.stringify([o.date, o.meal, o.party]), y = c.get(u);
    if (y && Date.now() - y.timestamp < vt && d === 0) {
      B(y.payload, o, f);
      return;
    }
    z(), X(), L(a.strings && a.strings.updatingSlots || "Updating availability…", "loading"), q(o, { state: "loading", slots: 0 });
    const A = St(a.endpoint, o), S = performance.now();
    fetch(A, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((l) => l.json().catch(() => ({})).then((C) => {
      if (!l.ok) {
        const g = new Error("availability_error");
        g.status = l.status, g.payload = C;
        const k = l.headers.get("Retry-After");
        if (k) {
          const P = Number.parseInt(k, 10);
          Number.isFinite(P) && (g.retryAfter = P);
        }
        throw g;
      }
      return C;
    })).then((l) => {
      if (f !== m)
        return;
      const C = performance.now() - S;
      typeof a.onLatency == "function" && a.onLatency(C), c.set(u, { payload: l, timestamp: Date.now() }), B(l, o, f);
    }).catch((l) => {
      if (f !== m)
        return;
      const C = performance.now() - S;
      typeof a.onLatency == "function" && a.onLatency(C);
      const g = l && l.payload && typeof l.payload == "object" ? l.payload.data || {} : {}, k = typeof l.status == "number" ? l.status : g && typeof g.status == "number" ? g.status : 0;
      let P = 0;
      if (l && typeof l.retryAfter == "number" && Number.isFinite(l.retryAfter))
        P = l.retryAfter;
      else if (g && typeof g.retry_after < "u") {
        const _ = Number.parseInt(g.retry_after, 10);
        Number.isFinite(_) && (P = _);
      }
      if (d >= gt - 1 ? !1 : k === 429 || k >= 500 && k < 600 ? !0 : k === 0) {
        const _ = d + 1;
        typeof a.onRetry == "function" && a.onRetry(_);
        const it = P > 0 ? Math.max(P * 1e3, K) : K * Math.pow(2, d);
        window.setTimeout(() => E(o, _), it);
        return;
      }
      const Z = l && l.payload && (l.payload.message || l.payload.code) || g && g.message || a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "We could not update available times. Please try again.", tt = l && l.payload || g || null, et = H(Z, tt);
      Y(et);
    });
  }
  return {
    schedule(o, d = {}) {
      p && window.clearTimeout(p);
      const f = d && typeof d == "object" ? d : {}, u = o || (typeof a.getParams == "function" ? a.getParams() : null), y = !!(u && u.requiresMeal);
      if (!u || !u.date || !u.party || y && !u.meal) {
        h = u, N(u || {});
        return;
      }
      if (f.immediate) {
        E(u, 0);
        return;
      }
      p = window.setTimeout(() => {
        E(u, 0);
      }, bt);
    },
    revalidate() {
      if (!h)
        return;
      const o = JSON.stringify([h.date, h.meal, h.party]);
      c.delete(o), E(h, 0);
    },
    getSelection() {
      return b;
    },
    clearSelection() {
      Q();
    }
  };
}
const wt = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: At
}, Symbol.toStringTag, { value: "Module" }));
