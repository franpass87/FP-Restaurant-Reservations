console.log('🚀 JavaScript del form caricato! [VERSIONE AUDIT COMPLETO v2.3]');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM caricato, inizializzo form...');
    const form = document.getElementById('fp-resv-default') || document.getElementById('fp-resv-simple') || document.querySelector('.fp-resv-simple');
    console.log('Form trovato:', form);
    
    if (!form) {
        console.error('Form non trovato!');
        return;
    }
    
    // Funzione helper per mostrare notice
    function showNotice(type, message, duration = 5000) {
        console.log('showNotice chiamata:', type, message);
        if (window.fpNoticeManager) {
            console.log('Usando NoticeManager');
            return window.fpNoticeManager.show(type, message, duration);
        } else {
            console.log('NoticeManager non disponibile, usando alert');
            // Fallback a alert se il notice manager non è disponibile
            alert(message);
        }
    }
    
    let currentStep = 1;
    const totalSteps = 4;
    let isSubmitting = false; // Protezione contro doppio submit
    let formNonce = null; // Nonce per la sicurezza
    
    const steps = form.querySelectorAll('.fp-step');
    const progressSteps = form.querySelectorAll('.fp-progress-step');
    
    // Ottieni il nonce all'avvio
    async function fetchNonce() {
        try {
            const response = await fetch('/wp-json/fp-resv/v1/nonce');
            if (response.ok) {
                const data = await response.json();
                formNonce = data.nonce;
                console.log('✅ Nonce ottenuto con successo');
            }
        } catch (error) {
            console.error('⚠️ Errore nel recupero del nonce:', error);
        }
    }
    
    // Ottieni il nonce subito
    fetchNonce();
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-btn');
    
    // Meal selection - dynamic
    let mealBtns = form.querySelectorAll('.fp-meal-btn');
    let selectedMeal = null;
    let selectedTime = null;
    let areDatesLoading = false; // PRELOAD: Stato caricamento date
    let areDatesReady = false;   // PRELOAD: Date pronte per step 2

    function setupMealButtons() {
        mealBtns = form.querySelectorAll('.fp-meal-btn');
        console.log('Trovati', mealBtns.length, 'pulsanti pasto');
        
        // Debug: mostra tutti i pasti con i loro messaggi
        console.log('=== DEBUG PASTI ===');
        mealBtns.forEach((btn, index) => {
            console.log(`Pasto ${index + 1}:`, {
                key: btn.dataset.meal,
                notice: btn.dataset.mealNotice,
                hint: btn.dataset.mealHint,
                hasNotice: !!btn.dataset.mealNotice && btn.dataset.mealNotice.trim() !== ''
            });
        });
        console.log('===================');
        
        mealBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Pulsante pasto cliccato:', this.dataset.meal);
                console.log('Tutti dataset del bottone:', this.dataset);
                
                mealBtns.forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                selectedMeal = this.dataset.meal;
                
                // Reset selectedTime quando cambi meal
                selectedTime = null;
                document.querySelectorAll('.fp-time-slot').forEach(s => s.classList.remove('selected'));
                
                // Mostra il messaggio del pasto se presente
                const mealNotice = this.dataset.mealNotice;
                const mealNoticeDiv = document.getElementById('meal-notice');
                
                console.log('mealNotice value:', mealNotice);
                console.log('mealNoticeDiv found:', mealNoticeDiv);
                
                if (mealNotice && mealNotice.trim() !== '' && mealNoticeDiv) {
                    // Usa textContent per sicurezza (previene XSS)
                    mealNoticeDiv.textContent = mealNotice;
                    mealNoticeDiv.hidden = false;
                    console.log('✅ Messaggio pasto mostrato:', mealNotice);
                } else {
                    if (mealNoticeDiv) {
                        mealNoticeDiv.hidden = true;
                    }
                    console.log('⚠️ Nessun messaggio per questo pasto o div non trovato');
                    console.log('  - mealNotice presente?', !!mealNotice);
                    console.log('  - mealNotice non vuoto?', mealNotice && mealNotice.trim() !== '');
                    console.log('  - mealNoticeDiv trovato?', !!mealNoticeDiv);
                }
                
                // PRELOAD: Carica date SUBITO (step 1), così sono pronte per step 2
                areDatesReady = false;
                areDatesLoading = true;
                updateNextButtonState(); // Disabilita "Avanti" durante loading
                loadAvailableDates(selectedMeal);
            });
        });
    }
    
    // Initialize meal buttons
    setupMealButtons();
    
    // Initialize party size selector
    setupPartySelector();
    
    function setupPartySelector() {
        const minusBtn = document.getElementById('party-minus');
        const plusBtn = document.getElementById('party-plus');
        const countDisplay = document.getElementById('party-count');
        const labelDisplay = document.getElementById('party-label');
        const hiddenInput = document.getElementById('party-size');
        
        let partyCount = 2; // Default value
        const minParty = 1;
        const maxParty = 20;
        
        function updatePartyDisplay() {
            countDisplay.textContent = partyCount;
            labelDisplay.textContent = partyCount === 1 ? 'persona' : 'persone';
            hiddenInput.value = partyCount;
            
            // Update button states
            minusBtn.disabled = partyCount <= minParty;
            plusBtn.disabled = partyCount >= maxParty;
            
            // Trigger change event to load time slots
            hiddenInput.dispatchEvent(new Event('change'));
            
            console.log('Party count updated:', partyCount);
        }
        
        minusBtn.addEventListener('click', function() {
            if (partyCount > minParty) {
                partyCount--;
                updatePartyDisplay();
            }
        });
        
        plusBtn.addEventListener('click', function() {
            if (partyCount < maxParty) {
                partyCount++;
                updatePartyDisplay();
            }
        });
        
        // Initialize display
        updatePartyDisplay();
    }
    
    function populateSummary() {
        // Dettagli prenotazione
        document.getElementById('summary-meal').textContent = selectedMeal || '-';
        document.getElementById('summary-date').textContent = document.getElementById('reservation-date').value || '-';
        document.getElementById('summary-time').textContent = selectedTime || '-';
        document.getElementById('summary-party').textContent = document.getElementById('party-size').value + ' persone';
        
        // Dettagli personali
        const firstName = document.getElementById('customer-first-name').value;
        const lastName = document.getElementById('customer-last-name').value;
        document.getElementById('summary-name').textContent = `${firstName} ${lastName}`;
        document.getElementById('summary-email').textContent = document.getElementById('customer-email').value || '-';
        
        // Telefono completo
        const phonePrefix = document.querySelector('select[name="fp_resv_phone_prefix"]').value;
        const phoneNumber = document.getElementById('customer-phone').value;
        document.getElementById('summary-phone').textContent = `+${phonePrefix} ${phoneNumber}`;
        
        // Occasione (se specificata)
        const occasion = document.getElementById('occasion').value;
        const occasionRow = document.getElementById('summary-occasion-row');
        if (occasion) {
            const occasionText = document.getElementById('occasion').selectedOptions[0].text;
            document.getElementById('summary-occasion').textContent = occasionText;
            occasionRow.hidden = false;
        } else {
            occasionRow.hidden = true;
        }
        
        // Note (se specificate)
        const notes = document.getElementById('notes').value;
        const notesRow = document.getElementById('summary-notes-row');
        if (notes) {
            document.getElementById('summary-notes').textContent = notes;
            notesRow.hidden = false;
        } else {
            notesRow.hidden = true;
        }
        
        // Allergie (se specificate)
        const allergies = document.getElementById('allergies').value;
        const allergiesRow = document.getElementById('summary-allergies-row');
        if (allergies) {
            document.getElementById('summary-allergies').textContent = allergies;
            allergiesRow.hidden = false;
        } else {
            allergiesRow.hidden = true;
        }
        
        // Servizi aggiuntivi
        const wheelchairTable = document.querySelector('input[name="fp_resv_wheelchair_table"]').checked;
        const pets = document.querySelector('input[name="fp_resv_pets"]').checked;
        const highChairCount = document.getElementById('high-chair-count').value;
        
        let hasExtras = false;
        
        const wheelchairRow = document.getElementById('summary-wheelchair-row');
        if (wheelchairTable) {
            wheelchairRow.hidden = false;
            hasExtras = true;
        } else {
            wheelchairRow.hidden = true;
        }
        
        const petsRow = document.getElementById('summary-pets-row');
        if (pets) {
            petsRow.hidden = false;
            hasExtras = true;
        } else {
            petsRow.hidden = true;
        }
        
        const highchairRow = document.getElementById('summary-highchair-row');
        if (highChairCount && parseInt(highChairCount) > 0) {
            document.getElementById('summary-highchair').textContent = highChairCount;
            highchairRow.hidden = false;
            hasExtras = true;
        } else {
            highchairRow.hidden = true;
        }
        
        // Mostra/nascondi sezione servizi aggiuntivi
        document.getElementById('summary-extras-row').hidden = !hasExtras;
        
        console.log('Riepilogo popolato');
    }
    
    // Navigation
    function showStep(step) {
        console.log('showStep chiamata con step:', step);
        steps.forEach(s => s.classList.remove('active'));
        progressSteps.forEach(p => p.classList.remove('active', 'completed'));
        
        const currentStepEl = form.querySelector(`.fp-step[data-step="${step}"]`);
        console.log('Elemento step trovato:', currentStepEl);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
            console.log('Classe active aggiunta al step', step);
        } else {
            console.error('Elemento step non trovato per step:', step);
        }
        
        // Update progress
        for (let i = 1; i <= step; i++) {
            const progressStep = form.querySelector(`[data-step="${i}"]`);
            if (progressStep) {
                if (i < step) {
                    progressStep.classList.add('completed');
                } else if (i === step) {
                    progressStep.classList.add('active');
                }
            }
        }
        
        // Update buttons
        prevBtn.hidden = step <= 1;
        nextBtn.hidden = step >= totalSteps;
        submitBtn.hidden = step < totalSteps;
        
        // Update button text for step 6
        if (step === totalSteps) {
            submitBtn.textContent = 'Conferma Prenotazione';
        } else {
            submitBtn.textContent = 'Prenota';
        }
    }
    
    // Aggiorna stato bottone "Avanti"
    function updateNextButtonState() {
        if (currentStep === 1 && selectedMeal) {
            if (areDatesLoading) {
                // Date in caricamento
                nextBtn.disabled = true;
                nextBtn.textContent = '⏳ Caricamento date...';
                nextBtn.style.opacity = '0.6';
            } else if (areDatesReady) {
                // Date pronte
                nextBtn.disabled = false;
                nextBtn.textContent = 'Avanti →';
                nextBtn.style.opacity = '1';
            }
        } else {
            // Altri step o meal non selezionato
            nextBtn.disabled = false;
            nextBtn.textContent = 'Avanti →';
            nextBtn.style.opacity = '1';
        }
    }
    
    function validateStep(step) {
        switch(step) {
            case 1:
                // PRELOAD: Step 1 valido solo se meal selezionato E date pronte
                return selectedMeal !== null && areDatesReady;
            case 2:
                const date = document.getElementById('reservation-date').value;
                const party = document.getElementById('party-size').value;
                return date !== '' && party !== '' && parseInt(party) > 0 && selectedTime !== null;
            case 3:
                const firstName = document.getElementById('customer-first-name').value;
                const lastName = document.getElementById('customer-last-name').value;
                const email = document.getElementById('customer-email').value;
                const phone = document.getElementById('customer-phone').value;
                const consent = document.querySelector('input[name="fp_resv_consent"]').checked;
                return firstName !== '' && lastName !== '' && email !== '' && phone !== '' && consent;
            case 4:
                // Step 4 è sempre valido (riepilogo)
                return true;
        }
        return true;
    }
    
    nextBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            currentStep++;
            
            // Se stiamo andando allo step 4 (riepilogo), popola i dati
            if (currentStep === 4) {
                populateSummary();
            }
            
            showStep(currentStep);
        } else {
            showNotice('warning', 'Per favore completa tutti i campi richiesti.');
        }
    });
    
    prevBtn.addEventListener('click', function() {
        currentStep--;
        showStep(currentStep);
    });
    
    submitBtn.addEventListener('click', async function() {
        // Protezione contro doppio submit
        if (isSubmitting) {
            console.log('⚠️ Submit già in corso, ignoro il click');
            return;
        }
        
        if (!validateStep(currentStep)) {
            showNotice('warning', 'Per favore completa tutti i campi richiesti.');
            return;
        }
        
        // Segna come submitting
        isSubmitting = true;
        
        // Disabilita il pulsante e mostra loading
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = '⏳ Invio in corso...';
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
        
        try {
            // Get phone data
            const phonePrefix = document.querySelector('select[name="fp_resv_phone_prefix"]').value;
            const phoneNumber = document.getElementById('customer-phone').value;
            const fullPhone = '+' + phonePrefix + ' ' + phoneNumber;
            
            // Genera request_id univoco per idempotenza
            const requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
            
            // Prepara i dati per l'API (usa i nomi che il server si aspetta)
            const payload = {
                fp_resv_meal: selectedMeal,
                fp_resv_date: document.getElementById('reservation-date').value,
                fp_resv_time: selectedTime,
                fp_resv_party: parseInt(document.getElementById('party-size').value, 10),
                fp_resv_first_name: document.getElementById('customer-first-name').value,
                fp_resv_last_name: document.getElementById('customer-last-name').value,
                fp_resv_email: document.getElementById('customer-email').value,
                fp_resv_phone: fullPhone,
                fp_resv_phone_cc: phonePrefix,
                fp_resv_phone_local: phoneNumber,
                fp_resv_notes: document.getElementById('notes').value,
                fp_resv_allergies: document.getElementById('allergies').value,
                fp_resv_high_chair_count: document.getElementById('high-chair-count').value,
                fp_resv_wheelchair_table: document.querySelector('input[name="fp_resv_wheelchair_table"]').checked ? '1' : '',
                fp_resv_pets: document.querySelector('input[name="fp_resv_pets"]').checked ? '1' : '',
                fp_resv_consent: document.querySelector('input[name="fp_resv_consent"]').checked,
                fp_resv_marketing_consent: document.querySelector('input[name="fp_resv_marketing_consent"]').checked ? '1' : '',
                fp_resv_location: document.querySelector('input[name="fp_resv_location"]').value || 'default',
                fp_resv_locale: document.querySelector('input[name="fp_resv_locale"]').value || 'it_IT',
                fp_resv_language: document.querySelector('input[name="fp_resv_language"]').value || 'it',
                fp_resv_currency: document.querySelector('input[name="fp_resv_currency"]').value || 'EUR',
                fp_resv_policy_version: document.querySelector('input[name="fp_resv_policy_version"]').value || '1.0',
                fp_resv_hp: document.querySelector('input[name="fp_resv_hp"]').value || '', // Honeypot
                fp_resv_nonce: formNonce,
                request_id: requestId
            };
            
            // Aggiungi occasione solo se compilata
            const occasion = document.getElementById('occasion').value;
            if (occasion) {
                payload.fp_resv_occasion = occasion;
            }
            
            console.log('📤 Invio prenotazione al server...', payload);
            
            // Invia la richiesta al server
            const response = await fetch('/wp-json/fp-resv/v1/reservations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (response.ok) {
                // Successo!
                console.log('✅ Prenotazione creata con successo:', data);
                showNotice('success', data.message || 'Prenotazione inviata con successo! Ti contatteremo presto per confermare.');
                
                // Nascondi il riepilogo dopo la conferma
                const summaryStep = form.querySelector('.fp-step[data-step="4"]');
                if (summaryStep) {
                    summaryStep.hidden = true;
                }
                
                // Nascondi i pulsanti
                if (submitBtn) submitBtn.hidden = true;
                if (prevBtn) prevBtn.hidden = true;
                
                // Nascondi la progress bar
                const progressBar = form.querySelector('.fp-progress');
                if (progressBar) {
                    progressBar.hidden = true;
                }
                
                // Lo scroll è gestito automaticamente dal NoticeManager
            } else {
                // Errore dal server
                console.error('❌ Errore dal server:', data);
                const errorMessage = data.message || 'Si è verificato un errore durante l\'invio della prenotazione. Riprova.';
                showNotice('error', errorMessage);
                
                // Riabilita il pulsante in caso di errore
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
                isSubmitting = false;
            }
        } catch (error) {
            // Errore di rete o JavaScript
            console.error('❌ Errore durante l\'invio:', error);
            showNotice('error', 'Errore di connessione. Verifica la tua connessione internet e riprova.');
            
            // Riabilita il pulsante in caso di errore
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            isSubmitting = false;
        }
    });
    
    // Helper: formatta data locale (NON UTC come toISOString!)
    function formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Set minimum date to today and load available dates
    let dateInput = document.getElementById('reservation-date');
    const today = formatLocalDate(new Date()); // Timezone locale!
    dateInput.min = today;
    
    // Store available dates globally
    let availableDates = [];
    let availableDatesSet = new Set(); // Performance: O(1) lookup invece di O(n)
    
    // AbortController per cancellare richieste precedenti (previene race condition)
    let availableDatesAbortController = null;
    let availableSlotsAbortController = null;
    
    // Inizializza Flatpickr per un calendario visivo migliore
    let flatpickrInstance = null;
    if (typeof flatpickr !== 'undefined' && dateInput) {
        flatpickrInstance = flatpickr(dateInput, {
            dateFormat: 'Y-m-d',
            minDate: 'today',
            locale: window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.it ? window.flatpickr.l10ns.it : 'it',
            enable: [], // Inizialmente nessuna data abilitata
            allowInput: false,
            disableMobile: false,
            onChange: function(selectedDates, dateStr, instance) {
                // Trigger evento change per compatibilità con il codice esistente
                const event = new Event('change', { bubbles: true });
                dateInput.dispatchEvent(event);
            },
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                // OTTIMIZZATO: usa Set per O(1) lookup invece di Array O(n)
                if (!dayElem || !dayElem.dateObj) return;
                
                const dateStr = formatLocalDate(dayElem.dateObj);
                if (availableDatesSet.has(dateStr)) {
                    dayElem.title = 'Data disponibile';
                    dayElem.setAttribute('aria-label', 'Data disponibile');
                } else {
                    dayElem.title = 'Data non disponibile';
                    dayElem.setAttribute('aria-label', 'Data non disponibile');
                }
            }
        });
        console.log('✅ Flatpickr inizializzato sul campo data');
    } else {
        console.log('⚠️ Flatpickr non disponibile, uso calendario nativo');
    }
    
    // Load available dates when meal is selected
    function loadAvailableDates(meal) {
        if (!meal) return;
        
        // PERFORMANCE TIMING START
        const perfStart = performance.now();
        console.log(`⏱️ [PERF] Inizio caricamento date per ${meal}`);
        
        // Cancella richiesta precedente se esiste (previene race condition)
        if (availableDatesAbortController) {
            availableDatesAbortController.abort();
            console.log('🚫 Richiesta precedente cancellata');
        }
        
        // Crea nuovo AbortController per questa richiesta
        availableDatesAbortController = new AbortController();
        
        // Show loading indicator
        const loadingEl = document.getElementById('date-loading');
        const infoEl = document.getElementById('date-info');
        loadingEl.hidden = false;
        infoEl.hidden = true;
        
        const from = today;
        const to = new Date();
        to.setMonth(to.getMonth() + 3); // 3 months ahead
        const toDate = formatLocalDate(to); // Timezone locale!
        
        // Prova prima l'endpoint WordPress, poi il fallback
        const endpoints = [
            `/wp-json/fp-resv/v1/available-days?from=${from}&to=${toDate}&meal=${meal}`,
            `/available-days-endpoint.php?from=${from}&to=${toDate}&meal=${meal}`
        ];
        
        let currentEndpointIndex = 0;
        
        function tryNextEndpoint() {
            if (currentEndpointIndex >= endpoints.length) {
                // Tutti gli endpoint hanno fallito, usa fallback locale
                console.log('Tutti gli endpoint hanno fallito, usando fallback locale');
                availableDates = generateFallbackDates(from, toDate, meal);
                availableDatesSet = new Set(availableDates); // Update Set per performance
                loadingEl.hidden = true;
                
                // PRELOAD: Date pronte, abilita "Avanti"
                areDatesLoading = false;
                areDatesReady = true;
                updateNextButtonState();
                
                // Show info about fallback dates
                if (infoEl) {
                    infoEl.hidden = false;
                    // Sanitizza meal per sicurezza (previene XSS)
                    const safeMeal = String(meal).replace(/[<>]/g, '');
                    infoEl.innerHTML = `<p>📅 ${availableDates.length} date disponibili per ${safeMeal} (modalità offline)</p>`;
                }
                
                // NON forzare lo step 2 - l'utente deve cliccare "Avanti"
                // Questo mantiene il comportamento consistente con il caso di successo API
                console.log('✅ Date di fallback caricate e PRONTE, puoi cliccare "Avanti"');
                
                // Show the date input
                if (dateInput) {
                    dateInput.hidden = false;
                    dateInput.disabled = false;
                }
                
                updateDateInput();
                console.log('Usando date di fallback per', meal, ':', availableDates);
                return;
            }
            
            const endpoint = endpoints[currentEndpointIndex];
            const fetchStart = performance.now();
            console.log(`⏱️ [PERF] Tentativo endpoint ${currentEndpointIndex + 1}:`, endpoint);
            
            fetch(endpoint, { signal: availableDatesAbortController.signal })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
            .then(data => {
                const fetchTime = performance.now() - fetchStart;
                console.log(`⏱️ [PERF] Fetch completato in ${fetchTime.toFixed(2)}ms`);
                
                // Hide loading indicator
                loadingEl.hidden = true;
                
                if (data && data.days) {
                    const parseStart = performance.now();
                    
                    // Store available dates
                    availableDates = Object.keys(data.days).filter(date => {
                        return data.days[date] && data.days[date].available;
                    });
                    availableDatesSet = new Set(availableDates); // Update Set per performance
                    
                    const parseTime = performance.now() - parseStart;
                    console.log(`⏱️ [PERF] Parsing dati in ${parseTime.toFixed(2)}ms`);
                    
                    // Show info if dates are available
                    if (availableDates.length > 0) {
                        infoEl.hidden = false;
                    }
                    
                    const updateStart = performance.now();
                    // Update date input with available dates info
                    updateDateInput();
                    const updateTime = performance.now() - updateStart;
                    console.log(`⏱️ [PERF] Update Flatpickr in ${updateTime.toFixed(2)}ms`);
                    
                    const totalTime = performance.now() - perfStart;
                    console.log(`⏱️ [PERF] TOTALE caricamento date: ${totalTime.toFixed(2)}ms`);
                    console.log('Date disponibili per', meal, ':', availableDates);
                    
                    // PRELOAD: Date pronte, abilita "Avanti"
                    areDatesLoading = false;
                    areDatesReady = true;
                    updateNextButtonState();
                } else {
                    // No data available, allow all dates
                    availableDates = [];
                    infoEl.hidden = true;
                }
            })
            .catch(error => {
                    // Se la richiesta è stata cancellata (AbortError), ignora silenziosamente
                    if (error.name === 'AbortError') {
                        console.log('🚫 Richiesta cancellata (cambio meal rapido)');
                        return;
                    }
                    
                    console.error(`Errore endpoint ${currentEndpointIndex + 1}:`, error);
                    if (currentEndpointIndex === 0) {
                        showNotice('warning', 'Problemi di connessione. Riprovo con un server alternativo...');
                    }
                    currentEndpointIndex++;
                    tryNextEndpoint();
                });
        }
        
        tryNextEndpoint();
    }
    
    // Genera date disponibili localmente come fallback
    function generateFallbackDates(from, to, meal) {
        // FIXED: Rimosso fetch asincrono che causava ritardi
        // Il fallback deve essere SINCRONO e IMMEDIATO per non bloccare l'UI
        // Se serve configurazione backend, usare endpoint /available-days che ha caching
        
        console.log('[FALLBACK] Generando date di default per', meal);
        
        // Fallback immediato: usa schedule di default
        return generateDatesFromDefaultSchedule(from, to, meal);
    }
    
    // Genera date usando configurazione di default
    function generateDatesFromDefaultSchedule(from, to, meal) {
        const startDate = new Date(from);
        const endDate = new Date(to);
        const fallbackDates = [];
        
        // Schedule di default (basato su configurazione tipica ristorante)
        const defaultSchedule = {
            'pranzo': {
                'mon': true,
                'tue': true,
                'wed': true,
                'thu': true,
                'fri': true,
                'sat': true,
                'sun': false, // Pranzo non disponibile la domenica
            },
            'cena': {
                'mon': false, // Cena non disponibile il lunedì
                'tue': true,
                'wed': true,
                'thu': true,
                'fri': true,
                'sat': true,
                'sun': true,
            }
        };
        
        const current = new Date(startDate);
        while (current <= endDate) {
            const dateKey = formatLocalDate(current); // Timezone locale!
            const dayKey = current.toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase();
            
            if (meal && defaultSchedule[meal]) {
                const isAvailable = defaultSchedule[meal][dayKey] || false;
                if (isAvailable) {
                    fallbackDates.push(dateKey);
                }
            } else {
                // Controlla tutti i pasti
                const hasAnyAvailability = Object.values(defaultSchedule).some(schedule => schedule[dayKey]);
                if (hasAnyAvailability) {
                    fallbackDates.push(dateKey);
                }
            }
            
            current.setDate(current.getDate() + 1);
        }
        
        return fallbackDates;
    }
    
    // Genera orari disponibili localmente come fallback
    function generateFallbackTimeSlots(meal) {
        // FIXED: Rimosso fetch asincrono che causava ritardi
        // Il fallback deve essere SINCRONO e IMMEDIATO per non bloccare l'UI
        // Se serve configurazione backend, usare endpoint /available-slots che ha caching
        
        console.log('[FALLBACK] Generando orari di default per', meal);
        
        // Fallback immediato: usa orari di default
        return generateTimeSlotsFromDefault(meal);
    }
    
    // Genera orari usando configurazione di default
    function generateTimeSlotsFromDefault(meal) {
        const slots = [];
        
        if (meal === 'pranzo') {
            // Orari pranzo: 12:00 - 14:30 ogni 30 minuti
            const startHour = 12;
            const endHour = 14;
            const startMinute = 0;
            const endMinute = 30;
            
            for (let hour = startHour; hour <= endHour; hour++) {
                const maxMinute = (hour === endHour) ? endMinute : 30;
                for (let minute = startMinute; minute <= maxMinute; minute += 30) {
                    const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    const slotStart = `${timeStr}:00`;
                    
                    slots.push({
                        time: timeStr,
                        slotStart: slotStart,
                        available: true,
                        capacity: 50,
                        status: 'available'
                    });
                }
            }
        } else if (meal === 'cena') {
            // Orari cena: 19:00 - 22:30 ogni 30 minuti
            const startHour = 19;
            const endHour = 22;
            const startMinute = 0;
            const endMinute = 30;
            
            for (let hour = startHour; hour <= endHour; hour++) {
                const maxMinute = (hour === endHour) ? endMinute : 30;
                for (let minute = startMinute; minute <= maxMinute; minute += 30) {
                    const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                    const slotStart = `${timeStr}:00`;
                    
                    slots.push({
                        time: timeStr,
                        slotStart: slotStart,
                        available: true,
                        capacity: 50,
                        status: 'available'
                    });
                }
            }
        }
        
        return slots;
    }
    
    // Update date input with availability info
    function updateDateInput() {
        // Set available dates as data attribute for validation
        if (availableDates.length > 0) {
            dateInput.setAttribute('data-available-dates', availableDates.join(','));
            dateInput.setAttribute('data-available-count', availableDates.length);
            
            // Aggiorna Flatpickr con le date disponibili
            if (flatpickrInstance) {
                flatpickrInstance.set('enable', availableDates);
                console.log('✅ Flatpickr aggiornato con', availableDates.length, 'date disponibili');
            }
            
            // Show info about available dates
            const infoEl = document.getElementById('date-info');
            if (infoEl) {
                infoEl.hidden = false;
                // Sanitizza selectedMeal per sicurezza (previene XSS)
                const safeMeal = String(selectedMeal).replace(/[<>]/g, '');
                infoEl.innerHTML = `<p>📅 ${availableDates.length} date disponibili per ${safeMeal}</p>`;
            }
        } else {
            // No restrictions, allow all dates
            dateInput.removeAttribute('data-available-dates');
            dateInput.removeAttribute('data-available-count');
            
            // Resetta Flatpickr
            if (flatpickrInstance) {
                flatpickrInstance.set('enable', []);
                console.log('⚠️ Flatpickr: nessuna data disponibile');
            }
            
            const infoEl = document.getElementById('date-info');
            if (infoEl) {
                infoEl.hidden = true;
            }
        }
    }
    
    // Validazione data - listener aggiunto UNA SOLA VOLTA (fuori dalla funzione)
    dateInput.addEventListener('change', function() {
        const selectedDate = this.value;
        const availableDatesList = this.getAttribute('data-available-dates');
        
        if (selectedDate && availableDatesList) {
            const availableDatesArray = availableDatesList.split(',');
            if (!availableDatesArray.includes(selectedDate)) {
                showNotice('error', 'Questa data non è disponibile per il servizio selezionato. Scegli un\'altra data.');
                this.value = '';
                return;
            }
        }
        
        // NON avanzare automaticamente - l'utente deve selezionare un orario prima
        // Gli orari vengono caricati dal listener checkAndLoadTimeSlots() qui sotto
    });
    
    // Load available time slots when date is selected
    function loadAvailableTimeSlots(meal, date, party) {
        if (!meal || !date || !party) return;
        
        // PERFORMANCE TIMING START
        const perfStart = performance.now();
        console.log(`⏱️ [PERF] Inizio caricamento slot per ${meal} ${date} ${party} persone`);
        
        // Cancella richiesta precedente se esiste (previene race condition)
        if (availableSlotsAbortController) {
            availableSlotsAbortController.abort();
            console.log('🚫 Richiesta slot precedente cancellata');
        }
        
        // Crea nuovo AbortController per questa richiesta
        availableSlotsAbortController = new AbortController();
        
        const loadingEl = document.getElementById('time-loading');
        const slotsEl = document.getElementById('time-slots');
        const infoEl = document.getElementById('time-info');
        
        loadingEl.hidden = false;
        slotsEl.innerHTML = '';
        infoEl.hidden = true;
        
        const fetchStart = performance.now();
        fetch(`/wp-json/fp-resv/v1/available-slots?meal=${meal}&date=${date}&party=${party}`, { signal: availableSlotsAbortController.signal })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                const fetchTime = performance.now() - fetchStart;
                console.log(`⏱️ [PERF] Fetch slot completato in ${fetchTime.toFixed(2)}ms`);
                
                loadingEl.hidden = true;
                
                if (data && data.slots && data.slots.length > 0) {
                    const renderStart = performance.now();
                    // OTTIMIZZATO: usa DocumentFragment per batch append (previene reflow multipli)
                    const fragment = document.createDocumentFragment();
                    
                    data.slots.forEach(slot => {
                        const slotBtn = document.createElement('button');
                        slotBtn.type = 'button';
                        slotBtn.className = 'fp-time-slot';
                        slotBtn.textContent = slot.time;
                        slotBtn.dataset.time = slot.time;
                        slotBtn.dataset.slotStart = slot.slot_start;
                        
                        if (slot.available) {
                            slotBtn.addEventListener('click', function() {
                                document.querySelectorAll('.fp-time-slot').forEach(s => s.classList.remove('selected'));
                                this.classList.add('selected');
                                selectedTime = this.dataset.time;
                                
                                // Update hidden fields
                                document.querySelector('input[name="fp_resv_time"]').value = this.dataset.time;
                                document.querySelector('input[name="fp_resv_slot_start"]').value = this.dataset.slotStart;
                                
                                // Auto-advance allo step 3 (dettagli cliente)
                                if (validateStep(2)) {
                                    currentStep = 3;
                                    showStep(currentStep);
                                }
                            });
                        } else {
                            slotBtn.classList.add('disabled');
                            slotBtn.disabled = true;
                        }
                        
                        fragment.appendChild(slotBtn);
                    });
                    
                    // Append tutto in una sola volta (1 reflow invece di N)
                    slotsEl.innerHTML = '';
                    slotsEl.appendChild(fragment);
                    
                    const renderTime = performance.now() - renderStart;
                    const totalTime = performance.now() - perfStart;
                    console.log(`⏱️ [PERF] Rendering ${data.slots.length} slot in ${renderTime.toFixed(2)}ms`);
                    console.log(`⏱️ [PERF] TOTALE caricamento slot: ${totalTime.toFixed(2)}ms`);
                    
                    infoEl.hidden = false;
                } else {
                    slotsEl.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Nessun orario disponibile per questa data</p>';
                }
            })
            .catch(error => {
                // Se la richiesta è stata cancellata (AbortError), ignora silenziosamente
                if (error.name === 'AbortError') {
                    console.log('🚫 Richiesta slot cancellata (cambio rapido data/party)');
                    return;
                }
                
                console.error('Errore nel caricamento orari:', error);
                console.log('Usando orari di fallback per', meal, 'alle', date);
                showNotice('info', 'Caricamento orari in corso...');
                
                // Fallback: genera orari localmente
                const fallbackSlots = generateFallbackTimeSlots(meal);
                loadingEl.hidden = true;
                
                if (fallbackSlots.length > 0) {
                    // OTTIMIZZATO: usa DocumentFragment per batch append
                    const fragment = document.createDocumentFragment();
                    
                    fallbackSlots.forEach(slot => {
                        const slotBtn = document.createElement('button');
                        slotBtn.type = 'button';
                        slotBtn.className = 'fp-time-slot';
                        slotBtn.textContent = slot.time;
                        slotBtn.dataset.time = slot.time;
                        slotBtn.dataset.slotStart = slot.slotStart;
                        
                        slotBtn.addEventListener('click', function() {
                            document.querySelectorAll('.fp-time-slot').forEach(s => s.classList.remove('selected'));
                            this.classList.add('selected');
                            selectedTime = this.dataset.time;
                            
                            // Update hidden fields
                            document.querySelector('input[name="fp_resv_time"]').value = this.dataset.time;
                            document.querySelector('input[name="fp_resv_slot_start"]').value = this.dataset.slotStart;
                            
                            // Auto-advance allo step 3 (dettagli cliente)
                            if (validateStep(2)) {
                                currentStep = 3;
                                showStep(currentStep);
                            }
                        });
                        
                        fragment.appendChild(slotBtn);
                    });
                    
                    // Append tutto in una sola volta
                    slotsEl.innerHTML = '';
                    slotsEl.appendChild(fragment);
                    infoEl.hidden = false;
                    // Sanitizza meal per sicurezza (previene XSS)
                    const safeMeal = String(meal).replace(/[<>]/g, '');
                    infoEl.innerHTML = `<p>🕐 ${fallbackSlots.length} orari disponibili per ${safeMeal} (modalità offline)</p>`;
                } else {
                    slotsEl.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Nessun orario disponibile per questa data</p>';
                }
            });
    }
    
        // Load time slots when date or party changes
        let checkSlotsTimeout = null;
        
        function checkAndLoadTimeSlots() {
            const date = document.getElementById('reservation-date').value;
            const party = document.getElementById('party-size').value;
            
            // Reset selectedTime quando cambiano data o party (gli orari cambiano)
            selectedTime = null;
            document.querySelectorAll('.fp-time-slot').forEach(s => s.classList.remove('selected'));
            
            if (date && party && selectedMeal) {
                loadAvailableTimeSlots(selectedMeal, date, party);
            }
        }
        
        // Debounced version per party size (previene troppe chiamate)
        function checkAndLoadTimeSlotsDebounced() {
            clearTimeout(checkSlotsTimeout);
            checkSlotsTimeout = setTimeout(checkAndLoadTimeSlots, 300); // 300ms debounce
        }

        document.getElementById('reservation-date').addEventListener('change', checkAndLoadTimeSlots);
        
        // Aggiorna anche quando cambia il numero di persone (con debounce)
        const partyInput = document.getElementById('party-size');
        if (partyInput) {
            partyInput.addEventListener('change', checkAndLoadTimeSlotsDebounced);
        }
});
