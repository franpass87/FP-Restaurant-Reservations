/**
 * FP Restaurant Reservations - Admin Agenda SPA bootstrap.
 *
 * This placeholder avoids shipping compiled assets while keeping the admin
 * agenda page functional. The rich drag & drop experience will be implemented
 * in upcoming phases.
 */

(function () {
  if (typeof window === 'undefined') {
    return;
  }

  var rootId = 'fp-resv-agenda-app';
  var container = document.getElementById(rootId);
  if (!container) {
    return;
  }

  var settings = window.fpResvAgendaSettings || {};
  var strings = settings.strings || {};
  var headline = strings.headline || 'Agenda in arrivo';
  var description =
    strings.description ||
    "L'interfaccia drag & drop dell'agenda verrà caricata qui nelle fasi successive.";
  var cta =
    strings.cta ||
    'Le API REST sono già disponibili: questo spazio mostrerà i dati delle prenotazioni.';

  var info = document.createElement('div');
  info.className = 'fp-resv-agenda-placeholder';
  info.innerHTML =
    '<h2>' +
    headline +
    '</h2><p>' +
    description +
    "</p><p class=\"description\">" +
    cta +
    '</p>';

  container.appendChild(info);
})();
