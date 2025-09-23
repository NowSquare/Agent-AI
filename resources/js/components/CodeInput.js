export default class CodeInput {
    constructor(element, options = {}) {
        this.element = element;
        this.options = {
            length: options.length || 6,
            onComplete: options.onComplete || (() => {}),
            onChange: options.onChange || (() => {}),
        };

        this.inputs = [];
        this.createInputs();
        this.setupEventListeners();
    }

    createInputs() {
        // Create container
        const container = document.createElement('div');
        container.className = 'flex gap-2 justify-center';

        // Create individual inputs
        for (let i = 0; i < this.options.length; i++) {
            const input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 1;
            input.pattern = '[0-9]';
            input.inputMode = 'numeric';
            input.autocomplete = 'one-time-code';
            input.className = 'w-12 h-14 text-center text-2xl font-medium border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-md focus:ring-primary-500 focus:border-primary-500 transition-all duration-150';
            
            container.appendChild(input);
            this.inputs.push(input);
        }

        // Create hidden input for form submission
        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        this.hiddenInput.name = this.element.dataset.name || 'code';
        container.appendChild(this.hiddenInput);

        // Replace original element
        this.element.replaceWith(container);
        this.container = container;
    }

    setupEventListeners() {
        this.inputs.forEach((input, index) => {
            // Handle input
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                
                // Validate input is numeric
                if (!/^\d*$/.test(value)) {
                    input.value = '';
                    return;
                }

                // Update hidden input
                this.updateHiddenInput();

                // Auto advance
                if (value && index < this.inputs.length - 1) {
                    this.inputs[index + 1].focus();
                }

                // Check if complete
                if (this.isComplete()) {
                    this.options.onComplete(this.getValue());
                }

                // Trigger change
                this.options.onChange(this.getValue());
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = paste.match(/\d/g);
                
                if (!numbers) return;

                numbers.forEach((number, i) => {
                    if (index + i < this.inputs.length) {
                        this.inputs[index + i].value = number;
                        
                        if (index + i < this.inputs.length - 1) {
                            this.inputs[index + i + 1].focus();
                        }
                    }
                });

                this.updateHiddenInput();

                if (this.isComplete()) {
                    this.options.onComplete(this.getValue());
                }
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    this.inputs[index - 1].focus();
                    this.inputs[index - 1].select();
                }
            });

            // Handle arrow keys
            input.addEventListener('keydown', (e) => {
                switch(e.key) {
                    case 'ArrowLeft':
                        if (index > 0) {
                            e.preventDefault();
                            this.inputs[index - 1].focus();
                        }
                        break;
                    case 'ArrowRight':
                        if (index < this.inputs.length - 1) {
                            e.preventDefault();
                            this.inputs[index + 1].focus();
                        }
                        break;
                }
            });

            // Select all on focus
            input.addEventListener('focus', () => {
                input.select();
            });
        });
    }

    updateHiddenInput() {
        this.hiddenInput.value = this.getValue();
    }

    getValue() {
        return this.inputs.map(input => input.value).join('');
    }

    isComplete() {
        return this.inputs.every(input => input.value.length === 1);
    }

    setError() {
        this.inputs.forEach(input => {
            input.classList.add('border-red-300', 'text-red-900', 'dark:text-red-400', 'dark:border-red-600');
            input.classList.remove('border-gray-300', 'dark:border-gray-700');
        });

        // Shake animation
        this.container.classList.add('animate-shake');
        setTimeout(() => {
            this.container.classList.remove('animate-shake');
        }, 500);
    }

    clearError() {
        this.inputs.forEach(input => {
            input.classList.remove('border-red-300', 'text-red-900', 'dark:text-red-400', 'dark:border-red-600');
            input.classList.add('border-gray-300', 'dark:border-gray-700');
        });
    }

    clear() {
        this.inputs.forEach(input => {
            input.value = '';
        });
        this.updateHiddenInput();
        this.inputs[0].focus();
    }
}
