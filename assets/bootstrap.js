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
import CsrfProtectionController from './controllers/csrf_protection_controller.js';
import RecipeSearchController from './controllers/recipe_search_controller.js';
import PictoSearchController from './controllers/picto_search_controller.js';
import UtensilFilterController from './controllers/utensil_filter_controller.js';
import PictogramFilterController from './controllers/pictogram_filter_controller.js';

app.register('pictogram-filter', PictogramFilterController);
app.register('utensil-filter', UtensilFilterController);
app.register('theme', ThemeController);
app.register('stepper', StepperController);
app.register('pictogram', PictogramController);
app.register('form-collection', FormCollectionController);
app.register('csrf-protection', CsrfProtectionController);
app.register('recipe-search', RecipeSearchController);
app.register('picto-search', PictoSearchController);

export { app };
