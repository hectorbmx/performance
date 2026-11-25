(() => {
    const form = document.getElementById('newTrainingForm');
    if (!form) return;

    const key = form.dataset.draftKey;
    const status = document.getElementById('trainingDraftStatus');
    const discard = document.getElementById('discardTrainingDraft');
    const error = document.getElementById('trainingValidationError');
    const overlay = document.getElementById('trainingSavingOverlay');
    const sections = document.getElementById('sections');
    const revision = form.elements.namedItem('browser_draft_revision');
    const noColor = document.getElementById('noColorCheck');
    const tagColor = document.getElementById('tagColorInput');
    const clients = document.getElementById('assignedClientsPills');
    const groups = document.getElementById('assignedGroupsPills');
    const headerFields = ['title', 'scheduled_at', 'duration_minutes', 'visibility', 'level',
        'training_type_catalog_id', 'training_goal_catalog_id', 'type', 'goal', 'tag_color', 'notes'];
    const sectionFields = { name: '.sec-name', description: '.sec-desc', video_url: '.sec-video-url',
        result_type: '.sec-result-type', unit_id: '.sec-unit-id' };
    const blockFields = { exercise_name: '.liftExerciseName', notes: '.liftBlockNotes' };
    const rowFields = { percentage: '.liftPct', reps: '.liftReps', sets: '.liftSets',
        rest_seconds: '.liftRest', notes: '.liftRowNotes' };
    const fileNote = ' Las portadas y videos subidos se deben volver a seleccionar si cierras la página.';
    let queued = false;
    let submitting = false;

    function fields(container, selectors) {
        return Object.fromEntries(Object.entries(selectors).map(([field, selector]) =>
            [field, container.querySelector(selector)?.value ?? '']));
    }

    function assignments(container, name) {
        return Array.from(container?.querySelectorAll(`input[name="${name}[]"]`) ?? [], input => ({
            id: Number(input.value),
            label: input.parentElement.querySelector('.font-medium')?.textContent?.trim(),
            name: input.parentElement.querySelector('.font-medium')?.textContent?.trim(),
        }));
    }

    function snapshot() {
        const data = new FormData(form);
        return {
            header: Object.fromEntries(headerFields.map(name => [
                name, name === 'tag_color' ? tagColor?.value ?? '' : data.get(name) ?? '',
            ])),
            noColor: noColor?.checked ?? false,
            clients: assignments(clients, 'assigned_clients'),
            groups: assignments(groups, 'assigned_groups'),
            sections: Array.from(sections.querySelectorAll('[data-sec]'), section => ({
                ...fields(section, sectionFields),
                library_video_ids: Array.from(section.querySelectorAll('.sec-library-video-id'), input => input.value),
                library_video_labels: Object.fromEntries(Array.from(
                    section.querySelectorAll('[data-pill-id]'),
                    pill => [pill.dataset.pillId, pill.querySelector('span')?.textContent ?? ''],
                )),
                lifting_enabled: !!section.querySelector('.sec-lifting-toggle')?.checked,
                lifting_blocks: Array.from(section.querySelectorAll('.liftBlock'), block => ({
                    ...fields(block, blockFields),
                    rows: Array.from(block.querySelectorAll('.liftRow'), row => fields(row, rowFields)),
                })),
            })),
        };
    }

    function restore(draft) {
        for (const name of headerFields) {
            const input = name === 'tag_color' ? tagColor : form.elements.namedItem(name);
            if (input && typeof draft.header[name] === 'string') input.value = draft.header[name];
        }
        if (noColor) {
            noColor.checked = !!draft.noColor;
            noColor.dispatchEvent(new Event('change'));
        }
        form.elements.namedItem('scheduled_at')?.dispatchEvent(new Event('change'));
        form.elements.namedItem('visibility')?.dispatchEvent(new Event('change'));
        if (draft.header.visibility === 'assigned') {
            for (const [container, items] of [[clients, draft.clients], [groups, draft.groups]]) {
                const valid = Array.isArray(items) ? items.filter(item => Number.isSafeInteger(item?.id) && item.id > 0) : [];
                container?.dispatchEvent(new CustomEvent('training:restore-assignments', { detail: valid }));
            }
        }
        form.dispatchEvent(new CustomEvent('training:restore-sections', { detail: draft.sections }));
    }

    function storageFailed() {
        status.textContent = 'El navegador no pudo guardar el borrador. Mantén esta página abierta mientras terminas.';
    }

    try {
        const saved = JSON.parse(localStorage.getItem(key) ?? 'null');
        if (saved?.draft?.header && Array.isArray(saved.draft.sections)) {
            discard.classList.remove('hidden');
            if (form.dataset.hasOldInput !== '1' || saved.revision === revision.value) {
                restore(saved.draft);
                revision.value = saved.revision;
                status.textContent = 'Borrador recuperado de este navegador.' + fileNote;
            }
        }
    } catch {
        storageFailed();
    }

    let lastSnapshot = JSON.stringify(snapshot());

    function saveDraft() {
        const draft = snapshot();
        const serialized = JSON.stringify(draft);
        if (serialized === lastSnapshot) return;

        try {
            const nextRevision = Array.from(crypto.getRandomValues(new Uint8Array(16)),
                byte => byte.toString(16).padStart(2, '0')).join('');
            localStorage.setItem(key, JSON.stringify({ revision: nextRevision, draft }));
            revision.value = nextRevision;
            lastSnapshot = serialized;
            status.textContent = 'Borrador guardado en este navegador.' + fileNote;
            discard.classList.remove('hidden');
        } catch {
            revision.value = '';
            storageFailed();
        }
    }

    function queueSave() {
        if (queued) return;
        queued = true;
        queueMicrotask(() => {
            queued = false;
            saveDraft();
        });
    }

    form.addEventListener('input', queueSave);
    form.addEventListener('change', queueSave);
    const observer = new MutationObserver(queueSave);
    for (const container of [sections, clients, groups]) {
        if (container) observer.observe(container, { childList: true, subtree: true });
    }
    window.addEventListener('pagehide', saveDraft);

    discard.addEventListener('click', () => {
        if (!confirm('¿Descartar el borrador de este entrenamiento?')) return;
        try {
            localStorage.removeItem(key);
            lastSnapshot = JSON.stringify(snapshot());
            location.reload();
        } catch {
            storageFailed();
        }
    });

    form.addEventListener('submit', event => {
        saveDraft();
        error.classList.add('hidden');
        if (event.defaultPrevented) return;
        if (submitting) {
            event.preventDefault();
            return;
        }

        const invalid = Array.from(form.elements).find(control => control.willValidate && !control.validity.valid);
        if (invalid) {
            event.preventDefault();
            const match = /^sections\[(\d+)\]\[lifting_blocks\]\[(\d+)\]\[rows\]\[(\d+)\]\[percentage\]$/.exec(invalid.name);
            const message = match
                ? `Revisa el porcentaje de la sección ${Number(match[1]) + 1}, ejercicio ${Number(match[2]) + 1}, fila ${Number(match[3]) + 1}. ${invalid.validationMessage}`
                : invalid.validationMessage;
            error.textContent = message;
            error.classList.remove('hidden');
            invalid.reportValidity();
            return;
        }

        submitting = true;
        overlay?.classList.remove('hidden');
        overlay?.classList.add('flex');
    });

    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        submitting = false;
        overlay?.classList.add('hidden');
        overlay?.classList.remove('flex');
    });
})();
