function b(a, t) {
  if (!a)
    return null;
  const e = Object.assign({ event: a }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
const ut = /\D+/g;
function Z(a) {
  return a ? String(a).replace(ut, "") : "";
}
function P(a) {
  const t = Z(a);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function N(a) {
  return Z(a);
}
function ht(a, t) {
  const e = P(a), i = N(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function K(a) {
  const t = N(a);
  return t.length >= 6 && t.length <= 15;
}
function ft(a) {
  const t = N(a);
  if (t === "")
    return { masked: "", digits: "" };
  const e = [3, 4], i = [];
  let s = 0, n = 0;
  for (; s < t.length; ) {
    const r = t.length - s;
    let o = e[n % e.length];
    r <= 4 && (o = r), i.push(t.slice(s, s + o)), s += o, n += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function z(a, t) {
  const e = a.value, { masked: i } = ft(e), s = a.selectionStart;
  if (a.value = i, s !== null) {
    const n = i.length - e.length, r = Math.max(0, s + n);
    a.setSelectionRange(r, r);
  }
  a.setAttribute("data-phone-local", N(a.value)), a.setAttribute("data-phone-cc", P(t));
}
function T(a, t) {
  const e = N(a.value), i = P(t);
  return {
    e164: ht(i, e),
    local: e,
    country: i
  };
}
function j(a) {
  if (a == null)
    return "";
  if (typeof a == "string")
    return a.trim();
  if (Array.isArray(a))
    return a.map((e) => j(e)).filter((e) => e !== "").join("; ");
  if (typeof a == "object") {
    if (typeof a.message == "string" && a.message.trim() !== "")
      return a.message.trim();
    if (typeof a.detail == "string" && a.detail.trim() !== "")
      return a.detail.trim();
  }
  return String(a).trim();
}
function pt(a) {
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
      const s = j(e);
      if (s !== "")
        return s;
      continue;
    }
    const i = ["details", "detail", "debug", "error"];
    for (let s = 0; s < i.length; s += 1) {
      const n = i[s];
      if (Object.prototype.hasOwnProperty.call(e, n)) {
        const r = j(e[n]);
        if (r !== "")
          return r;
      }
    }
    Object.prototype.hasOwnProperty.call(e, "data") && e.data && typeof e.data == "object" && t.push(e.data);
  }
  return "";
}
function tt(a, t) {
  const e = pt(t);
  return e === "" ? a : a ? a.includes(e) ? a : a + " (" + e + ")" : e;
}
function yt(a) {
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
function mt(a, t) {
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
function vt(a) {
  if (a == null)
    return null;
  if (typeof a == "number")
    return Number.isFinite(a) ? a : null;
  const t = String(a).replace(",", "."), e = parseFloat(t);
  return Number.isNaN(e) ? null : e;
}
function et(a, t) {
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
function bt(a, t) {
  a && (t ? (a.setAttribute("aria-disabled", "true"), a.setAttribute("disabled", "disabled")) : (a.removeAttribute("disabled"), a.setAttribute("aria-disabled", "false")));
}
function U(a) {
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
function $(a, t) {
  if (a && typeof a == "string")
    try {
      return new URL(a, window.location.origin).toString();
    } catch {
      return a;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
const gt = ["service", "date", "party", "slots", "details", "confirm"], J = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (a) => window.requestIdleCallback(a) : (a) => window.setTimeout(() => a(Date.now()), 1);
let O = null;
function St() {
  return O || (O = Promise.resolve().then(() => _t)), O;
}
function At(a) {
  return et(a, "data-fp-resv-section");
}
class it {
  constructor(t) {
    this.root = t, this.dataset = yt(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(gt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((s) => s.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((s, n) => this.getStepOrderIndex(s) - this.getStepOrderIndex(n)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((s, n) => this.getStepOrderIndex(s) - this.getStepOrderIndex(n)).forEach((s) => {
      this.progress.appendChild(s);
    }), this.submitButton = this.form ? this.form.querySelector("[data-fp-resv-submit]") : null, this.submitLabel = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-label]") || this.submitButton : null, this.submitSpinner = this.submitButton ? this.submitButton.querySelector("[data-fp-resv-submit-spinner]") : null, this.submitHint = this.form ? this.form.querySelector("[data-fp-resv-submit-hint]") : null, this.stickyCta = this.form ? this.form.querySelector("[data-fp-resv-sticky-cta]") : null, this.successAlert = this.form ? this.form.querySelector("[data-fp-resv-success]") : null, this.errorAlert = this.form ? this.form.querySelector("[data-fp-resv-error]") : null, this.errorMessage = this.form ? this.form.querySelector("[data-fp-resv-error-message]") : null, this.errorRetry = this.form ? this.form.querySelector("[data-fp-resv-error-retry]") : null, this.mealButtons = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-meal]")), this.mealNotice = t.querySelector("[data-fp-resv-meal-notice]"), this.mealNoticeText = this.mealNotice ? this.mealNotice.querySelector("[data-fp-resv-meal-notice-text]") || this.mealNotice : null, this.hiddenMeal = this.form ? this.form.querySelector('input[name="fp_resv_meal"]') : null, this.hiddenPrice = this.form ? this.form.querySelector('input[name="fp_resv_price_per_person"]') : null, this.hiddenSlot = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, this.dateField = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null, this.partyField = this.form ? this.form.querySelector('[data-fp-resv-field="party"]') : null, this.summaryTargets = Array.prototype.slice.call(t.querySelectorAll("[data-fp-resv-summary]")), this.phoneField = this.form ? this.form.querySelector('[data-fp-resv-field="phone"]') : null, this.phonePrefixField = this.form ? this.form.querySelector('[data-fp-resv-field="phone_prefix"]') : null, this.hiddenPhoneE164 = this.form ? this.form.querySelector('input[name="fp_resv_phone_e164"]') : null, this.hiddenPhoneCc = this.form ? this.form.querySelector('input[name="fp_resv_phone_cc"]') : null, this.hiddenPhoneLocal = this.form ? this.form.querySelector('input[name="fp_resv_phone_local"]') : null, this.availabilityRoot = this.form ? this.form.querySelector("[data-fp-resv-slots]") : null, this.availabilityIndicator = this.form ? this.form.querySelector("[data-fp-resv-availability-indicator]") : null, this.slotsLegend = this.form ? this.form.querySelector("[data-fp-resv-slots-legend]") : null, this.state = {
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
      mealAvailability: {},
      touchedFields: {}
    }, this.copy = {
      ctaDisabled: this.messages.cta_complete_fields || "Completa i campi richiesti",
      ctaEnabled: this.messages.cta_book_now || this.strings.actions && this.strings.actions.submit || "Prenota ora",
      ctaSending: this.messages.cta_sending || "Invio…",
      updatingSlots: this.messages.msg_updating_slots || "Aggiornamento disponibilità…",
      slotsUpdated: this.messages.msg_slots_updated || "Disponibilità aggiornata.",
      slotsEmpty: this.messages.slots_empty || "",
      selectMeal: this.messages.msg_select_meal || "Seleziona un servizio per visualizzare gli orari disponibili.",
      slotsError: this.messages.msg_slots_error || "Impossibile aggiornare la disponibilità. Riprova.",
      dateRequired: this.messages.date_required || "Seleziona una data per continuare.",
      slotRequired: this.messages.slot_required || "Seleziona un orario per continuare.",
      invalidPhone: this.messages.msg_invalid_phone || "Inserisci un numero di telefono valido (minimo 6 cifre).",
      invalidEmail: this.messages.msg_invalid_email || "Inserisci un indirizzo email valido.",
      submitError: this.messages.msg_submit_error || "Non è stato possibile completare la prenotazione. Riprova.",
      submitSuccess: this.messages.msg_submit_success || "Prenotazione inviata con successo.",
      mealFullNotice: this.messages.meal_full_notice || "Nessuna disponibilità per questo servizio. Scegli un altro giorno."
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateInlineErrors(), this.updateSummary(), J(() => {
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
    this.phoneField && z(this.phoneField, this.getPhoneCountryCode());
  }
  updatePhoneCountryFromPrefix() {
    if (!this.phonePrefixField)
      return;
    const t = P(this.phonePrefixField.value);
    let e = t;
    if (e === "" && this.phoneCountryCode) {
      const i = P(this.phoneCountryCode);
      i && (e = i);
    }
    if (e === "" && this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const i = P(this.hiddenPhoneCc.value);
      i && (e = i);
    }
    if (e === "") {
      const i = this.config && this.config.defaults || {};
      if (i.phone_country_code) {
        const s = P(i.phone_country_code);
        s && (e = s);
      }
    }
    e === "" && (e = "39"), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = e), t !== "" && (this.phoneCountryCode = t), this.phoneField && z(this.phoneField, e);
  }
  initializeDateField() {
    if (!this.dateField)
      return;
    const t = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
    this.dateField.setAttribute("min", t), this.currentAvailableDays = this.config && this.config.available_days ? this.config.available_days : [], this.dateField.addEventListener("change", (i) => {
      const s = i.target.value;
      if (s && s < t) {
        i.target.setCustomValidity("Non è possibile prenotare per giorni passati."), i.target.setAttribute("aria-invalid", "true");
        return;
      }
      if (this.currentAvailableDays.length > 0 && s) {
        const r = new Date(s).getDay().toString();
        if (!this.currentAvailableDays.includes(r)) {
          const o = ["domenica", "lunedì", "martedì", "mercoledì", "giovedì", "venerdì", "sabato"], f = `Questo giorno non è disponibile. Giorni disponibili: ${this.currentAvailableDays.map((y) => o[parseInt(y)]).join(", ")}.`;
          i.target.setCustomValidity(f), i.target.setAttribute("aria-invalid", "true"), window.console && window.console.warn && console.warn("[FP-RESV] " + f), setTimeout(() => {
            i.target.value = "";
          }, 100);
          return;
        }
      }
      i.target.setCustomValidity(""), i.target.setAttribute("aria-invalid", "false");
    });
    const e = () => {
      if (typeof this.dateField.focus == "function" && this.dateField.focus(), typeof this.dateField.showPicker == "function")
        try {
          this.dateField.showPicker();
        } catch {
        }
    };
    this.dateField.addEventListener("click", e);
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
    J(() => {
      St().then((e) => {
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
    this.handleFirstInteraction(), e === this.phoneField ? z(this.phoneField, this.getPhoneCountryCode()) : e === this.phonePrefixField && this.updatePhoneCountryFromPrefix(), this.updateSummary();
    const i = e.getAttribute("data-fp-resv-field") || "", s = i && e.dataset.fpResvLastValue || "", n = i && typeof e.value == "string" ? e.value : "", r = !i || s !== n, o = At(e);
    if (!o) {
      this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
      return;
    }
    this.ensureSectionActive(o), this.updateSectionAttributes(o, "active"), i && (e.dataset.fpResvLastValue = n), (i === "date" || i === "party" || i === "slots" || i === "time") && ((i === "date" || i === "party") && r && (this.clearSlotSelection({ schedule: !1 }), this.state.mealAvailability = {}), (i !== "date" || r || t.type === "change") && this.scheduleAvailabilityUpdate()), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState(), this.updateInlineErrors();
  }
  handleFieldBlur(t) {
    const e = t.target;
    if (!e || !(e instanceof HTMLElement))
      return;
    const i = e.getAttribute("data-fp-resv-field");
    i && (this.state.touchedFields[i] = !0, i === "phone" && this.phoneField && this.validatePhoneField(), i === "email" && e instanceof HTMLInputElement && this.validateEmailField(e), this.updateInlineErrors());
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
    t.preventDefault(), t.stopPropagation(), this.handleFirstInteraction();
    const s = e.getAttribute("data-fp-resv-nav");
    console.log("[FP-RESV] Navigation click:", s, "section:", i.getAttribute("data-step")), s === "prev" ? this.navigateToPrevious(i) : s === "next" && this.navigateToNext(i);
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
        const o = this.state.sectionStates[n] === "locked" ? "locked" : "completed";
        this.updateSectionAttributes(s, o, { silent: !0 });
      }
    }), this.updateProgressIndicators(), requestAnimationFrame(() => {
      const s = e.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
      s && typeof s.focus == "function" && s.focus({ preventScroll: !0 });
    }), this.updateSubmitState();
  }
  handleRetrySubmit(t) {
    t.preventDefault(), this.clearError(), this.errorRetry && (this.errorRetry.textContent = this.messages.retry_button || "Riprova", this.errorRetry.onclick = null), this.focusFirstInvalid(), this.updateSubmitState();
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
    b(s, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), this.updateAvailableDaysForMeal(e), this.scheduleAvailabilityUpdate({ immediate: !0 });
  }
  updateAvailableDaysForMeal(t) {
    if (!this.dateField || !t)
      return;
    const i = (this.config && this.config.meals ? this.config.meals : []).find((n) => n.key === t);
    i && i.available_days && i.available_days.length > 0 ? this.currentAvailableDays = i.available_days : this.currentAvailableDays = this.config && this.config.available_days ? this.config.available_days : [];
    const s = this.dateField.value;
    if (s && this.currentAvailableDays.length > 0) {
      const r = new Date(s).getDay().toString();
      if (!this.currentAvailableDays.includes(r)) {
        const o = ["domenica", "lunedì", "martedì", "mercoledì", "giovedì", "venerdì", "sabato"], u = this.currentAvailableDays.map((f) => o[parseInt(f)]).join(", ");
        window.console && window.console.warn && console.warn(`[FP-RESV] La data selezionata non è disponibile per questo servizio. Giorni disponibili: ${u}.`), this.dateField.value = "", this.dateField.setCustomValidity(""), this.dateField.setAttribute("aria-invalid", "false"), this.availabilityController && typeof this.availabilityController.clear == "function" && this.availabilityController.clear();
      }
    }
  }
  updateMealNoticeFromButton(t, e) {
    if (!this.mealNotice)
      return;
    const i = typeof e == "string" ? e : t && t.getAttribute("data-meal-notice") || "", s = i ? i.trim() : "", n = this.mealNoticeText || this.mealNotice;
    s !== "" && n ? (n.textContent = s, this.mealNotice.hidden = !1) : n && (n.textContent = "", this.mealNotice.hidden = !0);
  }
  applyMealAvailabilityNotice(t, e, i = {}) {
    const s = this.mealButtons.find((o) => (o.getAttribute("data-fp-resv-meal") || "") === t);
    if (!s)
      return;
    const n = s.getAttribute("data-meal-default-notice") || "", r = typeof e == "string" ? e : "";
    if (r === "full") {
      const o = this.copy.mealFullNotice || n;
      o !== "" ? s.setAttribute("data-meal-notice", o) : n === "" && s.removeAttribute("data-meal-notice"), s.setAttribute("aria-disabled", "true"), s.setAttribute("data-meal-unavailable", "true"), s.hasAttribute("data-active") && (i.skipSlotReset !== !0 && this.clearSlotSelection({ schedule: !1 }), this.updateMealNoticeFromButton(s));
      return;
    }
    if (r === "unavailable") {
      s.setAttribute("data-meal-notice", "Orari di servizio non configurati per questa data."), s.setAttribute("aria-disabled", "true"), s.setAttribute("data-meal-unavailable", "true"), s.hasAttribute("data-active") && (i.skipSlotReset !== !0 && this.clearSlotSelection({ schedule: !1 }), this.updateMealNoticeFromButton(s));
      return;
    }
    s.removeAttribute("aria-disabled"), s.removeAttribute("data-meal-unavailable"), n !== "" ? s.setAttribute("data-meal-notice", n) : s.hasAttribute("data-meal-notice") && s.removeAttribute("data-meal-notice"), s.hasAttribute("data-active") && this.updateMealNoticeFromButton(s);
  }
  applyMealSelection(t) {
    const e = t.getAttribute("data-fp-resv-meal") || "";
    this.hiddenMeal && (this.hiddenMeal.value = e);
    const i = vt(t.getAttribute("data-meal-price"));
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
        for (let o = r + 1; o < this.sections.length; o += 1) {
          const u = this.sections[o];
          this.updateSectionAttributes(u, "locked", { silent: !0 });
        }
      this.updateProgressIndicators(), (t.forceRewind && s || n === "completed" || n === "active") && this.activateSectionByKey(s);
    }
    t.schedule !== !1 && this.scheduleAvailabilityUpdate(), this.updateSummary(), this.updateSubmitState();
  }
  ensureSectionActive(t) {
    const e = t.getAttribute("data-step") || "";
    this.state.sectionStates[e] === "locked" && (this.state.sectionStates[e] = "active", this.updateSectionAttributes(t, "active"), this.dispatchSectionUnlocked(e));
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
    const e = t.getAttribute("data-step") || "";
    if (e === "date") {
      const i = this.form ? this.form.querySelector('[data-fp-resv-field="date"]') : null;
      if (!i || i.value.trim() === "") {
        const s = this.sections.find((n) => (n.getAttribute("data-step") || "") === "date");
        if (s) {
          const n = s.querySelector("[data-fp-resv-date-status]");
          n && (n.textContent = this.copy.dateRequired || "Seleziona una data per continuare.", n.style.color = "#dc2626", n.setAttribute("data-state", "error"), n.hidden = !1, n.removeAttribute("hidden"), setTimeout(() => {
            n.textContent = "", n.style.color = "", n.removeAttribute("data-state"), n.hidden = !0, n.setAttribute("hidden", "");
          }, 3e3));
        }
        return;
      }
      this.completeSection(t, !0);
      return;
    }
    if (e === "party") {
      this.completeSection(t, !0);
      return;
    }
    if (e === "slots") {
      const i = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null, s = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null;
      if (!i || i.value.trim() === "" || !s || s.value.trim() === "") {
        const n = this.sections.find((r) => (r.getAttribute("data-step") || "") === "slots");
        if (n) {
          const r = n.querySelector("[data-fp-resv-slots-status]");
          r && (r.textContent = this.copy.slotRequired || "Seleziona un orario per continuare.", r.style.color = "#dc2626", r.setAttribute("data-state", "error"), r.hidden = !1, r.removeAttribute("hidden"), setTimeout(() => {
            r.textContent = "", r.style.color = "", r.removeAttribute("data-state"), r.hidden = !0, r.setAttribute("hidden", "");
          }, 3e3));
        }
        return;
      }
    }
    if (!this.isSectionValid(t)) {
      const i = this.findFirstInvalid(t);
      i && (typeof i.reportValidity == "function" && i.reportValidity(), typeof i.focus == "function" && i.focus({ preventScroll: !1 }));
      return;
    }
    this.completeSection(t, !0);
  }
  dispatchSectionUnlocked(t) {
    if (this.state.unlocked[t])
      return;
    this.state.unlocked[t] = !0;
    const e = this.events.section_unlocked || "section_unlocked";
    b(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const s = t.getAttribute("data-step") || "", n = i && i.silent === !0;
    console.log(`[FP-RESV] updateSectionAttributes: step=${s}, state=${e}, silent=${n}`), this.state.sectionStates[s] = e, t.setAttribute("data-state", e), e === "completed" ? t.setAttribute("data-complete-hidden", "true") : t.removeAttribute("data-complete-hidden");
    const r = e === "active";
    t.setAttribute("aria-expanded", r ? "true" : "false"), r ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert"), t.style.display = "block", t.style.visibility = "visible", t.style.opacity = "1", console.log(`[FP-RESV] Step ${s} made visible`)) : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", ""), t.style.display = "none", t.style.visibility = "hidden", t.style.opacity = "0", console.log(`[FP-RESV] Step ${s} hidden`)), n || this.updateProgressIndicators(), this.updateStickyCtaVisibility();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const s = e.length || 1;
    Array.prototype.forEach.call(e, function(r, o) {
      const u = r.getAttribute("data-step") || "", f = t.state.sectionStates[u] || "locked";
      r.setAttribute("data-state", f), r.setAttribute("data-progress-state", f === "completed" ? "done" : f);
      const y = r.querySelector(".fp-progress__label");
      y && (f === "active" ? y.removeAttribute("aria-hidden") : y.setAttribute("aria-hidden", "true"));
      const A = f === "locked";
      r.tabIndex = A ? -1 : 0, A ? r.setAttribute("aria-disabled", "true") : r.removeAttribute("aria-disabled"), f === "active" ? (r.setAttribute("aria-current", "step"), i = Math.max(i, o + 0.5)) : r.removeAttribute("aria-current"), f === "completed" ? (r.setAttribute("data-completed", "true"), i = Math.max(i, o + 1)) : r.removeAttribute("data-completed");
    });
    const n = Math.min(100, Math.max(0, Math.round(i / s * 100)));
    this.progress.style.setProperty("--fp-progress-fill", n + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    if ((t.getAttribute("data-step") || "") === "slots") {
      const n = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null, r = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, o = n && n.value.trim() !== "", u = r && r.value.trim() !== "";
      if (!o || !u)
        return !1;
    }
    let s = !0;
    return Array.prototype.forEach.call(e, function(n) {
      typeof n.checkValidity == "function" && !n.checkValidity() && (s = !1);
    }), s;
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
      b(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
    }
  }
  updateInlineErrors() {
    if (!this.form)
      return;
    const t = {
      first_name: this.form.querySelector('[data-fp-resv-field="first_name"]'),
      last_name: this.form.querySelector('[data-fp-resv-field="last_name"]'),
      email: this.form.querySelector('[data-fp-resv-field="email"]'),
      phone: this.form.querySelector('[data-fp-resv-field="phone"]'),
      consent: this.form.querySelector('[data-fp-resv-field="consent"]')
    }, e = {
      first_name: this.strings?.messages?.required_first_name || "Inserisci il nome",
      last_name: this.strings?.messages?.required_last_name || "Inserisci il cognome",
      email: this.copy.invalidEmail,
      phone: this.copy.invalidPhone,
      consent: this.strings?.messages?.required_consent || "Accetta la privacy per procedere"
    };
    Object.keys(t).forEach((i) => {
      const s = t[i], n = this.form.querySelector(`[data-fp-resv-error="${i}"]`);
      if (!n)
        return;
      if (i === "consent" && !this.state.touchedFields[i]) {
        n.textContent = "", n.hidden = !0;
        return;
      }
      let r = !1, o = "";
      if (s && typeof s.checkValidity == "function" && !s.checkValidity() && (r = !0, o = e[i] || ""), i === "email" && s && s.value && s.value.trim() !== "" && s.checkValidity() && (r = !1, o = ""), i === "phone" && this.phoneField) {
        const u = T(this.phoneField, this.getPhoneCountryCode());
        u.local && !K(u.local) && (r = !0, o = this.copy.invalidPhone);
      }
      i === "consent" && s && s.checked && (r = !1, o = ""), r ? (n.textContent = o, n.hidden = !1, s && s.setAttribute && s.setAttribute("aria-invalid", "true")) : (n.textContent = "", n.hidden = !0, s && s.removeAttribute && s.removeAttribute("aria-invalid"));
    });
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
    bt(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), s !== i && e !== "sending" && b("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), s = this.form.querySelector('[data-fp-resv-field="first_name"]'), n = this.form.querySelector('[data-fp-resv-field="last_name"]'), r = this.form.querySelector('[data-fp-resv-field="email"]'), o = this.form.querySelector('[data-fp-resv-field="phone"]'), u = this.form.querySelector('[data-fp-resv-field="notes"]'), f = this.form.querySelector('[data-fp-resv-field="high_chair_count"]'), y = this.form.querySelector('[data-fp-resv-field="wheelchair_table"]'), A = this.form.querySelector('[data-fp-resv-field="pets"]');
    let F = "";
    s && s.value && (F = s.value.trim()), n && n.value && (F = (F + " " + n.value.trim()).trim());
    let w = "";
    if (r && r.value && (w = r.value.trim()), o && o.value) {
      const E = this.getPhoneCountryCode(), I = (E ? "+" + E + " " : "") + o.value.trim();
      w = w !== "" ? w + " / " + I : I;
    }
    const v = [];
    f && typeof f.value == "string" && parseInt(f.value, 10) > 0 && v.push("Seggioloni: " + parseInt(f.value, 10)), y && "checked" in y && y.checked && v.push("Tavolo accessibile per sedia a rotelle"), A && "checked" in A && A.checked && v.push("Animali domestici");
    const M = v.join("; ");
    this.summaryTargets.forEach(function(E) {
      switch (E.getAttribute("data-fp-resv-summary")) {
        case "date":
          E.textContent = t && t.value ? t.value : "";
          break;
        case "time":
          E.textContent = e && e.value ? e.value : "";
          break;
        case "party":
          E.textContent = i && i.value ? i.value : "";
          break;
        case "name":
          E.textContent = F;
          break;
        case "contact":
          E.textContent = w;
          break;
        case "notes":
          E.textContent = u && u.value ? u.value : "";
          break;
        case "extras":
          E.textContent = M;
          break;
      }
    });
  }
  async handleSubmit(t) {
    if (t.preventDefault(), this.state.sending)
      return !1;
    if (this.state.touchedFields.consent = !0, !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateInlineErrors(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    b(e, {
      source: "form",
      form_id: this.form && this.form.id ? this.form.id : this.root.id || "",
      date: i.date,
      party: i.party,
      meal: i.meal
    }), this.preparePhonePayload(), this.state.sending = !0, this.updateSubmitState(), this.clearError();
    const s = this.serializeForm();
    this.state.requestId || (this.state.requestId = "req_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)), s.request_id = this.state.requestId;
    const n = this.getReservationEndpoint(), r = performance.now();
    let o = 0;
    try {
      const u = await fetch(n, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(s),
        credentials: "same-origin"
      });
      if (o = Math.round(performance.now() - r), b("ui_latency", { op: "submit", ms: o }), !u.ok) {
        const y = await U(u);
        if (u.status === 403 && !this.state.nonceRetried) {
          await new Promise((w) => setTimeout(w, 500));
          const F = await this.refreshNonce();
          if (F) {
            this.state.nonceRetried = !0, s.fp_resv_nonce = F, await new Promise((v) => setTimeout(v, 200));
            const w = await fetch(n, {
              method: "POST",
              headers: {
                Accept: "application/json",
                "Content-Type": "application/json"
              },
              body: JSON.stringify(s),
              credentials: "same-origin"
            });
            if (w.ok) {
              const v = await w.json();
              return this.handleSubmitSuccess(v), this.state.nonceRetried = !1, !1;
            } else {
              const v = await U(w);
              throw v && v.message && (v.message = v.message + " Se hai appena accettato i cookie, riprova tra qualche secondo."), Object.assign(new Error(v.message || this.copy.submitError), {
                status: w.status,
                payload: v
              });
            }
          }
        }
        const A = y && y.message || this.copy.submitError;
        throw Object.assign(new Error(A), {
          status: u.status,
          payload: y
        });
      }
      const f = await u.json();
      this.handleSubmitSuccess(f), this.state.requestId = null;
    } catch (u) {
      o || (o = Math.round(performance.now() - r), b("ui_latency", { op: "submit", ms: o })), this.handleSubmitError(u, o);
    } finally {
      this.state.sending = !1, this.updateSubmitState();
    }
    return !1;
  }
  handleSubmitSuccess(t) {
    this.clearError();
    const e = t && t.message || this.copy.submitSuccess;
    if (this.successAlert && (this.successAlert.textContent = e, this.successAlert.hidden = !1, typeof this.successAlert.focus == "function" && this.successAlert.focus()), this.form) {
      this.form.setAttribute("data-state", "submitted");
      const i = this.form.querySelectorAll("input, select, textarea, button");
      Array.prototype.forEach.call(i, (s) => {
        try {
          s.setAttribute("disabled", "disabled");
        } catch {
        }
      });
    }
    t && Array.isArray(t.tracking) && t.tracking.forEach((i) => {
      i && i.event && b(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", s = t && t.message || this.copy.submitError, n = t && typeof t == "object" && t.payload || null;
    let r = tt(s, n);
    i === 403 && this.errorAlert && this.errorRetry && (this.errorRetry.textContent = this.messages.reload_button || "Ricarica pagina", this.errorRetry.onclick = (u) => {
      u.preventDefault(), window.location.reload();
    }), this.errorAlert && this.errorMessage && (this.errorMessage.textContent = r, this.errorAlert.hidden = !1, requestAnimationFrame(() => {
      typeof this.errorAlert.scrollIntoView == "function" && this.errorAlert.scrollIntoView({ behavior: "smooth", block: "center" }), typeof this.errorAlert.focus == "function" && (this.errorAlert.setAttribute("tabindex", "-1"), this.errorAlert.focus({ preventScroll: !0 }));
    })), this.state.hintOverride = r, this.updateSubmitState();
    const o = this.events.submit_error || "submit_error";
    b(o, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.errorRetry && (this.errorRetry.textContent = this.messages.retry_button || "Riprova", this.errorRetry.onclick = null), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, s) => {
      typeof i == "string" && (e[s] = i);
    }), this.phoneField) {
      const i = T(this.phoneField, this.getPhoneCountryCode());
      i.e164 && (e.fp_resv_phone = i.e164), i.country && (e.fp_resv_phone_cc = i.country), i.local && (e.fp_resv_phone_local = i.local);
    }
    if (this.phonePrefixField && this.phonePrefixField.value && !e.fp_resv_phone_cc) {
      const i = P(this.phonePrefixField.value);
      i && (e.fp_resv_phone_cc = i);
    }
    return e;
  }
  async refreshNonce() {
    try {
      const t = this.getReservationEndpoint().replace("/reservations", "/nonce"), e = await fetch(t, {
        method: "GET",
        headers: {
          Accept: "application/json"
        },
        credentials: "same-origin"
      });
      if (e.ok) {
        const i = await e.json(), s = this.form.querySelector('input[name="fp_resv_nonce"]');
        return s && i.nonce && (s.value = i.nonce), i.nonce || null;
      }
    } catch (t) {
      window.console && window.console.warn && console.warn("[fp-resv] Impossibile rigenerare il nonce", t);
    }
    return null;
  }
  preparePhonePayload() {
    if (!this.phoneField)
      return;
    const t = T(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = T(this.phoneField, this.getPhoneCountryCode());
    if (t.local === "") {
      this.phoneField.setCustomValidity(""), this.phoneField.removeAttribute("aria-invalid");
      return;
    }
    K(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), b("phone_validation_error", { field: "phone" }), b("ui_validation_error", { field: "phone" }));
  }
  validateEmailField(t) {
    if (typeof t.value == "string") {
      const e = t.value.trim();
      e !== t.value && (t.value = e);
    }
    if (t.value.trim() === "") {
      t.setCustomValidity(""), t.removeAttribute("aria-invalid");
      return;
    }
    t.setCustomValidity(""), t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), b("ui_validation_error", { field: "email" }));
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
    const s = ["available", "limited", "full", "unavailable"], n = e ? String(e).toLowerCase() : "";
    i.removeAttribute("data-availability-state"), n === "full" || n === "unavailable" ? (i.setAttribute("aria-disabled", "true"), i.setAttribute("data-meal-unavailable", "true")) : s.indexOf(n) !== -1 && (i.removeAttribute("aria-disabled"), i.removeAttribute("data-meal-unavailable"));
  }
  handleMealAvailabilitySummary(t, e) {
    if (!e || !e.meal)
      return;
    const i = t && t.state ? String(t.state).toLowerCase() : "", s = ["available", "limited", "full", "unavailable"], n = e.meal;
    if (this.state.mealAvailability || (this.state.mealAvailability = {}), s.indexOf(i) === -1) {
      delete this.state.mealAvailability[n], this.applyMealAvailabilityIndicator(n, ""), this.applyMealAvailabilityNotice(n, "", { skipSlotReset: !0 });
      return;
    }
    if (this.state.mealAvailability[n] = i, this.applyMealAvailabilityIndicator(n, i), this.applyMealAvailabilityNotice(n, i), this.slotsLegend && this.slotsLegend.hidden && (this.slotsLegend.hidden = !1, this.slotsLegend.removeAttribute("hidden")), this.availabilityIndicator) {
      let r = "";
      if (t && typeof t == "object") {
        const o = typeof t.slots == "number" ? t.slots : 0;
        i === "available" ? r = `Disponibile (${o})` : i === "limited" ? r = `Disponibilità limitata (${o})` : i === "full" ? r = "Completamente prenotato" : i === "unavailable" && (r = "Non disponibile per questa data");
      }
      this.availabilityIndicator.textContent = r, this.availabilityIndicator.hidden = r === "", this.availabilityIndicator.setAttribute("data-state", i || "");
    }
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
    b("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    b("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    b(t, { source: "form" }), this.state.started = !0;
  }
  handleDelegatedTrackingEvent(t) {
    const e = t.target instanceof HTMLElement ? t.target : null;
    if (!e)
      return;
    const i = et(e, "data-fp-resv-event");
    if (!i)
      return;
    const s = i.getAttribute("data-fp-resv-event");
    if (!s)
      return;
    let n = mt(i, "data-fp-resv-payload");
    if ((!n || typeof n != "object") && (n = {}), n.trigger || (n.trigger = t.type || "click"), !n.href && i instanceof HTMLAnchorElement && i.href && (n.href = i.href), !n.label) {
      const r = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      r && (n.label = r.trim());
    }
    b(s, n);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    b(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && b(this.events.purchase || "purchase", e.purchase);
  }
  scrollIntoView(t) {
    const e = this.root || t;
    !e || typeof e.scrollIntoView != "function" || requestAnimationFrame(() => {
      e.scrollIntoView({ behavior: "smooth", block: "start" });
    });
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
      const e = P(this.phonePrefixField.value);
      if (e)
        return e;
    }
    if (this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const e = P(this.hiddenPhoneCc.value);
      if (e)
        return e;
    }
    if (this.phoneCountryCode) {
      const e = P(this.phoneCountryCode);
      if (e)
        return e;
    }
    const t = this.config && this.config.defaults || {};
    if (t.phone_country_code) {
      const e = P(t.phone_country_code);
      if (e)
        return e;
    }
    return "39";
  }
  getReservationEndpoint() {
    const t = this.config.endpoints || {};
    return $(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return $(t.availability, "/wp-json/fp-resv/v1/availability");
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
          const u = e();
          s(u || null);
          return;
        }
        s(null);
      };
      let r = document.querySelector(`script[src="${t}"]`);
      if (!r && i && (r = document.querySelector(`script[${i}]`)), r) {
        if (typeof e == "function") {
          const u = e();
          if (u) {
            s(u);
            return;
          }
        }
        r.addEventListener("load", n, { once: !0 }), r.addEventListener("error", () => s(null), { once: !0 });
        return;
      }
      r = document.createElement("script"), r.src = t, r.async = !0, i && r.setAttribute(i, "1"), r.onload = n, r.onerror = () => s(null);
      const o = document.head || document.body || document.documentElement;
      if (!o) {
        s(null);
        return;
      }
      o.appendChild(r);
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
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = it, window.fpResvApp = window.FPResv);
function st(a) {
  if (!a)
    return;
  a.style.display = "block", a.style.visibility = "visible", a.style.opacity = "1", a.style.position = "relative", a.style.width = "100%", a.style.height = "auto";
  let t = a.parentElement, e = 0;
  for (; t && e < 5; )
    window.getComputedStyle(t).display === "none" && (console.warn("[FP-RESV] Found hidden parent element, making visible:", t), t.style.display = "block"), t = t.parentElement, e++;
  console.log("[FP-RESV] Widget visibility ensured:", a.id || "unnamed");
}
function G() {
  let a = 0;
  const t = 10, e = setInterval(function() {
    a++;
    const i = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]");
    let s = !1;
    Array.prototype.forEach.call(i, function(n) {
      const r = window.getComputedStyle(n);
      (r.display === "none" || r.visibility === "hidden" || r.opacity === "0") && (console.warn("[FP-RESV] Widget became hidden, forcing visibility again:", n.id || "unnamed"), st(n), s = !0);
    }), (a >= t || !s) && (clearInterval(e), a >= t && console.log("[FP-RESV] Visibility auto-check completed after " + a + " checks"));
  }, 1e3);
}
const R = /* @__PURE__ */ new Set();
function x() {
  console.log("[FP-RESV] Plugin v0.1.11 loaded - Complete form functionality active"), console.log("[FP-RESV] Current readyState:", document.readyState), console.log("[FP-RESV] Body innerHTML length:", document.body ? document.body.innerHTML.length : 0);
  const a = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]");
  console.log("[FP-RESV] Found widgets:", a.length);
  const t = document.querySelectorAll("[data-fp-resv]"), e = document.querySelectorAll(".fp-resv-widget"), i = document.querySelectorAll("[data-fp-resv-app]");
  if (console.log("[FP-RESV] Debug - Found by [data-fp-resv]:", t.length), console.log("[FP-RESV] Debug - Found by .fp-resv-widget:", e.length), console.log("[FP-RESV] Debug - Found by [data-fp-resv-app]:", i.length), document.body && document.body.innerHTML.indexOf("fp-resv") !== -1) {
    console.log('[FP-RESV] Debug - "fp-resv" text found in body HTML');
    const s = document.body.innerHTML, n = s.indexOf("fp-resv"), r = Math.max(0, n - 200), o = Math.min(s.length, n + 200), u = s.substring(r, o);
    console.log('[FP-RESV] Debug - Context around "fp-resv":', u);
  } else
    console.log('[FP-RESV] Debug - "fp-resv" text NOT found in body HTML');
  if (a.length === 0) {
    console.warn("[FP-RESV] No widgets found on page. Expected shortcode [fp_reservations] or Gutenberg block."), console.log("[FP-RESV] Searching for potential widget containers...");
    const s = document.querySelector(".entry-content, .post-content, .page-content, main, article");
    s ? (console.log("[FP-RESV] Found content container:", s.className || "unnamed"), console.log("[FP-RESV] Content container innerHTML length:", s.innerHTML.length), s.innerHTML.includes("fp-resv") && console.log("[FP-RESV] Found fp-resv string in content, but no valid widget element")) : console.log("[FP-RESV] No standard content container found");
    return;
  }
  Array.prototype.forEach.call(a, function(s) {
    if (R.has(s)) {
      console.log("[FP-RESV] Widget already initialized, skipping:", s.id || "unnamed");
      return;
    }
    try {
      R.add(s), st(s), console.log("[FP-RESV] Initializing widget:", s.id || "unnamed"), console.log("[FP-RESV] Widget sections found:", s.querySelectorAll("[data-fp-resv-section]").length);
      const n = new it(s);
      console.log("[FP-RESV] Widget initialized successfully:", s.id || "unnamed"), (n.sections || []).forEach(function(o, u) {
        const f = o.getAttribute("data-step"), y = o.getAttribute("data-state"), A = o.hasAttribute("hidden");
        console.log(`[FP-RESV] Step ${u + 1} (${f}): state=${y}, hidden=${A}`);
      });
    } catch (n) {
      console.error("[FP-RESV] Error initializing widget:", n), R.delete(s);
    }
  });
}
function X() {
  if (typeof MutationObserver > "u") {
    console.warn("[FP-RESV] MutationObserver not supported, dynamic widgets won't be detected");
    return;
  }
  new MutationObserver(function(t) {
    let e = !1;
    t.forEach(function(i) {
      i.addedNodes && i.addedNodes.length > 0 && Array.prototype.forEach.call(i.addedNodes, function(s) {
        s.nodeType === 1 && (s.matches && (s.matches("[data-fp-resv]") || s.matches(".fp-resv-widget") || s.matches("[data-fp-resv-app]")) || s.querySelector && s.querySelector("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]")) && (e = !0);
      });
    }), e && (console.log("[FP-RESV] New widget(s) detected in DOM, initializing..."), x());
  }).observe(document.body, {
    childList: !0,
    subtree: !0
  }), console.log("[FP-RESV] MutationObserver set up to detect dynamic widgets");
}
function Y() {
  [500, 1e3, 2e3, 3e3].forEach(function(t) {
    setTimeout(function() {
      const e = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length;
      e > R.size && (console.log("[FP-RESV] Retry: Found " + e + " widgets, " + R.size + " initialized"), x());
    }, t);
  });
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", function() {
  x(), setTimeout(G, 500), X(), Y();
}) : (x(), setTimeout(G, 500), X(), Y());
(typeof window.vc_js < "u" || document.querySelector("[data-vc-full-width]") || document.querySelector(".vc_row")) && (console.log("[FP-RESV] WPBakery detected - adding compatibility listeners"), document.addEventListener("vc-full-content-loaded", function() {
  console.log("[FP-RESV] WPBakery vc-full-content-loaded event - re-initializing..."), setTimeout(x, 100);
}), window.addEventListener("load", function() {
  setTimeout(function() {
    document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length > R.size && (console.log("[FP-RESV] WPBakery late load - found new widgets, initializing..."), x());
  }, 1e3);
}), [1500, 3e3, 5e3, 1e4].forEach(function(a) {
  setTimeout(function() {
    document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length > R.size && (console.log("[FP-RESV] WPBakery extended retry (" + a + "ms) - initializing..."), x());
  }, a);
}));
document.addEventListener("fp-resv:tracking:push", function(a) {
  if (!a || !a.detail)
    return;
  const t = a.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  b(e, i && typeof i == "object" ? i : {});
});
const wt = 400, Et = 6e4, Ct = 3, Q = 600;
function Pt(a, t) {
  let e;
  try {
    e = new URL(a, window.location.origin);
  } catch {
    const s = window.location.origin.replace(/\/$/, ""), n = a.startsWith("/") ? s + a : s + "/" + a;
    e = new URL(n, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function B(a) {
  for (; a.firstChild; )
    a.removeChild(a.firstChild);
}
function Ft(a) {
  const t = a.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), s = t.querySelector("[data-fp-resv-slots-empty]"), n = t.querySelector("[data-fp-resv-slots-boundary]"), r = n ? n.querySelector("[data-fp-resv-slots-retry]") : null, o = /* @__PURE__ */ new Map();
  let u = null, f = null, y = null, A = 0;
  function F(l) {
    if (typeof l != "string")
      return "";
    const d = l.trim().toLowerCase();
    if (d === "")
      return "";
    const h = ((g) => typeof g.normalize == "function" ? g.normalize("NFD").replace(/[\u0300-\u036f]/g, "") : g)(d), m = (g) => g.some((c) => h.startsWith(c)), C = (g) => g.some((c) => h.includes(c));
    return m(["available", "open", "disponibil", "disponible", "liber", "libre", "apert", "abiert"]) ? "available" : d === "waitlist" || d === "busy" || m(["limited", "limit", "limitat", "limite", "cupos limit", "attesa"]) || C(["pochi posti", "quasi pien", "lista attesa", "few spots", "casi llen"]) ? "limited" : m(["full", "complet", "esaurit", "soldout", "sold out", "agotad", "chius", "plen"]) ? "full" : d;
  }
  function w(l, d) {
    const p = Array.isArray(l) ? l : [], h = p.length;
    if (h === 0)
      return d === !1 ? { state: "unavailable", slots: 0 } : { state: "full", slots: 0 };
    const m = p.map((c) => F(c && c.status)).filter((c) => c !== "");
    return m.some((c) => c === "limited") ? { state: "limited", slots: h } : m.some((c) => c === "available") ? { state: "available", slots: h } : d ? { state: "available", slots: h } : m.length === 0 ? { state: "available", slots: h } : { state: "full", slots: h };
  }
  function v(l, d) {
    if (typeof a.onAvailabilitySummary == "function")
      try {
        a.onAvailabilitySummary(d, l || f || {});
      } catch {
      }
  }
  r && r.addEventListener("click", () => {
    f && L(f, 0);
  });
  function M(l, d) {
    const p = typeof d == "string" ? d : d ? "loading" : "idle", h = typeof l == "string" ? l : "";
    e && (e.textContent = h, e.setAttribute("data-state", p));
    const m = p === "loading";
    t.setAttribute("data-loading", m ? "true" : "false"), i && i.setAttribute("aria-busy", m ? "true" : "false");
  }
  function E() {
    if (!i)
      return;
    B(i);
    const l = a.skeletonCount || 4;
    for (let d = 0; d < l; d += 1) {
      const p = document.createElement("li"), h = document.createElement("span");
      h.className = "fp-skeleton", p.appendChild(h), i.appendChild(p);
    }
  }
  function V(l) {
    s && (s.hidden = !1);
    const d = l && typeof l == "object", p = d && typeof l.meal == "string" ? l.meal.trim() : "", h = d && typeof l.date == "string" ? l.date.trim() : "", m = d && typeof l.party < "u" ? String(l.party).trim() : "", C = d && !!l.requiresMeal, g = p !== "", S = h !== "" && (m !== "" && m !== "0") && (!C || g), _ = C && !g ? a.strings && a.strings.selectMeal || "" : S && a.strings && a.strings.slotsEmpty || "";
    M(_, "idle"), i && B(i), v(l, { state: S ? "unavailable" : "unknown", slots: 0 });
  }
  function I() {
    s && (s.hidden = !0);
  }
  function H() {
    n && (n.hidden = !0);
  }
  function at(l) {
    const d = a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.";
    if (n) {
      const p = n.querySelector("[data-fp-resv-slots-boundary-message]");
      p && (p.textContent = l || d), n.hidden = !1;
    }
    M(l || d, "error"), v(f, { state: "error", slots: 0 });
  }
  function nt(l, d) {
    const p = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(p, (h) => {
      h.setAttribute("aria-pressed", h === d ? "true" : "false");
    }), y = l, typeof a.onSlotSelected == "function" && a.onSlotSelected(l);
  }
  function rt() {
    if (y = null, !i)
      return;
    const l = i.querySelectorAll("button[data-slot]");
    Array.prototype.forEach.call(l, (d) => {
      d.setAttribute("aria-pressed", "false");
    });
  }
  function W(l, d, p) {
    if (p && p !== A || d && f && d !== f || (H(), I(), !i))
      return;
    B(i);
    const h = l && Array.isArray(l.slots) ? l.slots : [];
    if (h.length === 0) {
      V(d);
      return;
    }
    h.forEach((C) => {
      const g = document.createElement("li"), c = document.createElement("button");
      c.type = "button", c.textContent = C.label || "", c.dataset.slot = C.start || "", c.dataset.slotStatus = C.status || "", c.setAttribute("aria-pressed", y && y.start === C.start ? "true" : "false"), c.addEventListener("click", () => nt(C, c)), g.appendChild(c), i.appendChild(g);
    }), M(a.strings && a.strings.slotsUpdated || "", !1);
    const m = !!(l && (typeof l.has_availability < "u" && l.has_availability || l.meta && l.meta.has_availability));
    v(d, w(h, m));
  }
  function L(l, d) {
    if (f = l, !l || !l.date || !l.party) {
      V(l);
      return;
    }
    const p = ++A, h = JSON.stringify([l.date, l.meal, l.party]), m = o.get(h);
    if (m && Date.now() - m.timestamp < Et && d === 0) {
      W(m.payload, l, p);
      return;
    }
    H(), I(), E(), M(a.strings && a.strings.updatingSlots || "Aggiornamento disponibilità…", "loading"), v(l, { state: "loading", slots: 0 });
    const C = Pt(a.endpoint, l), g = performance.now();
    fetch(C, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((c) => c.json().catch(() => ({})).then((q) => {
      if (!c.ok) {
        const S = new Error("availability_error");
        S.status = c.status, S.payload = q;
        const _ = c.headers.get("Retry-After");
        if (_) {
          const k = Number.parseInt(_, 10);
          Number.isFinite(k) && (S.retryAfter = k);
        }
        throw S;
      }
      return q;
    })).then((c) => {
      if (p !== A)
        return;
      const q = performance.now() - g;
      typeof a.onLatency == "function" && a.onLatency(q), o.set(h, { payload: c, timestamp: Date.now() }), W(c, l, p);
    }).catch((c) => {
      if (p !== A)
        return;
      const q = performance.now() - g;
      typeof a.onLatency == "function" && a.onLatency(q);
      const S = c && c.payload && typeof c.payload == "object" ? c.payload.data || {} : {}, _ = typeof c.status == "number" ? c.status : S && typeof S.status == "number" ? S.status : 0;
      let k = 0;
      if (c && typeof c.retryAfter == "number" && Number.isFinite(c.retryAfter))
        k = c.retryAfter;
      else if (S && typeof S.retry_after < "u") {
        const D = Number.parseInt(S.retry_after, 10);
        Number.isFinite(D) && (k = D);
      }
      if (d >= Ct - 1 ? !1 : _ === 429 || _ >= 500 && _ < 600 ? !0 : _ === 0) {
        const D = d + 1;
        typeof a.onRetry == "function" && a.onRetry(D);
        const dt = k > 0 ? Math.max(k * 1e3, Q) : Q * Math.pow(2, d);
        window.setTimeout(() => L(l, D), dt);
        return;
      }
      const ot = c && c.payload && (c.payload.message || c.payload.code) || S && S.message || a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.", lt = c && c.payload || S || null, ct = tt(ot, lt);
      at(ct);
    });
  }
  return {
    schedule(l, d = {}) {
      u && window.clearTimeout(u);
      const p = d && typeof d == "object" ? d : {}, h = l || (typeof a.getParams == "function" ? a.getParams() : null), m = !!(h && h.requiresMeal);
      if (!h || !h.date || !h.party || m && !h.meal) {
        f = h, V(h || {});
        return;
      }
      if (p.immediate) {
        L(h, 0);
        return;
      }
      u = window.setTimeout(() => {
        L(h, 0);
      }, wt);
    },
    revalidate() {
      if (!f)
        return;
      const l = JSON.stringify([f.date, f.meal, f.party]);
      o.delete(l), L(f, 0);
    },
    getSelection() {
      return y;
    },
    clearSelection() {
      rt();
    }
  };
}
const _t = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: Ft
}, Symbol.toStringTag, { value: "Module" }));
