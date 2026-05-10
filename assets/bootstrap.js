import { startStimulusApp } from '@symfony/stimulus-bundle';
import ModalController from './controllers/modal_controller.js';
import UsersToggleController from './controllers/users_toggle_controller.js';
import UsersSearchController from './controllers/users_search_controller.js';
import EntitySortController from './controllers/entity_sort_controller.js';
import ImagePreviewController from './controllers/image_preview_controller.js';
import DeleteConfirmController from './controllers/delete_confirm_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('modal', ModalController);
app.register('users-toggle', UsersToggleController);
app.register('users-search', UsersSearchController);
app.register('entity-sort', EntitySortController);
app.register('image-preview', ImagePreviewController);
app.register('delete-confirm', DeleteConfirmController);
