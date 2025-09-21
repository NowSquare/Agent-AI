import './bootstrap'
import 'flowbite'
import 'flowbite-datepicker'

// Lucide (named exports)
import { createIcons, icons } from 'lucide'

// Replace all [data-lucide] with SVGs on page load
document.addEventListener('DOMContentLoaded', () => {
  createIcons({ icons })
})
