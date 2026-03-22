import { startStimulusApp } from '@symfony/stimulus-bundle';
import PollController from './controllers/poll_controller.js';
import ChoicesController from './controllers/choices_controller.js';
import VoteFormController from './controllers/vote_form_controller.js';

const app = startStimulusApp();
app.register('poll', PollController);
app.register('choices', ChoicesController);
app.register('vote-form', VoteFormController);
