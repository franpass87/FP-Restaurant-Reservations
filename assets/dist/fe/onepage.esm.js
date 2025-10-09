function y(r, t) {
  if (!r)
    return null;
  const e = Object.assign({ event: r }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
const at = /\D+/g;
function J(r) {
  return r ? String(r).replace(at, "") : "";
}
function w(r) {
  const t = J(r);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function M(r) {
  return J(r);
}
function nt(r, t) {
  const e = w(r), i = M(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function K(r) {
  const t = M(r);
  return t.length >= 6 && t.length <= 15;
}
function ot(r) {
  const t = M(r);
  if (t === "")
    return { masked: "", digits: "" };
  const e = [3, 4], i = [];
  let s = 0, a = 0;
  for (; s < t.length; ) {
    const n = t.length - s;
    let l = e[a % e.length];
    n <= 4 && (l = n), i.push(t.slice(s, s + l)), s += l, a += 1;
  }
  return { masked: i.join(" "), digits: t };
}
function z(r, t) {
  const e = r.value, { masked: i } = ot(e), s = r.selectionStart;
  if (r.value = i, s !== null) {
    const a = i.length - e.length, n = Math.max(0, s + a);
    r.setSelectionRange(n, n);
  }
  r.setAttribute("data-phone-local", M(r.value)), r.setAttribute("data-phone-cc", w(t));
}
function V(r, t) {
  const e = M(r.value), i = w(t);
  return {
    e164: nt(i, e),
    local: e,
    country: i
  };
}
function T(r) {
  if (r == null)
    return "";
  if (typeof r == "string")
    return r.trim();
  if (Array.isArray(r))
    return r.map((e) => T(e)).filter((e) => e !== "").join("; ");
  if (typeof r == "object") {
    if (typeof r.message == "string" && r.message.trim() !== "")
      return r.message.trim();
    if (typeof r.detail == "string" && r.detail.trim() !== "")
      return r.detail.trim();
  }
  return String(r).trim();
}
function lt(r) {
  if (r == null)
    return "";
  const t = Array.isArray(r) ? [...r] : [r];
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
      const a = i[s];
      if (Object.prototype.hasOwnProperty.call(e, a)) {
        const n = T(e[a]);
        if (n !== "")
          return n;
      }
    }
    Object.prototype.hasOwnProperty.call(e, "data") && e.data && typeof e.data == "object" && t.push(e.data);
  }
  return "";
}
function X(r, t) {
  const e = lt(t);
  return e === "" ? r : r ? r.includes(e) ? r : r + " (" + e + ")" : e;
}
function ct(r) {
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
function dt(r, t) {
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
function ut(r) {
  if (r == null)
    return null;
  if (typeof r == "number")
    return Number.isFinite(r) ? r : null;
  const t = String(r).replace(",", "."), e = parseFloat(t);
  return Number.isNaN(e) ? null : e;
}
function Y(r, t) {
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
function ht(r) {
  return r ? r.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]') : null;
}
function ft(r, t) {
  r && (t ? (r.setAttribute("aria-disabled", "true"), r.setAttribute("disabled", "disabled")) : (r.removeAttribute("disabled"), r.setAttribute("aria-disabled", "false")));
}
function pt(r) {
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
function H(r, t) {
  if (r && typeof r == "string")
    try {
      return new URL(r, window.location.origin).toString();
    } catch {
      return r;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
let D = null;
const U = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (r) => window.requestIdleCallback(r) : (r) => window.setTimeout(() => r(Date.now()), 1);
function mt() {
  return D || (D = Promise.resolve().then(() => wt)), D;
}
function yt(r) {
  return Y(r, "data-fp-resv-section");
}
const bt = ["service", "date", "party", "slots", "details", "confirm"];
class G {
  constructor(t) {
    this.root = t, this.dataset = ct(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(bt);
    this.sections = this.form ? Array.prototype.slice.call(this.form.querySelectorAll("[data-fp-resv-section]")) : [];
    const i = this.sections.map((s) => s.getAttribute("data-step") || "").filter(Boolean);
    this.stepOrder = Array.from(new Set(e.concat(i))), this.sections.length > 1 && this.sections.sort((s, a) => this.getStepOrderIndex(s) - this.getStepOrderIndex(a)), this.progress = this.form ? this.form.querySelector("[data-fp-resv-progress]") : null, this.progressItems = this.progress ? Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]")) : [], this.progress && this.progressItems.length > 1 && this.progressItems.sort((s, a) => this.getStepOrderIndex(s) - this.getStepOrderIndex(a)).forEach((s) => {
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
      slotRequired: this.messages.slot_required || "Seleziona un orario per continuare.",
      invalidPhone: this.messages.msg_invalid_phone || "Inserisci un numero di telefono valido (minimo 6 cifre).",
      invalidEmail: this.messages.msg_invalid_email || "Inserisci un indirizzo email valido.",
      submitError: this.messages.msg_submit_error || "Non è stato possibile completare la prenotazione. Riprova.",
      submitSuccess: this.messages.msg_submit_success || "Prenotazione inviata con successo.",
      mealFullNotice: this.messages.meal_full_notice || "Nessuna disponibilità per questo servizio. Scegli un altro giorno."
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateInlineErrors(), this.updateSummary(), U(() => {
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
    e === "" && (e = "39"), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = e), t !== "" && (this.phoneCountryCode = t), this.phoneField && z(this.phoneField, e);
  }
  initializeDateField() {
    if (!this.dateField)
      return;
    const t = (/* @__PURE__ */ new Date()).toISOString().split("T")[0];
    this.dateField.setAttribute("min", t), this.dateField.addEventListener("change", (i) => {
      const s = i.target.value;
      s && s < t ? (i.target.setCustomValidity("Non è possibile prenotare per giorni passati."), i.target.setAttribute("aria-invalid", "true")) : (i.target.setCustomValidity(""), i.target.setAttribute("aria-invalid", "false"));
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
      }, a = this.availabilityRoot.querySelectorAll("button[data-slot]");
      Array.prototype.forEach.call(a, (n) => {
        n.setAttribute("aria-pressed", n === i ? "true" : "false");
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
    U(() => {
      mt().then((e) => {
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
    const i = e.getAttribute("data-fp-resv-field") || "", s = i && e.dataset.fpResvLastValue || "", a = i && typeof e.value == "string" ? e.value : "", n = !i || s !== a, l = yt(e);
    if (!l) {
      this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState();
      return;
    }
    this.ensureSectionActive(l), this.updateSectionAttributes(l, "active"), i && (e.dataset.fpResvLastValue = a), (i === "date" || i === "party" || i === "slots" || i === "time") && ((i === "date" || i === "party") && n && this.clearSlotSelection({ schedule: !1 }), (i !== "date" || n || t.type === "change") && this.scheduleAvailabilityUpdate()), this.isConsentField(e) && this.syncConsentState(), this.updateSubmitState(), this.updateInlineErrors();
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
      const a = s.getAttribute("data-step") || "";
      if (a === t)
        i = !0, this.updateSectionAttributes(s, "active", { silent: !0 }), this.dispatchSectionUnlocked(a);
      else if (i)
        this.updateSectionAttributes(s, "locked", { silent: !0 });
      else {
        const l = this.state.sectionStates[a] === "locked" ? "locked" : "completed";
        this.updateSectionAttributes(s, l, { silent: !0 });
      }
    }), this.updateProgressIndicators(), this.scrollIntoView(e), requestAnimationFrame(() => {
      const s = e.querySelector('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
      s && typeof s.focus == "function" && s.focus({ preventScroll: !0 });
    }), this.updateSubmitState();
  }
  handleRetrySubmit(t) {
    t.preventDefault(), this.clearError(), this.errorRetry && (this.errorRetry.textContent = this.messages.retry_button || "Riprova", this.errorRetry.onclick = null), this.focusFirstInvalid(), this.updateSubmitState();
  }
  handleMealSelection(t) {
    this.mealButtons.forEach(function(a) {
      a.removeAttribute("data-active"), a.setAttribute("aria-pressed", "false");
    }), t.setAttribute("data-active", "true"), t.setAttribute("aria-pressed", "true");
    const e = t.getAttribute("data-fp-resv-meal") || "", i = this.state.mealAvailability ? this.state.mealAvailability[e] : "";
    if (this.applyMealAvailabilityIndicator(e, i), i === "full") {
      const a = t.getAttribute("data-meal-default-notice") || "", n = this.copy.mealFullNotice || a;
      n !== "" && t.setAttribute("data-meal-notice", n);
    }
    this.applyMealSelection(t), this.applyMealAvailabilityNotice(e, i, { skipSlotReset: !0 });
    const s = this.events.meal_selected || "meal_selected";
    y(s, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), i !== "full" && this.scheduleAvailabilityUpdate({ immediate: !0 });
  }
  updateMealNoticeFromButton(t, e) {
    if (!this.mealNotice)
      return;
    const i = typeof e == "string" ? e : t && t.getAttribute("data-meal-notice") || "", s = i ? i.trim() : "", a = this.mealNoticeText || this.mealNotice;
    s !== "" && a ? (a.textContent = s, this.mealNotice.hidden = !1) : a && (a.textContent = "", this.mealNotice.hidden = !0);
  }
  applyMealAvailabilityNotice(t, e, i = {}) {
    const s = this.mealButtons.find((l) => (l.getAttribute("data-fp-resv-meal") || "") === t);
    if (!s)
      return;
    const a = s.getAttribute("data-meal-default-notice") || "";
    if ((typeof e == "string" ? e : "") === "full") {
      const l = this.copy.mealFullNotice || a;
      l !== "" ? s.setAttribute("data-meal-notice", l) : a === "" && s.removeAttribute("data-meal-notice"), s.setAttribute("aria-disabled", "true"), s.setAttribute("data-meal-unavailable", "true"), s.hasAttribute("data-active") && (i.skipSlotReset !== !0 && this.clearSlotSelection({ schedule: !1 }), this.updateMealNoticeFromButton(s));
      return;
    }
    s.removeAttribute("aria-disabled"), s.removeAttribute("data-meal-unavailable"), a !== "" ? s.setAttribute("data-meal-notice", a) : s.hasAttribute("data-meal-notice") && s.removeAttribute("data-meal-notice"), s.hasAttribute("data-active") && this.updateMealNoticeFromButton(s);
  }
  applyMealSelection(t) {
    const e = t.getAttribute("data-fp-resv-meal") || "";
    this.hiddenMeal && (this.hiddenMeal.value = e);
    const i = ut(t.getAttribute("data-meal-price"));
    this.hiddenPrice && (this.hiddenPrice.value = i !== null ? String(i) : ""), this.clearSlotSelection({ schedule: !1 }), this.updateMealNoticeFromButton(t), this.updateSubmitState();
  }
  clearSlotSelection(t = {}) {
    this.hiddenSlot && (this.hiddenSlot.value = "");
    const e = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null;
    if (e && (e.value = "", e.removeAttribute("data-slot-start")), this.availabilityController && typeof this.availabilityController.clearSelection == "function" && this.availabilityController.clearSelection(), this.availabilityRoot) {
      const s = this.availabilityRoot.querySelectorAll('button[data-slot][aria-pressed="true"]');
      Array.prototype.forEach.call(s, (a) => {
        a.setAttribute("aria-pressed", "false");
      });
    }
    const i = this.sections.find((s) => (s.getAttribute("data-step") || "") === "slots");
    if (i) {
      const s = i.getAttribute("data-step") || "", a = this.state.sectionStates[s] || "locked";
      this.updateSectionAttributes(i, "locked", { silent: !0 });
      const n = this.sections.indexOf(i);
      if (n !== -1)
        for (let l = n + 1; l < this.sections.length; l += 1) {
          const h = this.sections[l];
          this.updateSectionAttributes(h, "locked", { silent: !0 });
        }
      this.updateProgressIndicators(), (t.forceRewind && s || a === "completed" || a === "active") && this.activateSectionByKey(s);
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
    const a = this.sections[s + 1];
    if (!a)
      return;
    const n = a.getAttribute("data-step") || String(s + 1);
    this.state.sectionStates[n] !== "completed" && (this.state.sectionStates[n] = "active", this.updateSectionAttributes(a, "active"), this.dispatchSectionUnlocked(n), this.scrollIntoView(a));
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
    if (e === "date" || e === "party") {
      this.completeSection(t, !0);
      return;
    }
    if (e === "slots") {
      const i = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null, s = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null;
      if (!i || i.value.trim() === "" || !s || s.value.trim() === "") {
        const a = this.sections.find((n) => (n.getAttribute("data-step") || "") === "slots");
        if (a) {
          const n = a.querySelector("[data-fp-resv-slots-status]");
          n && (n.textContent = this.copy.slotRequired || "Seleziona un orario per continuare.", n.style.color = "#dc2626", n.setAttribute("data-state", "error"), setTimeout(() => {
            n.textContent = "", n.style.color = "", n.removeAttribute("data-state");
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
    y(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const s = t.getAttribute("data-step") || "", a = i && i.silent === !0;
    console.log(`[FP-RESV] updateSectionAttributes: step=${s}, state=${e}, silent=${a}`), this.state.sectionStates[s] = e, t.setAttribute("data-state", e), e === "completed" ? t.setAttribute("data-complete-hidden", "true") : t.removeAttribute("data-complete-hidden");
    const n = e === "active";
    t.setAttribute("aria-hidden", n ? "false" : "true"), t.setAttribute("aria-expanded", n ? "true" : "false"), n ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert"), t.style.display = "block", t.style.visibility = "visible", t.style.opacity = "1", console.log(`[FP-RESV] Step ${s} made visible`)) : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", ""), t.style.display = "none", t.style.visibility = "hidden", t.style.opacity = "0", console.log(`[FP-RESV] Step ${s} hidden`)), a || this.updateProgressIndicators(), this.updateStickyCtaVisibility();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const s = e.length || 1;
    Array.prototype.forEach.call(e, function(n, l) {
      const h = n.getAttribute("data-step") || "", f = t.state.sectionStates[h] || "locked";
      n.setAttribute("data-state", f), n.setAttribute("data-progress-state", f === "completed" ? "done" : f);
      const b = n.querySelector(".fp-progress__label");
      b && (f === "active" ? b.removeAttribute("aria-hidden") : b.setAttribute("aria-hidden", "true"));
      const S = f === "locked";
      n.tabIndex = S ? -1 : 0, S ? n.setAttribute("aria-disabled", "true") : n.removeAttribute("aria-disabled"), f === "active" ? (n.setAttribute("aria-current", "step"), i = Math.max(i, l + 0.5)) : n.removeAttribute("aria-current"), f === "completed" ? (n.setAttribute("data-completed", "true"), i = Math.max(i, l + 1)) : n.removeAttribute("data-completed");
    });
    const a = Math.min(100, Math.max(0, Math.round(i / s * 100)));
    this.progress.style.setProperty("--fp-progress-fill", a + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    if ((t.getAttribute("data-step") || "") === "slots") {
      const a = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null, n = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, l = a && a.value.trim() !== "", h = n && n.value.trim() !== "";
      if (!l || !h)
        return !1;
    }
    let s = !0;
    return Array.prototype.forEach.call(e, function(a) {
      typeof a.checkValidity == "function" && !a.checkValidity() && (s = !1);
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
      y(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
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
      const s = t[i], a = this.form.querySelector(`[data-fp-resv-error="${i}"]`);
      if (!a)
        return;
      if (i === "consent" && !this.state.touchedFields[i]) {
        a.textContent = "", a.hidden = !0;
        return;
      }
      let n = !1, l = "";
      if (s && typeof s.checkValidity == "function" && !s.checkValidity() && (n = !0, l = e[i] || ""), i === "email" && s && s.value && s.value.trim() !== "" && s.checkValidity() && (n = !1, l = ""), i === "phone" && this.phoneField) {
        const h = V(this.phoneField, this.getPhoneCountryCode());
        h.local && !K(h.local) && (n = !0, l = this.copy.invalidPhone);
      }
      i === "consent" && s && s.checked && (n = !1, l = ""), n ? (a.textContent = l, a.hidden = !1, s && s.setAttribute && s.setAttribute("aria-invalid", "true")) : (a.textContent = "", a.hidden = !0, s && s.removeAttribute && s.removeAttribute("aria-invalid"));
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
    ft(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), s !== i && e !== "sending" && y("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), s = this.form.querySelector('[data-fp-resv-field="first_name"]'), a = this.form.querySelector('[data-fp-resv-field="last_name"]'), n = this.form.querySelector('[data-fp-resv-field="email"]'), l = this.form.querySelector('[data-fp-resv-field="phone"]'), h = this.form.querySelector('[data-fp-resv-field="notes"]'), f = this.form.querySelector('[data-fp-resv-field="high_chair_count"]'), b = this.form.querySelector('[data-fp-resv-field="wheelchair_table"]'), S = this.form.querySelector('[data-fp-resv-field="pets"]');
    let x = "";
    s && s.value && (x = s.value.trim()), a && a.value && (x = (x + " " + a.value.trim()).trim());
    let k = "";
    if (n && n.value && (k = n.value.trim()), l && l.value) {
      const A = this.getPhoneCountryCode(), N = (A ? "+" + A + " " : "") + l.value.trim();
      k = k !== "" ? k + " / " + N : N;
    }
    const E = [];
    f && typeof f.value == "string" && parseInt(f.value, 10) > 0 && E.push("Seggioloni: " + parseInt(f.value, 10)), b && "checked" in b && b.checked && E.push("Tavolo accessibile per sedia a rotelle"), S && "checked" in S && S.checked && E.push("Animali domestici");
    const q = E.join("; ");
    this.summaryTargets.forEach(function(A) {
      switch (A.getAttribute("data-fp-resv-summary")) {
        case "date":
          A.textContent = t && t.value ? t.value : "";
          break;
        case "time":
          A.textContent = e && e.value ? e.value : "";
          break;
        case "party":
          A.textContent = i && i.value ? i.value : "";
          break;
        case "name":
          A.textContent = x;
          break;
        case "contact":
          A.textContent = k;
          break;
        case "notes":
          A.textContent = h && h.value ? h.value : "";
          break;
        case "extras":
          A.textContent = q;
          break;
      }
    });
  }
  async handleSubmit(t) {
    if (t.preventDefault(), this.state.touchedFields.consent = !0, !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateInlineErrors(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    y(e, {
      source: "form",
      form_id: this.form && this.form.id ? this.form.id : this.root.id || "",
      date: i.date,
      party: i.party,
      meal: i.meal
    }), this.preparePhonePayload(), this.state.sending = !0, this.updateSubmitState(), this.clearError();
    const s = this.serializeForm(), a = this.getReservationEndpoint(), n = performance.now();
    let l = 0;
    try {
      const h = await fetch(a, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-WP-Nonce": s.fp_resv_nonce || ""
        },
        body: JSON.stringify(s),
        credentials: "same-origin"
      });
      if (l = Math.round(performance.now() - n), y("ui_latency", { op: "submit", ms: l }), !h.ok) {
        const b = await pt(h), S = b && b.message || this.copy.submitError;
        throw Object.assign(new Error(S), {
          status: h.status,
          payload: b
        });
      }
      const f = await h.json();
      this.handleSubmitSuccess(f);
    } catch (h) {
      l || (l = Math.round(performance.now() - n), y("ui_latency", { op: "submit", ms: l })), this.handleSubmitError(h, l);
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
      i && i.event && y(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", s = t && t.message || this.copy.submitError, a = t && typeof t == "object" && t.payload || null;
    let n = X(s, a);
    if (i === 403) {
      const h = this.messages.reload_hint || "La sessione potrebbe essere scaduta. Ricarica la pagina e riprova.";
      n = n + " " + h, this.errorAlert && this.errorRetry && (this.errorRetry.textContent = this.messages.reload_button || "Ricarica pagina", this.errorRetry.onclick = (f) => {
        f.preventDefault(), window.location.reload();
      });
    }
    this.errorAlert && this.errorMessage && (this.errorMessage.textContent = n, this.errorAlert.hidden = !1, requestAnimationFrame(() => {
      typeof this.errorAlert.scrollIntoView == "function" && this.errorAlert.scrollIntoView({ behavior: "smooth", block: "center" }), typeof this.errorAlert.focus == "function" && (this.errorAlert.setAttribute("tabindex", "-1"), this.errorAlert.focus({ preventScroll: !0 }));
    })), this.state.hintOverride = n, this.updateSubmitState();
    const l = this.events.submit_error || "submit_error";
    y(l, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.errorRetry && (this.errorRetry.textContent = this.messages.retry_button || "Riprova", this.errorRetry.onclick = null), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, s) => {
      typeof i == "string" && (e[s] = i);
    }), this.phoneField) {
      const i = V(this.phoneField, this.getPhoneCountryCode());
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
    const t = V(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = V(this.phoneField, this.getPhoneCountryCode());
    if (t.local === "") {
      this.phoneField.setCustomValidity(""), this.phoneField.removeAttribute("aria-invalid");
      return;
    }
    K(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), y("phone_validation_error", { field: "phone" }), y("ui_validation_error", { field: "phone" }));
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
    t.setCustomValidity(""), t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), y("ui_validation_error", { field: "email" }));
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
    const i = this.mealButtons.find((n) => (n.getAttribute("data-fp-resv-meal") || "") === t);
    if (!i)
      return;
    const s = ["available", "limited", "full"], a = e ? String(e).toLowerCase() : "";
    i.removeAttribute("data-availability-state"), a === "full" ? (i.setAttribute("aria-disabled", "true"), i.setAttribute("data-meal-unavailable", "true")) : s.indexOf(a) !== -1 && (i.removeAttribute("aria-disabled"), i.removeAttribute("data-meal-unavailable"));
  }
  handleMealAvailabilitySummary(t, e) {
    if (!e || !e.meal)
      return;
    const i = t && t.state ? String(t.state).toLowerCase() : "", s = ["available", "limited", "full"], a = e.meal;
    if (this.state.mealAvailability || (this.state.mealAvailability = {}), s.indexOf(i) === -1) {
      delete this.state.mealAvailability[a], this.applyMealAvailabilityIndicator(a, ""), this.applyMealAvailabilityNotice(a, "", { skipSlotReset: !0 });
      return;
    }
    if (this.state.mealAvailability[a] = i, this.applyMealAvailabilityIndicator(a, i), this.applyMealAvailabilityNotice(a, i), this.slotsLegend && this.slotsLegend.hidden && (this.slotsLegend.hidden = !1, this.slotsLegend.removeAttribute("hidden")), this.availabilityIndicator) {
      let n = "";
      if (t && typeof t == "object") {
        const l = typeof t.slots == "number" ? t.slots : 0;
        i === "available" ? n = `Disponibile (${l})` : i === "limited" ? n = `Disponibilità limitata (${l})` : i === "full" && (n = "Completamente prenotato");
      }
      this.availabilityIndicator.textContent = n, this.availabilityIndicator.hidden = n === "", this.availabilityIndicator.setAttribute("data-state", i || "");
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
    y("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    y("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    y(t, { source: "form" }), this.state.started = !0;
  }
  handleDelegatedTrackingEvent(t) {
    const e = t.target instanceof HTMLElement ? t.target : null;
    if (!e)
      return;
    const i = Y(e, "data-fp-resv-event");
    if (!i)
      return;
    const s = i.getAttribute("data-fp-resv-event");
    if (!s)
      return;
    let a = dt(i, "data-fp-resv-payload");
    if ((!a || typeof a != "object") && (a = {}), a.trigger || (a.trigger = t.type || "click"), !a.href && i instanceof HTMLAnchorElement && i.href && (a.href = i.href), !a.label) {
      const n = i.getAttribute("data-fp-resv-label") || i.getAttribute("aria-label") || i.textContent || "";
      n && (a.label = n.trim());
    }
    y(s, a);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    y(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && y(this.events.purchase || "purchase", e.purchase);
  }
  scrollIntoView(t) {
    typeof t.scrollIntoView == "function" && t.scrollIntoView({ behavior: "smooth", block: "start" });
    const e = ht(t);
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
    const a = this.form.querySelector('[data-fp-resv-field="marketing_consent"]');
    a && "checked" in a && (e.ads = a.checked ? "granted" : "denied", i = !0);
    const n = this.form.querySelector('[data-fp-resv-field="profiling_consent"]');
    n && "checked" in n && (e.personalization = n.checked ? "granted" : "denied", i = !0), i && t.updateConsent(e);
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
    return H(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return H(t.availability, "/wp-json/fp-resv/v1/availability");
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
      const a = () => {
        if (typeof e == "function") {
          const h = e();
          s(h || null);
          return;
        }
        s(null);
      };
      let n = document.querySelector(`script[src="${t}"]`);
      if (!n && i && (n = document.querySelector(`script[${i}]`)), n) {
        if (typeof e == "function") {
          const h = e();
          if (h) {
            s(h);
            return;
          }
        }
        n.addEventListener("load", a, { once: !0 }), n.addEventListener("error", () => s(null), { once: !0 });
        return;
      }
      n = document.createElement("script"), n.src = t, n.async = !0, i && n.setAttribute(i, "1"), n.onload = a, n.onerror = () => s(null);
      const l = document.head || document.body || document.documentElement;
      if (!l) {
        s(null);
        return;
      }
      l.appendChild(n);
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
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = G, window.fpResvApp = window.FPResv);
function $() {
  console.log("[FP-RESV] Plugin v0.1.5 loaded - Complete form functionality active");
  const r = document.querySelectorAll("[data-fp-resv]");
  console.log("[FP-RESV] Found widgets:", r.length), Array.prototype.forEach.call(r, function(t) {
    try {
      console.log("[FP-RESV] Initializing widget:", t.id || "unnamed"), console.log("[FP-RESV] Widget sections found:", t.querySelectorAll("[data-fp-resv-section]").length);
      const e = new G(t);
      console.log("[FP-RESV] Widget initialized successfully:", t.id || "unnamed"), (e.sections || []).forEach(function(s, a) {
        const n = s.getAttribute("data-step"), l = s.getAttribute("data-state"), h = s.hasAttribute("hidden");
        console.log(`[FP-RESV] Step ${a + 1} (${n}): state=${l}, hidden=${h}`);
      });
    } catch (e) {
      console.error("[FP-RESV] Error initializing widget:", e);
    }
  });
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", $) : $();
document.addEventListener("fp-resv:tracking:push", function(r) {
  if (!r || !r.detail)
    return;
  const t = r.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  y(e, i && typeof i == "object" ? i : {});
});
const vt = 400, gt = 6e4, St = 3, W = 600;
function At(r, t) {
  let e;
  try {
    e = new URL(r, window.location.origin);
  } catch {
    const s = window.location.origin.replace(/\/$/, ""), a = r.startsWith("/") ? s + r : s + "/" + r;
    e = new URL(a, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function O(r) {
  for (; r.firstChild; )
    r.removeChild(r.firstChild);
}
function Ct(r) {
  const t = r.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), s = t.querySelector("[data-fp-resv-slots-empty]"), a = t.querySelector("[data-fp-resv-slots-boundary]"), n = a ? a.querySelector("[data-fp-resv-slots-retry]") : null, l = /* @__PURE__ */ new Map();
  let h = null, f = null, b = null, S = 0;
  function x(o) {
    if (typeof o != "string")
      return "";
    const d = o.trim().toLowerCase();
    if (d === "")
      return "";
    const u = ((v) => typeof v.normalize == "function" ? v.normalize("NFD").replace(/[\u0300-\u036f]/g, "") : v)(d), m = (v) => v.some((c) => u.startsWith(c)), C = (v) => v.some((c) => u.includes(c));
    return m(["available", "open", "disponibil", "disponible", "liber", "libre", "apert", "abiert"]) ? "available" : d === "waitlist" || d === "busy" || m(["limited", "limit", "limitat", "limite", "cupos limit", "attesa"]) || C(["pochi posti", "quasi pien", "lista attesa", "few spots", "casi llen"]) ? "limited" : m(["full", "complet", "esaurit", "soldout", "sold out", "agotad", "chius", "plen"]) ? "full" : d;
  }
  function k(o, d) {
    const p = Array.isArray(o) ? o : [], u = p.length;
    if (u === 0)
      return { state: "full", slots: 0 };
    const m = p.map((c) => x(c && c.status)).filter((c) => c !== "");
    return m.some((c) => c === "limited") ? { state: "limited", slots: u } : m.some((c) => c === "available") ? { state: "available", slots: u } : d ? { state: "available", slots: u } : m.length === 0 ? { state: "available", slots: u } : { state: "full", slots: u };
  }
  function E(o, d) {
    if (typeof r.onAvailabilitySummary == "function")
      try {
        r.onAvailabilitySummary(d, o || f || {});
      } catch {
      }
  }
  n && n.addEventListener("click", () => {
    f && I(f, 0);
  });
  function q(o, d) {
    const p = typeof d == "string" ? d : d ? "loading" : "idle", u = typeof o == "string" ? o : "";
    e && (e.textContent = u, e.setAttribute("data-state", p));
    const m = p === "loading";
    t.setAttribute("data-loading", m ? "true" : "false"), i && i.setAttribute("aria-busy", m ? "true" : "false");
  }
  function A() {
    if (!i)
      return;
    O(i);
    const o = r.skeletonCount || 4;
    for (let d = 0; d < o; d += 1) {
      const p = document.createElement("li"), u = document.createElement("span");
      u.className = "fp-skeleton", p.appendChild(u), i.appendChild(p);
    }
  }
  function R(o) {
    s && (s.hidden = !1);
    const d = o && typeof o == "object", p = d && typeof o.meal == "string" ? o.meal.trim() : "", u = d && typeof o.date == "string" ? o.date.trim() : "", m = d && typeof o.party < "u" ? String(o.party).trim() : "", C = d && !!o.requiresMeal, v = p !== "", g = u !== "" && (m !== "" && m !== "0") && (!C || v), _ = C && !v ? r.strings && r.strings.selectMeal || "" : g && r.strings && r.strings.slotsEmpty || "";
    q(_, "idle"), i && O(i), E(o, { state: g ? "full" : "unknown", slots: 0 });
  }
  function N() {
    s && (s.hidden = !0);
  }
  function B() {
    a && (a.hidden = !0);
  }
  function Q(o) {
    const d = r.strings && r.strings.slotsError || r.strings && r.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.";
    if (a) {
      const p = a.querySelector("[data-fp-resv-slots-boundary-message]");
      p && (p.textContent = o || d), a.hidden = !1;
    }
    q(o || d, "error"), E(f, { state: "error", slots: 0 });
  }
  function Z(o, d) {
    const p = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(p, (u) => {
      u.setAttribute("aria-pressed", u === d ? "true" : "false");
    }), b = o, typeof r.onSlotSelected == "function" && r.onSlotSelected(o);
  }
  function tt() {
    if (b = null, !i)
      return;
    const o = i.querySelectorAll("button[data-slot]");
    Array.prototype.forEach.call(o, (d) => {
      d.setAttribute("aria-pressed", "false");
    });
  }
  function j(o, d, p) {
    if (p && p !== S || d && f && d !== f || (B(), N(), !i))
      return;
    O(i);
    const u = o && Array.isArray(o.slots) ? o.slots : [];
    if (u.length === 0) {
      R(d);
      return;
    }
    u.forEach((C) => {
      const v = document.createElement("li"), c = document.createElement("button");
      c.type = "button", c.textContent = C.label || "", c.dataset.slot = C.start || "", c.dataset.slotStatus = C.status || "", c.setAttribute("aria-pressed", b && b.start === C.start ? "true" : "false"), c.addEventListener("click", () => Z(C, c)), v.appendChild(c), i.appendChild(v);
    }), q(r.strings && r.strings.slotsUpdated || "", !1);
    const m = !!(o && (typeof o.has_availability < "u" && o.has_availability || o.meta && o.meta.has_availability));
    E(d, k(u, m));
  }
  function I(o, d) {
    if (f = o, !o || !o.date || !o.party) {
      R(o);
      return;
    }
    const p = ++S, u = JSON.stringify([o.date, o.meal, o.party]), m = l.get(u);
    if (m && Date.now() - m.timestamp < gt && d === 0) {
      j(m.payload, o, p);
      return;
    }
    B(), A(), q(r.strings && r.strings.updatingSlots || "Aggiornamento disponibilità…", "loading"), E(o, { state: "loading", slots: 0 });
    const C = At(r.endpoint, o), v = performance.now();
    fetch(C, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((c) => c.json().catch(() => ({})).then((F) => {
      if (!c.ok) {
        const g = new Error("availability_error");
        g.status = c.status, g.payload = F;
        const _ = c.headers.get("Retry-After");
        if (_) {
          const P = Number.parseInt(_, 10);
          Number.isFinite(P) && (g.retryAfter = P);
        }
        throw g;
      }
      return F;
    })).then((c) => {
      if (p !== S)
        return;
      const F = performance.now() - v;
      typeof r.onLatency == "function" && r.onLatency(F), l.set(u, { payload: c, timestamp: Date.now() }), j(c, o, p);
    }).catch((c) => {
      if (p !== S)
        return;
      const F = performance.now() - v;
      typeof r.onLatency == "function" && r.onLatency(F);
      const g = c && c.payload && typeof c.payload == "object" ? c.payload.data || {} : {}, _ = typeof c.status == "number" ? c.status : g && typeof g.status == "number" ? g.status : 0;
      let P = 0;
      if (c && typeof c.retryAfter == "number" && Number.isFinite(c.retryAfter))
        P = c.retryAfter;
      else if (g && typeof g.retry_after < "u") {
        const L = Number.parseInt(g.retry_after, 10);
        Number.isFinite(L) && (P = L);
      }
      if (d >= St - 1 ? !1 : _ === 429 || _ >= 500 && _ < 600 ? !0 : _ === 0) {
        const L = d + 1;
        typeof r.onRetry == "function" && r.onRetry(L);
        const rt = P > 0 ? Math.max(P * 1e3, W) : W * Math.pow(2, d);
        window.setTimeout(() => I(o, L), rt);
        return;
      }
      const et = c && c.payload && (c.payload.message || c.payload.code) || g && g.message || r.strings && r.strings.slotsError || r.strings && r.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.", it = c && c.payload || g || null, st = X(et, it);
      Q(st);
    });
  }
  return {
    schedule(o, d = {}) {
      h && window.clearTimeout(h);
      const p = d && typeof d == "object" ? d : {}, u = o || (typeof r.getParams == "function" ? r.getParams() : null), m = !!(u && u.requiresMeal);
      if (!u || !u.date || !u.party || m && !u.meal) {
        f = u, R(u || {});
        return;
      }
      if (p.immediate) {
        I(u, 0);
        return;
      }
      h = window.setTimeout(() => {
        I(u, 0);
      }, vt);
    },
    revalidate() {
      if (!f)
        return;
      const o = JSON.stringify([f.date, f.meal, f.party]);
      l.delete(o), I(f, 0);
    },
    getSelection() {
      return b;
    },
    clearSelection() {
      tt();
    }
  };
}
const wt = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: Ct
}, Symbol.toStringTag, { value: "Module" }));
