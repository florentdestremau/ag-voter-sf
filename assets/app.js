import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import * as bootstrap from 'bootstrap';
import './stimulus_bootstrap.js';

window.bootstrap = bootstrap;

const initTooltips = () => document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
document.addEventListener('DOMContentLoaded', initTooltips);
document.addEventListener('turbo:load', initTooltips);
