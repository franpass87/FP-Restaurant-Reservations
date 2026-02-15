console.log('🚀 JavaScript del form caricato! [VERSIONE AUDIT COMPLETO v2.4 + GTM TRACKING]');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM caricato, inizializzo form...');
    const form = document.getElementById('fp-resv-default') || document.getElementById('fp-resv-simple') || document.querySelector('.fp-resv-simple');
    console.log('Form trovato:', form);
    
    if (!form) {
        console.error('Form non trovato!');
        return;
    }

    // ─── GTM / DataLayer Tracking Helper ─────────────────────────
    // Pusha eventi FLAT nel dataLayer per essere letti da GTM come
    // Data Layer Variables (DLV). Usa fpResvTracking.dispatch() come
    // fallback per modalità non-GTM (gtag diretto).
    let _reservationStartFired = false;

    function pushDataLayerEvent(eventName, params) {
        window.dataLayer = window.dataLayer || [];
        var payload = { event: eventName };
        if (params && typeof params === 'object') {
            for (var key in params) {
                if (params.hasOwnProperty(key) && params[key] !== undefined && params[key] !== null) {
                    payload[key] = params[key];
                }
            }
        }
        window.dataLayer.push(payload);
        console.log('[FP-RESV-TRACKING] dataLayer.push:', eventName, payload);

        // Dispatch a fpResvTracking se presente (per modalità non-GTM)
        if (window.fpResvTracking && typeof window.fpResvTracking.dispatch === 'function') {
            try {
                window.fpResvTracking.dispatch({
                    event: eventName,
                    ga4: { name: eventName, params: params || {} }
                });
            } catch (e) {
                console.warn('[FP-RESV-TRACKING] dispatch error:', e);
            }
        }
    }
    // ─── Fine Helper ─────────────────────────────────────────────
    
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
    
    // Sincronizza i checkbox dello step finale con quelli dello step 3
    // Flag per tracciare se i listener sono già stati aggiunti
    let checkboxesSynced = false;
    
    function syncCheckboxes() {
        // Evita listener duplicati
        if (checkboxesSynced) {
            return;
        }
        
        const consentStep3 = document.querySelector('input[name="fp_resv_consent"]:not(#privacy-consent-final)');
        const consentFinal = document.getElementById('privacy-consent-final');
        if (consentStep3 && consentFinal) {
            // Sincronizza da step finale a step 3
            consentFinal.addEventListener('change', function() {
                if (consentStep3) {
                    consentStep3.checked = consentFinal.checked;
                }
            });
            // Sincronizza da step 3 a step finale
            consentStep3.addEventListener('change', function() {
                consentFinal.checked = consentStep3.checked;
            });
        }
        
        const marketingStep3 = document.querySelector('input[name="fp_resv_marketing_consent"]:not(#marketing-consent-final)');
        const marketingFinal = document.getElementById('marketing-consent-final');
        if (marketingStep3 && marketingFinal) {
            // Sincronizza da step finale a step 3
            marketingFinal.addEventListener('change', function() {
                if (marketingStep3) {
                    marketingStep3.checked = marketingFinal.checked;
                }
            });
            // Sincronizza da step 3 a step finale
            marketingStep3.addEventListener('change', function() {
                marketingFinal.checked = marketingStep3.checked;
            });
        }
        
        checkboxesSynced = true;
    }
    
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

                // GTM: reservation_start (solo alla prima interazione)
                if (!_reservationStartFired) {
                    _reservationStartFired = true;
                    var locationVal = (document.querySelector('input[name="fp_resv_location"]') || {}).value || 'default';
                    pushDataLayerEvent('reservation_start', {
                        reservation_location: locationVal,
                        meal_type: selectedMeal
                    });
                }
                
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
        
        // Sincronizza i checkbox dello step finale con quelli dello step 3
        // IMPORTANTE: sincronizza solo se i checkbox finali non sono stati ancora modificati dall'utente
        // Questo permette all'utente di modificare i checkbox finali senza che vengano sovrascritti
        const consentStep3 = document.querySelector('input[name="fp_resv_consent"]:not(#privacy-consent-final)');
        const consentFinal = document.getElementById('privacy-consent-final');
        if (consentStep3 && consentFinal) {
            // Sincronizza solo se il checkbox finale non è stato ancora toccato dall'utente
            // Usa un attributo data per tracciare se è stato modificato
            if (!consentFinal.hasAttribute('data-user-modified')) {
                consentFinal.checked = consentStep3.checked;
            }
            // FORZA le dimensioni e la visibilità del checkbox - CRITICO per la cliccabilità
            consentFinal.style.setProperty('width', '20px', 'important');
            consentFinal.style.setProperty('height', '20px', 'important');
            consentFinal.style.setProperty('min-width', '20px', 'important');
            consentFinal.style.setProperty('min-height', '20px', 'important');
            consentFinal.style.setProperty('opacity', '1', 'important');
            consentFinal.style.setProperty('visibility', 'visible', 'important');
            consentFinal.style.setProperty('display', 'inline-block', 'important');
            consentFinal.style.setProperty('pointer-events', 'auto', 'important');
            consentFinal.style.setProperty('z-index', '10000', 'important');
            consentFinal.style.setProperty('position', 'relative', 'important');
            consentFinal.style.setProperty('cursor', 'pointer', 'important');
            consentFinal.style.setProperty('margin', '0', 'important');
            consentFinal.style.setProperty('padding', '0', 'important');
            consentFinal.disabled = false;
            consentFinal.readOnly = false;
            
            // Aggiungi listener per tracciare quando l'utente modifica il checkbox
            if (!consentFinal.hasAttribute('data-listener-added')) {
                consentFinal.addEventListener('change', function() {
                    this.setAttribute('data-user-modified', 'true');
                });
                consentFinal.setAttribute('data-listener-added', 'true');
            }
        }
        
        const marketingStep3 = document.querySelector('input[name="fp_resv_marketing_consent"]:not(#marketing-consent-final)');
        const marketingFinal = document.getElementById('marketing-consent-final');
        if (marketingStep3 && marketingFinal) {
            // Sincronizza solo se il checkbox finale non è stato ancora toccato dall'utente
            if (!marketingFinal.hasAttribute('data-user-modified')) {
                marketingFinal.checked = marketingStep3.checked;
            }
            // FORZA le dimensioni e la visibilità del checkbox - CRITICO per la cliccabilità
            marketingFinal.style.setProperty('width', '20px', 'important');
            marketingFinal.style.setProperty('height', '20px', 'important');
            marketingFinal.style.setProperty('min-width', '20px', 'important');
            marketingFinal.style.setProperty('min-height', '20px', 'important');
            marketingFinal.style.setProperty('opacity', '1', 'important');
            marketingFinal.style.setProperty('visibility', 'visible', 'important');
            marketingFinal.style.setProperty('display', 'inline-block', 'important');
            marketingFinal.style.setProperty('pointer-events', 'auto', 'important');
            marketingFinal.style.setProperty('z-index', '10000', 'important');
            marketingFinal.style.setProperty('position', 'relative', 'important');
            marketingFinal.style.setProperty('cursor', 'pointer', 'important');
            marketingFinal.style.setProperty('margin', '0', 'important');
            marketingFinal.style.setProperty('padding', '0', 'important');
            marketingFinal.disabled = false;
            marketingFinal.readOnly = false;
            
            // Aggiungi listener per tracciare quando l'utente modifica il checkbox
            if (!marketingFinal.hasAttribute('data-listener-added')) {
                marketingFinal.addEventListener('change', function() {
                    this.setAttribute('data-user-modified', 'true');
                });
                marketingFinal.setAttribute('data-listener-added', 'true');
            }
        }
        
        console.log('Riepilogo popolato');
    }
    
    // Navigation
    function showStep(step) {
        console.log('showStep chiamata con step:', step);
        
        // Valida che lo step sia valido
        if (step < 1 || step > totalSteps) {
            console.error('Step non valido:', step);
            return;
        }
        
        // Scroll al top del form per evitare che l'utente resti in fondo
        // dopo step lunghi (es. step 3 → step 4)
        if (form && typeof form.scrollIntoView === 'function') {
            requestAnimationFrame(function() {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        
        // Nascondi tutti gli step e rimuovi classi
        // Verifica che steps sia un NodeList valido
        if (steps && steps.length > 0) {
            steps.forEach(s => {
                if (s && s.nodeType === 1) { // Verifica che sia un elemento DOM
                    s.classList.remove('active', 'completed');
                    // Usa display: none con !important per nascondere (sovrascrive CSS)
                    s.style.setProperty('display', 'none', 'important');
                    s.setAttribute('aria-hidden', 'true');
                }
            });
        }
        if (progressSteps && progressSteps.length > 0) {
            progressSteps.forEach(p => {
                if (p && p.nodeType === 1) {
                    p.classList.remove('active', 'completed');
                }
            });
        }
        
        // Mostra solo lo step corrente
        const currentStepEl = form.querySelector(`.fp-step[data-step="${step}"]`);
        console.log('Elemento step trovato:', currentStepEl);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
            // Mostra lo step corrente rimuovendo display: none
            // Usa !important per sovrascrivere eventuali regole CSS
            currentStepEl.style.setProperty('display', '', 'important');
            currentStepEl.removeAttribute('aria-hidden');
            currentStepEl.setAttribute('aria-hidden', 'false');
            console.log('Classe active aggiunta al step', step);
            
            // FIX CRITICO: Forza le dimensioni dei checkbox quando lo step 3 o 4 viene mostrato
            // Questo è necessario perché quando uno step è nascosto con display:none, i checkbox non vengono renderizzati
            if (step === 3 || step === 4) {
                setTimeout(() => {
                    let checkboxes = [];
                    
                    if (step === 3) {
                        // Checkbox dello step 3
                        const consentStep3 = document.getElementById('privacy-consent');
                        const marketingStep3 = document.getElementById('marketing-consent');
                        const profilingStep3 = document.getElementById('profiling-consent');
                        const wheelchairCheckbox = document.getElementById('wheelchair-table');
                        const petCheckbox = document.getElementById('pets-allowed');
                        
                        checkboxes = [consentStep3, marketingStep3, profilingStep3, wheelchairCheckbox, petCheckbox].filter(cb => cb !== null);
                    } else if (step === 4) {
                        // Checkbox dello step 4 finale
                        const privacyFinal = document.getElementById('privacy-consent-final');
                        const marketingFinal = document.getElementById('marketing-consent-final');
                        const profilingFinal = document.getElementById('profiling-consent-final');
                        
                        checkboxes = [privacyFinal, marketingFinal, profilingFinal].filter(cb => cb !== null);
                    }
                    
                    checkboxes.forEach(checkbox => {
                        if (checkbox) {
                            checkbox.style.setProperty('width', '20px', 'important');
                            checkbox.style.setProperty('height', '20px', 'important');
                            checkbox.style.setProperty('min-width', '20px', 'important');
                            checkbox.style.setProperty('min-height', '20px', 'important');
                            checkbox.style.setProperty('max-width', '20px', 'important');
                            checkbox.style.setProperty('max-height', '20px', 'important');
                            checkbox.style.setProperty('opacity', '1', 'important');
                            checkbox.style.setProperty('visibility', 'visible', 'important');
                            checkbox.style.setProperty('display', 'inline-block', 'important');
                            checkbox.style.setProperty('flex-shrink', '0', 'important');
                            checkbox.style.setProperty('pointer-events', 'auto', 'important');
                            checkbox.style.setProperty('z-index', '10001', 'important');
                            checkbox.style.setProperty('position', 'relative', 'important');
                            checkbox.style.setProperty('cursor', 'pointer', 'important');
                            checkbox.style.setProperty('margin', '0', 'important');
                            checkbox.style.setProperty('padding', '0', 'important');
                            checkbox.disabled = false;
                            checkbox.readOnly = false;
                            
                            // Assicura che wrapper e label siano cliccabili
                            const wrapper = checkbox.closest('.fp-checkbox-wrapper');
                            if (wrapper) {
                                wrapper.style.setProperty('pointer-events', 'auto', 'important');
                                wrapper.style.setProperty('z-index', '10000', 'important');
                                wrapper.style.setProperty('position', 'relative', 'important');
                            }
                            
                            const label = document.querySelector(`label[for="${checkbox.id}"]`);
                            if (label) {
                                label.style.setProperty('pointer-events', 'auto', 'important');
                                label.style.setProperty('cursor', 'pointer', 'important');
                                label.style.setProperty('user-select', 'none', 'important');
                            }
                        }
                    });
                }, 100);
            }
        } else {
            console.error('Elemento step non trovato per step:', step);
            // Fallback: mostra il primo step se lo step richiesto non esiste
            const firstStep = form.querySelector('.fp-step[data-step="1"]');
            if (firstStep) {
                firstStep.style.setProperty('display', '', 'important');
                firstStep.removeAttribute('aria-hidden');
                firstStep.setAttribute('aria-hidden', 'false');
                firstStep.classList.add('active');
                console.warn('Fallback: mostrato step 1');
            }
        }
        
        // Update progress - mostra gli step completati come completati
        // Cerca sia nei progress steps che negli step stessi
        for (let i = 1; i <= step; i++) {
            // Cerca nei progress steps (indicatori di progresso)
            const progressStep = form.querySelector(`.fp-progress-step[data-step="${i}"]`) || 
                                 form.querySelector(`[data-step="${i}"].fp-progress-step`);
            if (progressStep) {
                if (i < step) {
                    progressStep.classList.add('completed');
                } else if (i === step) {
                    progressStep.classList.add('active');
                }
            }
        }
        
        // Update buttons
        if (prevBtn) prevBtn.hidden = step <= 1;
        if (nextBtn) nextBtn.hidden = step >= totalSteps;
        if (submitBtn) submitBtn.hidden = step < totalSteps;
        
        // Update button text
        if (submitBtn) {
            if (step === totalSteps) {
                submitBtn.textContent = 'Conferma Prenotazione';
            } else {
                submitBtn.textContent = 'Prenota';
            }
        }
        
        // Aggiorna lo stato corrente
        currentStep = step;
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
                // Controlla i checkbox dello step 3 (escludi quelli dello step finale)
                const consent = document.querySelector('input[name="fp_resv_consent"]:not(#privacy-consent-final)')?.checked || false;
                return firstName !== '' && lastName !== '' && email !== '' && phone !== '' && consent;
            case 4:
                // Step 4: controlla i checkbox dello step finale
                const consentFinal = document.getElementById('privacy-consent-final')?.checked || document.querySelector('input[name="fp_resv_consent"]')?.checked || false;
                return consentFinal;
        }
        return true;
    }
    
    nextBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            currentStep++;
            
            // Se stiamo andando allo step 4 (riepilogo), popola i dati
            if (currentStep === 4) {
                populateSummary();
                // Inizializza la sincronizzazione dei checkbox quando si passa allo step 4
                // Chiama solo se i checkbox finali sono nel DOM
                if (document.getElementById('privacy-consent-final')) {
                    syncCheckboxes();
                }
            }
            
            showStep(currentStep);
        } else {
            // Mostra messaggio specifico per step 3 se manca il consenso privacy
            if (currentStep === 3) {
                const consent = document.querySelector('input[name="fp_resv_consent"]:not(#privacy-consent-final)');
                if (consent && !consent.checked) {
                    showNotice('warning', 'È necessario accettare la Privacy Policy per procedere.');
                    return;
                }
            }
            // Mostra messaggio specifico per step 4 se manca il consenso privacy
            if (currentStep === 4) {
                const consent = document.getElementById('privacy-consent-final') || document.querySelector('input[name="fp_resv_consent"]');
                if (consent && !consent.checked) {
                    showNotice('warning', 'È necessario accettare la Privacy Policy per procedere.');
                    return;
                }
            }
            showNotice('warning', 'Per favore completa tutti i campi richiesti.');
        }
    });
    
    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
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
                // Usa i checkbox dello step finale se presenti, altrimenti quelli dello step 3
                fp_resv_consent: (document.getElementById('privacy-consent-final')?.checked ?? document.querySelector('input[name="fp_resv_consent"]')?.checked) || false,
                fp_resv_marketing_consent: (document.getElementById('marketing-consent-final')?.checked ?? document.querySelector('input[name="fp_resv_marketing_consent"]')?.checked) ? '1' : '',
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

            // GTM: reservation_submit (pre-AJAX, flat per DLV)
            pushDataLayerEvent('reservation_submit', {
                reservation_date: payload.fp_resv_date,
                reservation_time: payload.fp_resv_time,
                reservation_party: payload.fp_resv_party,
                reservation_location: payload.fp_resv_location || 'default',
                meal_type: payload.fp_resv_meal,
                currency: payload.fp_resv_currency || 'EUR'
            });

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

                // ─── GTM: reservation_confirmed (flat per DLV) ───
                var res = (data && data.reservation) || {};
                var trackingArr = (data && Array.isArray(data.tracking)) ? data.tracking : [];
                var firstTrack = trackingArr.length > 0 ? trackingArr[0] : null;
                var ga4p = (firstTrack && firstTrack.ga4 && firstTrack.ga4.params) || {};
                var resvData = (firstTrack && firstTrack.reservation) || {};

                pushDataLayerEvent('reservation_confirmed', {
                    reservation_id: ga4p.reservation_id || resvData.id || res.id,
                    reservation_status: ga4p.reservation_status || resvData.status || (res.status || 'confirmed').toLowerCase(),
                    reservation_party: ga4p.reservation_party || resvData.party || res.party || res.guests || payload.fp_resv_party,
                    reservation_date: ga4p.reservation_date || resvData.date || res.date || payload.fp_resv_date,
                    reservation_time: ga4p.reservation_time || resvData.time || res.time || payload.fp_resv_time,
                    reservation_location: ga4p.reservation_location || resvData.location || res.location || payload.fp_resv_location || 'default',
                    meal_type: ga4p.meal_type || resvData.meal_type || payload.fp_resv_meal,
                    value: ga4p.value != null ? ga4p.value : (res.value != null ? Number(res.value) : undefined),
                    currency: ga4p.currency || res.currency || payload.fp_resv_currency || 'EUR',
                    event_id: (firstTrack && firstTrack.event_id) || undefined
                });

                // GTM: purchase (se presente nella risposta server)
                var purchaseEntry = trackingArr.find(function(e) { return e && e.event === 'purchase'; });
                if (purchaseEntry) {
                    var pGa4 = (purchaseEntry.ga4 && purchaseEntry.ga4.params) || {};
                    var pData = purchaseEntry.purchase || {};
                    pushDataLayerEvent('purchase', {
                        value: pGa4.value || pData.value,
                        currency: pGa4.currency || pData.currency || 'EUR',
                        value_is_estimated: pData.value_is_estimated || true,
                        reservation_id: pGa4.reservation_id || ga4p.reservation_id || res.id,
                        reservation_party: pGa4.reservation_party || pData.party_size || payload.fp_resv_party,
                        meal_type: pGa4.meal_type || pData.meal_type || payload.fp_resv_meal,
                        event_id: purchaseEntry.event_id || undefined
                    });
                }

                // Dispatch server-side tracking (per modalità non-GTM)
                trackingArr.forEach(function(entry) {
                    if (entry && window.fpResvTracking && typeof window.fpResvTracking.dispatch === 'function') {
                        try { window.fpResvTracking.dispatch(entry); } catch(e) {}
                    }
                });
                // ─── Fine GTM ───

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
                
                // Hide info about fallback dates (non mostriamo più il conteggio)
                if (infoEl) {
                    infoEl.hidden = true;
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
                    
                    // Hide info about available dates (non mostriamo più il conteggio)
                    if (infoEl) {
                        infoEl.hidden = true;
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
            
            // Hide info about available dates (non mostriamo più il conteggio)
            const infoEl = document.getElementById('date-info');
            if (infoEl) {
                infoEl.hidden = true;
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
        
        // Inizializza: mostra solo il primo step (dopo che tutti gli elementi sono stati definiti)
        showStep(1);
        
        // FIX STRUTTURALE: Assicura che i checkbox finali siano sempre renderizzati correttamente quando lo step 4 è mostrato
        // Il problema è che quando lo step 4 è nascosto con display:none, i checkbox non vengono renderizzati dal browser
        // Quando lo step viene mostrato, dobbiamo forzare il reflow per assicurare che i checkbox abbiano dimensioni corrette
        // Salva la funzione showStep originale e la sovrascrivi
        const originalShowStep = showStep;
        showStep = function(step) {
            // Chiama la funzione originale
            originalShowStep(step);
            
            // FIX CRITICO: Forza il reflow dei checkbox quando lo step 3 o 4 viene mostrato
            // Il problema è che quando uno step è nascosto con display:none, i checkbox non vengono renderizzati dal browser
            // Quando lo step viene mostrato, dobbiamo forzare il reflow per assicurare che i checkbox abbiano dimensioni corrette
            if (step === 3 || step === 4) {
                // Usa doppio requestAnimationFrame per assicurare che il DOM sia completamente aggiornato e renderizzato
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        // Verifica che lo step sia effettivamente visibile
                        const stepEl = form.querySelector(`.fp-step[data-step="${step}"]`);
                        if (!stepEl || stepEl.style.display === 'none' || stepEl.hasAttribute('aria-hidden')) {
                            // Se lo step non è visibile, riprova dopo un breve delay
                            setTimeout(() => {
                                fixCheckboxesForStep(step);
                            }, 100);
                            return;
                        }
                        
                        fixCheckboxesForStep(step);
                    });
                });
            }
        };
        
        // Funzione helper per fixare i checkbox di uno step
        function fixCheckboxesForStep(step) {
            let checkboxes = [];
            
            if (step === 3) {
                // Checkbox dello step 3
                const consentStep3 = document.getElementById('privacy-consent');
                const marketingStep3 = document.getElementById('marketing-consent');
                const profilingStep3 = document.getElementById('profiling-consent');
                const wheelchairCheckbox = document.getElementById('wheelchair-table');
                const petCheckbox = document.getElementById('pets-allowed');
                
                checkboxes = [consentStep3, marketingStep3, profilingStep3, wheelchairCheckbox, petCheckbox].filter(cb => cb !== null);
            } else if (step === 4) {
                // Checkbox dello step 4 finale
                const privacyFinal = document.getElementById('privacy-consent-final');
                const marketingFinal = document.getElementById('marketing-consent-final');
                const profilingFinal = document.getElementById('profiling-consent-final');
                
                checkboxes = [privacyFinal, marketingFinal, profilingFinal].filter(cb => cb !== null);
            }
            
            checkboxes.forEach(checkbox => {
                if (checkbox) {
                    // Forza il reflow leggendo offsetHeight - questo assicura che il browser calcoli le dimensioni
                    void checkbox.offsetHeight;
                    
                    // SEMPRE forza le dimensioni, anche se non sono 0x0, per assicurare che siano corrette
                    // Questo è necessario perché il CSS del tema potrebbe interferire
                    checkbox.style.setProperty('width', '20px', 'important');
                    checkbox.style.setProperty('height', '20px', 'important');
                    checkbox.style.setProperty('min-width', '20px', 'important');
                    checkbox.style.setProperty('min-height', '20px', 'important');
                    checkbox.style.setProperty('max-width', '20px', 'important');
                    checkbox.style.setProperty('max-height', '20px', 'important');
                    checkbox.style.setProperty('opacity', '1', 'important');
                    checkbox.style.setProperty('visibility', 'visible', 'important');
                    checkbox.style.setProperty('display', 'inline-block', 'important');
                    checkbox.style.setProperty('flex-shrink', '0', 'important');
                    checkbox.style.setProperty('pointer-events', 'auto', 'important');
                    checkbox.style.setProperty('z-index', '99999', 'important'); // Z-index molto alto per essere sopra tutto
                    checkbox.style.setProperty('position', 'relative', 'important');
                    checkbox.style.setProperty('cursor', 'pointer', 'important');
                    checkbox.style.setProperty('margin', '0', 'important');
                    checkbox.style.setProperty('padding', '0', 'important');
                    checkbox.style.setProperty('border', '1px solid #d1d5db', 'important');
                    checkbox.style.setProperty('background-color', '#ffffff', 'important');
                    checkbox.style.setProperty('appearance', 'checkbox', 'important');
                    checkbox.style.setProperty('-webkit-appearance', 'checkbox', 'important');
                    checkbox.style.setProperty('-moz-appearance', 'checkbox', 'important');
                    checkbox.style.setProperty('clip', 'auto', 'important');
                    checkbox.style.setProperty('clip-path', 'none', 'important');
                    checkbox.disabled = false;
                    checkbox.readOnly = false;
                    
                    // Forza un altro reflow dopo aver impostato tutte le proprietà
                    void checkbox.offsetHeight;
                    void checkbox.offsetWidth;
                    
                    // Assicura che wrapper e label siano cliccabili
                    const wrapper = checkbox.closest('.fp-checkbox-wrapper');
                    if (wrapper) {
                        wrapper.style.setProperty('pointer-events', 'auto', 'important');
                        wrapper.style.setProperty('z-index', '99998', 'important');
                        wrapper.style.setProperty('position', 'relative', 'important');
                    }
                    
                    const label = document.querySelector(`label[for="${checkbox.id}"]`);
                    if (label) {
                        label.style.setProperty('pointer-events', 'auto', 'important');
                        label.style.setProperty('cursor', 'pointer', 'important');
                        label.style.setProperty('user-select', 'none', 'important');
                        label.style.setProperty('z-index', '99997', 'important');
                        label.style.setProperty('position', 'relative', 'important');
                    }
                }
            });
        }
});
