import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChoicesController from './controllers/choices_controller.js';
import MercureReloadController from './controllers/mercure_reload_controller.js';

const app = startStimulusApp();
app.register('choices', ChoicesController);
app.register('mercure-reload', MercureReloadController);
