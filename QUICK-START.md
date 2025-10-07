# 🚀 Quick Start - FP Restaurant Reservations

**5 minuti per partire!**

---

## 📋 Requisiti Minimi

- WordPress 6.5+
- PHP 8.1+
- Estensioni PHP: `curl`, `json`, `mbstring`

## 🔧 Installazione Rapida

### 1. Installa il Plugin

```bash
# Upload ZIP tramite WordPress Admin
# oppure copia nella cartella plugins:
cp -r fp-restaurant-reservations /path/to/wp-content/plugins/
```

### 2. Attiva il Plugin

Vai su **WordPress Admin → Plugin** e attiva "FP Restaurant Reservations"

### 3. Configurazione Iniziale

Dopo l'attivazione vai su **Impostazioni → FP Reservations**

#### Tab Essenziali

**Generali**:
- ✅ Orari apertura (es: 12:00-14:30, 19:00-22:30)
- ✅ Capacità massima per turno
- ✅ Buffer tra prenotazioni (es: 15 min)

**Notifiche**:
- ✅ Email ristorante (dove ricevere prenotazioni)
- ✅ Email webmaster (notifiche tecniche)
- ✅ Nome mittente email

### 4. Inserisci Form

Aggiungi lo shortcode in una pagina:

```
[fp_reservations]
```

Oppure usa il blocco Gutenberg **"FP Reservations Form"**

### 5. Testa!

1. Apri la pagina con il form
2. Seleziona data, ora, numero ospiti
3. Compila i dati
4. Invia prenotazione
5. Controlla email ricevute

---

## 📚 Prossimi Passi

### Configurazioni Opzionali

#### Pagamenti Stripe
1. Tab **Pagamenti Stripe**
2. Inserisci API keys (test/production)
3. Configura caparra o pagamento completo

#### Automazioni Email (Brevo)
1. Tab **Brevo**
2. Inserisci API key
3. Configura liste IT/EN

#### Google Calendar
1. Tab **Google Calendar**
2. Segui wizard OAuth
3. Abilita sincronizzazione

#### Personalizza Stile
1. Tab **Stile del Form**
2. Modifica colori, font, spaziatura
3. Preview live

### Setup Avanzato

**Sale e Tavoli**:
- Menu **FP Reservations → Sale & Tavoli**
- Layout drag & drop
- Merge/split tavoli

**Chiusure**:
- Menu **FP Reservations → Chiusure**
- Gestisci chiusure ricorrenti
- Chiusure speciali/straordinarie

**Tracking**:
- Tab **Tracking & Consent**
- Configura GA4, Ads, Meta, Clarity
- Consent Mode v2 GDPR compliant

---

## 📖 Documentazione Completa

| Documento | Scopo |
|-----------|-------|
| [README.md](README.md) | Overview generale e features |
| [STATUS.md](STATUS.md) | Stato attuale del progetto |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Come contribuire |
| [CHANGELOG.md](CHANGELOG.md) | Cronologia modifiche |
| [docs/README.md](docs/README.md) | Indice documentazione tecnica |
| [docs/EXAMPLES.md](docs/EXAMPLES.md) | 8 esempi pratici |
| [AUDIT/REPORT.md](AUDIT/REPORT.md) | Report sicurezza |

---

## 🆘 Problemi Comuni

### Form non appare
- ✅ Verifica shortcode/blocco inserito correttamente
- ✅ Controlla console browser per errori JS
- ✅ Disabilita altri plugin per test conflitti

### Email non arrivano
- ✅ Verifica email configurate in tab Notifiche
- ✅ Controlla spam/posta indesiderata
- ✅ Testa invio email da tab Diagnostica

### Slot non disponibili
- ✅ Verifica orari apertura in tab Generali
- ✅ Controlla chiusure in menu Chiusure
- ✅ Verifica capacità massima turni

### Pagamento Stripe fallisce
- ✅ Verifica API keys corrette (test/prod)
- ✅ Controlla account Stripe attivo
- ✅ Verifica HTTPS abilitato su sito

---

## 🔒 Sicurezza

Il plugin è stato auditato ad Ottobre 2025:
- ✅ **5/5 problemi risolti**
- ✅ **Zero vulnerabilità note**
- ✅ **Production ready**

Dettagli: [AUDIT/REPORT.md](AUDIT/REPORT.md)

---

## 🚀 Performance

Ottimizzato per production:
- ✅ **+900% throughput** (50→500 req/s)
- ✅ **-97% latency** (200ms→5ms)
- ✅ **Cache Redis/Memcached**
- ✅ **Email asincrone**

Dettagli: [STATUS.md](STATUS.md) sezione Performance

---

## 📞 Supporto

**Email**: info@francescopasseri.com  
**GitHub**: https://github.com/franpass87/FP-Restaurant-Reservations

---

## ✅ Checklist Verifica Installazione

- [ ] Plugin attivato
- [ ] Orari apertura configurati
- [ ] Email notifiche configurate
- [ ] Form inserito in pagina
- [ ] Test prenotazione effettuato
- [ ] Email ricevute correttamente
- [ ] Prenotazione visibile in agenda admin
- [ ] (Opzionale) Pagamenti Stripe testati
- [ ] (Opzionale) Brevo configurato
- [ ] (Opzionale) Google Calendar sincronizzato

---

**🎉 Installazione completata!**

Per uso avanzato consulta la [documentazione completa](docs/README.md).
