import './bootstrap';
import CodeInput from './components/CodeInput';
import * as lucide from 'lucide';

// Initialize Lucide icons
window.lucide = lucide;

// Initialize code input
document.addEventListener('DOMContentLoaded', () => {
    const codeInputs = document.querySelectorAll('[data-code-input]');
    codeInputs.forEach(element => {
        new CodeInput(element, {
            length: parseInt(element.dataset.length || '6', 10),
            onComplete: (code) => {
                if (element.dataset.autoSubmit === 'true') {
                    element.closest('form').submit();
                }
            },
        });
    });
});