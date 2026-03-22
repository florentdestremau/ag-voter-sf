import { startStimulusApp } from '@symfony/stimulus-bundle';
import PollController from './controllers/poll_controller.js';
import ChoicesController from './controllers/choices_controller.js';

const app = startStimulusApp();
app.register('poll', PollController);
app.register('choices', ChoicesController);
