import { Application } from '@hotwired/stimulus';

// Start Stimulus application
const app = Application.start();

// Configure Stimulus development experience
app.debug = false;
window.Stimulus = app;

// Import and register controllers
import ThemeController from './controllers/theme_controller.js';
import StepperController from './controllers/stepper_controller.js';
import PictogramController from './controllers/pictogram_controller.js';
import FormCollectionController from './controllers/form_collection_controller.js';

app.register('theme', ThemeController);
app.register('stepper', StepperController);
app.register('pictogram', PictogramController);
app.register('form-collection', FormCollectionController);

export { app };
