function g(a, t) {
  if (!a)
    return null;
  const e = Object.assign({ event: a }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
const ut = /\D+/g;
function Q(a) {
  return a ? String(a).replace(ut, "") : "";
}
function P(a) {
  const t = Q(a);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function L(a) {
  return Q(a);
}
function ht(a, t) {
  const e = P(a), i = L(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function $(a) {
  const t = L(a);
  return t.length >= 6 && t.length <= 15;
}
function ft(a) {
  const t = L(a);
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
function T(a, t) {
  const e = a.value, { masked: i } = ft(e), s = a.selectionStart;
  if (a.value = i, s !== null) {
    const n = i.length - e.length, r = Math.max(0, s + n);
    a.setSelectionRange(r, r);
  }
  a.setAttribute("data-phone-local", L(a.value)), a.setAttribute("data-phone-cc", P(t));
}
function z(a, t) {
  const e = L(a.value), i = P(t);
  return {
    e164: ht(i, e),
    local: e,
    country: i
  };
}
function H(a) {
  if (a == null)
    return "";
  if (typeof a == "string")
    return a.trim();
  if (Array.isArray(a))
    return a.map((e) => H(e)).filter((e) => e !== "").join("; ");
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
      const s = H(e);
      if (s !== "")
        return s;
      continue;
    }
    const i = ["details", "detail", "debug", "error"];
    for (let s = 0; s < i.length; s += 1) {
      const n = i[s];
      if (Object.prototype.hasOwnProperty.call(e, n)) {
        const r = H(e[n]);
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
function gt(a) {
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
function U(a, t) {
  if (a && typeof a == "string")
    try {
      return new URL(a, window.location.origin).toString();
    } catch {
      return a;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
const St = ["service", "date", "party", "slots", "details", "confirm"], J = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (a) => window.requestIdleCallback(a) : (a) => window.setTimeout(() => a(Date.now()), 1);
let O = null;
function At() {
  return O || (O = Promise.resolve().then(() => Rt)), O;
}
function Et(a) {
  return et(a, "data-fp-resv-section");
}
function B(a) {
  if (!a || typeof a != "string" || /^\d{4}-\d{2}-\d{2}$/.test(a))
    return a;
  let t;
  if (a.includes("/"))
    t = a.split("/");
  else if (a.includes("-"))
    t = a.split("-");
  else
    return a;
  return t.length === 3 && t[0].length <= 2 ? `${t[2]}-${t[1].padStart(2, "0")}-${t[0].padStart(2, "0")}` : a;
}
class it {
  constructor(t) {
    this.root = t, this.dataset = yt(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(St);
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
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.ensureNoncePresent(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializePartyButtons(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateInlineErrors(), this.updateSummary(), J(() => {
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
      if (e.addEventListener("click", function(i) {
        i.preventDefault(), t.handleFirstInteraction(), t.handleMealSelection(e);
      }), e.hasAttribute("data-active") && t.hiddenMeal) {
        t.applyMealSelection(e);
        const i = e.getAttribute("data-fp-resv-meal") || "";
        i && t.updateAvailableDaysForMeal(i);
      }
    });
  }
  initializePhoneField() {
    if (this.phonePrefixField) {
      this.updatePhoneCountryFromPrefix();
      return;
    }
    this.phoneField && T(this.phoneField, this.getPhoneCountryCode());
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
    e === "" && (e = "39"), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = e), t !== "" && (this.phoneCountryCode = t), this.phoneField && T(this.phoneField, e);
  }
  initializeDateField() {
    if (!this.dateField)
      return;
    if (typeof window.flatpickr > "u") {
      console.error("[FP-RESV] Flatpickr non è disponibile. Impossibile inizializzare il calendario.");
      return;
    }
    this.availableDaysCache = {}, this.availableDaysLoading = !1, this.availableDaysCachedMeal = null, this.flatpickrInstance = window.flatpickr(this.dateField, {
      minDate: "today",
      dateFormat: "Y-m-d",
      // Formato ISO per il backend (nel campo hidden)
      altInput: !0,
      // Mostra un input alternativo all'utente
      altFormat: "d/m/Y",
      // Formato italiano mostrato all'utente
      locale: window.flatpickr.l10ns.it || "it",
      enable: [],
      // Inizialmente nessun giorno abilitato, lo aggiorneremo dopo il caricamento
      allowInput: !1,
      disableMobile: !1,
      // Usa il calendario nativo su mobile se preferito
      onChange: (e, i, s) => {
        const n = new Event("change", { bubbles: !0 });
        this.dateField.dispatchEvent(n);
      }
    }), this.createAvailableDaysHint();
    const t = this.getSelectedMeal();
    this.loadAvailableDays(t || void 0), this.dateField.addEventListener("change", (e) => {
      if (e.target.value) {
        e.target.setCustomValidity(""), e.target.setAttribute("aria-invalid", "false");
        const s = this.form.querySelector("[data-fp-resv-date-status]");
        s && (s.hidden = !0);
      }
    });
  }
  loadAvailableDays(t = null) {
    if (this.availableDaysLoading && this.availableDaysCachedMeal === t)
      return;
    this.availableDaysLoading = !0, this.availableDaysCachedMeal = t;
    const e = /* @__PURE__ */ new Date(), i = /* @__PURE__ */ new Date();
    i.setDate(i.getDate() + 90);
    const s = e.toISOString().split("T")[0], n = i.toISOString().split("T")[0], r = this.getRestRoot() + "/available-days", o = new URL(r, window.location.origin);
    o.searchParams.set("from", s), o.searchParams.set("to", n), t && o.searchParams.set("meal", t), fetch(o.toString(), {
      credentials: "same-origin",
      headers: { Accept: "application/json" }
    }).then((c) => c.json()).then((c) => {
      c && c.days && (this.availableDaysCache = c.days, this.applyDateRestrictions(), this.updateAvailableDaysHint());
    }).catch((c) => {
      console.warn("[FP-RESV] Errore nel caricamento dei giorni disponibili:", c);
    }).finally(() => {
      this.availableDaysLoading = !1;
    });
  }
  applyDateRestrictions() {
    if (!this.flatpickrInstance || !this.availableDaysCache)
      return;
    const t = this.getSelectedMeal(), e = [];
    Object.entries(this.availableDaysCache).forEach(([i, s]) => {
      if (!s)
        return;
      let n = !1;
      s.meals ? t ? n = s.meals[t] === !0 : n = Object.values(s.meals).some((r) => r === !0) : n = s.available === !0, n && e.push(i);
    }), this.flatpickrInstance.set("enable", e), this.updateAvailableDaysHint();
  }
  createAvailableDaysHint() {
    if (!this.dateField)
      return;
    const t = document.createElement("div");
    t.className = "fp-resv-available-days-hint", t.style.cssText = "margin-top: 8px; padding: 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; font-size: 14px; color: #0369a1; display: none;", t.setAttribute("aria-live", "polite"), t.setAttribute("data-fp-resv-days-hint", "");
    const e = this.dateField.closest("[data-fp-resv-field-container]") || this.dateField.parentElement;
    e && e.appendChild(t), this.availableDaysHintElement = t;
  }
  updateAvailableDaysHint() {
    if (!this.availableDaysHintElement || !this.availableDaysCache)
      return;
    const t = /* @__PURE__ */ new Set(), e = this.getSelectedMeal();
    if (Object.entries(this.availableDaysCache).forEach(([r, o]) => {
      if (!o)
        return;
      let c = !1;
      if (o.meals ? e ? c = o.meals[e] === !0 : c = Object.values(o.meals).some((h) => h === !0) : c = o.available === !0, c) {
        const v = (/* @__PURE__ */ new Date(r + "T12:00:00")).getDay();
        t.add(v);
      }
    }), t.size === 0) {
      this.availableDaysHintElement.style.display = "none";
      return;
    }
    if (t.size === 7) {
      this.availableDaysHintElement.style.display = "none";
      return;
    }
    const i = {
      0: "Domenica",
      1: "Lunedì",
      2: "Martedì",
      3: "Mercoledì",
      4: "Giovedì",
      5: "Venerdì",
      6: "Sabato"
    }, n = Array.from(t).sort((r, o) => r - o).map((r) => i[r]).join(", ");
    this.availableDaysHintElement.innerHTML = `
            <strong>📅 Giorni disponibili:</strong> ${n}<br>
            <span style="font-size: 12px; opacity: 0.8;">Seleziona una di queste giornate dal calendario</span>
        `, this.availableDaysHintElement.style.display = "block";
  }
  getRestRoot() {
    return this.dataset && this.dataset.restRoot ? this.dataset.restRoot : window.fpResvSettings && window.fpResvSettings.restRoot ? window.fpResvSettings.restRoot : "/wp-json/fp-resv/v1";
  }
  getSelectedMeal() {
    return this.hiddenMeal && this.hiddenMeal.value ? this.hiddenMeal.value : null;
  }
  initializePartyButtons() {
    const t = this.form ? this.form.querySelector("[data-fp-resv-party-decrement]") : null, e = this.form ? this.form.querySelector("[data-fp-resv-party-increment]") : null;
    if (!t || !e || !this.partyField)
      return;
    const i = () => {
      const s = parseInt(this.partyField.value, 10) || 1, n = parseInt(this.partyField.getAttribute("min"), 10) || 1, r = parseInt(this.partyField.getAttribute("max"), 10) || 40;
      t.disabled = s <= n, e.disabled = s >= r;
    };
    t.addEventListener("click", (s) => {
      s.preventDefault();
      const n = parseInt(this.partyField.value, 10) || 1, r = parseInt(this.partyField.getAttribute("min"), 10) || 1;
      n > r && (this.partyField.value = String(n - 1), this.partyField.dispatchEvent(new Event("input", { bubbles: !0 })), this.partyField.dispatchEvent(new Event("change", { bubbles: !0 })), i());
    }), e.addEventListener("click", (s) => {
      s.preventDefault();
      const n = parseInt(this.partyField.value, 10) || 1, r = parseInt(this.partyField.getAttribute("max"), 10) || 40;
      n < r && (this.partyField.value = String(n + 1), this.partyField.dispatchEvent(new Event("input", { bubbles: !0 })), this.partyField.dispatchEvent(new Event("change", { bubbles: !0 })), i());
    }), this.partyField.addEventListener("input", i), this.partyField.addEventListener("change", i), i();
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
      At().then((e) => {
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
    this.handleFirstInteraction(), e === this.phoneField ? T(this.phoneField, this.getPhoneCountryCode()) : e === this.phonePrefixField && this.updatePhoneCountryFromPrefix(), this.updateSummary();
    const i = e.getAttribute("data-fp-resv-field") || "", s = i && e.dataset.fpResvLastValue || "", n = i && typeof e.value == "string" ? e.value : "", r = !i || s !== n, o = Et(e);
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
    g(s, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), this.updateAvailableDaysForMeal(e), this.scheduleAvailabilityUpdate({ immediate: !0 });
  }
  updateAvailableDaysForMeal(t) {
    if (!this.dateField || !t)
      return;
    this.availableDaysCachedMeal !== t && this.loadAvailableDays(t);
    const e = this.dateField.value, i = B(e);
    if (i && this.availableDaysCache[i] !== void 0) {
      const s = this.availableDaysCache[i];
      let n = !1;
      if (s.meals ? n = s.meals[t] === !0 : n = s.available === !0, !n) {
        window.console && window.console.warn && console.warn("[FP-RESV] La data selezionata non è disponibile per questo servizio.");
        const r = this.form.querySelector("[data-fp-resv-date-status]");
        r && (r.textContent = "Questo servizio non è disponibile nel giorno selezionato.", r.hidden = !1, setTimeout(() => {
          r.hidden = !0;
        }, 3e3)), this.dateField.value = "", this.dateField.setCustomValidity(""), this.dateField.setAttribute("aria-invalid", "false"), this.availabilityController && typeof this.availabilityController.clearSelection == "function" && this.availabilityController.clearSelection();
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
          const c = this.sections[o];
          this.updateSectionAttributes(c, "locked", { silent: !0 });
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
    this.state.sectionStates[r] !== "completed" && (this.state.sectionStates[r] = "active", this.updateSectionAttributes(n, "active"), this.dispatchSectionUnlocked(r));
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
    g(e, { section: t });
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
      const c = r.getAttribute("data-step") || "", h = t.state.sectionStates[c] || "locked";
      r.setAttribute("data-state", h), r.setAttribute("data-progress-state", h === "completed" ? "done" : h);
      const v = r.querySelector(".fp-progress__label");
      v && (h === "active" ? v.removeAttribute("aria-hidden") : v.setAttribute("aria-hidden", "true"));
      const m = h === "locked";
      r.tabIndex = m ? -1 : 0, m ? r.setAttribute("aria-disabled", "true") : r.removeAttribute("aria-disabled"), h === "active" ? (r.setAttribute("aria-current", "step"), i = Math.max(i, o + 0.5)) : r.removeAttribute("aria-current"), h === "completed" ? (r.setAttribute("data-completed", "true"), i = Math.max(i, o + 1)) : r.removeAttribute("data-completed");
    });
    const n = Math.min(100, Math.max(0, Math.round(i / s * 100)));
    this.progress.style.setProperty("--fp-progress-fill", n + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    if ((t.getAttribute("data-step") || "") === "slots") {
      const n = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null, r = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, o = n && n.value.trim() !== "", c = r && r.value.trim() !== "";
      if (!o || !c)
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
    // Validazione logica aggiuntiva: richiedi selezione orario/slot quando presente lo step "slots"
    let logicallyValid = t;
    let hasSlotsStep = !1;
    let timeField = null;
    let slotStartField = null;
    let dateField = null;
    if (t) {
      hasSlotsStep = Array.isArray(this.sections) && this.sections.some((s) => (s.getAttribute("data-step") || "") === "slots");
      timeField = this.form.querySelector('[data-fp-resv-field="time"]');
      slotStartField = this.form.querySelector('input[name="fp_resv_slot_start"]');
      dateField = this.form.querySelector('[data-fp-resv-field="date"]');
      if (hasSlotsStep) {
        const hasTime = !!(timeField && typeof timeField.value === "string" && timeField.value.trim() !== "");
        const hasSlot = !!(slotStartField && typeof slotStartField.value === "string" && slotStartField.value.trim() !== "");
        logicallyValid = logicallyValid && hasTime && hasSlot;
      }
      if (dateField && typeof dateField.value === "string" && dateField.value.trim() === "") {
        logicallyValid = !1;
      }
    }
    // Aggiorna stato CTA
    if (this.state.sending) {
      this.setSubmitButtonState(!1, "sending");
    } else {
      this.setSubmitButtonState(logicallyValid, null);
    }
    // Aggiorna hint solo se non c'è già un override (es. errori server)
    if (!this.state.hintOverride) {
      if (!logicallyValid) {
        // Mostra suggerimento contestuale
        const needsSlot = hasSlotsStep && (!timeField || !timeField.value || timeField.value.trim() === "" || !slotStartField || !slotStartField.value || slotStartField.value.trim() === "");
        const needsDate = dateField && typeof dateField.value === "string" && dateField.value.trim() === "";
        if (needsSlot && this.copy && this.copy.slotRequired) {
          this.state.hintOverride = this.copy.slotRequired;
        } else if (needsDate && this.copy && this.copy.dateRequired) {
          this.state.hintOverride = this.copy.dateRequired;
        } else {
          this.state.hintOverride = "";
        }
      } else {
        this.state.hintOverride = "";
      }
    }
    if (this.submitHint) {
      const e = this.state.hintOverride || (logicallyValid ? this.state.initialHint : this.copy.ctaDisabled);
      this.submitHint.textContent = e;
    }
    if (logicallyValid && !this.state.formValidEmitted) {
      const e = this.events.form_valid || "form_valid";
      g(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
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
        const c = z(this.phoneField, this.getPhoneCountryCode());
        c.local && !$(c.local) && (r = !0, o = this.copy.invalidPhone);
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
    bt(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), s !== i && e !== "sending" && g("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), s = this.form.querySelector('[data-fp-resv-field="first_name"]'), n = this.form.querySelector('[data-fp-resv-field="last_name"]'), r = this.form.querySelector('[data-fp-resv-field="email"]'), o = this.form.querySelector('[data-fp-resv-field="phone"]'), c = this.form.querySelector('[data-fp-resv-field="notes"]'), h = this.form.querySelector('[data-fp-resv-field="high_chair_count"]'), v = this.form.querySelector('[data-fp-resv-field="wheelchair_table"]'), m = this.form.querySelector('[data-fp-resv-field="pets"]');
    let R = "";
    s && s.value && (R = s.value.trim()), n && n.value && (R = (R + " " + n.value.trim()).trim());
    let F = "";
    if (r && r.value && (F = r.value.trim()), o && o.value) {
      const S = this.getPhoneCountryCode(), V = (S ? "+" + S + " " : "") + o.value.trim();
      F = F !== "" ? F + " / " + V : V;
    }
    const w = [];
    h && typeof h.value == "string" && parseInt(h.value, 10) > 0 && w.push("Seggioloni: " + parseInt(h.value, 10)), v && "checked" in v && v.checked && w.push("Tavolo accessibile per sedia a rotelle"), m && "checked" in m && m.checked && w.push("Animali domestici");
    const b = w.join("; ");
    this.summaryTargets.forEach(function(S) {
      switch (S.getAttribute("data-fp-resv-summary")) {
        case "date":
          S.textContent = t && t.value ? t.value : "";
          break;
        case "time":
          S.textContent = e && e.value ? e.value : "";
          break;
        case "party":
          S.textContent = i && i.value ? i.value : "";
          break;
        case "name":
          S.textContent = R;
          break;
        case "contact":
          S.textContent = F;
          break;
        case "notes":
          S.textContent = c && c.value ? c.value : "";
          break;
        case "extras":
          S.textContent = b;
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
    g(e, {
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
    if (!s.fp_resv_nonce) {
      console.error("[FP-RESV] ATTENZIONE: Payload senza nonce! Tentativo di recupero...");
      const c = this.form.querySelector('input[name="fp_resv_nonce"]');
      c && c.value ? (s.fp_resv_nonce = c.value, console.log("[FP-RESV] Nonce recuperato dal form:", s.fp_resv_nonce.substring(0, 10) + "...")) : console.error("[FP-RESV] IMPOSSIBILE recuperare nonce!");
    }
    console.log("[FP-RESV] Payload inviato:", s), console.log("[FP-RESV] Nonce nel payload:", s.fp_resv_nonce ? "PRESENTE (" + s.fp_resv_nonce.substring(0, 10) + "...)" : "MANCANTE"), console.log("[FP-RESV] Endpoint:", n);
    try {
      const c = await fetch(n, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(s),
        credentials: "same-origin"
      });
      o = Math.round(performance.now() - r), g("ui_latency", { op: "submit", ms: o }), console.log("[FP-RESV] Response status:", c.status), console.log("[FP-RESV] Response headers:", {
        contentType: c.headers.get("content-type"),
        contentLength: c.headers.get("content-length")
      });
      const h = await c.text();
      if (console.log("[FP-RESV] Response text length:", h.length), console.log("[FP-RESV] Response text preview:", h.substring(0, 200)), !c.ok) {
        let m;
        try {
          m = h ? JSON.parse(h) : {};
        } catch (F) {
          console.error("[FP-RESV] Errore parsing risposta errore:", F), m = { message: "Risposta non valida dal server" };
        }
        if (console.error("[FP-RESV] Errore API:", {
          status: c.status,
          statusText: c.statusText,
          errorPayload: m
        }), c.status === 403 && !this.state.nonceRetried) {
          console.warn("[FP-RESV] Errore 403 - Tentativo di rigenerazione nonce..."), await new Promise((w) => setTimeout(w, 500));
          const F = await this.refreshNonce();
          if (console.log("[FP-RESV] Nonce fresco ottenuto:", F ? F.substring(0, 10) + "..." : "FALLITO"), F) {
            this.state.nonceRetried = !0, s.fp_resv_nonce = F, console.log("[FP-RESV] Retry con nuovo nonce..."), await new Promise((b) => setTimeout(b, 200));
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
              const b = await w.text();
              console.log("[FP-RESV] Retry success - Response text:", b.substring(0, 200));
              let S;
              try {
                S = b ? JSON.parse(b) : {};
              } catch (q) {
                console.error("[FP-RESV] Errore parsing retry success:", q), S = {};
              }
              return this.handleSubmitSuccess(S), this.state.nonceRetried = !1, !1;
            } else {
              const b = await gt(w);
              throw b && b.message && (b.message = b.message + " Se hai appena accettato i cookie, riprova tra qualche secondo."), Object.assign(new Error(b.message || this.copy.submitError), {
                status: w.status,
                payload: b
              });
            }
          }
        }
        const R = m && m.message || this.copy.submitError;
        throw Object.assign(new Error(R), {
          status: c.status,
          payload: m
        });
      }
      let v;
      try {
        v = h ? JSON.parse(h) : {}, console.log("[FP-RESV] Risposta successo parsata:", v);
      } catch (m) {
        throw console.error("[FP-RESV] ERRORE parsing risposta successo:", m), console.error("[FP-RESV] Testo risposta:", h), new Error("Risposta non valida dal server (JSON malformato)");
      }
      this.handleSubmitSuccess(v), this.state.requestId = null;
    } catch (c) {
      o || (o = Math.round(performance.now() - r), g("ui_latency", { op: "submit", ms: o })), this.handleSubmitError(c, o);
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
      i && i.event && g(i.event, i);
    });
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", s = t && t.message || this.copy.submitError, n = t && typeof t == "object" && t.payload || null;
    let r = tt(s, n);
    i === 403 && this.errorAlert && this.errorRetry && (this.errorRetry.textContent = this.messages.reload_button || "Ricarica pagina", this.errorRetry.onclick = (c) => {
      c.preventDefault(), window.location.reload();
    }), this.errorAlert && this.errorMessage && (this.errorMessage.textContent = r, this.errorAlert.hidden = !1, requestAnimationFrame(() => {
      typeof this.errorAlert.scrollIntoView == "function" && this.errorAlert.scrollIntoView({ behavior: "smooth", block: "center" }), typeof this.errorAlert.focus == "function" && (this.errorAlert.setAttribute("tabindex", "-1"), this.errorAlert.focus({ preventScroll: !0 }));
    })), this.state.hintOverride = r, this.updateSubmitState();
    const o = this.events.submit_error || "submit_error";
    g(o, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.errorRetry && (this.errorRetry.textContent = this.messages.retry_button || "Riprova", this.errorRetry.onclick = null), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, s) => {
      typeof i == "string" && (e[s] = i);
    }), e.fp_resv_date && (e.fp_resv_date = B(e.fp_resv_date)), console.log("[FP-RESV] Nonce nel form:", e.fp_resv_nonce ? "PRESENTE" : "MANCANTE"), !e.fp_resv_nonce) {
      console.warn("[FP-RESV] ATTENZIONE: Nonce mancante! Cercando nel DOM...");
      const i = this.form.querySelector('input[name="fp_resv_nonce"]');
      i ? (console.log("[FP-RESV] Nonce trovato nel DOM:", i.value.substring(0, 10) + "..."), e.fp_resv_nonce = i.value) : console.error("[FP-RESV] Campo nonce non trovato nel DOM!");
    }
    if (this.phoneField) {
      const i = z(this.phoneField, this.getPhoneCountryCode());
      i.e164 && (e.fp_resv_phone = i.e164), i.country && (e.fp_resv_phone_cc = i.country), i.local && (e.fp_resv_phone_local = i.local);
    }
    if (this.phonePrefixField && this.phonePrefixField.value && !e.fp_resv_phone_cc) {
      const i = P(this.phonePrefixField.value);
      i && (e.fp_resv_phone_cc = i);
    }
    return e;
  }
  async ensureNoncePresent() {
    if (!this.form.querySelector('input[name="fp_resv_nonce"]')) {
      console.warn("[FP-RESV] Campo nonce non trovato nel DOM! Creazione campo...");
      const e = document.createElement("input");
      e.type = "hidden", e.name = "fp_resv_nonce", e.value = "", this.form.appendChild(e);
    }
    console.log("[FP-RESV] Rigenerazione nonce per sicurezza...");
    try {
      const e = await this.refreshNonce();
      e ? console.log("[FP-RESV] Nonce rigenerato con successo:", e.substring(0, 10) + "...") : console.error("[FP-RESV] Impossibile ottenere nonce fresco!");
    } catch (e) {
      console.error("[FP-RESV] Errore richiesta nonce:", e);
    }
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
    const t = z(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = z(this.phoneField, this.getPhoneCountryCode());
    if (t.local === "") {
      this.phoneField.setCustomValidity(""), this.phoneField.removeAttribute("aria-invalid");
      return;
    }
    $(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), g("phone_validation_error", { field: "phone" }), g("ui_validation_error", { field: "phone" }));
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
    t.setCustomValidity(""), t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), g("ui_validation_error", { field: "email" }));
  }
  focusFirstInvalid() {
    const t = this.form.querySelector("[data-fp-resv-field]:invalid, [required]:invalid");
    t && typeof t.focus == "function" && t.focus();
  }
  findFirstInvalid(t) {
    return t ? t.querySelector("[data-fp-resv-field]:invalid, [required]:invalid") : null;
  }
  collectAvailabilityParams() {
    const t = this.hiddenMeal ? this.hiddenMeal.value : "", e = this.dateField && this.dateField.value ? this.dateField.value : "", i = B(e), s = this.partyField && this.partyField.value ? this.partyField.value : "";
    return {
      date: i,
      party: s,
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
    g("ui_latency", { op: "availability", ms: Math.round(t) });
  }
  handleAvailabilityRetry(t) {
    g("availability_retry", { attempt: t });
  }
  handleWindowFocus() {
    this.availabilityController && typeof this.availabilityController.revalidate == "function" && this.availabilityController.revalidate();
  }
  handleFirstInteraction() {
    if (this.state.started)
      return;
    const t = this.events.start || "reservation_start";
    g(t, { source: "form" }), this.state.started = !0;
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
    g(s, n);
  }
  handleReservationConfirmed(t) {
    if (!t || !t.detail)
      return;
    const e = t.detail || {}, i = this.events.confirmed || "reservation_confirmed";
    g(i, e), e && e.purchase && e.purchase.value && e.purchase.value_is_estimated && g(this.events.purchase || "purchase", e.purchase);
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
    return U(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return U(t.availability, "/wp-json/fp-resv/v1/availability");
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
          const c = e();
          s(c || null);
          return;
        }
        s(null);
      };
      let r = document.querySelector(`script[src="${t}"]`);
      if (!r && i && (r = document.querySelector(`script[${i}]`)), r) {
        if (typeof e == "function") {
          const c = e();
          if (c) {
            s(c);
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
function Y() {
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
const D = /* @__PURE__ */ new Set();
function I() {
  const a = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]");
  if (a.length === 0) {
    const t = document.querySelector(".entry-content, .post-content, .page-content, main, article");
    t ? (console.log("[FP-RESV] Found content container:", t.className || "unnamed"), console.log("[FP-RESV] Content container innerHTML length:", t.innerHTML.length), t.innerHTML.includes("fp-resv") && console.log("[FP-RESV] Found fp-resv string in content, but no valid widget element")) : console.log("[FP-RESV] No standard content container found");
    return;
  }
  Array.prototype.forEach.call(a, function(t) {
    if (t.parentElement && t.parentElement.tagName === "P") {
      const e = t.parentElement;
      e.parentElement && (e.parentElement.insertBefore(t, e), e.remove(), console.log("[FP-RESV] Removed WPBakery <p> wrapper"));
    }
    if (D.has(t)) {
      console.log("[FP-RESV] Widget already initialized, skipping:", t.id || "unnamed");
      return;
    }
    try {
      D.add(t), st(t), console.log("[FP-RESV] Initializing widget:", t.id || "unnamed"), console.log("[FP-RESV] Widget sections found:", t.querySelectorAll("[data-fp-resv-section]").length);
      const e = new it(t);
      console.log("[FP-RESV] Widget initialized successfully:", t.id || "unnamed"), (e.sections || []).forEach(function(s, n) {
        const r = s.getAttribute("data-step"), o = s.getAttribute("data-state"), c = s.hasAttribute("hidden");
        console.log(`[FP-RESV] Step ${n + 1} (${r}): state=${o}, hidden=${c}`);
      });
    } catch (e) {
      console.error("[FP-RESV] Error initializing widget:", e), D.delete(t);
    }
  });
}
function G() {
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
    }), e && (console.log("[FP-RESV] New widget(s) detected in DOM, initializing..."), I());
  }).observe(document.body, {
    childList: !0,
    subtree: !0
  }), console.log("[FP-RESV] MutationObserver set up to detect dynamic widgets");
}
function X() {
  [500, 1e3, 2e3, 3e3].forEach(function(t) {
    setTimeout(function() {
      const e = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length;
      e > D.size && (console.log("[FP-RESV] Retry: Found " + e + " widgets, " + D.size + " initialized"), I());
    }, t);
  });
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", function() {
  I(), setTimeout(Y, 500), G(), X();
}) : (I(), setTimeout(Y, 500), G(), X());
(typeof window.vc_js < "u" || document.querySelector("[data-vc-full-width]") || document.querySelector(".vc_row")) && (console.log("[FP-RESV] WPBakery detected - adding compatibility listeners"), document.addEventListener("vc-full-content-loaded", function() {
  console.log("[FP-RESV] WPBakery vc-full-content-loaded event - re-initializing..."), setTimeout(I, 100);
}), window.addEventListener("load", function() {
  setTimeout(function() {
    document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length > D.size && (console.log("[FP-RESV] WPBakery late load - found new widgets, initializing..."), I());
  }, 1e3);
}), [1500, 3e3, 5e3, 1e4].forEach(function(a) {
  setTimeout(function() {
    document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length > D.size && (console.log("[FP-RESV] WPBakery extended retry (" + a + "ms) - initializing..."), I());
  }, a);
}));
document.addEventListener("fp-resv:tracking:push", function(a) {
  if (!a || !a.detail)
    return;
  const t = a.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  g(e, i && typeof i == "object" ? i : {});
});
const wt = 400, Ft = 6e4, Ct = 3, Z = 600;
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
function j(a) {
  for (; a.firstChild; )
    a.removeChild(a.firstChild);
}
function _t(a) {
  const t = a.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), s = t.querySelector("[data-fp-resv-slots-empty]"), n = t.querySelector("[data-fp-resv-slots-boundary]"), r = n ? n.querySelector("[data-fp-resv-slots-retry]") : null, o = /* @__PURE__ */ new Map();
  let c = null, h = null, v = null, m = 0;
  function R(l) {
    if (typeof l != "string")
      return "";
    const u = l.trim().toLowerCase();
    if (u === "")
      return "";
    const f = ((A) => typeof A.normalize == "function" ? A.normalize("NFD").replace(/[\u0300-\u036f]/g, "") : A)(u), y = (A) => A.some((d) => f.startsWith(d)), C = (A) => A.some((d) => f.includes(d));
    return y(["available", "open", "disponibil", "disponible", "liber", "libre", "apert", "abiert"]) ? "available" : u === "waitlist" || u === "busy" || y(["limited", "limit", "limitat", "limite", "cupos limit", "attesa"]) || C(["pochi posti", "quasi pien", "lista attesa", "few spots", "casi llen"]) ? "limited" : y(["full", "complet", "esaurit", "soldout", "sold out", "agotad", "chius", "plen"]) ? "full" : u;
  }
  function F(l, u) {
    const p = Array.isArray(l) ? l : [], f = p.length;
    if (f === 0)
      return u === !1 ? { state: "unavailable", slots: 0 } : { state: "full", slots: 0 };
    const y = p.map((d) => R(d && d.status)).filter((d) => d !== "");
    return y.some((d) => d === "limited") ? { state: "limited", slots: f } : y.some((d) => d === "available") ? { state: "available", slots: f } : u ? { state: "available", slots: f } : y.length === 0 ? { state: "available", slots: f } : { state: "full", slots: f };
  }
  function w(l, u) {
    if (typeof a.onAvailabilitySummary == "function")
      try {
        a.onAvailabilitySummary(u, l || h || {});
      } catch {
      }
  }
  r && r.addEventListener("click", () => {
    h && M(h, 0);
  });
  function b(l, u) {
    const p = typeof u == "string" ? u : u ? "loading" : "idle", f = typeof l == "string" ? l : "";
    e && (e.textContent = f, e.setAttribute("data-state", p));
    const y = p === "loading";
    t.setAttribute("data-loading", y ? "true" : "false"), i && i.setAttribute("aria-busy", y ? "true" : "false");
  }
  function S() {
    if (!i)
      return;
    j(i);
    const l = a.skeletonCount || 4;
    for (let u = 0; u < l; u += 1) {
      const p = document.createElement("li"), f = document.createElement("span");
      f.className = "fp-skeleton", p.appendChild(f), i.appendChild(p);
    }
  }
  function q(l) {
    s && (s.hidden = !1);
    const u = l && typeof l == "object", p = u && typeof l.meal == "string" ? l.meal.trim() : "", f = u && typeof l.date == "string" ? l.date.trim() : "", y = u && typeof l.party < "u" ? String(l.party).trim() : "", C = u && !!l.requiresMeal, A = p !== "", E = f !== "" && (y !== "" && y !== "0") && (!C || A), _ = C && !A ? a.strings && a.strings.selectMeal || "" : E && a.strings && a.strings.slotsEmpty || "";
    b(_, "idle"), i && j(i), w(l, { state: E ? "unavailable" : "unknown", slots: 0 });
  }
  function V() {
    s && (s.hidden = !0);
  }
  function W() {
    n && (n.hidden = !0);
  }
  function at(l) {
    const u = a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.";
    if (n) {
      const p = n.querySelector("[data-fp-resv-slots-boundary-message]");
      p && (p.textContent = l || u), n.hidden = !1;
    }
    b(l || u, "error"), w(h, { state: "error", slots: 0 });
  }
  function nt(l, u) {
    const p = i ? i.querySelectorAll("button[data-slot]") : [];
    Array.prototype.forEach.call(p, (f) => {
      f.setAttribute("aria-pressed", f === u ? "true" : "false");
    }), v = l, typeof a.onSlotSelected == "function" && a.onSlotSelected(l);
  }
  function rt() {
    if (v = null, !i)
      return;
    const l = i.querySelectorAll("button[data-slot]");
    Array.prototype.forEach.call(l, (u) => {
      u.setAttribute("aria-pressed", "false");
    });
  }
  function K(l, u, p) {
    if (p && p !== m || u && h && u !== h || (W(), V(), !i))
      return;
    j(i);
    const f = l && Array.isArray(l.slots) ? l.slots : [];
    if (f.length === 0) {
      q(u);
      return;
    }
    f.forEach((C) => {
      const A = document.createElement("li"), d = document.createElement("button");
      d.type = "button", d.textContent = C.label || "", d.dataset.slot = C.start || "", d.dataset.slotStatus = C.status || "", d.setAttribute("aria-pressed", v && v.start === C.start ? "true" : "false"), d.addEventListener("click", () => nt(C, d)), A.appendChild(d), i.appendChild(A);
    }), b(a.strings && a.strings.slotsUpdated || "", !1);
    const y = !!(l && (typeof l.has_availability < "u" && l.has_availability || l.meta && l.meta.has_availability));
    w(u, F(f, y));
  }
  function M(l, u) {
    if (h = l, !l || !l.date || !l.party) {
      q(l);
      return;
    }
    const p = ++m, f = JSON.stringify([l.date, l.meal, l.party]), y = o.get(f);
    if (y && Date.now() - y.timestamp < Ft && u === 0) {
      K(y.payload, l, p);
      return;
    }
    W(), V(), S(), b(a.strings && a.strings.updatingSlots || "Aggiornamento disponibilità…", "loading"), w(l, { state: "loading", slots: 0 });
    const C = Pt(a.endpoint, l), A = performance.now();
    fetch(C, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((d) => d.json().catch(() => ({})).then((x) => {
      if (!d.ok) {
        const E = new Error("availability_error");
        E.status = d.status, E.payload = x;
        const _ = d.headers.get("Retry-After");
        if (_) {
          const k = Number.parseInt(_, 10);
          Number.isFinite(k) && (E.retryAfter = k);
        }
        throw E;
      }
      return x;
    })).then((d) => {
      if (p !== m)
        return;
      const x = performance.now() - A;
      typeof a.onLatency == "function" && a.onLatency(x), o.set(f, { payload: d, timestamp: Date.now() }), K(d, l, p);
    }).catch((d) => {
      if (p !== m)
        return;
      const x = performance.now() - A;
      typeof a.onLatency == "function" && a.onLatency(x);
      const E = d && d.payload && typeof d.payload == "object" ? d.payload.data || {} : {}, _ = typeof d.status == "number" ? d.status : E && typeof E.status == "number" ? E.status : 0;
      let k = 0;
      if (d && typeof d.retryAfter == "number" && Number.isFinite(d.retryAfter))
        k = d.retryAfter;
      else if (E && typeof E.retry_after < "u") {
        const N = Number.parseInt(E.retry_after, 10);
        Number.isFinite(N) && (k = N);
      }
      if (u >= Ct - 1 ? !1 : _ === 429 || _ >= 500 && _ < 600 ? !0 : _ === 0) {
        const N = u + 1;
        typeof a.onRetry == "function" && a.onRetry(N);
        const dt = k > 0 ? Math.max(k * 1e3, Z) : Z * Math.pow(2, u);
        window.setTimeout(() => M(l, N), dt);
        return;
      }
      const ot = d && d.payload && (d.payload.message || d.payload.code) || E && E.message || a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.", lt = d && d.payload || E || null, ct = tt(ot, lt);
      at(ct);
    });
  }
  return {
    schedule(l, u = {}) {
      c && window.clearTimeout(c);
      const p = u && typeof u == "object" ? u : {}, f = l || (typeof a.getParams == "function" ? a.getParams() : null), y = !!(f && f.requiresMeal);
      if (!f || !f.date || !f.party || y && !f.meal) {
        h = f, q(f || {});
        return;
      }
      if (p.immediate) {
        M(f, 0);
        return;
      }
      c = window.setTimeout(() => {
        M(f, 0);
      }, wt);
    },
    revalidate() {
      if (!h)
        return;
      const l = JSON.stringify([h.date, h.meal, h.party]);
      o.delete(l), M(h, 0);
    },
    getSelection() {
      return v;
    },
    clearSelection() {
      rt();
    }
  };
}
const Rt = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: _t
}, Symbol.toStringTag, { value: "Module" }));
