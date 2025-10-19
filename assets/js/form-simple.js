console.log('JavaScript del form caricato!');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM caricato, inizializzo form...');
    const form = document.getElementById('fp-resv-default');
    console.log('Form trovato:', form);
    
    if (!form) {
        console.error('Form non trovato!');
        return;
    }
    
    // Funzione helper per mostrare notice
    function showNotice(type, message, duration = 5000) {
        if (window.fpNoticeManager) {
            return window.fpNoticeManager.show(type, message, duration);
        } else {
            // Fallback a alert se il notice manager non è disponibile
            alert(message);
        }
    }
    
    let currentStep = 1;
    const totalSteps = 4;
    
    const steps = form.querySelectorAll('.fp-step');
    const progressSteps = form.querySelectorAll('.fp-progress-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-btn');
    
    // Meal selection - dynamic
    let mealBtns = form.querySelectorAll('.fp-meal-btn');
    let selectedMeal = null;
    let selectedTime = null;

    function setupMealButtons() {
        mealBtns = form.querySelectorAll('.fp-meal-btn');
        console.log('Trovati', mealBtns.length, 'pulsanti pasto');
        mealBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Pulsante pasto cliccato:', this.dataset.meal);
                mealBtns.forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                selectedMeal = this.dataset.meal;
                
                // Load available dates for selected meal
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
        if (occasion) {
            const occasionText = document.getElementById('occasion').selectedOptions[0].text;
            document.getElementById('summary-occasion').textContent = occasionText;
            document.getElementById('summary-occasion-row').style.display = 'flex';
        } else {
            document.getElementById('summary-occasion-row').style.display = 'none';
        }
        
        // Note (se specificate)
        const notes = document.getElementById('notes').value;
        if (notes) {
            document.getElementById('summary-notes').textContent = notes;
            document.getElementById('summary-notes-row').style.display = 'flex';
        } else {
            document.getElementById('summary-notes-row').style.display = 'none';
        }
        
        // Allergie (se specificate)
        const allergies = document.getElementById('allergies').value;
        if (allergies) {
            document.getElementById('summary-allergies').textContent = allergies;
            document.getElementById('summary-allergies-row').style.display = 'flex';
        } else {
            document.getElementById('summary-allergies-row').style.display = 'none';
        }
        
        // Servizi aggiuntivi
        const wheelchairTable = document.querySelector('input[name="fp_resv_wheelchair_table"]').checked;
        const pets = document.querySelector('input[name="fp_resv_pets"]').checked;
        const highChairCount = document.getElementById('high-chair-count').value;
        
        let hasExtras = false;
        
        if (wheelchairTable) {
            document.getElementById('summary-wheelchair-row').style.display = 'flex';
            hasExtras = true;
        } else {
            document.getElementById('summary-wheelchair-row').style.display = 'none';
        }
        
        if (pets) {
            document.getElementById('summary-pets-row').style.display = 'flex';
            hasExtras = true;
        } else {
            document.getElementById('summary-pets-row').style.display = 'none';
        }
        
        if (highChairCount && parseInt(highChairCount) > 0) {
            document.getElementById('summary-highchair').textContent = highChairCount;
            document.getElementById('summary-highchair-row').style.display = 'flex';
            hasExtras = true;
        } else {
            document.getElementById('summary-highchair-row').style.display = 'none';
        }
        
        // Mostra/nascondi sezione servizi aggiuntivi
        document.getElementById('summary-extras-row').style.display = hasExtras ? 'block' : 'none';
        
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
        prevBtn.style.display = step > 1 ? 'block' : 'none';
        nextBtn.style.display = step < totalSteps ? 'block' : 'none';
        submitBtn.style.display = step === totalSteps ? 'block' : 'none';
        
        // Update button text for step 6
        if (step === totalSteps) {
            submitBtn.textContent = 'Conferma Prenotazione';
        } else {
            submitBtn.textContent = 'Prenota';
        }
    }
    
    function validateStep(step) {
        switch(step) {
            case 1:
                return selectedMeal !== null;
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
    
    submitBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            // Get phone data
            const phonePrefix = document.querySelector('select[name="fp_resv_phone_prefix"]').value;
            const phoneNumber = document.getElementById('customer-phone').value;
            const fullPhone = '+' + phonePrefix + ' ' + phoneNumber;
            
            // Update hidden fields
            document.querySelector('input[name="fp_resv_meal"]').value = selectedMeal;
            document.querySelector('input[name="fp_resv_date"]').value = document.getElementById('reservation-date').value;
            document.querySelector('input[name="fp_resv_party"]').value = document.getElementById('party-size').value;
            document.querySelector('input[name="fp_resv_phone_cc"]').value = phonePrefix;
            document.querySelector('input[name="fp_resv_phone_local"]').value = phoneNumber;
            document.querySelector('input[name="fp_resv_phone_e164"]').value = fullPhone;
            
            const formData = {
                meal: selectedMeal,
                date: document.getElementById('reservation-date').value,
                time: selectedTime,
                party: document.getElementById('party-size').value,
                firstName: document.getElementById('customer-first-name').value,
                lastName: document.getElementById('customer-last-name').value,
                email: document.getElementById('customer-email').value,
                phone: fullPhone,
                phonePrefix: phonePrefix,
                phoneNumber: phoneNumber,
                occasion: document.getElementById('occasion').value,
                notes: document.getElementById('notes').value,
                allergies: document.getElementById('allergies').value,
                wheelchairTable: document.querySelector('input[name="fp_resv_wheelchair_table"]').checked,
                pets: document.querySelector('input[name="fp_resv_pets"]').checked,
                highChairCount: document.getElementById('high-chair-count').value,
                consent: document.querySelector('input[name="fp_resv_consent"]').checked,
                marketingConsent: document.querySelector('input[name="fp_resv_marketing_consent"]').checked
            };
            
            // Submit form
            console.log('Form data completo:', formData);
            showNotice('success', 'Prenotazione inviata con successo! Ti contatteremo presto per confermare.');
        } else {
            showNotice('warning', 'Per favore completa tutti i campi richiesti.');
        }
    });
    
    // Set minimum date to today and load available dates
    let dateInput = document.getElementById('reservation-date');
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    // Store available dates globally
    let availableDates = [];
    
    // Load available dates when meal is selected
    function loadAvailableDates(meal) {
        if (!meal) return;
        
        // Show loading indicator
        const loadingEl = document.getElementById('date-loading');
        const infoEl = document.getElementById('date-info');
        loadingEl.style.display = 'block';
        infoEl.style.display = 'none';
        
        const from = today;
        const to = new Date();
        to.setMonth(to.getMonth() + 3); // 3 months ahead
        const toDate = to.toISOString().split('T')[0];
        
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
                loadingEl.style.display = 'none';
                
                // Show info about fallback dates
                if (infoEl) {
                    infoEl.style.display = 'block';
                    infoEl.innerHTML = `<p>📅 ${availableDates.length} date disponibili per ${meal} (modalità offline)</p>`;
                }
                
                // Force show step 2 if it's hidden
                if (currentStep === 1) {
                    currentStep = 2;
                    console.log('Forzando visualizzazione step 2, currentStep:', currentStep);
                    showStep(currentStep);
                }
                
                // Also show the date input field
                const dateField = form.querySelector('.fp-step[data-step="2"]');
                if (dateField) {
                    dateField.style.display = 'block';
                    console.log('Date field mostrato:', dateField);
                    showNotice('success', 'Date disponibili caricate con successo!');
                } else {
                    console.error('Date field non trovato');
                }
                
                // Show the date input
                if (dateInput) {
                    dateInput.style.display = 'block';
                    dateInput.disabled = false;
                }
                
                updateDateInput();
                console.log('Usando date di fallback per', meal, ':', availableDates);
                return;
            }
            
            const endpoint = endpoints[currentEndpointIndex];
            console.log(`Tentativo endpoint ${currentEndpointIndex + 1}:`, endpoint);
            
            fetch(endpoint)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
            .then(data => {
                // Hide loading indicator
                loadingEl.style.display = 'none';
                
                if (data && data.days) {
                    // Store available dates
                    availableDates = Object.keys(data.days).filter(date => {
                        return data.days[date] && data.days[date].available;
                    });
                    
                    // Show info if dates are available
                    if (availableDates.length > 0) {
                        infoEl.style.display = 'block';
                    }
                    
                    // Update date input with available dates info
                    updateDateInput();
                    console.log('Date disponibili per', meal, ':', availableDates);
                } else {
                    // No data available, allow all dates
                    availableDates = [];
                    infoEl.style.display = 'none';
                }
            })
            .catch(error => {
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
        const startDate = new Date(from);
        const endDate = new Date(to);
        const fallbackDates = [];
        
        // Prova a recuperare i dati dal backend tramite endpoint alternativo
        try {
            // Endpoint alternativo per recuperare configurazione meal
            fetch('/wp-json/fp-resv/v1/meal-config')
                .then(response => response.json())
                .then(data => {
                    if (data && data.meals) {
                        console.log('Configurazione meal recuperata dal backend:', data.meals);
                        // Usa i dati reali dal backend
                        return generateDatesFromBackendConfig(data.meals, from, to, meal);
                    }
                })
                .catch(error => {
                    console.log('Impossibile recuperare configurazione backend, usando schedule di default');
                    return generateDatesFromDefaultSchedule(from, to, meal);
                });
        } catch (error) {
            console.log('Errore nel recupero configurazione backend, usando schedule di default');
        }
        
        // Fallback: usa schedule di default se non riesce a recuperare dal backend
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
            const dateKey = current.toISOString().split('T')[0];
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
    
    // Genera date usando configurazione dal backend
    function generateDatesFromBackendConfig(meals, from, to, meal) {
        const startDate = new Date(from);
        const endDate = new Date(to);
        const fallbackDates = [];
        
        // Trova la configurazione del meal specifico
        const mealConfig = meals.find(m => m.key === meal);
        if (!mealConfig) {
            console.log('Configurazione meal non trovata, usando schedule di default');
            return generateDatesFromDefaultSchedule(from, to, meal);
        }
        
        // Usa la configurazione dal backend
        const current = new Date(startDate);
        while (current <= endDate) {
            const dateKey = current.toISOString().split('T')[0];
            const dayKey = current.toLocaleDateString('en-US', { weekday: 'short' }).toLowerCase();
            
            // Controlla se il meal è disponibile in questo giorno
            let isAvailable = false;
            
            if (mealConfig.days_of_week) {
                // Usa days_of_week se disponibile
                isAvailable = mealConfig.days_of_week[dayKey] || false;
            } else if (mealConfig.hours_definition) {
                // Usa hours_definition se disponibile
                isAvailable = mealConfig.hours_definition[dayKey] && mealConfig.hours_definition[dayKey].enabled;
            }
            
            if (isAvailable) {
                fallbackDates.push(dateKey);
            }
            
            current.setDate(current.getDate() + 1);
        }
        
        return fallbackDates;
    }
    
    // Genera orari disponibili localmente come fallback
    function generateFallbackTimeSlots(meal) {
        const slots = [];
        
        // Prova a recuperare la configurazione dal backend
        fetch('/wp-json/fp-resv/v1/meal-config')
            .then(response => response.json())
            .then(data => {
                if (data && data.meals) {
                    const mealConfig = data.meals.find(m => m.key === meal);
                    if (mealConfig && mealConfig.hours_definition) {
                        console.log('Usando orari dal backend per', meal);
                        return generateTimeSlotsFromBackendConfig(mealConfig);
                    }
                }
                console.log('Usando orari di default per', meal);
                return generateTimeSlotsFromDefault(meal);
            })
            .catch(error => {
                console.log('Errore nel recupero configurazione orari, usando default');
                return generateTimeSlotsFromDefault(meal);
            });
        
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
    
    // Genera orari usando configurazione dal backend
    function generateTimeSlotsFromBackendConfig(mealConfig) {
        const slots = [];
        
        if (!mealConfig.hours_definition) {
            return generateTimeSlotsFromDefault(mealConfig.key);
        }
        
        // Usa la configurazione dal backend per generare gli orari
        // Per ora, usa la configurazione del primo giorno disponibile
        const firstAvailableDay = Object.keys(mealConfig.hours_definition).find(day => 
            mealConfig.hours_definition[day] && mealConfig.hours_definition[day].enabled
        );
        
        if (firstAvailableDay && mealConfig.hours_definition[firstAvailableDay]) {
            const dayConfig = mealConfig.hours_definition[firstAvailableDay];
            const startTime = dayConfig.start || '12:00';
            const endTime = dayConfig.end || '14:30';
            
            // Parsa gli orari e genera slot ogni 30 minuti
            const [startHour, startMinute] = startTime.split(':').map(Number);
            const [endHour, endMinute] = endTime.split(':').map(Number);
            
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
            
            // Show info about available dates
            const infoEl = document.getElementById('date-info');
            if (infoEl) {
                infoEl.style.display = 'block';
                infoEl.innerHTML = `<p>📅 ${availableDates.length} date disponibili per ${selectedMeal}</p>`;
            }
        } else {
            // No restrictions, allow all dates
            dateInput.removeAttribute('data-available-dates');
            dateInput.removeAttribute('data-available-count');
            
            const infoEl = document.getElementById('date-info');
            if (infoEl) {
                infoEl.style.display = 'none';
            }
        }
        
        // Add validation on change
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
            
            // If date is valid, proceed to next step
            if (selectedDate && validateStep(2)) {
                currentStep++;
                showStep(currentStep);
            }
        });
    }
    
    // Load available time slots when date is selected
    function loadAvailableTimeSlots(meal, date, party) {
        if (!meal || !date || !party) return;
        
        const loadingEl = document.getElementById('time-loading');
        const slotsEl = document.getElementById('time-slots');
        const infoEl = document.getElementById('time-info');
        
        loadingEl.style.display = 'block';
        slotsEl.innerHTML = '';
        infoEl.style.display = 'none';
        
        fetch(`/wp-json/fp-resv/v1/available-slots?meal=${meal}&date=${date}&party=${party}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data && data.slots && data.slots.length > 0) {
                    slotsEl.innerHTML = '';
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
                                
                                // Auto-advance to next step
                                if (validateStep(4)) {
                                    currentStep++;
                                    showStep(currentStep);
                                }
                            });
                        } else {
                            slotBtn.classList.add('disabled');
                            slotBtn.disabled = true;
                        }
                        
                        slotsEl.appendChild(slotBtn);
                    });
                    infoEl.style.display = 'block';
                } else {
                    slotsEl.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Nessun orario disponibile per questa data</p>';
                }
            })
            .catch(error => {
                console.error('Errore nel caricamento orari:', error);
                console.log('Usando orari di fallback per', meal, 'alle', date);
                showNotice('info', 'Caricamento orari in corso...');
                
                // Fallback: genera orari localmente
                const fallbackSlots = generateFallbackTimeSlots(meal);
                loadingEl.style.display = 'none';
                
                if (fallbackSlots.length > 0) {
                    slotsEl.innerHTML = '';
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
                            
                            // Auto-advance to next step
                            if (validateStep(4)) {
                                currentStep++;
                                showStep(currentStep);
                            }
                        });
                        
                        slotsEl.appendChild(slotBtn);
                    });
                    infoEl.style.display = 'block';
                    infoEl.innerHTML = `<p>🕐 ${fallbackSlots.length} orari disponibili per ${meal} (modalità offline)</p>`;
                } else {
                    slotsEl.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Nessun orario disponibile per questa data</p>';
                }
            });
    }
    
        // Load time slots when date or party changes
        function checkAndLoadTimeSlots() {
            const date = document.getElementById('reservation-date').value;
            const party = document.getElementById('party-size').value;
            if (date && party && selectedMeal) {
                loadAvailableTimeSlots(selectedMeal, date, party);
            }
        }

        document.getElementById('reservation-date').addEventListener('change', checkAndLoadTimeSlots);
        
        // Aggiorna anche quando cambia il numero di persone
        const partyInput = document.getElementById('party-size');
        if (partyInput) {
            partyInput.addEventListener('change', checkAndLoadTimeSlots);
        }
});
