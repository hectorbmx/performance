<script>
    document.querySelectorAll('input[name="phone"][data-validate="phone"]').forEach((input) => {
        const label = input.closest('.col-span-12')?.querySelector('label');
        if (label) label.textContent = 'Celular';
    });

    document.querySelectorAll('[data-validate]').forEach((input) => {
        const type = input.dataset.validate;
        const feedback = document.querySelector(`[data-feedback-for="${input.name}"]`);
        const messages = {
            email: {
                error: 'Usa un correo valido, por ejemplo cliente@email.com.',
            },
            phone: {
                error: 'Usa 10 digitos o +52 seguido de 10 digitos.',
            },
        };

        const setState = () => {
            const value = input.value.trim();

            input.classList.remove('border-red-400', 'ring-red-100', 'border-emerald-400', 'ring-emerald-100', 'ring-2');
            feedback?.classList.add('hidden');
            if (feedback) feedback.textContent = '';

            if (!value) return;

            const isValid = input.checkValidity();
            input.classList.add('ring-2', isValid ? 'border-emerald-400' : 'border-red-400', isValid ? 'ring-emerald-100' : 'ring-red-100');

            if (feedback && !isValid) {
                feedback.textContent = messages[type].error;
                feedback.classList.remove('hidden', 'text-red-600', 'text-emerald-600');
                feedback.classList.add('text-red-600');
            }
        };

        input.addEventListener('input', setState);
        input.addEventListener('blur', setState);
        setState();
    });
</script>
