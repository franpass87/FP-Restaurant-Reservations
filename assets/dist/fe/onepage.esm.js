function v(a, t) {
  if (!a)
    return null;
  const e = Object.assign({ event: a }, t || {});
  return window.dataLayer = window.dataLayer || [], window.dataLayer.push(e), window.fpResvTracking && typeof window.fpResvTracking.dispatch == "function" && window.fpResvTracking.dispatch(e), e;
}
const mt = /\D+/g;
function st(a) {
  return a ? String(a).replace(mt, "") : "";
}
function k(a) {
  const t = st(a);
  return t === "" ? "" : t.replace(/^0+/, "");
}
function z(a) {
  return st(a);
}
function yt(a, t) {
  const e = k(a), i = z(t);
  return e === "" || i === "" ? "" : "+" + e + i;
}
function G(a) {
  const t = z(a);
  return t.length >= 6 && t.length <= 15;
}
function vt(a) {
  const t = z(a);
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
function W(a, t) {
  const e = a.value, { masked: i } = vt(e), s = a.selectionStart;
  if (a.value = i, s !== null) {
    const n = i.length - e.length, r = Math.max(0, s + n);
    a.setSelectionRange(r, r);
  }
  a.setAttribute("data-phone-local", z(a.value)), a.setAttribute("data-phone-cc", k(t));
}
function j(a, t) {
  const e = z(a.value), i = k(t);
  return {
    e164: yt(i, e),
    local: e,
    country: i
  };
}
function J(a) {
  if (a == null)
    return "";
  if (typeof a == "string")
    return a.trim();
  if (Array.isArray(a))
    return a.map((e) => J(e)).filter((e) => e !== "").join("; ");
  if (typeof a == "object") {
    if (typeof a.message == "string" && a.message.trim() !== "")
      return a.message.trim();
    if (typeof a.detail == "string" && a.detail.trim() !== "")
      return a.detail.trim();
  }
  return String(a).trim();
}
function bt(a) {
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
      const s = J(e);
      if (s !== "")
        return s;
      continue;
    }
    const i = ["details", "detail", "debug", "error"];
    for (let s = 0; s < i.length; s += 1) {
      const n = i[s];
      if (Object.prototype.hasOwnProperty.call(e, n)) {
        const r = J(e[n]);
        if (r !== "")
          return r;
      }
    }
    Object.prototype.hasOwnProperty.call(e, "data") && e.data && typeof e.data == "object" && t.push(e.data);
  }
  return "";
}
function at(a, t) {
  const e = bt(t);
  return e === "" ? a : a ? a.includes(e) ? a : a + " (" + e + ")" : e;
}
function gt(a) {
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
function St(a, t) {
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
function At(a) {
  if (a == null)
    return null;
  if (typeof a == "number")
    return Number.isFinite(a) ? a : null;
  const t = String(a).replace(",", "."), e = parseFloat(t);
  return Number.isNaN(e) ? null : e;
}
function nt(a, t) {
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
function Et(a, t) {
  a && (t ? (a.setAttribute("aria-disabled", "true"), a.setAttribute("disabled", "disabled")) : (a.removeAttribute("disabled"), a.setAttribute("aria-disabled", "false")));
}
function Ct(a) {
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
function X(a, t) {
  if (a && typeof a == "string")
    try {
      return new URL(a, window.location.origin).toString();
    } catch {
      return a;
    }
  return window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root.replace(/\/$/, "") + t : t;
}
const wt = ["service", "date", "party", "slots", "details", "confirm"], Q = typeof window < "u" && typeof window.requestIdleCallback == "function" ? (a) => window.requestIdleCallback(a) : (a) => window.setTimeout(() => a(Date.now()), 1);
let K = null;
function Ft() {
  return K || (K = Promise.resolve().then(() => Nt)), K;
}
function _t(a) {
  return nt(a, "data-fp-resv-section");
}
function U(a) {
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
class rt {
  constructor(t) {
    this.root = t, this.dataset = gt(t), this.config = this.dataset.config || {}, this.strings = this.dataset.strings || {}, this.messages = this.strings.messages || {}, this.events = this.dataset && this.dataset.events || {}, this.integrations = this.config.integrations || this.config.features || {}, this.form = t.querySelector("[data-fp-resv-form]");
    const e = Array.from(wt);
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
    }, this.phoneCountryCode = this.getPhoneCountryCode(), this.hiddenPhoneCc && this.hiddenPhoneCc.value === "" && (this.hiddenPhoneCc.value = this.phoneCountryCode), this.handleDelegatedTrackingEvent = this.handleDelegatedTrackingEvent.bind(this), this.handleReservationConfirmed = this.handleReservationConfirmed.bind(this), this.handleWindowFocus = this.handleWindowFocus.bind(this), !(!this.form || this.sections.length === 0) && (this.bind(), this.initializeSections(), this.ensureNoncePresent(), this.initializePhoneField(), this.initializeMeals(), this.initializeDateField(), this.initializePartyButtons(), this.initializeAvailability(), this.syncConsentState(), this.updateSubmitState(), this.updateInlineErrors(), this.updateSummary(), Q(() => {
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
    this.phoneField && W(this.phoneField, this.getPhoneCountryCode());
  }
  updatePhoneCountryFromPrefix() {
    if (!this.phonePrefixField)
      return;
    const t = k(this.phonePrefixField.value);
    let e = t;
    if (e === "" && this.phoneCountryCode) {
      const i = k(this.phoneCountryCode);
      i && (e = i);
    }
    if (e === "" && this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const i = k(this.hiddenPhoneCc.value);
      i && (e = i);
    }
    if (e === "") {
      const i = this.config && this.config.defaults || {};
      if (i.phone_country_code) {
        const s = k(i.phone_country_code);
        s && (e = s);
      }
    }
    e === "" && (e = "39"), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = e), t !== "" && (this.phoneCountryCode = t), this.phoneField && W(this.phoneField, e);
  }
  initializeDateField() {
    if (!this.dateField)
      return;
    if (typeof window.flatpickr > "u") {
      console.error("[FP-RESV] Flatpickr non è disponibile. Impossibile inizializzare il calendario.");
      return;
    }
    this.availableDaysCache = {}, this.availableDaysLoading = !1, this.availableDaysCachedMeal = null, this.calendarErrorTimeout = null, this.availableDaysRequestId = 0, this.availableDaysAbortController = null, this.flatpickrInstance = window.flatpickr(this.dateField, {
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
      },
      onDayCreate: (e, i, s, n) => {
        if (!n || !n.dateObj)
          return;
        const r = this.formatLocalDate(n.dateObj), o = this.availableDaysCache[r];
        if (!o || !o.available)
          n.title = "Data non disponibile", n.setAttribute("aria-label", "Data non disponibile");
        else if (o.meals && typeof o.meals == "object") {
          const l = Object.keys(o.meals).filter((c) => o.meals[c]);
          if (l.length > 0) {
            const c = "Disponibile: " + l.join(", ");
            n.title = c, n.setAttribute("aria-label", c);
          } else
            n.title = "Seleziona per vedere disponibilità", n.setAttribute("aria-label", "Data selezionabile");
        }
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
    this.availableDaysAbortController && this.availableDaysAbortController.abort(), this.availableDaysAbortController = new AbortController(), this.availableDaysRequestId++;
    const e = this.availableDaysRequestId;
    this.availableDaysLoading = !0, this.availableDaysCachedMeal = t, this.showCalendarLoading();
    const i = /* @__PURE__ */ new Date(), s = /* @__PURE__ */ new Date();
    s.setDate(s.getDate() + 90);
    const n = this.formatLocalDate(i), r = this.formatLocalDate(s), o = this.getRestRoot() + "/available-days", l = new URL(o, window.location.origin);
    l.searchParams.set("from", n), l.searchParams.set("to", r), t && l.searchParams.set("meal", t), fetch(l.toString(), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
      signal: this.availableDaysAbortController.signal
      // Supporto abort
    }).then((c) => {
      if (!c.ok)
        throw new Error(`HTTP error! status: ${c.status}`);
      return c.json();
    }).then((c) => {
      e === this.availableDaysRequestId && c && c.days && (this.availableDaysCache = c.days, this.applyDateRestrictions(), this.updateAvailableDaysHint());
    }).catch((c) => {
      c.name !== "AbortError" && e === this.availableDaysRequestId && this.showCalendarError();
    }).finally(() => {
      e === this.availableDaysRequestId && (this.availableDaysLoading = !1, this.hideCalendarLoading(), this.availableDaysAbortController = null);
    });
  }
  showCalendarLoading() {
    if (this.hideCalendarLoading(), !this.dateField || !this.dateField.parentElement)
      return;
    const t = document.createElement("div");
    t.className = "fp-calendar-loading", t.setAttribute("data-fp-loading", "true"), t.setAttribute("role", "status"), t.setAttribute("aria-live", "polite"), t.textContent = "Caricamento date disponibili...", this.dateField.parentElement.appendChild(t);
  }
  hideCalendarLoading() {
    if (!this.dateField || !this.dateField.parentElement)
      return;
    const t = this.dateField.parentElement.querySelector('[data-fp-loading="true"]');
    t && t.parentNode && t.remove();
  }
  showCalendarError() {
    if (this.hideCalendarLoading(), this.calendarErrorTimeout && (clearTimeout(this.calendarErrorTimeout), this.calendarErrorTimeout = null), this.hideCalendarError(), !this.dateField || !this.dateField.parentElement)
      return;
    const t = document.createElement("div");
    t.className = "fp-calendar-error", t.setAttribute("data-fp-error", "true"), t.setAttribute("role", "alert"), t.setAttribute("aria-live", "assertive"), t.style.cssText = "margin-top:8px;padding:8px 12px;background:#fee2e2;border-left:3px solid #ef4444;border-radius:4px;font-size:13px;color:#991b1b;", t.textContent = "⚠️ Impossibile caricare le date disponibili. Riprova.", this.dateField.parentElement.appendChild(t), this.calendarErrorTimeout = setTimeout(() => {
      t && t.parentNode && t.remove(), this.calendarErrorTimeout = null;
    }, 5e3);
  }
  hideCalendarError() {
    if (!this.dateField || !this.dateField.parentElement)
      return;
    const t = this.dateField.parentElement.querySelector('[data-fp-error="true"]');
    t && t.parentNode && t.remove(), this.calendarErrorTimeout && (clearTimeout(this.calendarErrorTimeout), this.calendarErrorTimeout = null);
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
    const e = document.createElement("div");
    e.className = "fp-calendar-hint", e.innerHTML = `
            <span class="fp-hint-icon">📅</span>
            <span class="fp-hint-text">
                <strong>Legenda calendario:</strong><br>
                <span style="color:#10b981">●</span> Verde = Disponibile &nbsp;|&nbsp;
                <span style="color:#9ca3af">●</span> Grigio barrato = Non disponibile &nbsp;|&nbsp;
                <span style="color:#3b82f6">●</span> Blu = Oggi
            </span>
        `;
    const i = this.dateField.closest("[data-fp-resv-field-container]") || this.dateField.parentElement;
    i && (i.appendChild(t), i.appendChild(e)), this.availableDaysHintElement = t;
  }
  updateAvailableDaysHint() {
    if (!this.availableDaysHintElement || !this.availableDaysCache)
      return;
    const t = /* @__PURE__ */ new Set(), e = this.getSelectedMeal();
    if (Object.entries(this.availableDaysCache).forEach(([u, h]) => {
      if (!h)
        return;
      let y = !1;
      if (h.meals ? e ? y = h.meals[e] === !0 : y = Object.values(h.meals).some((b) => b === !0) : y = h.available === !0, y) {
        const S = (/* @__PURE__ */ new Date(u + "T12:00:00")).getDay();
        t.add(S);
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
    }, n = Array.from(t).sort((u, h) => u - h).map((u) => i[u]).join(", ");
    this.availableDaysHintElement.innerHTML = "";
    const r = document.createElement("strong");
    r.textContent = "📅 Giorni disponibili: ";
    const o = document.createTextNode(n), l = document.createElement("br"), c = document.createElement("span");
    c.style.cssText = "font-size: 12px; opacity: 0.8;", c.textContent = "Seleziona una di queste giornate dal calendario", this.availableDaysHintElement.appendChild(r), this.availableDaysHintElement.appendChild(o), this.availableDaysHintElement.appendChild(l), this.availableDaysHintElement.appendChild(c), this.availableDaysHintElement.style.display = "block";
  }
  getRestRoot() {
    return this.dataset && this.dataset.restRoot ? this.dataset.restRoot : window.fpResvSettings && window.fpResvSettings.restRoot ? window.fpResvSettings.restRoot : "/wp-json/fp-resv/v1";
  }
  /**
   * Formatta una data nel timezone locale (YYYY-MM-DD) senza convertire in UTC
   * IMPORTANTE: toISOString() converte sempre in UTC, causando problemi con timezone
   * @param {Date} date - Oggetto Date
   * @returns {string} Data formattata in YYYY-MM-DD nel timezone locale
   */
  formatLocalDate(t) {
    const e = t.getFullYear(), i = String(t.getMonth() + 1).padStart(2, "0"), s = String(t.getDate()).padStart(2, "0");
    return `${e}-${i}-${s}`;
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
    Q(() => {
      Ft().then((e) => {
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
    this.handleFirstInteraction(), e === this.phoneField ? W(this.phoneField, this.getPhoneCountryCode()) : e === this.phonePrefixField && this.updatePhoneCountryFromPrefix(), this.updateSummary();
    const i = e.getAttribute("data-fp-resv-field") || "", s = i && e.dataset.fpResvLastValue || "", n = i && typeof e.value == "string" ? e.value : "", r = !i || s !== n, o = _t(e);
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
    v(s, {
      meal_type: t.getAttribute("data-fp-resv-meal") || "",
      meal_label: t.getAttribute("data-meal-label") || ""
    }), this.updateAvailableDaysForMeal(e), this.scheduleAvailabilityUpdate({ immediate: !0 });
  }
  updateAvailableDaysForMeal(t) {
    if (!this.dateField || !t)
      return;
    this.availableDaysCachedMeal !== t && this.loadAvailableDays(t);
    const e = this.dateField.value, i = U(e);
    if (i && this.availableDaysCache[i] !== void 0) {
      const s = this.availableDaysCache[i];
      let n = !1;
      if (s.meals ? n = s.meals[t] === !0 : n = s.available === !0, !n) {
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
    const i = At(t.getAttribute("data-meal-price"));
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
          const l = this.sections[o];
          this.updateSectionAttributes(l, "locked", { silent: !0 });
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
    v(e, { section: t });
  }
  updateSectionAttributes(t, e, i = {}) {
    const s = t.getAttribute("data-step") || "", n = i && i.silent === !0;
    this.state.sectionStates[s] = e, t.setAttribute("data-state", e), e === "completed" ? t.setAttribute("data-complete-hidden", "true") : t.removeAttribute("data-complete-hidden");
    const r = e === "active";
    t.setAttribute("aria-expanded", r ? "true" : "false"), r ? (t.hidden = !1, t.removeAttribute("hidden"), t.removeAttribute("inert"), t.style.display = "block", t.style.visibility = "visible", t.style.opacity = "1") : (t.hidden = !0, t.setAttribute("hidden", ""), t.setAttribute("inert", ""), t.style.display = "none", t.style.visibility = "hidden", t.style.opacity = "0"), n || this.updateProgressIndicators(), this.updateStickyCtaVisibility();
  }
  updateProgressIndicators() {
    if (!this.progress)
      return;
    const t = this, e = this.progressItems && this.progressItems.length ? this.progressItems : Array.prototype.slice.call(this.progress.querySelectorAll("[data-step]"));
    let i = 0;
    const s = e.length || 1;
    Array.prototype.forEach.call(e, function(r, o) {
      const l = r.getAttribute("data-step") || "", c = t.state.sectionStates[l] || "locked";
      r.setAttribute("data-state", c), r.setAttribute("data-progress-state", c === "completed" ? "done" : c);
      const u = r.querySelector(".fp-progress__label");
      u && (c === "active" ? u.removeAttribute("aria-hidden") : u.setAttribute("aria-hidden", "true"));
      const h = c === "locked";
      r.tabIndex = h ? -1 : 0, h ? r.setAttribute("aria-disabled", "true") : r.removeAttribute("aria-disabled"), c === "active" ? (r.setAttribute("aria-current", "step"), i = Math.max(i, o + 0.5)) : r.removeAttribute("aria-current"), c === "completed" ? (r.setAttribute("data-completed", "true"), i = Math.max(i, o + 1)) : r.removeAttribute("data-completed");
    });
    const n = Math.min(100, Math.max(0, Math.round(i / s * 100)));
    this.progress.style.setProperty("--fp-progress-fill", n + "%");
  }
  isSectionValid(t) {
    const e = t.querySelectorAll("[data-fp-resv-field]");
    if (e.length === 0)
      return !0;
    if ((t.getAttribute("data-step") || "") === "slots") {
      const n = this.form ? this.form.querySelector('[data-fp-resv-field="time"]') : null, r = this.form ? this.form.querySelector('input[name="fp_resv_slot_start"]') : null, o = n && n.value.trim() !== "", l = r && r.value.trim() !== "";
      if (!o || !l)
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
      v(e, { timestamp: Date.now() }), this.state.formValidEmitted = !0;
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
        const l = j(this.phoneField, this.getPhoneCountryCode());
        l.local && !G(l.local) && (r = !0, o = this.copy.invalidPhone);
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
    Et(this.submitButton, !i), this.submitLabel && (e === "sending" ? this.submitLabel.textContent = this.copy.ctaSending : i ? this.submitLabel.textContent = this.copy.ctaEnabled : this.submitLabel.textContent = this.copy.ctaDisabled), this.submitSpinner && (this.submitSpinner.hidden = e !== "sending"), s !== i && e !== "sending" && v("cta_state_change", { enabled: i }), this.state.ctaEnabled = i;
  }
  updateSummary() {
    if (this.summaryTargets.length === 0)
      return;
    const t = this.form.querySelector('[data-fp-resv-field="date"]'), e = this.form.querySelector('[data-fp-resv-field="time"]'), i = this.form.querySelector('[data-fp-resv-field="party"]'), s = this.form.querySelector('[data-fp-resv-field="first_name"]'), n = this.form.querySelector('[data-fp-resv-field="last_name"]'), r = this.form.querySelector('[data-fp-resv-field="email"]'), o = this.form.querySelector('[data-fp-resv-field="phone"]'), l = this.form.querySelector('[data-fp-resv-field="notes"]'), c = this.form.querySelector('[data-fp-resv-field="high_chair_count"]'), u = this.form.querySelector('[data-fp-resv-field="wheelchair_table"]'), h = this.form.querySelector('[data-fp-resv-field="pets"]'), y = this.form.querySelector('[data-fp-resv-field="occasion"]');
    let b = "";
    s && s.value && (b = s.value.trim()), n && n.value && (b = (b + " " + n.value.trim()).trim());
    let S = "";
    if (r && r.value && (S = r.value.trim()), o && o.value) {
      const w = this.getPhoneCountryCode(), B = (w ? "+" + w + " " : "") + o.value.trim();
      S = S !== "" ? S + " / " + B : B;
    }
    const C = {
      birthday: "Compleanno",
      anniversary: "Anniversario",
      business: "Cena di lavoro",
      celebration: "Festa/Celebrazione",
      romantic: "Cena romantica",
      other: "Altro"
    };
    let _ = "";
    y && y.value && y.value !== "" && (_ = C[y.value] || y.value);
    const P = [];
    c && typeof c.value == "string" && parseInt(c.value, 10) > 0 && P.push("Seggioloni: " + parseInt(c.value, 10)), u && "checked" in u && u.checked && P.push("Tavolo accessibile per sedia a rotelle"), h && "checked" in h && h.checked && P.push("Animali domestici");
    const H = P.join("; ");
    this.summaryTargets.forEach(function(w) {
      switch (w.getAttribute("data-fp-resv-summary")) {
        case "date":
          w.textContent = t && t.value ? t.value : "";
          break;
        case "time":
          w.textContent = e && e.value ? e.value : "";
          break;
        case "party":
          w.textContent = i && i.value ? i.value : "";
          break;
        case "name":
          w.textContent = b;
          break;
        case "contact":
          w.textContent = S;
          break;
        case "notes":
          w.textContent = l && l.value ? l.value : "";
          break;
        case "occasion":
          w.textContent = _;
          break;
        case "extras":
          w.textContent = H;
          break;
      }
    });
    const L = this.form ? this.form.querySelector("[data-fp-resv-summary-occasion-row]") : null;
    L && (_ === "" ? (L.hidden = !0, L.style.display = "none") : (L.hidden = !1, L.style.display = ""));
  }
  async handleSubmit(t) {
    if (t.preventDefault(), this.state.sending)
      return !1;
    if (this.state.touchedFields.consent = !0, !this.form.checkValidity())
      return this.form.reportValidity(), this.focusFirstInvalid(), this.updateInlineErrors(), this.updateSubmitState(), !1;
    const e = this.events.submit || "reservation_submit", i = this.collectAvailabilityParams();
    v(e, {
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
      const l = this.form.querySelector('input[name="fp_resv_nonce"]');
      l && l.value ? s.fp_resv_nonce = l.value : console.error("[FP-RESV] IMPOSSIBILE recuperare nonce!");
    }
    try {
      const l = await fetch(n, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(s),
        credentials: "same-origin"
      });
      o = Math.round(performance.now() - r), v("ui_latency", { op: "submit", ms: o });
      const c = await l.text();
      if (!l.ok) {
        let h;
        try {
          h = c ? JSON.parse(c) : {};
        } catch {
          h = { message: "Risposta non valida dal server" };
        }
        if (l.status === 403 && !this.state.nonceRetried) {
          await new Promise((S) => setTimeout(S, 500));
          const b = await this.refreshNonce();
          if (b) {
            this.state.nonceRetried = !0, s.fp_resv_nonce = b, await new Promise((C) => setTimeout(C, 200));
            const S = await fetch(n, {
              method: "POST",
              headers: {
                Accept: "application/json",
                "Content-Type": "application/json"
              },
              body: JSON.stringify(s),
              credentials: "same-origin"
            });
            if (S.ok) {
              const C = await S.text();
              let _;
              try {
                _ = C ? JSON.parse(C) : {};
              } catch {
                _ = {};
              }
              return this.handleSubmitSuccess(_), this.state.nonceRetried = !1, !1;
            } else {
              const C = await Ct(S);
              throw C && C.message && (C.message = C.message + " Se hai appena accettato i cookie, riprova tra qualche secondo."), Object.assign(new Error(C.message || this.copy.submitError), {
                status: S.status,
                payload: C
              });
            }
          }
        }
        const y = h && h.message || this.copy.submitError;
        throw Object.assign(new Error(y), {
          status: l.status,
          payload: h
        });
      }
      let u;
      try {
        u = c ? JSON.parse(c) : {};
      } catch (h) {
        throw console.error("[FP-RESV] ERRORE parsing risposta successo:", h), console.error("[FP-RESV] Testo risposta:", c), new Error("Risposta non valida dal server (JSON malformato)");
      }
      this.handleSubmitSuccess(u), this.state.requestId = null;
    } catch (l) {
      o || (o = Math.round(performance.now() - r), v("ui_latency", { op: "submit", ms: o })), this.handleSubmitError(l, o);
    } finally {
      this.state.sending = !1, this.updateSubmitState();
    }
    return !1;
  }
  handleSubmitSuccess(t) {
    this.clearError();
    const e = t && t.message || this.copy.submitSuccess;
    if (this.successAlert && (this.successAlert.textContent = e, this.successAlert.hidden = !1, setTimeout(() => {
      this.successAlert.scrollIntoView({
        behavior: "smooth",
        block: "center"
      }), typeof this.successAlert.focus == "function" && this.successAlert.focus();
    }, 100)), this.form && (this.form.setAttribute("data-state", "submitted"), this.form.style.transition = "opacity 0.3s ease-out", this.form.style.opacity = "0", setTimeout(() => {
      this.form.style.display = "none", this.successAlert && this.successAlert.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
    }, 300));
    if (t && Array.isArray(t.tracking) && t.tracking.length > 0)
      t.tracking.forEach((i) => {
        i && i.event && i.event !== "reservation_confirmed" && v(i.event, i);
      });
    if (t) {
      const i = Array.isArray(t.tracking) && t.tracking.length > 0 ? t.tracking[0] : null,
        s = t.reservation || {},
        n = (i && i.ga4 && i.ga4.params) || {},
        r = (i && i.reservation) || {},
        o = {
          reservation_id: n.reservation_id || r.id || s.id,
          reservation_status: n.reservation_status || r.status || (s.status || "confirmed").toLowerCase(),
          reservation_party: n.reservation_party || r.party || s.party || s.guests,
          reservation_date: n.reservation_date || r.date || s.date,
          reservation_time: n.reservation_time || r.time || s.time,
          reservation_location: n.reservation_location || r.location || s.location || "default",
          value: n.value != null ? n.value : (s.value != null ? Number(s.value) : null),
          currency: n.currency || s.currency || "EUR",
          event_id: (i && i.event_id) || void 0
        };
      v(this.events.confirmed || "reservation_confirmed", o);
    }
  }
  handleSubmitError(t, e) {
    const i = t && typeof t.status == "number" ? t.status : "unknown", s = t && t.message || this.copy.submitError, n = t && typeof t == "object" && t.payload || null;
    let r = at(s, n);
    i === 403 && this.errorAlert && this.errorRetry && (this.errorRetry.textContent = this.messages.reload_button || "Ricarica pagina", this.errorRetry.onclick = (l) => {
      l.preventDefault(), window.location.reload();
    }), this.errorAlert && this.errorMessage && (this.errorMessage.textContent = r, this.errorAlert.hidden = !1, requestAnimationFrame(() => {
      typeof this.errorAlert.scrollIntoView == "function" && this.errorAlert.scrollIntoView({ behavior: "smooth", block: "center" }), typeof this.errorAlert.focus == "function" && (this.errorAlert.setAttribute("tabindex", "-1"), this.errorAlert.focus({ preventScroll: !0 }));
    })), this.state.hintOverride = r, this.updateSubmitState();
    const o = this.events.submit_error || "submit_error";
    v(o, { code: i, latency: e });
  }
  clearError() {
    this.errorAlert && (this.errorAlert.hidden = !0), this.errorRetry && (this.errorRetry.textContent = this.messages.retry_button || "Riprova", this.errorRetry.onclick = null), this.state.hintOverride = "";
  }
  serializeForm() {
    const t = new FormData(this.form), e = {};
    if (t.forEach((i, s) => {
      typeof i == "string" && (e[s] = i);
    }), e.fp_resv_date && (e.fp_resv_date = U(e.fp_resv_date)), !e.fp_resv_nonce) {
      const i = this.form.querySelector('input[name="fp_resv_nonce"]');
      i ? e.fp_resv_nonce = i.value : console.error("[FP-RESV] Campo nonce non trovato nel DOM!");
    }
    if (this.phoneField) {
      const i = j(this.phoneField, this.getPhoneCountryCode());
      i.e164 && (e.fp_resv_phone = i.e164), i.country && (e.fp_resv_phone_cc = i.country), i.local && (e.fp_resv_phone_local = i.local);
    }
    if (this.phonePrefixField && this.phonePrefixField.value && !e.fp_resv_phone_cc) {
      const i = k(this.phonePrefixField.value);
      i && (e.fp_resv_phone_cc = i);
    }
    return e;
  }
  async ensureNoncePresent() {
    if (!this.form.querySelector('input[name="fp_resv_nonce"]')) {
      const e = document.createElement("input");
      e.type = "hidden", e.name = "fp_resv_nonce", e.value = "", this.form.appendChild(e);
    }
    try {
      await this.refreshNonce() || console.error("[FP-RESV] Impossibile ottenere nonce fresco!");
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
    } catch {
    }
    return null;
  }
  preparePhonePayload() {
    if (!this.phoneField)
      return;
    const t = j(this.phoneField, this.getPhoneCountryCode());
    this.hiddenPhoneE164 && (this.hiddenPhoneE164.value = t.e164), this.hiddenPhoneCc && (this.hiddenPhoneCc.value = t.country), this.hiddenPhoneLocal && (this.hiddenPhoneLocal.value = t.local);
  }
  validatePhoneField() {
    if (!this.phoneField)
      return;
    const t = j(this.phoneField, this.getPhoneCountryCode());
    if (t.local === "") {
      this.phoneField.setCustomValidity(""), this.phoneField.removeAttribute("aria-invalid");
      return;
    }
    G(t.local) ? (this.phoneField.setCustomValidity(""), this.phoneField.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidPhone && (this.state.hintOverride = "", this.updateSubmitState())) : (this.phoneField.setCustomValidity(this.copy.invalidPhone), this.phoneField.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidPhone, this.updateSubmitState(), v("phone_validation_error", { field: "phone" }), v("ui_validation_error", { field: "phone" }));
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
    t.setCustomValidity(""), t.checkValidity() ? (t.setCustomValidity(""), t.setAttribute("aria-invalid", "false"), this.state.hintOverride === this.copy.invalidEmail && (this.state.hintOverride = "", this.updateSubmitState())) : (t.setCustomValidity(this.copy.invalidEmail), t.setAttribute("aria-invalid", "true"), this.state.hintOverride = this.copy.invalidEmail, this.updateSubmitState(), v("ui_validation_error", { field: "email" }));
  }
  focusFirstInvalid() {
    const t = this.form.querySelector("[data-fp-resv-field]:invalid, [required]:invalid");
    t && typeof t.focus == "function" && t.focus();
  }
  findFirstInvalid(t) {
    return t ? t.querySelector("[data-fp-resv-field]:invalid, [required]:invalid") : null;
  }
  collectAvailabilityParams() {
    const t = this.hiddenMeal ? this.hiddenMeal.value : "", e = this.dateField && this.dateField.value ? this.dateField.value : "", i = U(e), s = this.partyField && this.partyField.value ? this.partyField.value : "";
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
    const i = nt(e, "data-fp-resv-event");
    if (!i)
      return;
    const s = i.getAttribute("data-fp-resv-event");
    if (!s)
      return;
    let n = St(i, "data-fp-resv-payload");
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
      const e = k(this.phonePrefixField.value);
      if (e)
        return e;
    }
    if (this.hiddenPhoneCc && this.hiddenPhoneCc.value) {
      const e = k(this.hiddenPhoneCc.value);
      if (e)
        return e;
    }
    if (this.phoneCountryCode) {
      const e = k(this.phoneCountryCode);
      if (e)
        return e;
    }
    const t = this.config && this.config.defaults || {};
    if (t.phone_country_code) {
      const e = k(t.phone_country_code);
      if (e)
        return e;
    }
    return "39";
  }
  getReservationEndpoint() {
    const t = this.config.endpoints || {};
    return X(t.reservations, "/wp-json/fp-resv/v1/reservations");
  }
  getAvailabilityEndpoint() {
    const t = this.config.endpoints || {};
    return X(t.availability, "/wp-json/fp-resv/v1/availability");
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
          const l = e();
          s(l || null);
          return;
        }
        s(null);
      };
      let r = document.querySelector(`script[src="${t}"]`);
      if (!r && i && (r = document.querySelector(`script[${i}]`)), r) {
        if (typeof e == "function") {
          const l = e();
          if (l) {
            s(l);
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
typeof window < "u" && (window.FPResv = window.FPResv || {}, window.FPResv.FormApp = rt, window.fpResvApp = window.FPResv);
function ot(a) {
  if (!a)
    return;
  a.style.display = "block", a.style.visibility = "visible", a.style.opacity = "1", a.style.position = "relative", a.style.width = "100%", a.style.height = "auto";
  let t = a.parentElement, e = 0;
  for (; t && e < 5; )
    window.getComputedStyle(t).display === "none" && (console.warn("[FP-RESV] Found hidden parent element, making visible:", t), t.style.display = "block"), t = t.parentElement, e++;
  console.log("[FP-RESV] Widget visibility ensured:", a.id || "unnamed");
}
function Z() {
  let a = 0;
  const t = 10, e = setInterval(function() {
    a++;
    const i = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]");
    let s = !1;
    Array.prototype.forEach.call(i, function(n) {
      const r = window.getComputedStyle(n);
      (r.display === "none" || r.visibility === "hidden" || r.opacity === "0") && (console.warn("[FP-RESV] Widget became hidden, forcing visibility again:", n.id || "unnamed"), ot(n), s = !0);
    }), (a >= t || !s) && (clearInterval(e), a >= t && console.log("[FP-RESV] Visibility auto-check completed after " + a + " checks"));
  }, 1e3);
}
const x = /* @__PURE__ */ new Set();
function M() {
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
    if (x.has(t)) {
      console.log("[FP-RESV] Widget already initialized, skipping:", t.id || "unnamed");
      return;
    }
    try {
      x.add(t), ot(t), console.log("[FP-RESV] Initializing widget:", t.id || "unnamed"), console.log("[FP-RESV] Widget sections found:", t.querySelectorAll("[data-fp-resv-section]").length);
      const e = new rt(t);
      console.log("[FP-RESV] Widget initialized successfully:", t.id || "unnamed"), (e.sections || []).forEach(function(s, n) {
        const r = s.getAttribute("data-step"), o = s.getAttribute("data-state"), l = s.hasAttribute("hidden");
        console.log(`[FP-RESV] Step ${n + 1} (${r}): state=${o}, hidden=${l}`);
      });
    } catch (e) {
      console.error("[FP-RESV] Error initializing widget:", e), x.delete(t);
    }
  });
}
function tt() {
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
    }), e && (console.log("[FP-RESV] New widget(s) detected in DOM, initializing..."), M());
  }).observe(document.body, {
    childList: !0,
    subtree: !0
  }), console.log("[FP-RESV] MutationObserver set up to detect dynamic widgets");
}
function et() {
  [500, 1e3, 2e3, 3e3].forEach(function(t) {
    setTimeout(function() {
      const e = document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length;
      e > x.size && (console.log("[FP-RESV] Retry: Found " + e + " widgets, " + x.size + " initialized"), M());
    }, t);
  });
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", function() {
  M(), setTimeout(Z, 500), tt(), et();
}) : (M(), setTimeout(Z, 500), tt(), et());
(typeof window.vc_js < "u" || document.querySelector("[data-vc-full-width]") || document.querySelector(".vc_row")) && (console.log("[FP-RESV] WPBakery detected - adding compatibility listeners"), document.addEventListener("vc-full-content-loaded", function() {
  console.log("[FP-RESV] WPBakery vc-full-content-loaded event - re-initializing..."), setTimeout(M, 100);
}), window.addEventListener("load", function() {
  setTimeout(function() {
    document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length > x.size && (console.log("[FP-RESV] WPBakery late load - found new widgets, initializing..."), M());
  }, 1e3);
}), [1500, 3e3, 5e3, 1e4].forEach(function(a) {
  setTimeout(function() {
    document.querySelectorAll("[data-fp-resv], .fp-resv-widget, [data-fp-resv-app]").length > x.size && (console.log("[FP-RESV] WPBakery extended retry (" + a + "ms) - initializing..."), M());
  }, a);
}));
document.addEventListener("fp-resv:tracking:push", function(a) {
  if (!a || !a.detail)
    return;
  const t = a.detail, e = t && (t.event || t.name);
  if (!e)
    return;
  const i = t.payload || t.data || {};
  v(e, i && typeof i == "object" ? i : {});
});
const E = {
  AVAILABLE: "available",
  LIMITED: "limited",
  FULL: "full",
  UNAVAILABLE: "unavailable"
};
function lt(a) {
  if (typeof a != "string")
    return "";
  const t = a.trim().toLowerCase();
  if (t === "")
    return "";
  const i = ((r) => typeof r.normalize == "function" ? r.normalize("NFD").replace(/[\u0300-\u036f]/g, "") : r)(t), s = (r) => r.some((o) => i.startsWith(o)), n = (r) => r.some((o) => i.includes(o));
  return s(["available", "open", "disponibil", "disponible", "liber", "libre", "apert", "abiert"]) ? E.AVAILABLE : t === "waitlist" || t === "busy" || s(["limited", "limit", "limitat", "limite", "cupos limit", "attesa"]) || n(["pochi posti", "quasi pien", "lista attesa", "few spots", "casi llen"]) ? E.LIMITED : s(["full", "complet", "esaurit", "soldout", "sold out", "agotad", "chius", "plen"]) ? E.FULL : t;
}
function $(a) {
  for (; a.firstChild; )
    a.removeChild(a.firstChild);
}
function kt() {
  const a = document.createElement("li"), t = document.createElement("span");
  return t.className = "fp-skeleton", a.appendChild(t), a;
}
function Pt(a, t, e) {
  const i = document.createElement("button");
  i.type = "button", i.textContent = a.label || "", i.dataset.slot = a.start || "", i.dataset.slotStatus = a.status || "", i.setAttribute("aria-pressed", t ? "true" : "false");
  const s = lt(a.status);
  return s && i.classList.add(`fp-slot-button--${s}`), i.addEventListener("click", e), i;
}
function Lt(a, t) {
  const e = Array.isArray(a) ? a : [], i = e.length;
  if (i === 0)
    return t === !1 ? { state: E.UNAVAILABLE, slots: 0 } : { state: E.FULL, slots: 0 };
  const s = e.map((o) => lt(o && o.status)).filter((o) => o !== "");
  return s.some((o) => o === E.LIMITED) ? { state: E.LIMITED, slots: i } : s.some((o) => o === E.AVAILABLE) ? { state: E.AVAILABLE, slots: i } : t ? { state: E.AVAILABLE, slots: i } : s.length === 0 ? { state: E.AVAILABLE, slots: i } : { state: E.FULL, slots: i };
}
function Rt(a) {
  const { listElement: t, skeletonCount: e = 4 } = a;
  if (!t)
    return {
      renderSlots: () => {
      },
      showSkeleton: () => {
      },
      clear: () => {
      }
    };
  function i() {
    $(t);
    for (let o = 0; o < e; o += 1) {
      const l = kt();
      t.appendChild(l);
    }
  }
  function s() {
    $(t);
  }
  function n(o, l, c) {
    $(t), !(!Array.isArray(o) || o.length === 0) && o.forEach((u) => {
      const h = l && l.start === u.start, y = document.createElement("li"), b = Pt(
        u,
        h,
        () => c(u, b)
      );
      y.appendChild(b), t.appendChild(y);
    });
  }
  function r(o) {
    t.querySelectorAll("button[data-slot]").forEach((c) => {
      const u = o && c.dataset.slot === o.start;
      c.setAttribute("aria-pressed", u ? "true" : "false");
    });
  }
  return {
    renderSlots: n,
    showSkeleton: i,
    clear: s,
    updateSelection: r
  };
}
function Dt(a) {
  if (!a)
    return {
      show: () => {
      },
      hide: () => {
      },
      isVisible: () => !1
    };
  function t() {
    a.hidden = !1, a.removeAttribute("hidden");
  }
  function e() {
    a.hidden = !0, a.setAttribute("hidden", "");
  }
  function i() {
    return !a.hidden;
  }
  function s(n) {
    a.querySelectorAll('[class*="legend-item"]').forEach((o) => {
      const l = o.className;
      let c = "";
      l.includes("available") ? c = E.AVAILABLE : l.includes("limited") ? c = E.LIMITED : l.includes("full") && (c = E.FULL), c === n ? o.setAttribute("data-active", "true") : o.removeAttribute("data-active");
    });
  }
  return {
    show: t,
    hide: e,
    isVisible: i,
    updateLegendState: s
  };
}
const qt = 400, It = 6e4, xt = 3, it = 600;
function Mt(a, t) {
  let e;
  try {
    e = new URL(a, window.location.origin);
  } catch {
    const s = window.location.origin.replace(/\/$/, ""), n = a.startsWith("/") ? s + a : s + "/" + a;
    e = new URL(n, window.location.origin);
  }
  return e.searchParams.set("date", t.date), e.searchParams.set("party", String(t.party)), t.meal && e.searchParams.set("meal", t.meal), e.toString();
}
function Tt(a) {
  const t = a.root, e = t.querySelector("[data-fp-resv-slots-status]"), i = t.querySelector("[data-fp-resv-slots-list]"), s = t.querySelector("[data-fp-resv-slots-empty]"), n = t.querySelector("[data-fp-resv-slots-boundary]"), r = n ? n.querySelector("[data-fp-resv-slots-retry]") : null, o = t.querySelector("[data-fp-resv-slots-legend]"), l = /* @__PURE__ */ new Map();
  let c = null, u = null, h = null, y = 0;
  const b = Rt({
    listElement: i,
    skeletonCount: a.skeletonCount || 4
  }), S = Dt(o), C = Lt;
  function _(d, f) {
    if (typeof a.onAvailabilitySummary == "function")
      try {
        a.onAvailabilitySummary(f, d || u || {});
      } catch {
      }
  }
  r && r.addEventListener("click", () => {
    u && T(u, 0);
  });
  function P(d, f) {
    const g = typeof f == "string" ? f : f ? "loading" : "idle", m = typeof d == "string" ? d : "";
    e && (e.textContent = m, e.setAttribute("data-state", g));
    const F = g === "loading";
    t.setAttribute("data-loading", F ? "true" : "false"), i && i.setAttribute("aria-busy", F ? "true" : "false");
  }
  function H() {
    b.showSkeleton();
  }
  function L(d) {
    s && (s.hidden = !1);
    const f = d && typeof d == "object", g = f && typeof d.meal == "string" ? d.meal.trim() : "", m = f && typeof d.date == "string" ? d.date.trim() : "", F = f && typeof d.party < "u" ? String(d.party).trim() : "", R = f && !!d.requiresMeal, N = g !== "", A = m !== "" && (F !== "" && F !== "0") && (!R || N), D = R && !N ? a.strings && a.strings.selectMeal || "" : A && a.strings && a.strings.slotsEmpty || "";
    P(D, "idle"), i && clearChildren(i), _(d, { state: A ? "unavailable" : "unknown", slots: 0 });
  }
  function w() {
    s && (s.hidden = !0);
  }
  function O() {
    n && (n.hidden = !0);
  }
  function B(d) {
    const f = a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.";
    if (n) {
      const g = n.querySelector("[data-fp-resv-slots-boundary-message]");
      g && (g.textContent = d || f), n.hidden = !1;
    }
    P(d || f, "error"), _(u, { state: "error", slots: 0 });
  }
  function ct(d, f) {
    h = d, b.updateSelection(d), typeof a.onSlotSelected == "function" && a.onSlotSelected(d);
  }
  function dt() {
    h = null, b.updateSelection(null);
  }
  function Y(d, f, g) {
    if (g && g !== y || f && u && f !== u || (O(), w(), !i))
      return;
    const m = d && Array.isArray(d.slots) ? d.slots : [];
    if (m.length === 0) {
      L(f);
      return;
    }
    b.renderSlots(m, h, ct), S.show(), P(a.strings && a.strings.slotsUpdated || "", !1);
    const F = !!(d && (typeof d.has_availability < "u" && d.has_availability || d.meta && d.meta.has_availability)), R = C(m, F);
    _(f, R), R && R.state && S.updateLegendState(R.state);
  }
  function T(d, f) {
    if (u = d, !d || !d.date || !d.party) {
      L(d);
      return;
    }
    const g = ++y, m = JSON.stringify([d.date, d.meal, d.party]), F = l.get(m);
    if (F && Date.now() - F.timestamp < It && f === 0) {
      Y(F.payload, d, g);
      return;
    }
    O(), w(), H(), P(a.strings && a.strings.updatingSlots || "Aggiornamento disponibilità…", "loading"), _(d, { state: "loading", slots: 0 });
    const R = Mt(a.endpoint, d), N = performance.now();
    fetch(R, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((p) => p.json().catch(() => ({})).then((I) => {
      if (!p.ok) {
        const A = new Error("availability_error");
        A.status = p.status, A.payload = I;
        const D = p.headers.get("Retry-After");
        if (D) {
          const q = Number.parseInt(D, 10);
          Number.isFinite(q) && (A.retryAfter = q);
        }
        throw A;
      }
      return I;
    })).then((p) => {
      if (g !== y)
        return;
      const I = performance.now() - N;
      typeof a.onLatency == "function" && a.onLatency(I), l.set(m, { payload: p, timestamp: Date.now() }), Y(p, d, g);
    }).catch((p) => {
      if (g !== y)
        return;
      const I = performance.now() - N;
      typeof a.onLatency == "function" && a.onLatency(I);
      const A = p && p.payload && typeof p.payload == "object" ? p.payload.data || {} : {}, D = typeof p.status == "number" ? p.status : A && typeof A.status == "number" ? A.status : 0;
      let q = 0;
      if (p && typeof p.retryAfter == "number" && Number.isFinite(p.retryAfter))
        q = p.retryAfter;
      else if (A && typeof A.retry_after < "u") {
        const V = Number.parseInt(A.retry_after, 10);
        Number.isFinite(V) && (q = V);
      }
      if (f >= xt - 1 ? !1 : D === 429 || D >= 500 && D < 600 ? !0 : D === 0) {
        const V = f + 1;
        typeof a.onRetry == "function" && a.onRetry(V);
        const pt = q > 0 ? Math.max(q * 1e3, it) : it * Math.pow(2, f);
        window.setTimeout(() => T(d, V), pt);
        return;
      }
      const ut = p && p.payload && (p.payload.message || p.payload.code) || A && A.message || a.strings && a.strings.slotsError || a.strings && a.strings.submitError || "Impossibile aggiornare la disponibilità. Riprova.", ht = p && p.payload || A || null, ft = at(ut, ht);
      B(ft);
    });
  }
  return {
    schedule(d, f = {}) {
      c && window.clearTimeout(c);
      const g = f && typeof f == "object" ? f : {}, m = d || (typeof a.getParams == "function" ? a.getParams() : null), F = !!(m && m.requiresMeal);
      if (!m || !m.date || !m.party || F && !m.meal) {
        u = m, L(m || {});
        return;
      }
      if (g.immediate) {
        T(m, 0);
        return;
      }
      c = window.setTimeout(() => {
        T(m, 0);
      }, qt);
    },
    revalidate() {
      if (!u)
        return;
      const d = JSON.stringify([u.date, u.meal, u.party]);
      l.delete(d), T(u, 0);
    },
    getSelection() {
      return h;
    },
    clearSelection() {
      dt();
    }
  };
}
const Nt = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  createAvailabilityController: Tt
}, Symbol.toStringTag, { value: "Module" }));
